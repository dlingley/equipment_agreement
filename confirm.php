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

    if(curl_errno($cr)) {
        $error_msg = 'Curl error: ' . curl_error($cr);
        debugLog($error_msg, 'ERROR');
        return array('error' => $error_msg);
    }

    $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
    debugLog('GET Response HTTP Code: ' . $http_code);
    debugLog('GET Response: ' . $response);

    curl_close($cr);

    if (!$response || !simplexml_load_string($response)) {
        $error_msg = 'Invalid XML response';
        debugLog($error_msg, 'ERROR');
        return array('error' => $error_msg);
    }

    $doc = new DOMDocument();
    $doc->loadXML($response);
    $xpath = new DOMXpath($doc);

    $errorsExist = $xpath->query("//errorsExist[text()='true']");
    if ($errorsExist->length > 0) {
        $errorMessage = $xpath->query("//errorMessage")->item(0)->nodeValue;
        debugLog('API Error: ' . $errorMessage, 'ERROR');
        return array('error' => $errorMessage);
    }

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

        if(curl_errno($cr)) {
            $error_msg = 'Curl error during PUT: ' . curl_error($cr);
            debugLog($error_msg, 'ERROR');
            return array('error' => $error_msg);
        }

        $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
        debugLog('PUT Response HTTP Code: ' . $http_code);
        debugLog('PUT Response: ' . $response);

        curl_close($cr);

        return array(
            'success' => false,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'userGroup' => $userGroup
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
            'userGroup' => $userGroup
        );
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
    $noteText = isset($result['noteText']) ? $result['noteText'] : null;
    $userGroup = $result['userGroup'];
}

// Log the check-in
$checkInLog = 'logs/checkin_log.csv';
$timestamp = date('Y-m-d H:i:s');
$logEntry = "$purdueId,$timestamp,$userGroup\n";
file_put_contents($checkInLog, $logEntry, FILE_APPEND);

// Process confirmation form submission
if ($_POST && isset($_POST['confirm'])) {
    session_unset();
    session_destroy();
    header("Location: success.php");
    exit();
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
    <?php if ($agreementExists): ?>
    <meta http-equiv="refresh" content="5;url=index.php">
    <?php endif; ?>
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
    </div>

    <?php if (isset($error) && $error): ?>
        <div class="error-message">
            <b>Error:</b> <?php echo htmlspecialchars($errorMessage); ?>
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
