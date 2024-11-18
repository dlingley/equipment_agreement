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
    private $config;  // Add config property

    /**
     * Constructor: Sets up the logging system when a new Logger is created
     * Tries different locations to store log files based on system permissions
     */
    public function __construct($config) {  // Add config parameter
        $this->config = $config;  // Store config
        
        // Define possible locations for log files in order of preference
        $possiblePaths = [
            $this->config['LOG_PATHS']['DEBUG'],  // Use config from class property
            '/var/log/apache2/equipment_agreement_debug.log',
            sys_get_temp_dir() . '/equipment_agreement_debug.log',
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
$logger = new Logger($config);    // Pass config to Logger constructor

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
        <h1>Purdue Libraries Knowledge Lab User Agreement</h1>
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
                Read this document before signing. This legally binding contract must be signed prior to working in the Knowledge Lab.
            </div>

            <!-- Agreement Content Section -->
            <section class="agreement-section">
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
