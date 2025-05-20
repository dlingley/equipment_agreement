<?php
// ===== Session Management and Authentication =====
session_start();

// Load application configuration
$config = include('config.php');

// Set session configuration using loaded config values
ini_set('session.gc_maxlifetime', $config['SESSION_CONFIG']['TIMEOUT']);
session_set_cookie_params([
    'lifetime' => $config['SESSION_CONFIG']['TIMEOUT'],
    'secure' => $config['SESSION_CONFIG']['SECURE'],
    'httponly' => $config['SESSION_CONFIG']['HTTP_ONLY'],
    'samesite' => 'Strict'
]);

// Update session activity
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['last_regeneration']) || 
    (time() - $_SESSION['last_regeneration']) > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Verify user is logged in and has admin privileges
// If not, redirect to login page for security
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle logout request
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// ===== Error Reporting Configuration =====
// Enable comprehensive error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

// Log script initialization
error_log("Admin panel initializing at " . date('Y-m-d H:i:s'));
error_log("Script path: " . __FILE__);
error_log("Config loaded: " . print_r($config['LOG_PATHS'], true));

// ===== Debug Operations =====
// Initialize debug message variables
$debugMessage = '';
$debugError = '';

/**
 * Retrieves debug log entries from the log file
 * @param array $config Application configuration
 * @param int $limit Number of lines to retrieve (default 100)
 * @return array Array of debug entries
 */
function getDebugLogEntries($config, $limit = 100) {
    if (!isset($config['LOG_PATHS']) || !isset($config['LOG_PATHS']['DEBUG'])) {
        error_log('Configuration error: LOG_PATHS[DEBUG] not properly set');
        return array();
    }
    
    $rootDir = dirname(__FILE__);
    $debugLogFile = $rootDir . '/' . $config['LOG_PATHS']['DEBUG'];
    
    error_log("Attempting to read debug log from: $debugLogFile");
    
    if (!file_exists($debugLogFile)) {
        // Try to create the logs directory if it doesn't exist
        $logsDir = dirname($debugLogFile);
        if (!is_dir($logsDir)) {
            if (!@mkdir($logsDir, 0755, true)) {
                error_log("Failed to create logs directory: $logsDir");
                return array();
            }
            error_log("Created logs directory: $logsDir");
            // Create an empty debug log file
            if (!@file_put_contents($debugLogFile, "")) {
                error_log("Failed to create debug log file: $debugLogFile");
                return array();
            }
            error_log("Created empty debug log file: $debugLogFile");
        } else {
            error_log("Debug log file not found at: $debugLogFile");
            return array();
        }
    }
    
    // Get last N lines from the file
    $entries = array();
    $lines = array_reverse(array_slice(file($debugLogFile), -$limit));
    
    foreach ($lines as $index => $line) {
        if (preg_match('/\[(.*?)\]\s*\[(.*?)\]\s*(.*)/', trim($line), $matches)) {
            $entries[] = array(
                'id' => $index,
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => $matches[3]
            );
        }
    }
    
    return $entries;
}

// Handle debug actions (e.g., clearing log files)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug_action'])) {
    if ($_POST['debug_action'] === 'clear_log') {
        $rootDir = dirname(__FILE__);
        $debugLogFile = $rootDir . '/' . ($config['LOG_PATHS']['DEBUG'] ?? '');
        if ($debugLogFile && file_exists($debugLogFile)) {
            if (@file_put_contents($debugLogFile, '') !== false) {
                $debugMessage = 'Debug log cleared successfully';
            } else {
                $debugError = 'Failed to clear debug log';
            }
        } else {
            $debugError = 'Debug log file not found';
        }
    } elseif ($_POST['debug_action'] === 'download_debug_log') {
        $rootDir = dirname(__FILE__);
        $debugLogFile = $rootDir . '/' . ($config['LOG_PATHS']['DEBUG'] ?? '');
        if ($debugLogFile && file_exists($debugLogFile)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="debug_log_' . date('Y-m-d') . '.txt"');
            header('Pragma: no-cache');
            readfile($debugLogFile);
            exit();
        } else {
            $debugError = 'Debug log file not found';
        }
    } elseif ($_POST['debug_action'] === 'download_log') {
        $rootDir = dirname(__FILE__);
        $logsDir = dirname($rootDir . '/' . ($config['LOG_PATHS']['CHECKIN'] ?? ''));
        $checkInLog = $rootDir . '/' . ($config['LOG_PATHS']['CHECKIN'] ?? '');
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="usage_log_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        
        // Create output buffer to build CSV content
        ob_start();
        
        // Write header row
        echo "Purdue ID,Timestamp,User Group,Visit Count\n";
        
        // Write current log contents if file exists
        if ($checkInLog && file_exists($checkInLog)) {
            readfile($checkInLog);
        }
        
        // Write archived logs
        $archiveDir = $logsDir . '/archives';
        if (is_dir($archiveDir)) {
            $archivedFiles = glob($archiveDir . '/checkin_*.csv');
            sort($archivedFiles); // Sort files to maintain chronological order
            foreach ($archivedFiles as $archiveFile) {
                if (file_exists($archiveFile)) {
                    readfile($archiveFile);
                }
            }
        }
        
        // Get complete content and clean buffer
        $content = ob_get_clean();
        
        // Output the CSV content
        if (!empty($content)) {
            echo $content;
            exit();
        } else {
            $debugError = 'No log data found';
        }
    }
}

