<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (!isset($_SESSION['purdueid'])) {
    header("Location: index.php");
    exit();
}

$config = include('config.php');

if (!isset($config['ALMA_API_KEY'])) {
    die('API key not set in config.php.');
}

$purdueId = $_SESSION['purdueid'];

function debugLog($message, $level = 'INFO') {
    $logFile = 'logs/equipment_agreement_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function sendAgreementEmail($email, $firstName, $lastName, $semester) {
    $to = $email;
    $subject = "Equipment Agreement Confirmation";
    $message = "Dear $firstName $lastName,\n\n";
    $message .= "This email confirms that you have agreed to the Equipment Agreement valid until $semester.\n\n";
    $message .= "Agreement Details:\n";
    $message .= "- You are responsible for any equipment borrowed from the Knowledge Lab\n";
    $message .= "- Equipment must be returned in the same condition as when borrowed\n";
    $message .= "- Any damage or loss must be reported immediately\n\n";
    $message .= "Thank you for using the Knowledge Lab!\n";
    $message .= "Purdue University Libraries";

    $headers = "From: noreply@purdue.edu\r\n";
    $headers .= "Reply-To: noreply@purdue.edu\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, $message, $headers);
}

function pushUserNoteAndCheckAgreement($purdueId, $config) {
    $ALMA_REQ_URL = "https://api-na.hosted.exlibrisgroup.com/almaws/v1/users/";
    $ALMA_API_KEY = $config['ALMA_API_KEY'];
    $ALMA_GET_PARAM = "?user_id_type=all_unique&view=full&expand=none&apikey=";
    $ALMA_PUT_PARAM = "?user_id_type=all_unique&send_pin_number_letter=false&recalculate_roles=false&apikey=";
    
    debugLog('Starting API call for Purdue ID: ' . $purdueId);
    
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

    $response = curl_exec($cr);
    $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
    
    debugLog('GET Response HTTP Code: ' . $http_code);
    debugLog('GET Response: ' . $response);

    if(curl_errno($cr)) {
        $error_msg = 'Curl error: ' . curl_error($cr);
        debugLog($error_msg, 'ERROR');
        curl_close($cr);
        return array('error' => $error_msg);
    }

    curl_close($cr);

    // Check for HTTP error codes
    if ($http_code === 400 || $http_code === 404) {
        debugLog('Received error response: ' . $response, 'ERROR');
        if ($response && ($xml = @simplexml_load_string($response))) {
            // Try to get error message from errorList/error/errorMessage
            if (isset($xml->errorList) && isset($xml->errorList->error)) {
                $errorMessage = (string)$xml->errorList->error->errorMessage;
            } 
            // Also try web_service_result/errorList/error/errorMessage structure
            else if (isset($xml->web_service_result) && isset($xml->web_service_result->errorList)) {
                $errorMessage = (string)$xml->web_service_result->errorList->error->errorMessage;
            }
            // Fallback error message
            else {
                $errorMessage = "Invalid Purdue ID or user not found";
            }
            debugLog('Parsed error message: ' . $errorMessage, 'ERROR');
            return array('error' => $errorMessage, 'xml_response' => $response);
        }
        return array('error' => "Invalid Purdue ID or user not found", 'xml_response' => $response);
    }

    if (!$response || !simplexml_load_string($response)) {
        $error_msg = 'Invalid XML response';
        debugLog($error_msg, 'ERROR');
        return array('error' => $error_msg, 'xml_response' => $response);
    }

    $doc = new DOMDocument();
    $doc->loadXML($response);
    $xpath = new DOMXpath($doc);

    $errorsExist = $xpath->query("//errorsExist[text()='true']");
    if ($errorsExist->length > 0) {
        $errorMessage = $xpath->query("//errorMessage")->item(0)->nodeValue;
        debugLog('API Error: ' . $errorMessage, 'ERROR');
        return array('error' => $errorMessage, 'xml_response' => $response);
    }

    // Extract user information
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

    $semester = "August 17, 2025";
    debugLog('Checking for existing agreement for semester: ' . $semester);

    $note_text_nodes = $xpath->query("//user_note[note_text[contains(text(),'Equipment Agreement') and contains(text(),'$semester')]]");

    if ($note_text_nodes->length == 0) {
        debugLog('No existing agreement found, creating new note');

        // If this is a POST request with confirmation, proceed with creating the note
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
            // Create a new document for the PUT request by cloning the GET response
            $putDoc = new DOMDocument();
            $putDoc->loadXML($response);
            $putXpath = new DOMXPath($putDoc);

            // Remove the roles section
            $rolesNode = $putXpath->query("//user_roles")->item(0);
            if ($rolesNode) {
                $rolesNode->parentNode->removeChild($rolesNode);
                debugLog('Removed roles section from PUT request');
    }

            // Get the user_notes node or create it if it doesn't exist
            $userNotes = $putXpath->query("//user_notes")->item(0);
            if (!$userNotes) {
                $userNotes = $putDoc->createElement("user_notes");
                $putDoc->documentElement->appendChild($userNotes);
            }

            // Create and add the new note
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

            // Log the differences for verification
            debugLog('Original GET response structure: ' . preg_replace('/>\s+</', '><', $doc->saveXML()));
            debugLog('Modified PUT request structure: ' . preg_replace('/>\s+</', '><', $putDoc->saveXML()));

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

        // If no confirmation yet, return user info for confirmation screen
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

// Call the function and handle the result
$result = pushUserNoteAndCheckAgreement($purdueId, $config);

if (isset($result['error'])) {
    $error = true;
    $errorMessage = $result['error'];
    $xmlResponse = isset($result['xml_response']) ? $result['xml_response'] : '';
    
    $_SESSION['error_message'] = $errorMessage;
    header("Location: index.php");
    exit();
} else {
    $firstName = $result['firstName'];
    $lastName = $result['lastName'];
    $email = $result['email'];
    $phone = $result['phone'];
    $userGroup = $result['userGroup'];
    
    if (isset($result['agreement_exists'])) {
        $agreementExists = true;
        $agreementDate = $result['agreementDate'];
        $noteText = $result['noteText'];
    } elseif (isset($result['agreement_created'])) {
        // Agreement was just created, redirect to success page
        header("Location: success.php");
        exit();
    } else {
        // Show confirmation screen
        $agreementExists = false;
    }

    // Only log successful check-ins when agreement exists or was just created
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
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
    </div>

    <?php if (isset($error) && $error): ?>
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
