<?php
// Start session first
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Add these checks before the Logger class:
if (!function_exists('posix_getpwuid')) {
    function posix_getpwuid($uid) {
        return array('name' => 'unknown');
    }
}

if (!function_exists('posix_getgrgid')) {
    function posix_getgrgid($gid) {
        return array('name' => 'unknown');
    }
}

// Logging configuration and helper class
class Logger {
    private $logPath;
    private $fallbackPath;
    private $initialized = false;

    public function __construct() {
        // Try multiple possible log locations in order of preference
        $possiblePaths = [
            // Application-specific directory (most preferred)
            dirname(__FILE__) . '/logs/equipment_agreement_debug.log',
            // Apache logs directory
            '/var/log/apache2/equipment_agreement_debug.log',
            // System temp directory
            sys_get_temp_dir() . '/equipment_agreement_debug.log',
            // PHP's upload_tmp_dir
            ini_get('upload_tmp_dir') . '/equipment_agreement_debug.log'
        ];

        $this->initializeLogging($possiblePaths);
    }

    private function initializeLogging($possiblePaths) {
        foreach ($possiblePaths as $path) {
            $dir = dirname($path);
            
            // Try to create directory if it doesn't exist
            if (!file_exists($dir)) {
                if (@mkdir($dir, 0755, true)) {
                    $this->initializeLogFile($path);
                    break;
                }
                continue;
            }
            
            // Directory exists, try to initialize log file
            if ($this->initializeLogFile($path)) {
                break;
            }
        }

        // If no logging location works, set up error handler
        if (!$this->initialized) {
            $this->setupErrorHandler();
        }
    }

    private function initializeLogFile($path) {
        // Check if we can write to the directory
        if (is_writable(dirname($path))) {
            // Create log file if it doesn't exist
            if (!file_exists($path)) {
                if (@touch($path)) {
                    chmod($path,0644);
                }
            }
            
            // Verify we can write to the log file
            if (is_writable($path)) {
                $this->logPath = $path;
                $this->initialized = true;
                return true;
            }
        }
        return false;
    }

    private function setupErrorHandler() {
        // Set up error handler for when logging fails
        set_error_handler(function($errno, $errstr) {
            if (error_reporting() & $errno) {
                // Try to log to PHP's error log
                error_log("Equipment Agreement Error: $errstr");
                
                // If in debug mode, display the error
                if (isset($_GET['debug']) && $_GET['debug'] == '1') {
                    echo "Logging Error: $errstr\n";
                }
            }
            return true;
        });
    }

    public function log($message, $type = 'INFO') {
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] [$type] $message" . PHP_EOL;
        
        if ($this->initialized) {
            // Try to write to our log file
            if (@error_log($logMessage, 3, $this->logPath)) {
                return true;
            }
        }
        
        // Fallback to PHP's error_log
        error_log("Equipment Agreement: $message");
        return false;
    }

    public function getLogPath() {
        return $this->initialized ? $this->logPath : null;
    }

    public function isInitialized() {
        return $this->initialized;
    }
}

// Initialize logger
$logger = new Logger();

// Replace the existing debugLog function with this new version
function debugLog($message, $type = 'INFO') {
    global $logger;
    $logger->log($message, $type);
}

// Add logging status to debug information
function getLoggingStatus() {
    global $logger;
    return [
        'Logging Initialized' => $logger->isInitialized() ? 'Yes' : 'No',
        'Log File Path' => $logger->getLogPath() ?: 'Not set - using fallback',
        'PHP Error Log Path' => ini_get('error_log'),
        'Current Script Owner' => posix_getpwuid(posix_geteuid())['name'],
        'Current Script Group' => posix_getgrgid(posix_getegid())['name'],
        'Upload Temp Dir' => ini_get('upload_tmp_dir'),
        'Sys Temp Dir' => sys_get_temp_dir()
    ];
}

$message = array();
$error = false;

// Define the $showDebug variable
$showDebug = false;

// Check for error message in session
if (isset($_SESSION['error_message'])) {
    $error = true;
    $errorMsg = htmlspecialchars($_SESSION['error_message']);
    array_push($message, $errorMsg);
    debugLog('Error encountered: ' . $errorMsg, 'ERROR');
    // Clear the error message from session after displaying
    unset($_SESSION['error_message']);
}

// Debug POST data
if ($_POST) {
    debugLog('Form submitted with POST data: ' . print_r($_POST, true));
}

// Process form submission
if ($_POST && isset($_POST['purdueid'])):
    // Validate Purdue ID
    if (empty($_POST["purdueid"])):
        $error = true;
        $errorMsg = "Purdue ID is required.";
        debugLog($errorMsg, 'ERROR');
        array_push($message, "Purdue ID is required.");
    else:
        // Store Purdue ID in session for the next step
        $_SESSION['purdueid'] = $_POST["purdueid"];
        
        // Redirect to confirmation page
        header("Location: confirm.php");
        exit();
    endif;
