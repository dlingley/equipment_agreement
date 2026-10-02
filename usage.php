<?php
$config = include('config.php');
date_default_timezone_set($config['TIMEZONE'] ?? 'America/Indianapolis');

$possibleGuards = [
    __DIR__ . '/../auth/auth_guard.php',
    '/var/www/html/webapps/alma/auth/auth_guard.php',
    '/Volumes/alma$/auth/auth_guard.php',
];

$authGuardLoaded = false;
foreach ($possibleGuards as $guardPath) {
    if (file_exists($guardPath)) {
        require_once $guardPath;
        $authGuardLoaded = true;
        break;
    }
}

if ($authGuardLoaded) {
    auth_start_session();
} else {
    session_start();
}

// If no pending checkin is set (e.g., duplicate or direct access), redirect to success
if (!isset($_SESSION['pending_checkin'])) {
    header("Location: success.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usage'])) {
    $usage = $_POST['usage'];
    $valid_options = [
        "Coursework",
        "Research or scholarly work",
        "Personal project",
        "Student club/organization",
        "University department/organization",
        "Class visit/tour",
        "Other"
    ];
    
    if (in_array($usage, $valid_options)) {
        $logData = $_SESSION['pending_checkin'];
        $logData['usageReason'] = $usage;
        
        $checkInLogFile = dirname(__FILE__) . '/' . $config['LOG_PATHS']['CHECKIN'];
        $checkInDir = dirname($checkInLogFile);
        if (!is_dir($checkInDir)) {
            @mkdir($checkInDir, 0777, true);
        }
        $checkInRes = @file_put_contents($checkInLogFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
        if ($checkInRes === false) {
            @error_log("Equipment Agreement ERROR: Could not append check-in log to $checkInLogFile");
        }
        
        // Unset so if they go back they skip
        unset($_SESSION['pending_checkin']);
        header("Location: success.php");
        exit();
    } else {
        $error = 'Please select a valid option.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <title>Knowledge Lab Usage</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-section {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .usage-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            text-align: left;
        }
        .usage-options label {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: background-color 0.2s;
        }
        .usage-options label:hover {
            background-color: #f8f9fa;
        }
        .usage-options input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.5);
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Knowledge Lab Check-In</h1>
    </div>

    <form method="POST">
        <div class="form-section">
            <h2>How are you utilizing the Knowledge Lab today? Pick the closest option.</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="usage-options">
                <label><input type="radio" name="usage" value="Coursework" required> Coursework</label>
                <label><input type="radio" name="usage" value="Research or scholarly work"> Research or scholarly work</label>
                <label><input type="radio" name="usage" value="Personal project"> Personal project</label>
                <label><input type="radio" name="usage" value="Student club/organization"> Student club/organization</label>
                <label><input type="radio" name="usage" value="University department/organization"> University department/organization</label>
                <label><input type="radio" name="usage" value="Class visit/tour"> Class visit/tour</label>
                <label><input type="radio" name="usage" value="Other"> Other</label>
            </div>
            <div class="button-group">
                <input type="submit" value="Submit">
            </div>
        </div>
    </form>
</body>
</html>
