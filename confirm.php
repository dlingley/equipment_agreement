<?php
// ===== Session and Error Handling Setup =====
// Start the session to maintain user data across pages
session_start();

// Enable comprehensive error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ===== Authentication Check =====
// Verify that user has provided a Purdue ID through the previous form
// If not, redirect back to the index page
if (!isset($_SESSION['purdueid'])) {
    header("Location: index.php");
    exit();
}

// ===== Configuration Loading =====
// Load application configuration settings
$config = include('config.php');

// Verify that the required API key is present in config
if (!isset($config['ALMA_API_KEY'])) {
    die('API key not set in config.php.');
}

// Get Purdue ID from session
$purdueId = $_SESSION['purdueid'];

/**
 * Writes debug messages to a log file
 * @param string $message The message to log
 * @param string $level The log level (INFO, ERROR, etc.)
 */
function debugLog($message, $level = 'INFO') {
    $logFile = $config['LOG_PATHS']['DEBUG'];
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Sends a confirmation email to the user after agreement is signed
 * Uses PHPMailer to handle email sending
 * 
 * @param string $email User's email address
 * @param string $firstName User's first name
 * @param string $lastName User's last name
 * @param string $semester Current semester end date
 * @return bool True if email sent successfully, false otherwise
 */
function sendAgreementEmail($email, $firstName, $lastName, $semester) {
    require '../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Configure SMTP server settings
        $mail->isSMTP();
        $mail->Host = $config['SMTP_CONFIG']['HOST'];
        $mail->Port = $config['SMTP_CONFIG']['PORT'];
        $mail->SMTPAuth = false; // No authentication needed
        $mail->SMTPSecure = false; // No encryption needed
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Set email sender and recipient
        $mail->setFrom($config['SMTP_CONFIG']['FROM_EMAIL']);
        $mail->addAddress($email);

        // Compose email content
        $mail->isHTML(false);
        $mail->Subject = "Equipment Agreement Confirmation";
        $message = "Dear $firstName $lastName,\n\n";
        $message .= "This email confirms that you have agreed to the Equipment Agreement valid until $semester.\n\n";
        $message .= "Agreement Details:\n";
        $message .= "- You are responsible for any equipment borrowed from the Knowledge Lab\n";
        $message .= "- Equipment must be returned in the same condition as when borrowed\n";
        $message .= "- Any damage or loss must be reported immediately\n\n";
        $message .= "Thank you for using the Knowledge Lab!\n";
        $message .= "Purdue University Libraries";
        $mail->Body = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        debugLog('Failed to send confirmation email to ' . $email . '. Error: ' . $mail->ErrorInfo, 'ERROR');
        return false;
    }
}

/**
 * Main function to handle user agreement verification and creation
 * Interacts with Alma API to check for existing agreements and create new ones
 * 
 * @param string $purdueId User's Purdue ID
 * @param array $config Application configuration
 * @return array Result of the operation including user info and status
 */