endif;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <title>Purdue Libraries Equipment Agreement</title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($error): ?>
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
        .redirect-message {
            color: #6c757d;
            font-style: italic;
            margin-top: 1rem;
        }
        .logout-form {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .logout-button, .admin-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
        }
        .logout-button:hover, .admin-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Purdue Libraries Equipment Agreement</h1>
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
        <div class="header-buttons">
            <a href="admin.php" class="button">Admin Page</a>
            <a href="logout.php" class="button">Logout</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="error-container">
            <div class="error-message">
                <h2>Error</h2>
                <ul>
                    <?php foreach ($message as $msg) { print "<li>$msg</li>"; } ?>
                </ul>
            </div>
            <div class="redirect-message">
                You will be redirected to the homepage in 5 seconds...
            </div>
        </div>
    <?php else: ?>
        <form method="POST" id="agreement_form">
            <div class="important-notice">
                Read this document before signing. This legally binding contract must be signed prior to equipment checkout and is valid until August 17, 2025.
            </div>

            <div id="agreement_content">
                <p>I have provided my current Purdue University Identification Number, Purdue email address, full name, and phone number so Purdue Libraries staff may contact me regarding the status and/or terms of my equipment request. I am solely responsible for keeping Purdue Libraries informed with my accurate contact information.</p>

                <p>I acknowledge that it is my responsibility to check the condition of the equipment I am receiving at the time of checkout.</p>

                <p>Purdue Libraries staff documents the condition of the equipment upon both checkout and return, and I will be financially responsible for any cosmetic wear and tear or other damage, loss, or theft that occurs while the equipment is on loan to me. I will immediately report any damage, loss, or theft of the borrowed equipment during my loan period to Purdue Libraries. In the event that the borrowed equipment is stolen, I am required to immediately notify library staff and provide a police report detailing the theft of the equipment.</p>

                <p>I understand that when returning any borrowed equipment, staff will check the condition of all items while I am present. I acknowledge that if I do not remain at the service point during this process, I will accept responsibility for any damage that is deemed to have occurred while on loan to me.</p>

                <p>I acknowledge and agree that I shall not have equipment repaired by an outside source. I understand all repairs of university equipment must be handled by Purdue Libraries Knowledge Lab. Any violation will warrant repair charges not to exceed a replacement charge.</p>

                <p>I acknowledge and understand that any and all equipment borrowed must be returned to the appropriate service point in the library by the date and time noted in the email receipt that was sent to my Purdue email address. Failure to act in accordance with the terms within the receipt may result in the forfeiture of borrowing privileges, and/or replacement fees based on the current replacement cost of the borrowed item.</p>

                <p>I agree that I will not install, modify, or copy software on borrowed equipment and will not remove "Library Use Only" equipment from the library. I further understand that my violation may warrant an intervention by Purdue University Police Department to retrieve my borrowed equipment in addition to any fines and forfeiture of borrowing privileges that may be levied against me.</p>

                <p>I hereby release Purdue University from liability and responsibility whatsoever for any claim of action that I, my estate, heirs, executors, or assigns may have for any personal injury, property damage, or wrongful death arising from the activities of my voluntary equipment request and agree to indemnify and hold harmless Purdue University from any demands, loss, liability, claims, or expenses (including attorneys' fees), made against the University by any third party, arising out of or in connection with my borrowing equipment from the University.</p>

                <p class="bold">I understand this is a legally binding agreement and specifically agree to the terms herein as a condition for using the equipment.</p>
            </div>

            <div class="form-section">
                <div class="form-group">
                    <label for="purdueid">Purdue ID:</label>
                    <input type="text" id="purdueid" name="purdueid" required>
                </div>
                <div class="button-group">
                    <input type="submit" name="submit" value="Submit">
                    <input type="reset" name="cancel" value="Cancel" onclick="window.location=''; return false;">
                </div>
            </div>
        </form>
    <?php endif; ?>
    
    <?php if ($showDebug): ?>
    <div class="debug-panel">
        <h2>Debug Information</h2>
        <pre>
<?php
    echo "Debug Information:\n";
    foreach (getLoggingStatus() as $key => $value) {
        echo htmlspecialchars("$key: $value") . "\n";
    }
    
    echo "\nPOST Data:\n";
    echo htmlspecialchars(print_r($_POST, true));
    
    echo "\nSession Data:\n";
    echo htmlspecialchars(print_r($_SESSION, true));
    
    if (file_exists('equipment_agreement_debug.log')) {
        echo "\nRecent Log Entries:\n";
        $logEntries = array_slice(file('equipment_agreement_debug.log'), -10);
        foreach ($logEntries as $entry) {
            echo htmlspecialchars($entry);
        }
    }
?>
        </pre>
    </div>
    <?php endif; ?>
</body>
</html>