/**
 * Retrieves all check-in log entries from the CSV file
 * @param array $config Application configuration
 * @return array Array of check-in entries
 */
function getCheckinLogEntries($config) {
    if (!isset($config['LOG_PATHS']) || !isset($config['LOG_PATHS']['CHECKIN'])) {
        error_log('Configuration error: LOG_PATHS[CHECKIN] not properly set');
        return array();
    }
    
    $rootDir = dirname(__FILE__);
    $checkInLog = $rootDir . '/' . $config['LOG_PATHS']['CHECKIN'];
    $entries = array();
    
    error_log("Attempting to read check-in logs...");
    
    // Create logs directory if it doesn't exist
    $logsDir = dirname($checkInLog);
    if (!is_dir($logsDir)) {
        if (!@mkdir($logsDir, 0755, true)) {
            error_log("Failed to create logs directory: $logsDir");
            return array();
        }
    }

    // Create empty check-in log if it doesn't exist
    if (!file_exists($checkInLog)) {
        if (!@file_put_contents($checkInLog, "")) {
            error_log("Failed to create check-in log file: $checkInLog");
            return array();
        }
    }

    // Read current log file
    if (file_exists($checkInLog)) {
        $lines = file($checkInLog);
        foreach ($lines as $index => $line) {
            $entry = str_getcsv(trim($line));
            if (count($entry) >= 3) {
                $entries[] = array(
                    'id' => count($entries),
                    'purdue_id' => $entry[0],
                    'timestamp' => $entry[1],
                    'user_group' => $entry[2],
                    'visit_count' => isset($entry[3]) ? intval($entry[3]) : null
                );
            }
        }
    }

    // Check for archived logs
    $archiveDir = $logsDir . '/archives';
    if (is_dir($archiveDir)) {
        $archivedFiles = glob($archiveDir . '/checkin_*.csv');
        foreach ($archivedFiles as $archiveFile) {
            if (file_exists($archiveFile)) {
                $lines = file($archiveFile);
                foreach ($lines as $line) {
                    $entry = str_getcsv(trim($line));
                    if (count($entry) >= 3) {
                        $entries[] = array(
                            'id' => count($entries),
                            'purdue_id' => $entry[0],
                            'timestamp' => $entry[1],
                            'user_group' => $entry[2],
                            'visit_count' => isset($entry[3]) ? intval($entry[3]) : null
                        );
                    }
                }
            }
        }
    }
    
    // Sort entries by timestamp in descending order (newest first)
    usort($entries, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return $entries;
}

/**
 * Saves check-in log entries back to the CSV file
 * @param array $entries Array of check-in entries to save
 * @param array $config Application configuration
 * @return bool True if successful, false otherwise
 */
function saveCheckinLogEntries($entries, $config) {
    if (!isset($config['LOG_PATHS']) || !isset($config['LOG_PATHS']['CHECKIN'])) {
        error_log('Configuration error: LOG_PATHS[CHECKIN] not properly set');
        return false;
    }

    $rootDir = dirname(__FILE__);
    $checkInLog = $rootDir . '/' . $config['LOG_PATHS']['CHECKIN'];
    $content = '';
    foreach ($entries as $entry) {
        $content .= implode(',', [
            $entry['purdue_id'],
            $entry['timestamp'],
            $entry['user_group'],
            isset($entry['visit_count']) ? $entry['visit_count'] : ''
        ]) . "\n";
    }
    
    // Ensure the directory exists
    $dir = dirname($checkInLog);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            error_log("Failed to create directory: $dir");
            return false;
        }
    }
    
    // Try to write the file
    if (@file_put_contents($checkInLog, $content) === false) {
        error_log("Failed to write to check-in log: $checkInLog");
        return false;
    }
    
    return true;
}