function pushUserNoteAndCheckAgreement($purdueId, $config) {
    // Define Alma API endpoints and parameters
    $ALMA_REQ_URL = $config['ALMA_API_CONFIG']['BASE_URL'];
    $ALMA_API_KEY = $config['ALMA_API_KEY'];
    $ALMA_GET_PARAM = $config['ALMA_API_CONFIG']['GET_PARAMS'] . '&apikey=';
    $ALMA_PUT_PARAM = $config['ALMA_API_CONFIG']['PUT_PARAMS'] . '&apikey=';
    
    debugLog('Starting API call for Purdue ID: ' . $purdueId);
    
    // Initialize cURL for GET request to fetch user information
    $cr = curl_init();
    $curl_options = array(
        CURLOPT_URL => sprintf("%s%s%s%s", $ALMA_REQ_URL, $purdueId, $ALMA_GET_PARAM, $ALMA_API_KEY),
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => array("Accept: application/xml"),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    );
    curl_setopt_array($cr, $curl_options);
    
    debugLog('GET Request URL: ' . $curl_options[CURLOPT_URL]);

    // Execute GET request and handle response
    $response = curl_exec($cr);
    $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
    
    debugLog('GET Response HTTP Code: ' . $http_code);
    debugLog('GET Response: ' . $response);

    // Check for cURL errors
    if(curl_errno($cr)) {
        $error_msg = 'Curl error: ' . curl_error($cr);
        debugLog($error_msg, 'ERROR');
        curl_close($cr);
        return array('error' => $error_msg);
    }

    curl_close($cr);

    // Handle HTTP error responses
    if ($http_code === 400 || $http_code === 404) {
        debugLog('Received error response: ' . $response, 'ERROR');
        if ($response && ($xml = @simplexml_load_string($response))) {
            // Extract error message from XML response
            if (isset($xml->errorList) && isset($xml->errorList->error)) {
                $errorMessage = (string)$xml->errorList->error->errorMessage;
            } 
            else if (isset($xml->web_service_result) && isset($xml->web_service_result->errorList)) {
                $errorMessage = (string)$xml->web_service_result->errorList->error->errorMessage;
            }
            else {
                $errorMessage = "Invalid Purdue ID or user not found";
            }
            debugLog('Parsed error message: ' . $errorMessage, 'ERROR');
            return array('error' => $errorMessage, 'xml_response' => $response);
        }
        return array('error' => "Invalid Purdue ID or user not found", 'xml_response' => $response);
    }

    // Validate XML response
    if (!$response || !simplexml_load_string($response)) {
        $error_msg = 'Invalid XML response';
        debugLog($error_msg, 'ERROR');
        return array('error' => $error_msg, 'xml_response' => $response);
    }

    // Parse XML response using DOMDocument for more precise control
    $doc = new DOMDocument();
    $doc->loadXML($response);
    $xpath = new DOMXpath($doc);

    // Check for API errors in response
    $errorsExist = $xpath->query("//errorsExist[text()='true']");
    if ($errorsExist->length > 0) {
        $errorMessage = $xpath->query("//errorMessage")->item(0)->nodeValue;
        debugLog('API Error: ' . $errorMessage, 'ERROR');
        return array('error' => $errorMessage, 'xml_response' => $response);
    }

    // Extract user information from response
    $firstNameNode = $xpath->query("//first_name")->item(0);
    $lastNameNode = $xpath->query("//last_name")->item(0);
    $emailNode = $xpath->query("//email_address")->item(0);
    $phoneNode = $xpath->query("//phone_number")->item(0);
    $userGroupNode = $xpath->query("//user_group")->item(0);

    $firstName = $firstNameNode ? $firstNameNode->nodeValue : '';
    $lastName = $lastNameNode ? $lastNameNode->nodeValue : '';
    $email = $emailNode ? $emailNode->nodeValue : '';
    $phone = $phoneNode ? $phoneNode->nodeValue : '';
    $userGroup = $userGroupNode ? $userGroupNode->nodeValue : '';

    // Check for existing agreement
    $semester = $config['SEMESTER_END_DATE'];
    debugLog('Checking for existing agreement for semester: ' . $semester);

    $note_text_nodes = $xpath->query("//user_note[note_text[contains(text(),'Equipment Agreement') and contains(text(),'$semester')]]");

    if ($note_text_nodes->length == 0) {
        debugLog('No existing agreement found, creating new note');

        // Handle agreement creation if user has confirmed
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
            // Create new XML document for PUT request
            $putDoc = new DOMDocument();
            $putDoc->loadXML($response);
            $putXpath = new DOMXPath($putDoc);

            // Remove roles section to prevent conflicts
            $rolesNode = $putXpath->query("//user_roles")->item(0);
            if ($rolesNode) {
                $rolesNode->parentNode->removeChild($rolesNode);
                debugLog('Removed roles section from PUT request');
            }

            // Create or get user_notes section
            $userNotes = $putXpath->query("//user_notes")->item(0);
            if (!$userNotes) {
                $userNotes = $putDoc->createElement("user_notes");
                $putDoc->documentElement->appendChild($userNotes);
            }

            // Create new agreement note
            $userNote = $putDoc->createElement("user_note");
            $userNote->setAttribute("segment_type", "External");
            $userNotes->appendChild($userNote);

            $noteType = $putDoc->createElement("note_type", "CIRCULATION");
            $userNote->appendChild($noteType);

            $noteText = $putDoc->createElement("note_text", "Equipment Agreement valid to $semester.");
            $userNote->appendChild($noteText);

            $userViewable = $putDoc->createElement("user_viewable", "true");
            $userNote->appendChild($userViewable);

            $popupNote = $putDoc->createElement("popup_note", "true");
            $userNote->appendChild($popupNote);

            // Log XML changes for debugging
            debugLog('Original GET response structure: ' . preg_replace('/>\s+</', '><', $doc->saveXML()));
            debugLog('Modified PUT request structure: ' . preg_replace('/>\s+</', '><', $putDoc->saveXML()));

            // Send PUT request to update user record
            $cr = curl_init();
            $curl_options = array(
                CURLOPT_URL => sprintf("%s%s%s%s", $ALMA_REQ_URL, $purdueId, $ALMA_PUT_PARAM, $ALMA_API_KEY),
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS => $putDoc->saveXML(),
                CURLOPT_HTTPHEADER => array("Content-Type: application/xml", "Accept: application/xml"),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false
            );
            curl_setopt_array($cr, $curl_options);

            debugLog('PUT Request URL: ' . $curl_options[CURLOPT_URL]);
            debugLog('PUT Request Body: ' . $putDoc->saveXML());

            $response = curl_exec($cr);

            if(curl_errno($cr)) {
                $error_msg = 'Curl error during PUT: ' . curl_error($cr);
                debugLog($error_msg, 'ERROR');
                return array('error' => $error_msg, 'xml_response' => $response);
            }

            $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
            debugLog('PUT Response HTTP Code: ' . $http_code);
            debugLog('PUT Response: ' . $response);

            curl_close($cr);

            if ($http_code !== 200) {
                return array('error' => 'Failed to update user note. Please try again later.', 'xml_response' => $response);
            }

            // Send confirmation email
            if (!sendAgreementEmail($email, $firstName, $lastName, $semester)) {
                debugLog('Failed to send confirmation email to ' . $email, 'WARNING');
            }

            return array(
                'success' => true,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'userGroup' => $userGroup,
                'agreement_created' => true
            );
        }

        // Return user info for confirmation screen if not confirmed yet
        return array(
            'success' => true,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'userGroup' => $userGroup,
            'needs_confirmation' => true
        );
    } else {
        // Handle existing agreement case
        debugLog('Existing agreement found, no update needed');
        $user_note_node = $note_text_nodes->item(0);
        $creationDateNode = $xpath->query("created_date", $user_note_node)->item(0);
        $noteTextNode = $xpath->query("note_text", $user_note_node)->item(0);

        if ($creationDateNode) {
            $agreementDate = $creationDateNode->nodeValue;
            $agreementDate = date("F j, Y", strtotime($agreementDate));
        } else {
            $agreementDate = 'Unknown';
        }

        $noteText = $noteTextNode ? $noteTextNode->nodeValue : 'Unknown';

        return array(
            'success' => true,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'agreementDate' => $agreementDate,
            'noteText' => $noteText,
            'userGroup' => $userGroup,
            'agreement_exists' => true
        );
    }
}

