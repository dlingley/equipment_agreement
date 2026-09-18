<?php
// ===== Centralized Authentication (Auth Hub) =====
$config = include('config.php');
date_default_timezone_set($config['TIMEZONE']);

// Extend PHP session gc_maxlifetime so the kiosk session is never cleaned up by GC during shifts
if (!empty($config['SESSION_CONFIG']['TIMEOUT'])) {
    ini_set('session.gc_maxlifetime', $config['SESSION_CONFIG']['TIMEOUT']);
}

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

if (!$authGuardLoaded) {
    die('Error: Centralized Auth Hub (auth_guard.php) could not be loaded.');
}

// Require authentication via Purdue SSO & allowed_users.txt
$authUser = require_auth(['allowed_users_file' => __DIR__ . '/allowed_users.txt']);

// Sync session variables for downstream compatibility
$_SESSION['logged_in'] = true;
$_SESSION['username'] = $authUser->username;
$_SESSION['user_type'] = $authUser->isAdmin() ? 'admin' : 'user';
$_SESSION['last_activity'] = time();

// ===== Error Reporting Configuration =====
// Log errors to server log; suppress displaying raw errors to public kiosk patrons
error_reporting(E_ALL);
$debugMode = (isset($_GET['debug']) && $_GET['debug'] === '1');
ini_set('display_errors', $debugMode ? 1 : 0);
ini_set('display_startup_errors', $debugMode ? 1 : 0);
ini_set('log_errors', 1);

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
    // Rest of the Logger class implementation remains the same...
    private $logPath;
    private $fallbackPath;
    private $initialized = false;
    private $config;

    public function __construct($config) {
        $this->config = $config;
        
        $possiblePaths = [
            $this->config['LOG_PATHS']['DEBUG'],
            '/var/log/apache2/equipment_agreement_debug.log',
            sys_get_temp_dir() . '/equipment_agreement_debug.log',
            ini_get('upload_tmp_dir') . '/equipment_agreement_debug.log'
        ];

        $this->initializeLogging($possiblePaths);
    }

    private function initializeLogging($possiblePaths) {
        foreach ($possiblePaths as $path) {
            $dir = dirname($path);
            
            if (!file_exists($dir)) {
                if (@mkdir($dir, 0755, true)) {
                    $this->initializeLogFile($path);
                    break;
                }
                continue;
            }
            
            if ($this->initializeLogFile($path)) {
                break;
            }
        }

        if (!$this->initialized) {
            $this->setupErrorHandler();
        }
    }

    private function initializeLogFile($path) {
        if (is_writable(dirname($path))) {
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

    private function setupErrorHandler() {
        set_error_handler(function($errno, $errstr) {
            if (error_reporting() & $errno) {
                error_log("Equipment Agreement Error: $errstr");
                
                if (isset($_GET['debug']) && $_GET['debug'] == '1') {
                    echo "Logging Error: $errstr\n";
                }
            }
            return true;
        });
    }

    public function log($message, $type = 'INFO') {
        date_default_timezone_set($this->config['TIMEZONE']);
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] [$type] $message" . PHP_EOL;
        
        if ($this->initialized) {
            if (@error_log($logMessage, 3, $this->logPath)) {
                return true;
            }
        }
        
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

// ===== Initialize Logging =====
$logger = new Logger($config);

function debugLog($message, $type = 'INFO') {
    global $logger;
    $logger->log($message, $type);
}

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
$message = array();
$error = false;
$showDebug = false;

if (isset($_SESSION['error_message'])) {
    $error = true;
    $errorMsg = htmlspecialchars($_SESSION['error_message']);
    array_push($message, $errorMsg);
    debugLog('Error encountered: ' . $errorMsg, 'ERROR');
    unset($_SESSION['error_message']);
}

if ($_POST) {
    debugLog('Form submitted with POST data: ' . print_r($_POST, true));
}

// ===== Form Submission Handler =====
if ($_POST && isset($_POST['purdueid'])):
    if (empty($_POST["purdueid"])):
        $error = true;
        $errorMsg = "Purdue ID is required.";
        debugLog($errorMsg, 'ERROR');
        array_push($message, "Purdue ID is required.");
    else:
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
    <title>Purdue Libraries Knowledge Lab User Agreement</title>
    <link rel="stylesheet" href="styles.css">
    <script src="session.js"></script>
    <?php if ($error): ?>
    <meta http-equiv="refresh" content="1;url=index.php">
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
        #scannerUI {
            width: 100%;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        #purdueid {
            font-size: 1.2rem;
            padding: 12px;
            width: 100%;
            box-sizing: border-box;
            border: 2px solid #007bff;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        #purdueid:focus {
            outline: none;
            border-color: #0056b3;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('purdueid');
            
            input.setAttribute('autofocus', 'true');
            
            if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                setTimeout(function() {
                    input.focus();
                    input.scrollIntoView(true);
                }, 100);
                
                document.addEventListener('touchend', function firstTouch() {
                    input.focus();
                    document.removeEventListener('touchend', firstTouch);
                }, false);
            } else {
                input.focus();
            }
        });
    </script>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Purdue Libraries Knowledge Lab User Agreement</h1>
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
                You will be redirected to the homepage in 1 second...
            </div>
        </div>
    <?php else: ?>
        <form method="POST" id="agreement_form">
            <div class="important-notice">
            Please scan your Purdue ID. If you are directed to the Knowledge Lab User Agreement, please agree before entering the Lab.
            </div>

            <div class="form-section">
                <div id="scannerUI">
                    <input type="text" id="purdueid" name="purdueid" placeholder="Purdue ID" required inputmode="text" autocomplete="off" spellcheck="false">
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
