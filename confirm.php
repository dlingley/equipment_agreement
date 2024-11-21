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
 * @global array $config Application configuration
 */
function debugLog($message, $level = 'INFO') {
    global $config; // Add global keyword to access config
    
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
function sendAgreementEmail($email, $firstName, $lastName) {
    global $config; // Add global keyword to access config
    
    require '../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configure SMTP server settings
        $mail->isSMTP();
        $mail->Host = $config['SMTP_CONFIG']['HOST'];
        $mail->Port = $config['SMTP_CONFIG']['PORT'];
        $mail->SMTPAuth = false;
        $mail->SMTPSecure = false;
        
        // Disable SSL verification (for testing purposes only)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set proper From address with name
        $mail->setFrom(
            $config['SMTP_CONFIG']['FROM_EMAIL'],
            $config['SMTP_CONFIG']['FROM_NAME']
        );
        
        // Set email recipient
        $mail->addAddress($email);
        
        // Compose email content
        $mail->isHTML(false);
        $mail->Subject = "Purdue Libraries Knowledge Lab User Agreement Confirmation";
        $message = "Dear $firstName $lastName,\n\n";
        $message .= "This email confirms that you have agreed to the Purdue Libraries Knowledge Lab User Agreement:\n\n";

        $message .= "I hereby agree to the following conditions for use of the Knowledge Lab (the “Lab”) facilities and equipment.\n";

        $message .= "Conditions of Use and General Conduct\n";
        $message .= "I will comply with Purdue University (\"Purdue\") and Purdue Libraries policies and procedures including, but not limited to Lab guidelines, signage, and instructions.\n\n";
        $message .= "The Knowledge Lab is a collection of equipment, tools, materials, and supplies intended to allow for exploration, creativity, and innovation. To maintain the privilege of use for all members of the Purdue community we ask that you respect the space, the items, and the other users. All Purdue community members who use the space agree to comply with all Knowledge Lab policies and Purdue University codes of conduct.\n";
        $message .= "1. Keep the Knowledge Lab and everything within the facility clean and organized. If you take out tools and materials, return them to the correct location and clean up the area used. If equipment or a tool is broken or not functioning correctly, please notify staff immediately.\n";
        $message .= "2. Be respectful to Knowledge Lab staff, other users of the Knowledge and towards its equipment at all times.\n";
        $message .= "3. Staff are available to assist patrons with equipment use, understanding materials and processes, and talking through ideas and project concepts for you to complete the work yourself.\n";
        $message .= "4.  Staff have the right to refuse projects and material usage if they are in violation of Knowledge Lab policies.\n";
        $message .= "5. Users are responsible for properly monitoring and labeling anything brought into the lab and the Knowledge Lab is not responsible for any lost, damaged, or stolen property.\n";
        $message .= "6. Use of the Knowledge Lab and materials created will not be:\n";
        $message .= "   a. Prohibited by local, state, or federal law.\n";
        $message .= "   b. Unsafe, harmful, dangerous, or pose an immediate or perceived threat to the well-being of others.\n";
        $message .= "   c. Obscene or otherwise inappropriate for the University and Library environment.\n";
        $message .= "   d. violation of intellectual property rights.\n\n";
        
        $message .= "Knowledge Lab Access\n";
        $message .= "The Knowledge Lab is open and free to those affiliated with Purdue. Always bring a valid Purdue ID to swipe in at the front desk, all first time users must fill out a release form. The Knowledge Lab is not open to the public. Outside guests may accompany members of the Purdue community but may not use any of the tools, equipment, or materials in the space. Guests under 18 years old must be accompanied by their parent or legal guardian at all times and may not be left alone in the Knowledge Lab.\n\n";
        
        $message .= "Material Usage\n";
        $message .= "Materials available in the Knowledge Lab are a courtesy provided by Purdue University and are intended to be used in the lab. Please be respectful of the resources provided and avoid wasting consumable supplies and materials. We cannot guarantee the availability of any materials at any time. If a project requires a larger amount of materials that exceeds our monthly allotments, Knowledge Lab staff can provide you with information on recommended materials and suppliers. Our facility is a place of learning and exploration. The free materials in the Knowledge are not to be used for commercial purposes or mass production.\n\n";
        
        $message .= "Copyright\n";
        $message .= "The Knowledge Lab encourages innovation and creations of one's own design. Projects created in the Knowledge Lab must respect and comply with intellectual property laws at all times, including, but not limited to, trademarks, logos, and copyrighted designs.\n\n";
        
        $message .= "Safety and Assumption of Risk\n";
        $message .= "Use of the Lab facility, tools, equipment, and materials is entirely voluntary and optional. Such use involves inherent hazards, dangers, and risks. I hereby agree that I assume all responsibility for any risks of loss, damage, or personal injury that I may sustain and/or any loss or damage to property that I own, as a result of being engaged in Lab activities, whether caused by the negligence of the Lab personnel, equipment or otherwise.\n\n";
        
        $message .= "Release of Liability\n";
        $message .= "I hereby release and forever discharge Purdue University, the Board of Trustees of Purdue University, its members individually, and the officers, agents and employees of Purdue University from any and all claims, demands, rights and causes of action of whatever kind that I may have, caused by or arising from my use of the Lab facilities, tools, equipment, or materials regardless of whether or not caused in whole or part by the negligence or other fault of the parties to this agreement.\n\n";
        
        $message .= "Indemnification\n";
        $message .= "I agree to indemnify and hold Purdue harmless from and against any and all losses, liabilities, damages, costs or expenses (including but not limited to reasonable attorneys' fees and other litigation costs and expenses) incurred by Purdue.\n\n";
        
        $message .= "Consent to Medical Treatment\n";
        $message .= "I give permission for Purdue and its employees, volunteers, agents, representatives and emergency personnel to make necessary first aid decisions in the event of an accident, injury, or illness I may suffer during the use of the Lab. If I need medical treatment, I will be financially responsible for any costs incurred as a result of such treatment.\n\n";
        
        $message .= "Miscellaneous\n";
        $message .= "A. PURDUE EXPRESSLY DISCLAIMS ALL WARRANTIES OF ANY KIND, EXPRESS, IMPLIED, STATUTORY OR OTHERWISE, INCLUDING WITHOUT LIMITATION IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE AND NON-INFRINGEMENT WITH REGARD TO THE LAB, EQUIPMENT, TOOLS AND MATERIALS.\n\n";
        $message .= "B. If any provision of this document is determined to be invalid for any reason, such invalidity shall not affect the validity of any other provisions, which other provisions shall remain in full force and effect as if this agreement had been executed with the invalid provision eliminated.\n\n";
        $message .= "C. This agreement is entered into in Indiana and shall be governed by and construed in accordance with the substantive law (and not the law of conflicts) of the State of Indiana and applicable U.S. federal law. Courts of competent authority located in Tippecanoe County, Indiana shall have sole and exclusive jurisdiction of any action arising out of or in connection with the agreement, and such courts shall be the sole and exclusive venue for any such action.\n\n";
        
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
    // $semester = $config['SEMESTER_END_DATE'];
    debugLog('Checking for existing agreement');
    $note_text_nodes = $xpath->query("//user_note[note_text[contains(text(),'Agreed to Knowledge Lab User Agreement')]]");

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

            $noteText = $putDoc->createElement("note_text", "Agreed to Knowledge Lab User Agreement");
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
        /* Add these styles to the existing <style> section */
        .form-section input[readonly] {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            cursor: not-allowed;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            font-weight: bold;
            color: #212529;
        }
        
        .agreement-details {
            background-color: #f8f9fa;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .agreement-details p {
            margin: 0.5rem 0;
            display: flex;
            justify-content: space-between;
        }
        
        .agreement-details span.label {
            font-weight: bold;
            color: #495057;
        }
        
        .agreement-details span.value {
            color: #212529;
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
                <h2>Agreement already exists.</h2>
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