// ===== Main Execution Flow =====
// Call the main function and process results
$result = pushUserNoteAndCheckAgreement($purdueId, $config);

// Handle errors
if (isset($result['error'])) {
    $error = true;
    $errorMessage = $result['error'];
    $xmlResponse = isset($result['xml_response']) ? $result['xml_response'] : '';
    
    $_SESSION['error_message'] = $errorMessage;
    header("Location: index.php");
    exit();
} else {
    // Process successful response
    $firstName = $result['firstName'];
    $lastName = $result['lastName'];
    $email = $result['email'];
    $phone = $result['phone'];
    $userGroup = $result['userGroup'];
    
    if (isset($result['agreement_exists'])) {
        // Handle existing agreement case
        $agreementExists = true;
        $agreementDate = $result['agreementDate'];
        $noteText = $result['noteText'];
    } elseif (isset($result['agreement_created'])) {
        // Redirect to success page for newly created agreement
        header("Location: success.php");
        exit();
    } else {
        // Show confirmation screen for new agreement
        $agreementExists = false;
    }

    // Log successful check-ins
    if ($agreementExists || isset($result['agreement_created'])) {
        $checkInLog = 'logs/checkin_log.csv';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "$purdueId,$timestamp,$userGroup\n";
        file_put_contents($checkInLog, $logEntry, FILE_APPEND);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <title>Confirm Your Information</title>
    <link rel="stylesheet" href="styles.css">
    <?php if (($agreementExists && !isset($error)) || isset($error)): ?>
    <meta http-equiv="refresh" content="5;url=index.php">
    <?php endif; ?>
    <style>
        /* Styles for error messages and containers */
        .error-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            text-align: left;
        }
        .xml-response {
            background-color: #f8f9fa;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            text-align: left;
            overflow-x: auto;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .return-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-top: 1rem;
        }
        .return-button:hover {
            background-color: #0056b3;
        }
        .redirect-message {
            color: #6c757d;
            font-style: italic;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header with logo -->
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
    </div>

    <?php if (isset($error) && $error): ?>
        <!-- Error message display -->
        <div class="error-container">
            <div class="error-message">
                <h2>Update Failed</h2>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
                <?php if (!empty($xmlResponse)): ?>
                    <h3>API Response Details:</h3>
                    <div class="xml-response"><?php echo htmlspecialchars($xmlResponse); ?></div>
                <?php endif; ?>
            </div>
            <div class="redirect-message">
                You will be redirected to the homepage in 5 seconds...
            </div>
            <a href="index.php" class="return-button">Return to Homepage</a>
        </div>
    <?php else: ?>
        <?php if ($agreementExists): ?>
            <!-- Display existing agreement information -->
            <div class="agreement-note">
                <h2>Agreement already exists for this semester.</h2>
                <p>First Name: <?php echo htmlspecialchars($firstName); ?></p>
                <p>Last Name: <?php echo htmlspecialchars($lastName); ?></p>
                <p>Email: <?php echo htmlspecialchars($email); ?></p>
                <p>Phone: <?php echo htmlspecialchars($phone); ?></p>
                <p>User Group: <?php echo htmlspecialchars($userGroup); ?></p>
                <h2>Agreement Details</h2>
                <p>Agreement Date: <?php echo htmlspecialchars($agreementDate); ?></p>
                <p>Agreement Details: <?php echo htmlspecialchars($noteText); ?></p>
                <div class="thank-you-message">
                    <h3>Thank you for using the Knowledge Lab!</h3>
                    <p>You will be redirected to the home page in 5 seconds...</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Confirmation form for new agreement -->
            <h1>Confirm Your Information</h1>
            <form method="POST" id="confirmation_form">
                <div class="form-section">
                    <div class="form-group">
                        <label for="firstname">First Name:</label>
                        <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($firstName); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name:</label>
                        <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($lastName); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="email">Purdue Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="purdueid">Purdue ID:</label>
                        <input type="text" id="purdueid" name="purdueid" value="<?php echo htmlspecialchars($purdueId); ?>" readonly>
                    </div>
                    <div class="button-group">
                        <input type="submit" name="confirm" value="Confirm">
                        <input type="reset" name="cancel" value="Cancel" onclick="window.location='index.php'; return false;">
                    </div>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
