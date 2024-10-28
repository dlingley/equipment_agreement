<?php
session_start();
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Check if session data is available
if (!isset($_SESSION['purdueid'])) {
    header("Location: index.php");
    exit();
}

// Load the configuration file
$config = include('config.php');

if (!isset($config['ALMA_API_KEY'])) {
    die('API key not set in config.php.');
}

// Retrieve Purdue ID from session
$purdueId = $_SESSION['purdueid'];

// Function to log debug information
function debugLog($message, $level = 'INFO') {
    $logFile = 'logs/equipment_agreement_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Function to push user note via Alma API
function pushUserNoteAndCheckAgreement($purdueId, $config) {
    // Base URL
    $ALMA_REQ_URL = "https://api-na.hosted.exlibrisgroup.com/almaws/v1/users/";
    // API KEY
    $ALMA_API_KEY = $config['ALMA_API_KEY'];
    // GET PARAMETERS
    $ALMA_GET_PARAM = "?user_id_type=all_unique&view=full&expand=none&apikey=";
    // PUT PARAMETERS
    $ALMA_PUT_PARAM = "?user_id_type=all_unique&send_pin_number_letter=false&recalculate_roles=false&apikey=";
    
    debugLog('Starting API call for Purdue ID: ' . $purdueId);
    
    // Initialize cURL GET
    $cr = curl_init();
    $curl_options = array(
        CURLOPT_URL => sprintf("%s%s%s%s", $ALMA_REQ_URL, $purdueId, $ALMA_GET_PARAM, $ALMA_API_KEY),
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => array("Accept: application/xml"),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    );
    curl_setopt_array($cr, $curl_options);
    
    // Debug curl request
    debugLog('GET Request URL: ' . $curl_options[CURLOPT_URL]);

    $response = curl_exec($cr);

    // Check for cURL errors
    if(curl_errno($cr)) {
        $error_msg = 'Curl error: ' . curl_error($cr);
        debugLog($error_msg, 'ERROR');
        return array('error' => $error_msg);
    }

    $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
    debugLog('GET Response HTTP Code: ' . $http_code);
    debugLog('GET Response: ' . $response);

    curl_close($cr);

    // Check if response is valid XML
    if (!$response || !simplexml_load_string($response)) {
        $error_msg = 'Invalid XML response';
        debugLog($error_msg, 'ERROR');
        return array('error' => $error_msg);
    }

    $doc = new DOMDocument();
    $doc->loadXML($response);
    $xpath = new DOMXpath($doc);
    
    // Check for errors in the API response
    $errorsExist = $xpath->query("//errorsExist[text()='true']");
    if ($errorsExist->length > 0) {
        $errorMessage = $xpath->query("//errorMessage")->item(0)->nodeValue;
        debugLog('API Error: ' . $errorMessage, 'ERROR');
        return array('error' => $errorMessage);
    }
    
    // Extract user details
    $firstNameNode = $xpath->query("//first_name")->item(0);
    $lastNameNode = $xpath->query("//last_name")->item(0);
    $emailNode = $xpath->query("//email_address")->item(0);
    $phoneNode = $xpath->query("//phone_number")->item(0);

    $firstName = $firstNameNode ? $firstNameNode->nodeValue : '';
    $lastName = $lastNameNode ? $lastNameNode->nodeValue : '';
    $email = $emailNode ? $emailNode->nodeValue : '';
    $phone = $phoneNode ? $phoneNode->nodeValue : '';
    
    // Updated to August 17, 2025
    $semester = "August 17, 2025";
    debugLog('Checking for existing agreement for semester: ' . $semester);
    
    $note_text = $xpath->query("//note_text/text()[contains(.,\"Equipment Agreement\") and contains(.,\"$semester\")]");
    $agreementDate = null;
    
    if ($note_text->length == 0) {
        debugLog('No existing agreement found, creating new note');
        $user_notes = $xpath->query("//user_notes")->item(0);
        
        if (!$user_notes) {
            $error_msg = 'User notes element not found in API response';
            debugLog($error_msg, 'ERROR');
            header("Location: index.php?error=PUID not found");
            exit();
        }
        
        $user_notes_domnode = $user_notes->cloneNode();
        $user_note = new DOMElement("user_note");
        $user_notes->appendChild($user_note);
        $user_note->setAttribute("segment_type","External");
        $user_note->appendChild(new DOMElement("note_type","CIRCULATION"));
        $user_note->appendChild(new DOMElement("note_text","Equipment Agreement valid to $semester."));
        $user_note->appendChild(new DOMElement("user_viewable","true"));
        $user_note->appendChild(new DOMElement("popup_note","true"));
    
        // Initialize cURL PUT
        $cr = curl_init();
        $curl_options = array(
            CURLOPT_URL => sprintf("%s%s%s%s", $ALMA_REQ_URL, $purdueId, $ALMA_PUT_PARAM, $ALMA_API_KEY),
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $doc->saveXML(),
            CURLOPT_HTTPHEADER => array("Content-Type: application/xml", "Accept: application/xml"),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );
        curl_setopt_array($cr, $curl_options);
        
        debugLog('PUT Request URL: ' . $curl_options[CURLOPT_URL]);
        debugLog('PUT Request Body: ' . $doc->saveXML());
        
        $response = curl_exec($cr);
        
        // Check for cURL errors
        if(curl_errno($cr)) {
            $error_msg = 'Curl error during PUT: ' . curl_error($cr);
            debugLog($error_msg, 'ERROR');
            return array('error' => $error_msg);
        }
        
        $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
        debugLog('PUT Response HTTP Code: ' . $http_code);
        debugLog('PUT Response: ' . $response);
        
        curl_close($cr);
        
        return array('success' => false, 'firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'phone' => $phone); // Agreement was not in place, created new one
    } else {
        debugLog('Existing agreement found, no update needed');
        $creationDateNode = $xpath->query("//note_text/text()[contains(.,\"Equipment Agreement\") and contains(.,\"$semester\")]/../creation_date")->item(0);
        if ($creationDateNode) {
            $agreementDate = $creationDateNode->nodeValue;
        }
        return array('success' => true, 'firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'phone' => $phone, 'agreementDate' => $agreementDate); // Agreement already in place
    }
}

// Call the function and handle the result
$result = pushUserNoteAndCheckAgreement($purdueId, $config);

if (isset($result['error'])) {
    $error = true;
    $errorMessage = $result['error'];
} else {
    $firstName = $result['firstName'];
    $lastName = $result['lastName'];
    $email = $result['email'];
    $phone = $result['phone'];
    $agreementExists = $result['success'];
    $agreementDate = isset($result['agreementDate']) ? $result['agreementDate'] : null;
}

// Log the check-in
$checkInLog = 'logs/checkin_log.csv';
$timestamp = date('Y-m-d H:i:s');
$logEntry = "$purdueId,$timestamp\n";
file_put_contents($checkInLog, $logEntry, FILE_APPEND);

// Process confirmation form submission
if ($_POST && isset($_POST['confirm'])):
    // Here you can handle the final submission logic if needed
    // For example, you can save the agreement to the database or perform other actions
    
    // Clear session data
    session_unset();
    session_destroy();
    
    // Redirect to a success page or display a success message
    header("Location: success.php");
    exit();
endif;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <title>Confirm Your Information</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Confirm Your Information</h1>
    </div>

    <?php if (isset($error) && $error): ?>
        <div class="error-message">
            <b>Error:</b> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php else: ?>
        <?php if ($agreementExists): ?>
            <div class="agreement-note">
                <h2>Existing Agreement</h2>
                <p>Agreement Date: <?php echo htmlspecialchars($agreementDate); ?></p>
            </div>
        <?php else: ?>
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