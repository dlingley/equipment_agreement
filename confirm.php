<?php
// ===== Session and Error Handling Setup =====
session_start();

// Set timezone to Eastern Time
$config = include('config.php');
date_default_timezone_set($config['TIMEZONE']);

// Enable comprehensive error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ===== Authentication Check =====
if (!isset($_SESSION['purdueid'])) {
    // Log authentication failure
    debugLog("Failed authentication attempt - no Purdue ID in session");
    header("Location: index.php");
    exit();
}

// ===== Configuration Loading =====
$config = include('config.php');

if (!isset($config['ALMA_API_KEY'])) {
    die('API key not set in config.php.');
}

$purdueId = $_SESSION['purdueid'];

/**
 * Writes debug messages to a log file
 * @param string $message The message to log
 * @param string $level The log level (INFO, ERROR, etc.)
 */
function debugLog($message, $level = 'INFO') {
    global $config;
    date_default_timezone_set($config['TIMEZONE']);
    $logFile = $config['LOG_PATHS']['DEBUG'];
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Sends a confirmation email to the user after agreement is signed
 * @param string $email User's email address
 * @param string $firstName User's first name
 * @param string $lastName User's last name
 * @return bool True if email sent successfully, false otherwise
 */
function sendAgreementEmail($email, $firstName, $lastName) {
    global $config;
    
    if (!file_exists('../vendor/autoload.php')) {
        debugLog('PHPMailer not found at ../vendor/autoload.php. Cannot send email.', 'ERROR');
        return false;
    }
    require '../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configure SMTP server settings
        $mail->isSMTP();
        $mail->Host = $config['SMTP_CONFIG']['HOST'];
        $mail->Port = $config['SMTP_CONFIG']['PORT'];
        $mail->SMTPAuth = false;
        $mail->SMTPSecure = false;
        
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->setFrom($config['SMTP_CONFIG']['FROM_EMAIL'], $config['SMTP_CONFIG']['FROM_NAME']);
        $mail->addAddress($email);
        
        // Compose email content
        $mail->isHTML(false);
        $mail->Subject = "Purdue Libraries Knowledge Lab User Agreement Confirmation";
        $message = "Dear $firstName $lastName,\n\n";
        $message .= "This email confirms that you have agreed to the Purdue Libraries Knowledge Lab User Agreement:\n\n";
        $message .= "I hereby agree to the following conditions for use of the Knowledge Lab (the \"Lab\") facilities and equipment.\n\n";

        $message .= "Conditions of Use and General Conduct\n\n";

        $message .= "I will comply with Purdue University (\"Purdue\") and Purdue Libraries policies and procedures including, but not limited to Lab guidelines, signage, and instructions.\n\n";
        $message .= "The Knowledge Lab is a collection of equipment, tools, materials, and supplies intended to allow for exploration, creativity, and innovation. To maintain the privilege of use for all members of the Purdue community we ask that you respect the space, the items, and the other users. All Purdue community members who use the space agree to comply with all Knowledge Lab policies and Purdue University codes of conduct.\n";
        $message .= "   1. Keep the Knowledge Lab and everything within the facility clean and organized. If you take out tools and materials, return them to the correct location and clean up the area used. If equipment or a tool is broken or not functioning correctly, please notify staff immediately.\n";
        $message .= "   2. Be respectful to Knowledge Lab staff, other users of the Knowledge and towards its equipment at all times.\n";
        $message .= "   3. Staff are available to assist patrons with equipment use, understanding materials and processes, and talking through ideas and project concepts for you to complete the work yourself.\n";
        $message .= "   4.  Staff have the right to refuse projects and material usage if they are in violation of Knowledge Lab policies.\n";
        $message .= "   5. Users are responsible for properly monitoring and labeling anything brought into the lab and the Knowledge Lab is not responsible for any lost, damaged, or stolen property.\n";
        $message .= "   6. Use of the Knowledge Lab and materials created will not be:\n";
        $message .= "       a. Prohibited by local, state, or federal law.\n";
        $message .= "       b. Unsafe, harmful, dangerous, or pose an immediate or perceived threat to the well-being of others.\n";
        $message .= "       c. Obscene or otherwise inappropriate for the University and Library environment.\n";
        $message .= "       d. violation of intellectual property rights.\n\n";
        
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
 * Main function to handle user agreement verification and creation.
 * It now uses the official Primary ID from the API response for all logging and update actions.
 * 
 * @param string $purdueId_input The raw ID scanned by the user.
 * @param array $config Application configuration
 * @return array Result of the operation
 */
function pushUserNoteAndCheckAgreement($purdueId_input, $config) {
    // Define Alma API endpoints and parameters
    $ALMA_REQ_URL = $config['ALMA_API_CONFIG']['BASE_URL'];
    $ALMA_API_KEY = $config['ALMA_API_KEY'];
    $ALMA_GET_PARAM = $config['ALMA_API_CONFIG']['GET_PARAMS'] . '&apikey=';
    $ALMA_PUT_PARAM = $config['ALMA_API_CONFIG']['PUT_PARAMS'] . '&apikey=';
    
    debugLog('Starting API call for user input ID: ' . $purdueId_input);
    
    // Initialize cURL for GET request to fetch user information
    $cr = curl_init();
    curl_setopt_array($cr, [
        CURLOPT_URL => sprintf("%s%s%s%s", $ALMA_REQ_URL, $purdueId_input, $ALMA_GET_PARAM, $ALMA_API_KEY),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Accept: application/xml"],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($cr);
    $http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
    
    if (curl_errno($cr)) {
        $error_msg = 'cURL Error: ' . curl_error($cr);
        debugLog($error_msg, 'ERROR');
        curl_close($cr);
        return ['error' => $error_msg];
    }
    curl_close($cr);

    // Robust error handling for non-successful API calls
    if ($http_code >= 400) {
        $error_message = "User not found for ID '" . htmlspecialchars($purdueId_input) . "'."; // Default message
        if ($response && ($xml = @simplexml_load_string($response))) {
            if (isset($xml->errorList->error->errorMessage)) {
                $error_message = (string)$xml->errorList->error->errorMessage;
            }
        }
        debugLog("API returned HTTP error $http_code. Message: '$error_message'", 'ERROR');
        return ['error' => $error_message]; // Stop execution and return the error
    }

    $doc = new DOMDocument();
    if (!$response || !@$doc->loadXML($response)) {
        debugLog("Could not parse valid XML from API response.", 'ERROR');
        return ['error' => 'Invalid XML response from the server.'];
    }
    
    $xpath = new DOMXpath($doc);

    // ** NEW: True Normalization - Get the official ID from the API response **
    // We will use this official ID for all logging and update operations.
    $purdueId_official = $xpath->query("//primary_id")->item(0)->nodeValue ?? $purdueId_input;
    debugLog("Found official Primary ID from API: $purdueId_official");

    // Extract all other user data
    $firstName = $xpath->query("//first_name")->item(0)->nodeValue ?? '';
    $lastName = $xpath->query("//last_name")->item(0)->nodeValue ?? '';
    $email = $xpath->query("//email_address")->item(0)->nodeValue ?? '';
    $phone = $xpath->query("//phone_number")->item(0)->nodeValue ?? '';
    $fullName = $xpath->query("//full_name")->item(0)->nodeValue ?? trim("$firstName $lastName");
    $userGroup = $xpath->query('//user_group')->item(0)->nodeValue ?? 'unknown';
    $userStatus = $xpath->query('//status')->item(0)->nodeValue ?? 'N/A';
    $campusCode = $xpath->query('//campus_code')->item(0)->nodeValue ?? 'N/A';
    
    $jobDescription = $xpath->query('//job_description')->item(0)->nodeValue ?? '';
    $department = 'N/A';
    $classification = 'N/A';
    if (in_array($userGroup, ['staff', 'faculty']) && strpos($jobDescription, '|') !== false) {
        $parts = array_map('trim', explode('|', $jobDescription));
        $department = $parts[0] ?? 'N/A';
        $classification = $parts[1] ?? 'N/A';
    } else {
        $department = $xpath->query("//user_statistic[category_type='dept']/statistic_note")->item(0)->nodeValue ?? 'N/A';
        $classification = $xpath->query("//user_statistic[category_type='semester']/statistic_note")->item(0)->nodeValue ?? 'N/A';
    }

    $checkInLogFile = $config['LOG_PATHS']['CHECKIN'];
    
    // Encapsulate logging logic - ** NOW USES THE OFFICIAL ID **
    $logVisit = function($agreementStatus) use ($purdueId_official, $checkInLogFile, $fullName, $userGroup, $department, $classification, $campusCode, $userStatus) {
        $visitCount = 0;
        $lastVisitTimestamp = 0;
        if (is_readable($checkInLogFile) && ($handle = fopen($checkInLogFile, "r"))) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, '"purdueId":"'.$purdueId_official.'"') !== false) {
                    $visitCount++;
                    $data = json_decode(trim($line), true);
                    if ($data && isset($data['timestamp'])) {
                        $currentTs = strtotime($data['timestamp']);
                        if ($currentTs > $lastVisitTimestamp) $lastVisitTimestamp = $currentTs;
                    }
                }
            }
            fclose($handle);
        }
        if ($lastVisitTimestamp > 0 && (time() - $lastVisitTimestamp < 30)) {
            debugLog("Skipping duplicate check-in for user: $purdueId_official");
            return false;
        }
        $visitCount++;
        
        $logData = [
            'purdueId' => $purdueId_official, 'timestamp' => date('Y-m-d H:i:s'),
            'fullName' => $fullName, 'userGroup' => $userGroup,
            'department' => $department, 'classification' => $classification,
            'campusCode' => $campusCode, 'userStatus' => $userStatus,
            'visitCount' => $visitCount, 'agreementStatus' => $agreementStatus
        ];
        file_put_contents($checkInLogFile, json_encode($logData) . "\n", FILE_APPEND);
        debugLog("Logged JSON check-in: " . json_encode($logData));
        return true;
    };

    // ===== AGREEMENT HANDLING =====
    $note_text_nodes = $xpath->query("//user_note[note_text[contains(text(),'Agreed to Knowledge Lab User Agreement')]]");

    if ($note_text_nodes->length > 0) {
        $logVisit('had_agreement');
        return ['success' => true, 'agreement_exists' => true];
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
        debugLog('No existing agreement found, creating new note based on POST confirmation.');
        
        $putDoc = new DOMDocument();
        $putDoc->loadXML($response);
        $putXpath = new DOMXPath($putDoc);
        if ($rolesNode = $putXpath->query("//user_roles")->item(0)) {
            $rolesNode->parentNode->removeChild($rolesNode);
        }
        $userNotes = $putXpath->query("//user_notes")->item(0) ?: $putDoc->createElement("user_notes");
        $putDoc->documentElement->appendChild($userNotes);
        
        $userNote = $putDoc->createElement("user_note");
        $userNote->setAttribute("segment_type", "Internal");
        $userNotes->appendChild($userNote);
        $userNote->appendChild($putDoc->createElement("note_type", "CIRCULATION"));
        $userNote->appendChild($putDoc->createElement("note_text", "Agreed to Knowledge Lab User Agreement"));
        $userNote->appendChild($putDoc->createElement("user_viewable", "true"));
        $userNote->appendChild($putDoc->createElement("popup_note", "true"));
        
        // ** NEW: Use the OFFICIAL ID for the PUT request to update the correct user **
        $cr = curl_init();
        curl_setopt_array($cr, [
            CURLOPT_URL => sprintf("%s%s%s%s", $ALMA_REQ_URL, $purdueId_official, $ALMA_PUT_PARAM, $ALMA_API_KEY),
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $putDoc->saveXML(),
            CURLOPT_HTTPHEADER => ["Content-Type: application/xml", "Accept: application/xml"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $putResponse = curl_exec($cr);
        $put_http_code = curl_getinfo($cr, CURLINFO_HTTP_CODE);
        curl_close($cr);
        if ($put_http_code !== 200) {
            debugLog("Failed to update user note. HTTP: $put_http_code, Response: $putResponse", "ERROR");
            return ['error' => 'Failed to update user note. Please try again later.'];
        }
        
        sendAgreementEmail($email, $firstName, $lastName);
        $logVisit('created_agreement');
        return ['success' => true, 'agreement_created' => true];
    }
    
    return [
        'success' => true, 'needs_confirmation' => true, 'firstName' => $firstName, 
        'lastName' => $lastName, 'email' => $email, 'phone' => $phone, 'userGroup' => $userGroup
    ];
}

// ===== Main Execution Flow =====
$result = pushUserNoteAndCheckAgreement($purdueId, $config);

if (isset($result['error'])) {
    $_SESSION['error_message'] = $result['error'];
    header("Location: index.php");
    exit();
}

if (isset($result['agreement_created']) || isset($result['agreement_exists'])) {
    header("Location: success.php");
    exit();
}

$firstName = $result['firstName'];
$lastName = $result['lastName'];
$email = $result['email'];
$phone = $result['phone'];
$userGroup = $result['userGroup'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <title>Confirm Your Information</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .agreement-section { max-width: 800px; margin: 2rem auto; padding: 2rem; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .agreement-section h2 { color: #212529; margin-top: 2rem; }
        .agreement-section p, .agreement-section ul { color: #495057; line-height: 1.6; }
        .agreement-section ul { padding-left: 2rem; }
        .final-statement { font-weight: bold; margin-top: 2rem; padding: 1rem; background-color: #f8f9fa; border-radius: 4px; }
        .form-section input[readonly] { background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; cursor: not-allowed; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { font-weight: bold; color: #212529; }
    </style>
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Knowledge Lab User Agreement</h1>
    </div>
    
    <section class="agreement-section">
        <h2>Please scroll to the bottom and click "I Agree"</h2>
        <h3>You will also receive a copy of this agreement via email.</h3>
        <p>I hereby agree to the following conditions for use of the Knowledge Lab (the "Lab") facilities and equipment.</p>
        <h2>Conditions of Use and General Conduct</h2>
        <p>I will comply with Purdue University ("Purdue") and Purdue Libraries policies and procedures including, but not limited to Lab guidelines, signage, and instructions.</p>
        <p>The Knowledge Lab is a collection of equipment, tools, materials, and supplies intended to allow for exploration, creativity, and innovation. To maintain the privilege of use for all members of the Purdue community we ask that you respect the space, the items, and the other users. All Purdue community members who use the space agree to comply with all Knowledge Lab policies and Purdue University codes of conduct.</p>
        <ul>
            <li>Keep the Knowledge Lab and everything within the facility clean and organized. If you take out tools and materials, return them to the correct location and clean up the area used. If equipment or a tool is broken or not functioning correctly, please notify staff immediately.</li>
            <li>Be respectful to Knowledge Lab staff, other users of the Knowledge and towards its equipment at all times.</li>
            <li>Staff are available to assist patrons with equipment use, understanding materials and processes, and talking through ideas and project concepts for you to complete the work yourself.</li>
            <li>Staff have the right to refuse projects and material usage if they are in violation of Knowledge Lab policies.</li>
            <li>Users are responsible for properly monitoring and labeling anything brought into the lab and the Knowledge Lab is not responsible for any lost, damaged, or stolen property.</li>
        </ul>
        <p>Use of the Knowledge Lab and materials created will not be:</p>
        <ul>
            <li>Prohibited by local, state, or federal law.</li>
            <li>Unsafe, harmful, dangerous, or pose an immediate or perceived threat to the well-being of others.</li>
            <li>Obscene or otherwise inappropriate for the University and Library environment.</li>
            <li>In violation of intellectual property rights.</li>
        </ul>
        <h2>Knowledge Lab Access</h2>
        <p>The Knowledge Lab is open and free to those affiliated with Purdue. Always bring a valid Purdue ID to swipe in at the front desk, all first time users must fill out a release form. The Knowledge Lab is not open to the public. Outside guests may accompany members of the Purdue community but may not use any of the tools, equipment, or materials in the space. Guests under 18 years old must be accompanied by their parent or legal guardian at all times and may not be left alone in the Knowledge Lab.</p>
        <h2>Material Usage</h2>
        <p>Materials available in the Knowledge Lab are a courtesy provided by Purdue University and are intended to be used in the lab. Please be respectful of the resources provided and avoid wasting consumable supplies and materials. We cannot guarantee the availability of any materials at any time. If a project requires a larger amount of materials that exceeds our monthly allotments, Knowledge Lab staff can provide you with information on recommended materials and suppliers. Our facility is a place of learning and exploration. The free materials in the Knowledge are not to be used for commercial purposes or mass production.</p>
        <h2>Copyright</h2>
        <p>The Knowledge Lab encourages innovation and creations of one's own design. Projects created in the Knowledge Lab must respect and comply with intellectual property laws at all times, including, but not limited to, trademarks, logos, and copyrighted designs.</p>
        <h2>Safety and Assumption of Risk</h2>
        <p>Use of the Lab facility, tools, equipment, and materials is entirely voluntary and optional. Such use involves inherent hazards, dangers, and risks. I hereby agree that I assume all responsibility for any risks of loss, damage, or personal injury that I may sustain and/or any loss or damage to property that I own, as a result of being engaged in Lab activities, whether caused by the negligence of the Lab personnel, equipment or otherwise.</p>
        <h2>Release of Liability</h2>
        <p>I hereby release and forever discharge Purdue University, the Board of Trustees of Purdue University, its members individually, and the officers, agents and employees of Purdue University from any and all claims, demands, rights and causes of action of whatever kind that I may have, caused by or arising from my use of the Lab facilities, tools, equipment, or materials regardless of whether or not caused in whole or part by the negligence or other fault of the parties to this agreement.</p>
        <h2>Indemnification</h2>
        <p>I agree to indemnify and hold Purdue harmless from and against any and all losses, liabilities, damages, costs or expenses (including but not limited to reasonable attorneys' fees and other litigation costs and expenses) incurred by Purdue.</p>
        <h2>Consent to Medical Treatment</h2>
        <p>I give permission for Purdue and its employees, volunteers, agents, representatives and emergency personnel to make necessary first aid decisions in the event of an accident, injury, or illness I may suffer during the use of the Lab. If I need medical treatment, I will be financially responsible for any costs incurred as a result of such treatment.</p>
        <h2>Miscellaneous</h2>
        <p>A. PURDUE EXPRESSLY DISCLAIMS ALL WARRANTIES OF ANY KIND, EXPRESS, IMPLIED, STATUTORY OR OTHERWISE, INCLUDING WITHOUT LIMITATION IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE AND NON-INFRINGEMENT WITH REGARD TO THE LAB, EQUIPMENT, TOOLS AND MATERIALS.</p>
        <p>B. If any provision of this document is determined to be invalid for any reason, such invalidity shall not affect the validity of any other provisions, which other provisions shall remain in full force and effect as if this agreement had been executed with the invalid provision eliminated.</p>
        <p>C. This agreement is entered into in Indiana and shall be governed by and construed in accordance with the substantive law (and not the law of conflicts) of the State of Indiana and applicable U.S. federal law. Courts of competent authority located in Tippecanoe County, Indiana shall have sole and exclusive jurisdiction of any action arising out of or in connection with the agreement, and such courts shall be the sole and exclusive venue for any such action.</p>
        <p class="final-statement">I hereby warrant that I am eighteen (18) years old or more and competent to contract in my own name or, if I am less than eighteen years old, that my parent or guardian has signed this release form below. This release is binding on me and my heirs, assigns and personal representatives.</p>
    </section>

    <!-- Confirmation form for new agreement -->
    <h2>Confirm Your Information</h2>
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
                <input type="submit" name="confirm" value="I Agree">
                <input type="reset" name="cancel" value="Cancel" onclick="window.location='index.php'; return false;">
            </div>
        </div>
    </form>
</body>
</html>