// ===== Log Entry Operations =====
// Handle POST requests for log management (delete, edit entries)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entries = getCheckinLogEntries($config);
    
    // Handle single entry deletion
    if (isset($_POST['delete']) && isset($_POST['entry_id'])) {
        $id = (int)$_POST['entry_id'];
        unset($entries[$id]);
        if (!saveCheckinLogEntries(array_values($entries), $config)) {
            $debugError = 'Failed to save changes to check-in log';
        }
        header('Location: admin.php#log-section');
        exit();
    }

    // Handle bulk deletion of selected entries
    if (isset($_POST['delete_selected']) && isset($_POST['selected_entries'])) {
        $selectedIds = $_POST['selected_entries'];
        foreach ($selectedIds as $id) {
            unset($entries[(int)$id]);
        }
        if (!saveCheckinLogEntries(array_values($entries), $config)) {
            $debugError = 'Failed to save changes to check-in log';
        }
        header('Location: admin.php#log-section');
        exit();
    }
    
    // Handle entry editing
    if (isset($_POST['edit']) && isset($_POST['entry_id'])) {
        $id = (int)$_POST['entry_id'];
        $entries[$id]['purdue_id'] = $_POST['purdue_id'];
        $entries[$id]['timestamp'] = $_POST['timestamp'];
        $entries[$id]['user_group'] = $_POST['user_group'];
        $entries[$id]['visit_count'] = !empty($_POST['visit_count']) ? intval($_POST['visit_count']) : null;
        if (!saveCheckinLogEntries($entries, $config)) {
            $debugError = 'Failed to save changes to check-in log';
        }
        header('Location: admin.php#log-section');
        exit();
    }
}

// Handle AJAX request for monthly data
if (isset($_GET['action']) && $_GET['action'] === 'get_month_data') {
    if (isset($_GET['month'])) {
        header('Content-Type: application/json');
        $data = getDailyUsageData($config, $_GET['month']);
        // Add debug info
        error_log("Returning data for month: " . $_GET['month'] . ", Data: " . print_r($data, true));
        echo json_encode($data);
        exit();
    }
}

// Get current log entries for display
$logEntries = getCheckinLogEntries($config);

/**
 * Gets daily usage data for calendar view
 * @param array $config Application configuration
 * @param string $month YYYY-MM format
 * @return array Daily usage data
 */
function getDailyUsageData($config, $month = null) {
    if (!isset($config['LOG_PATHS']) || !isset($config['LOG_PATHS']['CHECKIN'])) {
        error_log('Configuration error: LOG_PATHS[CHECKIN] not properly set');
        return array();
    }

    if ($month === null) {
        $month = date('Y-m');
    }

    $rootDir = dirname(__FILE__);
    $logsDir = dirname($rootDir . '/' . $config['LOG_PATHS']['CHECKIN']);
    $dailyData = array();

    // Process active log file
    $checkInLog = $rootDir . '/' . $config['LOG_PATHS']['CHECKIN'];
    if (file_exists($checkInLog)) {
        $dailyData = processLogForDailyData($checkInLog, $month, $dailyData);
    }

    // Process archived log for the requested month
    $archiveDir = $logsDir . '/archives';
    if (is_dir($archiveDir)) {
        $archiveFile = $archiveDir . '/checkin_' . str_replace('-', '_', $month) . '.csv';
        if (file_exists($archiveFile)) {
            $dailyData = processLogForDailyData($archiveFile, $month, $dailyData);
        }
    }

    return $dailyData;
}

function processLogForDailyData($logFile, $month, $dailyData) {
    $logEntries = file($logFile);
    foreach ($logEntries as $entry) {
        $entryParts = explode(',', trim($entry));
        if (count($entryParts) < 3) continue;

        list($purdueId, $timestamp, $userGroup) = $entryParts;
        $date = date('Y-m-d', strtotime($timestamp));
        
        // Only process entries for the specified month
        if (strpos($date, $month) !== 0) continue;

        if (!isset($dailyData[$date])) {
            $dailyData[$date] = array(
                'total' => 0,
                'groups' => array()
            );
        }

        $dailyData[$date]['total']++;
        if (!isset($dailyData[$date]['groups'][$userGroup])) {
            $dailyData[$date]['groups'][$userGroup] = 0;
        }
        $dailyData[$date]['groups'][$userGroup]++;
    }

    return $dailyData;
}

/**
 * Generates usage report data grouped by month and user group
 * @param array $config Application configuration
 * @return array Array of usage statistics
 */
function getUsageReport($config) {
    if (!isset($config['LOG_PATHS']) || !isset($config['LOG_PATHS']['CHECKIN'])) {
        error_log('Configuration error: LOG_PATHS[CHECKIN] not properly set');
        return array();
    }

    $rootDir = dirname(__FILE__);
    $logsDir = dirname($rootDir . '/' . $config['LOG_PATHS']['CHECKIN']);
    $usageReport = array();

    // Process active log file
    $checkInLog = $rootDir . '/' . $config['LOG_PATHS']['CHECKIN'];
    if (file_exists($checkInLog)) {
        $usageReport = processLogForUsageReport($checkInLog, $usageReport);
    }

    // Process archived logs
    $archiveDir = $logsDir . '/archives';
    if (is_dir($archiveDir)) {
        $archivedFiles = glob($archiveDir . '/checkin_*.csv');
        foreach ($archivedFiles as $archiveFile) {
            if (file_exists($archiveFile)) {
                $usageReport = processLogForUsageReport($archiveFile, $usageReport);
            }
        }
    }

    // Sort months in descending order
    krsort($usageReport);

    return $usageReport;
}

