<?php
// ===== Session Management =====
// Start a new session or resume an existing one. Sessions are used to store user data across multiple pages
session_start();

// ===== Authentication Check =====
// Check if the user is logged in by verifying session variables
// If not logged in, redirect them to the login page for security
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// ===== Error Reporting Configuration =====
// Enable all types of error reporting for debugging purposes
// This helps developers see any PHP errors that occur
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ===== Load Configuration =====
// Include the config file which contains important settings
$config = include('config.php');

// ===== POSIX Function Compatibility =====
// These functions provide fallbacks for systems that don't have POSIX functions
// POSIX functions are used to get user and group information on Unix-like systems
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

// ===== Logging System =====
/**
 * Logger Class: Handles all logging operations for the application
 * This class provides methods to write log messages to files and handle errors
 */
class Logger {
    // Class properties to store log file path and initialization status
    private $logPath;
    private $fallbackPath;
    private $initialized = false;

    /**
     * Constructor: Sets up the logging system when a new Logger is created
     * Tries different locations to store log files based on system permissions
     */
    public function __construct() {
        // Define possible locations for log files in order of preference
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

    /**
     * Attempts to set up logging in one of the possible locations
     * Goes through each path until it finds one that works
     */
    private function initializeLogging($possiblePaths) {
        foreach ($possiblePaths as $path) {
            $dir = dirname($path);
            
            // Try to create the log directory if it doesn't exist
            if (!file_exists($dir)) {
                if (@mkdir($dir, 0755, true)) {
                    $this->initializeLogFile($path);
                    break;
                }
                continue;
            }
            
            // If directory exists, try to set up the log file
            if ($this->initializeLogFile($path)) {
                break;
            }
        }

        // If no logging location works, set up an error handler as fallback
        if (!$this->initialized) {
            $this->setupErrorHandler();
        }
    }

    /**
     * Creates and sets up permissions for the log file
     */
    private function initializeLogFile($path) {
        if (is_writable(dirname($path))) {
            // Create log file if it doesn't exist
            if (!file_exists($path)) {
                if (@touch($path)) {
                    chmod($path,0644);
                }
            }
            
            if (is_writable($path)) {
                $this->logPath = $path;
                $this->initialized = true;
                return true;
            }
        }
        return false;
    }

    /**
     * Sets up a custom error handler for when logging fails
     */
    private function setupErrorHandler() {
        set_error_handler(function($errno, $errstr) {
            if (error_reporting() & $errno) {
                error_log("Equipment Agreement Error: $errstr");
                
                // Show errors in debug mode only
                if (isset($_GET['debug']) && $_GET['debug'] == '1') {
                    echo "Logging Error: $errstr\n";
                }
            }
            return true;
        });
    }

    /**
     * Writes a message to the log file
     * @param string $message The message to log
     * @param string $type The type of log message (INFO, ERROR, etc.)
     */
    public function log($message, $type = 'INFO') {
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] [$type] $message" . PHP_EOL;
        
        if ($this->initialized) {
            // Try to write to our log file
            if (@error_log($logMessage, 3, $this->logPath)) {
                return true;
            }
        }
        
        // Fallback to PHP's built-in error log
        error_log("Equipment Agreement: $message");
        return false;
    }

    // Getter methods to check logger status
    public function getLogPath() {
        return $this->initialized ? $this->logPath : null;
    }

    public function isInitialized() {
        return $this->initialized;
    }
}

// ===== Initialize Logging =====
// Create a new logger instance to handle application logging
$logger = new Logger();

/**
 * Helper function to write debug messages to the log
 */
function debugLog($message, $type = 'INFO') {
    global $logger;
    $logger->log($message, $type);
}

/**
 * Gets the current status of the logging system
 * Used for debugging and monitoring
 */
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

// ===== Form Processing =====
// Initialize variables for message handling and error states
$message = array();
$error = false;

// Define the $showDebug variable
$showDebug = false;

// Check for any error messages stored in the session
if (isset($_SESSION['error_message'])) {
    $error = true;
    $errorMsg = htmlspecialchars($_SESSION['error_message']);
    array_push($message, $errorMsg);
    debugLog('Error encountered: ' . $errorMsg, 'ERROR');
    // Clear the error message after displaying it
    unset($_SESSION['error_message']);
}

// Log form submissions for debugging
if ($_POST) {
    debugLog('Form submitted with POST data: ' . print_r($_POST, true));
}

// ===== Form Submission Handler =====
// Process the form when it's submitted with a Purdue ID
if ($_POST && isset($_POST['purdueid'])):
    // Validate that Purdue ID is not empty
    if (empty($_POST["purdueid"])):
        $error = true;
        $errorMsg = "Purdue ID is required.";
        debugLog($errorMsg, 'ERROR');
        array_push($message, "Purdue ID is required.");
    else:
        // Store the Purdue ID in session and move to confirmation page
        $_SESSION['purdueid'] = $_POST["purdueid"];
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
    <!-- Auto-refresh page after 5 seconds when there's an error -->
    <meta http-equiv="refresh" content="5;url=index.php">
    <?php endif; ?>
    <style>
        /* CSS styles for error messages and UI components */
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
    <!-- Header Section -->
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Purdue Libraries Equipment Agreement</h1>
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
        <!-- Show admin navigation buttons if user is an admin -->
        <div class="header-buttons">
            <a href="admin.php" class="button">Admin Page</a>
            <a href="logout.php" class="button">Logout</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <!-- Error message display section -->
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
        <!-- Main Agreement Form -->
        <form method="POST" id="agreement_form">
            <!-- Notice about the agreement's legal binding nature -->
            <div class="important-notice">
                Read this document before signing. This legally binding contract must be signed prior to equipment checkout and is valid until <?php echo htmlspecialchars($config['SEMESTER_END_DATE']); ?>.
            </div>

            <!-- Agreement Content Section -->
            <div id="agreement_content">
                <!-- Each paragraph represents a different term of the agreement -->
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

            <!-- Form Input Section -->
            <div class="form-section">
                <!-- Purdue ID input field -->
                <div class="form-group">
                    <label for="purdueid">Purdue ID:</label>
                    <input type="text" id="purdueid" name="purdueid" required>
                </div>
                <!-- Form submission buttons -->
                <div class="button-group">
                    <input type="submit" name="submit" value="Submit">
                    <input type="reset" name="cancel" value="Cancel" onclick="window.location=''; return false;">
                </div>
            </div>
        </form>
    <?php endif; ?>
    
    <?php if ($showDebug): ?>
        <!-- Debug Information Panel (only shown when debug mode is enabled) -->
        <div class="debug-panel">
            <h2>Debug Information</h2>
            <pre>
<?php
    // Display various debug information about the system and application state
    echo "Debug Information:\n";
    foreach (getLoggingStatus() as $key => $value) {
        echo htmlspecialchars("$key: $value") . "\n";
    }
    
    echo "\nPOST Data:\n";
    echo htmlspecialchars(print_r($_POST, true));
    
    echo "\nSession Data:\n";
    echo htmlspecialchars(print_r($_SESSION, true));
    
    // Display recent log entries if available
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