function processLogForUsageReport($logFile, $usageReport) {
    $logEntries = file($logFile);
    foreach ($logEntries as $entry) {
        $entryParts = explode(',', trim($entry));
        
        if (count($entryParts) < 3) {
            continue;
        }

        list($purdueId, $timestamp, $userGroup) = $entryParts;
        $month = date('Y-m', strtotime($timestamp));

        if (!isset($usageReport[$month])) {
            $usageReport[$month] = array();
        }

        if (!isset($usageReport[$month][$userGroup])) {
            $usageReport[$month][$userGroup] = 0;
        }

        $usageReport[$month][$userGroup]++;
    }

    return $usageReport;
}

// ===== Usage Report Generation =====
// Get usage statistics
$usageReport = getUsageReport($config);

// Process data for the graph visualization
$months = array_keys($usageReport);
$userGroups = array();
$datasets = array();

// Get unique user groups
foreach ($usageReport as $month => $groups) {
    foreach ($groups as $group => $count) {
        if (!in_array($group, $userGroups)) {
            $userGroups[] = $group;
        }
    }
}

// Generate random colors for graph visualization
$colors = array();
foreach ($userGroups as $group) {
    $colors[$group] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

// Prepare datasets for Chart.js
foreach ($userGroups as $group) {
    $data = array();
    foreach ($months as $month) {
        $data[] = isset($usageReport[$month][$group]) ? $usageReport[$month][$group] : 0;
    }
    $datasets[] = array(
        'label' => $group,
        'data' => $data,
        'backgroundColor' => $colors[$group],
        'borderColor' => $colors[$group],
        'borderWidth' => 1
    );
}

// Convert data to JSON for JavaScript
$graphData = array(
    'labels' => $months,
    'datasets' => $datasets
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Usage Report</title>
    <link rel="stylesheet" href="styles.css">
    <script src="session.js"></script>
    <!-- Include Chart.js for graph visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Styles for log management interface */
        .log-section {
            margin: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .log-viewer, .log-editor {
            display: none;
        }
        .log-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .log-section th, .log-section td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .log-section th {
            background-color: #f5f5f5;
        }
        .log-section tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .log-section .actions {
            display: flex;
            gap: 5px;
        }
        .edit-form {
            display: none;
        }
        .edit-form.active {
            display: table-row;
        }
        /* Styles for bulk delete functionality */
        .delete-selected {
            margin: 10px 0;
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: none;
        }
        .delete-selected:hover {
            background-color: #c82333;
        }
        .checkbox-column {
            width: 30px;
            text-align: center;
        }
        /* Styles for log controls */
        .log-controls {
            margin-bottom: 20px;
        }
        .log-controls button {
            margin-right: 10px;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background-color: #007bff;
            color: white;
        }
        .log-controls button:hover {
            background-color: #0056b3;
        }
        /* Styles for debug section */
        .debug-section {
            margin: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .debug-form {
            margin-bottom: 15px;
        }
        .debug-form button {
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .debug-form button:hover {
            background-color: #c82333;
        }
        /* Styles for debug messages and log viewer */
        .debug-message {
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .debug-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .debug-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .log-viewer {
            display: none;
        }
        .log-editor {
            display: none;
        }
        .debug-log-viewer {
            display: none;
            margin-top: 15px;
            max-height: 500px;
            overflow-y: auto;
        }
        .debug-log-viewer table {
            width: 100%;
            border-collapse: collapse;
        }
        .debug-log-viewer tr.log-level-error {
            background-color: #fff3f3;
        }
        .debug-log-viewer tr.log-level-warning {
            background-color: #fff8e8;
        }
        .debug-log-viewer tr.log-level-info {
            background-color: #f8f9fa;
        }
        .clear-button {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .clear-button:hover {
            background-color: #c82333;
        }
        .download-button {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }

        .download-button:hover {
            background-color: #218838;
        }
        /* Calendar styles */
        .calendar-section {
            margin: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            text-align: center;
        }
        .calendar-header {
            font-weight: bold;
            padding: 10px;
            background: #f5f5f5;
        }
        .calendar-day {
            padding: 10px;
            border: 1px solid #ddd;
            min-height: 80px;
            position: relative;
        }
        .calendar-day.other-month {
            background: #f9f9f9;
            color: #999;
        }
        .calendar-day:hover {
            background: #f0f0f0;
        }
        .day-number {
            position: absolute;
            top: 5px;
            left: 5px;
            font-size: 0.9em;
        }
        .usage-count {
            font-size: 1.2em;
            margin-top: 25px;
        }
        .usage-breakdown {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 200px;
            text-align: left;
        }
        .calendar-day:hover .usage-breakdown {
            display: block;
        }
        .heat-0 { background-color: #ffffff; }
        .heat-1 { background-color: #feedde; }
        .heat-2 { background-color: #fdbe85; }
        .heat-3 { background-color: #fd8d3c; }
        .heat-4 { background-color: #e6550d; }
        .heat-5 { background-color: #a63603; }
        
        /* Error and loading message styles */
        .error-message {
            margin: 10px 0;
            padding: 10px 15px;
            background-color: #fff3f3;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
            color: #d32f2f;
        }
        
        .loading-indicator {
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Admin Panel</h1>
        <div class="header-buttons">
            <a href="index.php" class="button">Back to Homepage</a>
            <a href="?logout=1" class="button">Logout</a>
        </div>
    </div>

    <!-- Calendar View Section -->
    <div class="calendar-section">
        <div class="calendar-controls">
            <button onclick="navigateToPreviousMonth()">&lt; Previous</button>
            <h2 id="calendar-title"></h2>
            <button onclick="navigateToNextMonth()">Next &gt;</button>
        </div>
        <div class="calendar-grid">
            <div class="calendar-header">Sun</div>
            <div class="calendar-header">Mon</div>
            <div class="calendar-header">Tue</div>
            <div class="calendar-header">Wed</div>
            <div class="calendar-header">Thu</div>
            <div class="calendar-header">Fri</div>
            <div class="calendar-header">Sat</div>
            <!-- Calendar days will be inserted here by JavaScript -->
        </div>
    </div>

    <!-- Usage Graph Section -->
    <div class="graph-container">
        <canvas id="usageGraph"></canvas>
    </div>

    <!-- Monthly Usage Report Table -->
    <div class="usage-report">
        <h2>Monthly Usage</h2>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>User Group</th>
                    <th>Check-ins</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usageReport as $month => $groups): ?>
                    <?php foreach ($groups as $userGroup => $count): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($month); ?></td>
                            <td><?php echo htmlspecialchars($userGroup); ?></td>
                            <td><?php echo htmlspecialchars($count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Log Management Section -->
    <div class="log-section" id="log-section">
        <h2>Check-in Log Editor</h2>
        <div class="log-controls">
            <!-- Log Management Section buttons -->
            <button>View Log</button>
            <button>Edit Log</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="debug_action" value="download_log">
                <button type="submit" class="download-button">Download Log</button>
            </form>
        </div>

        <!-- Read-only Log Viewer -->
        <div class="log-viewer" id="log-viewer">
            <table>
                <thead>
                    <tr>
                        <th>Purdue ID</th>
                        <th>Timestamp</th>
                        <th>User Group</th>
                        <th>Visit Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logEntries as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['purdue_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($entry['user_group']); ?></td>
                            <td><?php echo isset($entry['visit_count']) ? htmlspecialchars($entry['visit_count']) : 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Editable Log Editor -->
        <div class="log-editor" id="log-editor">
            <form id="bulk-actions-form" method="POST">
                <button type="submit" name="delete_selected" class="delete-selected" id="delete-selected-btn" onclick="return confirm('Are you sure you want to delete all selected entries?')">Delete Selected</button>
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-column">
                                <input type="checkbox" id="select-all" onclick="toggleAllCheckboxes()">
                            </th>
                            <th>Purdue ID</th>
                            <th>Timestamp</th>
                            <th>User Group</th>
                            <th>Visit Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logEntries as $index => $entry): ?>
                            <tr id="entry-<?php echo $index; ?>">
                                <td class="checkbox-column">
                                    <input type="checkbox" name="selected_entries[]" value="<?php echo $index; ?>" class="entry-checkbox" onclick="updateDeleteSelectedButton()">
                                </td>
                                <td><?php echo htmlspecialchars($entry['purdue_id']); ?></td>
                                <td><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                                <td><?php echo htmlspecialchars($entry['user_group']); ?></td>
                                <td><?php echo isset($entry['visit_count']) ? htmlspecialchars($entry['visit_count']) : 'N/A'; ?></td>
                                <td class="actions">
                                    <button type="button" onclick="showEditForm(<?php echo $index; ?>)" class="button">Edit</button>
                                    <button type="submit" name="delete" value="<?php echo $index; ?>" class="button" onclick="return confirm('Are you sure you want to delete this entry?')">Delete</button>
                                    <input type="hidden" name="entry_id" value="<?php echo $index; ?>">
                                </td>
                            </tr>
                            <tr id="edit-form-<?php echo $index; ?>" class="edit-form">
                                <td colspan="5">
                                    <form method="POST">
                                        <input type="hidden" name="entry_id" value="<?php echo $index; ?>">
                                        <input type="text" name="purdue_id" value="<?php echo htmlspecialchars($entry['purdue_id']); ?>" required>
                                        <input type="datetime-local" name="timestamp" value="<?php echo date('Y-m-d\TH:i', strtotime($entry['timestamp'])); ?>" required>
                                        <input type="text" name="user_group" value="<?php echo htmlspecialchars($entry['user_group']); ?>" required>
                                        <input type="number" name="visit_count" value="<?php echo isset($entry['visit_count']) ? htmlspecialchars($entry['visit_count']) : ''; ?>" placeholder="Visit Count">
                                        <button type="submit" name="edit" class="button">Save</button>
                                        <button type="button" onclick="hideEditForm(<?php echo $index; ?>)" class="button">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <!-- Debug Section -->
    <div class="debug-section">
        <h2>Debug Controls</h2>
        <div class="log-controls">
            <!-- Debug Section button -->
            <button>View Debug Log</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="debug_action" value="download_debug_log">
                <button type="submit" class="download-button">Download Debug Log</button>
            </form>
            <form method="POST" style="display: inline;" class="debug-form">
                <input type="hidden" name="debug_action" value="clear_log">
                <button type="submit" onclick="return confirm('Are you sure you want to clear the debug log?')" class="clear-button">Clear Debug Log</button>
            </form>
        </div>

        <!-- Debug Log Viewer -->
        <div class="debug-log-viewer" id="debug-log-viewer">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Level</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $debugEntries = getDebugLogEntries($config);
                    foreach ($debugEntries as $entry): 
                        $levelClass = strtolower($entry['level']);
                    ?>
                        <tr class="log-level-<?php echo $levelClass; ?>">
                            <td><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($entry['level']); ?></td>
                            <td><?php echo htmlspecialchars($entry['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($debugMessage): ?>
            <div class="debug-message success"><?php echo htmlspecialchars($debugMessage); ?></div>
        <?php endif; ?>
        <?php if ($debugError): ?>
            <div class="debug-message error"><?php echo htmlspecialchars($debugError); ?></div>
        <?php endif; ?>
    </div>

    <!-- JavaScript for UI Functionality -->
    <script>
        // Calendar functionality
        let currentMonth = '<?php echo date('Y-m'); ?>';
        let dailyData = <?php 
            $initialData = array();
            try {
                if (!isset($config['LOG_PATHS']) || !isset($config['LOG_PATHS']['CHECKIN'])) {
                    error_log('ERROR: Missing required LOG_PATHS configuration');
                    $initialData = array();
                } else {
                    $initialData = getDailyUsageData($config);
                    error_log('Initial daily data: ' . print_r($initialData, true));
                }
            } catch (Exception $e) {
                error_log('Error getting initial data: ' . $e->getMessage());
                $initialData = array();
            }
            echo json_encode($initialData);
        ?>;

        // Ensure Chart.js is loaded before initializing
        function ensureChartJsLoaded() {
            return new Promise((resolve, reject) => {
                if (window.Chart) {
                    resolve(window.Chart);
                } else {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                    script.onload = () => resolve(window.Chart);
                    script.onerror = () => reject(new Error('Failed to load Chart.js'));
                    document.head.appendChild(script);
                }
            });
        }

        // Make sure the script runs after DOM is fully loaded
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                // Force enable console logging
                localStorage.setItem('debug', 'true');
                
                // Enhanced debug logging
                console.log('%c=== Debug Information ===', 'background: #ff0; color: #000; font-weight: bold;');
                console.log('Calendar Data:', dailyData);
                console.log('Current Month:', currentMonth);

                // Check if dailyData is empty or invalid
                if (!dailyData || Object.keys(dailyData).length === 0) {
                    console.log('No initial data, fetching current month data...');
                    try {
                        dailyData = await fetchMonthData(currentMonth);
                    } catch (error) {
                        console.error('Failed to fetch initial month data:', error);
                        // Show error message on page
                        const calendarSection = document.querySelector('.calendar-section');
                        if (calendarSection) {
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'error-message';
                            errorDiv.style.color = 'red';
                            errorDiv.style.padding = '10px';
                            errorDiv.textContent = 'Failed to load calendar data. Please try refreshing the page.';
                            calendarSection.insertBefore(errorDiv, calendarSection.firstChild);
                        }
                    }
                }

                // Initialize calendar with error handling
                try {
                    updateCalendar();
                } catch (error) {
                    console.error('Error updating calendar:', error);
                }
                
                // Initialize usage graph using Chart.js with error handling
                try {
                    await ensureChartJsLoaded();
                    initializeGraph();
                } catch (error) {
                    console.error('Error initializing graph:', error);
                }
                
                // Get references to buttons and log their status
                const logSection = document.querySelector('.log-section .log-controls');
                const logButtons = logSection ? logSection.querySelectorAll('button') : [];
                console.log('Log Management Buttons:', {
                    count: logButtons.length,
                    buttons: Array.from(logButtons).map(b => ({
                        text: b.textContent,
                        visible: b.offsetParent !== null
                    }))
                });
                
                const debugButton = document.querySelector('.debug-section .log-controls button:first-child');
                console.log('Debug Button:', {
                    found: !!debugButton,
                    text: debugButton ? debugButton.textContent : 'Not found',
                    visible: debugButton ? debugButton.offsetParent !== null : false
                });

                // Add event listeners for log management buttons
                if (logSection) {
                    const viewLogBtn = logSection.querySelector('button:first-child');
                    const editLogBtn = logSection.querySelector('button:nth-child(2)');
                    
                    if (viewLogBtn) {
                        console.log('Adding click listener to View Log button');
                        viewLogBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('View Log button clicked');
                            showLogViewer();
                        });
                    } else {
                        console.error('View Log button not found');
                    }
                    
                    if (editLogBtn) {
                        console.log('Adding click listener to Edit Log button');
                        editLogBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('Edit Log button clicked');
                            showLogEditor();
                        });
                    } else {
                        console.error('Edit Log button not found');
                    }
                } else {
                    throw new Error('Log controls container not found');
                }

                // Add event listener for debug log viewer button
                if (debugButton) {
                    console.log('Adding click listener to Debug Log button');
                    debugButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Debug Log button clicked');
                        showDebugLogViewer();
                    });
                } else {
                    throw new Error('Debug Log button not found');
                }
            } catch (error) {
                console.error('Error setting up event listeners:', error);
            }

            // Initialize calendar data immediately with error handling
            console.log('Initial daily data:', dailyData);
            if (Object.keys(dailyData).length === 0) {
                fetchMonthData(currentMonth).then(data => {
                    dailyData = data;
                    updateCalendar();
                }).catch(error => {
                    console.error('Error loading initial calendar data:', error);
                });
            }
        });

        // Define navigation functions
        function navigateToPreviousMonth() {
            changeMonth('prev');
        }
        
        function navigateToNextMonth() {
            changeMonth('next');
        }
        
        async function fetchMonthData(monthStr) {
            console.log('Fetching data for month:', monthStr);

            // Add loading indicator
            const calendarGrid = document.querySelector('.calendar-grid');
            if (calendarGrid) {
                calendarGrid.style.opacity = '0.5';
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'loading-indicator';
                loadingDiv.style.position = 'absolute';
                loadingDiv.style.top = '50%';
                loadingDiv.style.left = '50%';
                loadingDiv.style.transform = 'translate(-50%, -50%)';
                loadingDiv.textContent = 'Loading...';
                calendarGrid.parentElement.appendChild(loadingDiv);
            }

            try {
                // First try to ping keepalive to ensure session is active
                try {
                    await fetch('keepalive.php');
                } catch (error) {
                    console.warn('Keepalive check failed:', error);
                    // Continue anyway since this is not critical
                }
                
                const response = await fetch(`admin.php?action=get_month_data&month=${monthStr}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                console.log('Received month data:', data);

                // Validate data structure
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid data format received');
                }

                return data;
            } catch (error) {
                console.error('Error fetching month data:', error);
                // Show error message to user
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = 'red';
                errorDiv.style.padding = '10px';
                errorDiv.textContent = `Failed to load data for ${monthStr}. ${error.message}`;
                const calendarSection = document.querySelector('.calendar-section');
                if (calendarSection) {
                    calendarSection.insertBefore(errorDiv, calendarSection.firstChild);
                }
                return {};
            } finally {
                // Remove loading indicator
                const loadingIndicator = document.querySelector('.loading-indicator');
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
                if (calendarGrid) {
                    calendarGrid.style.opacity = '1';
                }
            }
        }

        function updateCalendar(monthStr = currentMonth) {
            try {
                if (!monthStr || typeof monthStr !== 'string' || !monthStr.match(/^\d{4}-\d{2}$/)) {
                    throw new Error('Invalid month format');
                }

                const [year, month] = monthStr.split('-');
                const date = new Date(year, month - 1);

                if (isNaN(date.getTime())) {
                    throw new Error('Invalid date');
                }
                
                console.log('Calendar data for month:', {
                    month: monthStr,
                    data: dailyData
                });
                
                // Update calendar title
            document.getElementById('calendar-title').textContent = 
                date.toLocaleString('default', { month: 'long', year: 'numeric' });

            // Get first day of month and total days
            const firstDay = new Date(year, month - 1, 1).getDay();
            const totalDays = new Date(year, month, 0).getDate();
            
            // Calculate previous month's days to show
            const prevMonth = new Date(year, month - 2, 1);
            const prevMonthDays = new Date(year, month - 1, 0).getDate();
            
            let calendarHtml = '';
            let currentDate = new Date();
            
            // Previous month's days
            for (let i = 0; i < firstDay; i++) {
                const day = prevMonthDays - firstDay + i + 1;
                calendarHtml += `
                    <div class="calendar-day other-month">
                        <div class="day-number">${day}</div>
                    </div>`;
            }
            
            // Current month's days
            for (let day = 1; day <= totalDays; day++) {
                const dateStr = `${year}-${month.padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                const dayData = dailyData[dateStr] || { total: 0, groups: {} };
                
                // Determine heat level based on usage
                let heatLevel = 0;
                if (dayData.total > 0) heatLevel = 1;
                if (dayData.total >= 5) heatLevel = 2;
                if (dayData.total >= 10) heatLevel = 3;
                if (dayData.total >= 20) heatLevel = 4;
                if (dayData.total >= 30) heatLevel = 5;
                
                // Create breakdown HTML
                let breakdownHtml = '<ul style="margin: 0; padding-left: 20px;">';
                for (const [group, count] of Object.entries(dayData.groups || {})) {
                    breakdownHtml += `<li>${group}: ${count}</li>`;
                }
                breakdownHtml += '</ul>';
                
                calendarHtml += `
                    <div class="calendar-day heat-${heatLevel}">
                        <div class="day-number">${day}</div>
                        <div class="usage-count">${dayData.total}</div>
                        <div class="usage-breakdown">
                            <strong>${dateStr}</strong>
                            <p>Total check-ins: ${dayData.total}</p>
                            ${breakdownHtml}
                        </div>
                    </div>`;
            }
            
            // Next month's days
            const remainingDays = 42 - (firstDay + totalDays); // 42 = 6 rows × 7 days
            for (let day = 1; day <= remainingDays; day++) {
                calendarHtml += `
                    <div class="calendar-day other-month">
                        <div class="day-number">${day}</div>
                    </div>`;
            }
            
            // Get the calendar grid and clear existing days
            const grid = document.querySelector('.calendar-grid');
            const days = document.querySelectorAll('.calendar-day');
            days.forEach(day => day.remove());

            // Create a temporary container and insert the HTML
            const temp = document.createElement('div');
            temp.innerHTML = calendarHtml;

            // Append each calendar day to the grid
            while (temp.firstChild) {
                grid.appendChild(temp.firstChild);
            }
        }

        async function changeMonth(direction) {
            const [year, month] = currentMonth.split('-');
            const currentDate = new Date(year, direction === 'next' ? month : month - 2);
            currentMonth = `${currentDate.getFullYear()}-${(currentDate.getMonth() + 1).toString().padStart(2, '0')}`;
            
            // Disable navigation buttons during data fetch
            const buttons = document.querySelectorAll('.calendar-controls button');
            buttons.forEach(btn => btn.disabled = true);
            
            try {
                dailyData = await fetchMonthData(currentMonth);
                updateCalendar(currentMonth);
            } catch (error) {
                console.error('Error changing month:', error);
            } finally {
                // Re-enable navigation buttons
                buttons.forEach(btn => btn.disabled = false);
            }
        }
        
        function initializeGraph() {
            const ctx = document.getElementById('usageGraph').getContext('2d');
            const data = <?php echo json_encode($graphData); ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Equipment Agreement Check-ins by User Group'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Check-ins'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });
        }

        // Log viewer/editor toggle functions
        function showLogViewer() {
            document.getElementById('log-viewer').style.display = 'block';
            document.getElementById('log-editor').style.display = 'none';
        }

        function showLogEditor() {
            document.getElementById('log-viewer').style.display = 'none';
            document.getElementById('log-editor').style.display = 'block';
        }

        // Hide both log viewer and editor by default
        document.getElementById('log-viewer').style.display = 'none';
        document.getElementById('log-editor').style.display = 'none';

        // Log editor functions
        function showEditForm(index) {
            document.getElementById(`edit-form-${index}`).classList.add('active');
        }

        function hideEditForm(index) {
            document.getElementById(`edit-form-${index}`).classList.remove('active');
        }

        // Bulk selection functions
        function toggleAllCheckboxes() {
            const selectAllCheckbox = document.getElementById('select-all');
            const checkboxes = document.getElementsByClassName('entry-checkbox');
            
            for (let checkbox of checkboxes) {
                checkbox.checked = selectAllCheckbox.checked;
            }
            
            updateDeleteSelectedButton();
        }

        function updateDeleteSelectedButton() {
            const checkboxes = document.getElementsByClassName('entry-checkbox');
            const deleteSelectedBtn = document.getElementById('delete-selected-btn');
            let checkedCount = 0;
            
            for (let checkbox of checkboxes) {
                if (checkbox.checked) {
                    checkedCount++;
                }
            }
            
            deleteSelectedBtn.style.display = checkedCount > 0 ? 'block' : 'none';
        }

        // Debug log viewer toggle function
        function showDebugLogViewer() {
            const viewer = document.getElementById('debug-log-viewer');
            if (viewer.style.display === 'none' || viewer.style.display === '') {
                viewer.style.display = 'block';
            } else {
                viewer.style.display = 'none';
            }
        }
    </script>
</body>
</html>
