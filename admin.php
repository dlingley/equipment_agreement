<?php
// ===== Session Management and Authentication =====
$config = include('config.php');

// Set session configuration using loaded config values
if (!empty($config['SESSION_CONFIG']['SAVE_PATH'])) {
    if (!file_exists($config['SESSION_CONFIG']['SAVE_PATH'])) {
        @mkdir($config['SESSION_CONFIG']['SAVE_PATH'], 0700, true);
    }
    ini_set('session.save_path', $config['SESSION_CONFIG']['SAVE_PATH']);
}
ini_set('session.gc_maxlifetime', $config['SESSION_CONFIG']['TIMEOUT']);
session_set_cookie_params([
    'lifetime' => $config['SESSION_CONFIG']['TIMEOUT'],
    'secure' => $config['SESSION_CONFIG']['SECURE'],
    'httponly' => $config['SESSION_CONFIG']['HTTP_ONLY'],
    'samesite' => 'Strict'
]);

session_start();

// Generate CSRF token if not present in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ===== Session Timeout Check =====
// Check BEFORE updating last_activity, otherwise the check can never trigger
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $config['SESSION_CONFIG']['TIMEOUT']) {
    // Session expired, destroy and redirect to login
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}

// Update session activity (after timeout check)
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Verify user is logged in and has admin privileges
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
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
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

error_log("Admin panel initializing at " . date('Y-m-d H:i:s'));

// ===== Self-Maintaining Log Rotation Functions =====

/**
 * Rotates the check-in log automatically. JSON-ONLY.
 */
function rotateCheckinLogIfNeeded($config) {
    if (!isset($config['LOG_PATHS']['CHECKIN'])) return false;
    $checkInLog = dirname(__FILE__) . '/' . $config['LOG_PATHS']['CHECKIN'];
    $logDir = dirname($checkInLog);
    $archiveDir = $logDir . '/archives';

    if (!is_readable($checkInLog) || !is_writable($logDir)) {
        error_log("LOG ROTATION (CHECKIN) FAILED: Check permissions on $checkInLog and $logDir");
        return false;
    }
    if (!is_dir($archiveDir)) {
        if (!mkdir($archiveDir, 0775, true)) {
            error_log("LOG ROTATION (CHECKIN) FAILED: Could not create archive directory at $archiveDir");
            return false;
        }
    }

    $currentMonthKey = date('Y_m');
    $entriesByMonth = [];
    $hasOldEntries = false;

    $handle = fopen($checkInLog, 'r');
    if (!$handle) return false;

    while (($line = fgets($handle)) !== false) {
        $data = json_decode(trim($line), true);
        if (!$data || !isset($data['timestamp'])) continue;
        try { $monthKey = (new DateTime($data['timestamp']))->format('Y_m'); } catch (Exception $e) { continue; }
        if ($monthKey !== $currentMonthKey) $hasOldEntries = true;
        if (!isset($entriesByMonth[$monthKey])) $entriesByMonth[$monthKey] = [];
        $entriesByMonth[$monthKey][] = $line;
    }
    fclose($handle);

    if (!$hasOldEntries) return true;

    error_log("Old checkin entries found. Starting rotation.");
    foreach ($entriesByMonth as $month => $entries) {
        if ($month === $currentMonthKey) continue;
        $archiveFile = $archiveDir . '/checkin_' . $month . '.json';
        file_put_contents($archiveFile, implode('', $entries), FILE_APPEND | LOCK_EX);
    }
    $currentMonthEntries = $entriesByMonth[$currentMonthKey] ?? [];
    file_put_contents($checkInLog, implode('', $currentMonthEntries), LOCK_EX);
    error_log("Checkin log rotation completed.");
    return true;
}

/**
 * Rotates the debug log if it exceeds a maximum size.
 */
function rotateDebugLogIfNeeded($config) {
    if (!isset($config['LOG_PATHS']['DEBUG'])) return false;
    $maxSizeMb = 25;
    $maxSizeBytes = $maxSizeMb * 1024 * 1024;
    $debugLogFile = dirname(__FILE__) . '/' . $config['LOG_PATHS']['DEBUG'];
    $archiveDir = dirname($debugLogFile) . '/archives';
    if (!is_file($debugLogFile) || !is_readable($debugLogFile)) return false;
    if (filesize($debugLogFile) > $maxSizeBytes) {
        error_log("Debug log exceeds ${maxSizeMb}MB. Starting rotation.");
        if (!is_dir($archiveDir)) {
            if (!mkdir($archiveDir, 0775, true)) {
                error_log("LOG ROTATION (DEBUG) FAILED: Could not create archive directory at $archiveDir");
                return false;
            }
        }
        $archiveFile = $archiveDir . '/debug_log_' . date('Y-m-d_His') . '.txt';
        if (copy($debugLogFile, $archiveFile)) {
            file_put_contents($debugLogFile, '');
            error_log("Debug log successfully archived to $archiveFile and truncated.");
        } else {
            error_log("LOG ROTATION (DEBUG) FAILED: Could not copy log to archive.");
            return false;
        }
    }
    return true;
}

/**
 * Deletes old debug log archives to save disk space.
 */
function cleanupOldDebugArchives($config) {
    if (!isset($config['LOG_PATHS']['DEBUG'])) return;
    $archiveDir = dirname(dirname(__FILE__) . '/' . $config['LOG_PATHS']['DEBUG']) . '/archives';
    if (!is_dir($archiveDir)) return;
    $retentionDays = 7;
    $retentionLimit = time() - ($retentionDays * 24 * 60 * 60);
    foreach (glob($archiveDir . '/debug_log_*.txt') as $file) {
        if (filemtime($file) < $retentionLimit) {
            unlink($file);
        }
    }
}

// ** Trigger self-maintaining tasks at most once per hour per session. **
$lastRotation = $_SESSION['last_log_rotation'] ?? 0;
if (time() - $lastRotation > 3600) {
    rotateCheckinLogIfNeeded($config);
    rotateDebugLogIfNeeded($config);
    cleanupOldDebugArchives($config);
    $_SESSION['last_log_rotation'] = time();
}


// ===== AJAX Request Handlers =====
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'get_month_data') {
        if (!isset($_GET['month']) || !preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing month parameter.']);
            exit();
        }
        try {
            $data = getDailyUsageData($config, $_GET['month']);
            echo json_encode($data);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'An internal server error occurred.']);
        }
        exit();
    }

    if ($_GET['action'] === 'get_log_entries') {
        try {
            $entries = getCheckinLogEntries($config);
            echo json_encode(['entries' => $entries]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load log entries.']);
        }
        exit();
    }
}


// ===== Post Request Handlers for Downloads and Log Management =====
$debugMessage = '';
$debugError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }

    // --- Handle Debug and Download Actions ---
    if (isset($_POST['debug_action'])) {
        $rootDir = dirname(__FILE__);
        if ($_POST['debug_action'] === 'clear_log') {
            $debugLogFile = $rootDir . '/' . ($config['LOG_PATHS']['DEBUG'] ?? '');
            if ($debugLogFile && file_exists($debugLogFile)) {
                if (@file_put_contents($debugLogFile, '') !== false) $debugMessage = 'Debug log cleared successfully';
                else $debugError = 'Failed to clear debug log';
            } else $debugError = 'Debug log file not found';
        } elseif ($_POST['debug_action'] === 'download_debug_log') {
            $debugLogFile = $rootDir . '/' . ($config['LOG_PATHS']['DEBUG'] ?? '');
            if ($debugLogFile && file_exists($debugLogFile)) {
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="debug_log_' . date('Y-m-d') . '.txt"');
                readfile($debugLogFile); exit();
            } else $debugError = 'Debug log file not found';
        } elseif ($_POST['debug_action'] === 'download_log') {
            // Get all log entries
            $allLogEntries = getCheckinLogEntries($config);
            
            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="checkin_log_full_' . date('Y-m-d') . '.csv"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            // Create file pointer connected to the output stream
            $output = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($output, [
                'Timestamp',
                'Purdue ID',
                'Full Name',
                'User Group',
                'Department',
                'Classification',
                'Campus Code',
                'User Status',
                'Visit Count',
                'Agreement Status'
            ]);
            
            // Add data rows
            foreach ($allLogEntries as $entry) {
                fputcsv($output, [
                    $entry['timestamp'] ?? '',
                    $entry['purdueId'] ?? '',
                    $entry['fullName'] ?? '',
                    $entry['userGroup'] ?? '',
                    $entry['department'] ?? '',
                    $entry['classification'] ?? '',
                    $entry['campusCode'] ?? '',
                    $entry['userStatus'] ?? '',
                    $entry['visitCount'] ?? '',
                    $entry['agreementStatus'] ?? ''
                ]);
            }
            
            fclose($output);
            exit();
        }

        // Alternative: If you prefer Excel format (.xlsx), you can use this instead:
        /*
        elseif ($_POST['debug_action'] === 'download_log_excel') {
            // Get all log entries
            $allLogEntries = getCheckinLogEntries($config);
            
            // Create Excel content as XML (simple Excel format)
            $excelData = '<?xml version="1.0"?>' . "\n";
            $excelData .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
            $excelData .= '<Worksheet ss:Name="Check-in Log">' . "\n";
            $excelData .= '<Table>' . "\n";
            
            // Add headers
            $excelData .= '<Row>';
            $headers = ['Timestamp', 'Purdue ID', 'Full Name', 'User Group', 'Department', 'Classification', 'Campus Code', 'User Status', 'Visit Count', 'Agreement Status'];
            foreach ($headers as $header) {
                $excelData .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
            }
            $excelData .= '</Row>' . "\n";
            
            // Add data rows
            foreach ($allLogEntries as $entry) {
                $excelData .= '<Row>';
                $rowData = [
                    $entry['timestamp'] ?? '',
                    $entry['purdueId'] ?? '',
                    $entry['fullName'] ?? '',
                    $entry['userGroup'] ?? '',
                    $entry['department'] ?? '',
                    $entry['classification'] ?? '',
                    $entry['campusCode'] ?? '',
                    $entry['userStatus'] ?? '',
                    $entry['visitCount'] ?? '',
                    $entry['agreementStatus'] ?? ''
                ];
                foreach ($rowData as $cellData) {
                    $excelData .= '<Cell><Data ss:Type="String">' . htmlspecialchars($cellData) . '</Data></Cell>';
                }
                $excelData .= '</Row>' . "\n";
            }
            
            $excelData .= '</Table>' . "\n";
            $excelData .= '</Worksheet>' . "\n";
            $excelData .= '</Workbook>';
            
            // Set headers for Excel download
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="checkin_log_full_' . date('Y-m-d') . '.xls"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            echo $excelData;
            exit();
        }
        */
    }
    // --- Handle Check-in Log Edits and Deletes ---
    // Note: This is inefficient for very large logs as it rewrites the entire file.
    // For now, it is functional.
    function saveAllLogEntries($entries, $config) {
        // This helper function will sort and write all entries back to the correct files.
        $entriesByMonth = [];
        foreach($entries as $entry) {
            $monthKey = date('Y_m', strtotime($entry['timestamp']));
            if (!isset($entriesByMonth[$monthKey])) $entriesByMonth[$monthKey] = [];
            $entriesByMonth[$monthKey][] = json_encode($entry) . "\n";
        }

        $checkInLogFile = dirname(__FILE__) . '/' . $config['LOG_PATHS']['CHECKIN'];
        $archiveDir = dirname($checkInLogFile) . '/archives';
        $currentMonthKey = date('Y_m');

        // Write current month's entries to the main log
        file_put_contents($checkInLogFile, implode('', $entriesByMonth[$currentMonthKey] ?? []));
        unset($entriesByMonth[$currentMonthKey]);

        // Write all other months to their respective archives
        foreach($entriesByMonth as $month => $lines) {
            $archiveFile = $archiveDir . '/checkin_' . $month . '.json';
            file_put_contents($archiveFile, implode('', $lines));
        }
        return true;
    }

    $allEntries = getCheckinLogEntries($config); // Load all entries

    if (isset($_POST['delete_entry']) && isset($_POST['entry_id'])) {
        $idToDelete = $_POST['entry_id'];
        $allEntries = array_filter($allEntries, fn($entry) => $entry['id'] != $idToDelete);
        saveAllLogEntries($allEntries, $config);
        header("Location: admin.php?view=editor"); exit();
    }
    
    if (isset($_POST['save_entry']) && isset($_POST['entry_id'])) {
        $idToEdit = $_POST['entry_id'];
        foreach($allEntries as &$entry) {
            if ($entry['id'] == $idToEdit) {
                // Update fields from POST data (sanitized)
                $entry['fullName'] = trim(strip_tags($_POST['fullName'] ?? ''));
                $entry['userGroup'] = trim(strip_tags($_POST['userGroup'] ?? ''));
                $entry['department'] = trim(strip_tags($_POST['department'] ?? ''));
                $entry['classification'] = trim(strip_tags($_POST['classification'] ?? ''));
                $entry['visitCount'] = max(0, intval($_POST['visitCount'] ?? 0));
                // Add more fields here if you make them editable
                break;
            }
        }
        saveAllLogEntries($allEntries, $config);
        header("Location: admin.php?view=editor"); exit();
    }
}


// ===== Log Parsing and Data Retrieval Functions (JSON-ONLY) =====

/**
 * Parses a single JSON line from a log file.
 */
function parseLogLine($line) {
    $line = trim($line);
    if (empty($line)) return null;
    $data = json_decode($line, true);
    if (is_array($data) && isset($data['purdueId'])) {
        return ['purdueId' => $data['purdueId'] ?? 'N/A', 'timestamp' => $data['timestamp'] ?? 'N/A', 'fullName' => $data['fullName'] ?? 'N/A', 'userGroup' => $data['userGroup'] ?? 'N/A', 'department' => $data['department'] ?? 'N/A', 'classification' => $data['classification'] ?? 'N/A', 'campusCode' => $data['campusCode'] ?? 'N/A', 'userStatus' => $data['userStatus'] ?? 'N/A', 'visitCount' => $data['visitCount'] ?? 'N/A', 'agreementStatus' => $data['agreementStatus'] ?? 'N/A'];
    }
    return null;
}

function readLogMemorySafe($filePath, &$entries) {
    if (!is_readable($filePath)) return;
    $handle = fopen($filePath, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $parsedData = parseLogLine($line);
            if ($parsedData !== null) {
                $parsedData['id'] = count($entries);
                $entries[] = $parsedData;
            }
        }
        fclose($handle);
    }
}

function getCheckinLogEntries($config) {
    if (!isset($config['LOG_PATHS']['CHECKIN'])) return [];
    $entries = [];
    $checkInLog = dirname(__FILE__) . '/' . $config['LOG_PATHS']['CHECKIN'];
    $archiveDir = dirname($checkInLog) . '/archives';
    readLogMemorySafe($checkInLog, $entries);
    foreach (glob($archiveDir . '/checkin_*.json') as $archiveFile) {
        readLogMemorySafe($archiveFile, $entries);
    }
    usort($entries, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
    return $entries;
}
function getDebugLogEntries($config, $limit = 100) {
    if (!isset($config['LOG_PATHS']['DEBUG'])) return [];
    $debugLogFile = dirname(__FILE__) . '/' . $config['LOG_PATHS']['DEBUG'];
    if (!is_readable($debugLogFile)) return [];

    // Pure PHP tail — no shell_exec needed
    $file = new SplFileObject($debugLogFile, 'r');
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();
    $startLine = max(0, $totalLines - intval($limit));
    $file->seek($startLine);
    $lines = [];
    while (!$file->eof()) {
        $line = trim($file->current());
        if ($line !== '') $lines[] = $line;
        $file->next();
    }

    $entries = [];
    foreach (array_reverse($lines) as $index => $line) {
        if (preg_match('/\[(.*?)\]\s*\[(.*?)\]\s*(.*)/', $line, $matches)) {
            $entries[] = ['id' => $index, 'timestamp' => $matches[1], 'level' => $matches[2], 'message' => $matches[3]];
        }
    }
    return $entries;
}
function getDailyUsageData($config, $month) {
    if (!isset($config['LOG_PATHS']['CHECKIN'])) return [];
    $dailyData = [];
    $checkInLog = dirname(__FILE__) . '/' . $config['LOG_PATHS']['CHECKIN'];
    $dailyData = processLogForDailyData($checkInLog, $month, $dailyData);
    $archiveFileJson = dirname($checkInLog) . '/archives/checkin_' . str_replace('-', '_', $month) . '.json';
    $dailyData = processLogForDailyData($archiveFileJson, $month, $dailyData);
    return $dailyData;
}
function processLogForDailyData($logFile, $month, $dailyData) {
    if (!is_readable($logFile)) return $dailyData;
    $handle = fopen($logFile, 'r');
    if (!$handle) return $dailyData;
    while (($line = fgets($handle)) !== false) {
        $data = parseLogLine($line);
        if ($data === null) continue;
        try {
            $dt = new DateTime($data['timestamp']);
            if ($dt->format('Y-m') !== $month) continue;
            $date = $dt->format('Y-m-d');
        } catch (Exception $e) { continue; }
        if (!isset($dailyData[$date])) $dailyData[$date] = ['total' => 0, 'groups' => []];
        $dailyData[$date]['total']++;
        $dailyData[$date]['groups'][$data['userGroup']] = ($dailyData[$date]['groups'][$data['userGroup']] ?? 0) + 1;
    }
    fclose($handle);
    return $dailyData;
}
function getUsageReport($config) {
    if (!isset($config['LOG_PATHS']['CHECKIN'])) return [];
    $usageReport = [];
    $checkInLog = dirname(__FILE__) . '/' . $config['LOG_PATHS']['CHECKIN'];
    $usageReport = processLogForUsageReport($checkInLog, $usageReport);
    $archiveDir = dirname($checkInLog) . '/archives';
    foreach (glob($archiveDir . '/checkin_*.json') as $archiveFile) {
        $usageReport = processLogForUsageReport($archiveFile, $usageReport);
    }
    krsort($usageReport);
    return $usageReport;
}
function processLogForUsageReport($logFile, $usageReport) {
    if (!is_readable($logFile)) return $usageReport;
    $handle = fopen($logFile, 'r');
    if (!$handle) return $usageReport;
    while (($line = fgets($handle)) !== false) {
        $data = parseLogLine($line);
        if ($data === null) continue;
        try { $month = (new DateTime($data['timestamp']))->format('Y-m'); } catch (Exception $e) { continue; }
        if (!isset($usageReport[$month])) $usageReport[$month] = [];
        $usageReport[$month][$data['userGroup']] = ($usageReport[$month][$data['userGroup']] ?? 0) + 1;
    }
    fclose($handle);
    return $usageReport;
}

// ===== Prepare Data for Page Display =====
// Note: Log entries are now loaded via AJAX (get_log_entries) for performance
$usageReport = getUsageReport($config);
$months = array_keys($usageReport);
$userGroups = [];
foreach ($usageReport as $groups) {
    foreach (array_keys($groups) as $group) { if (!in_array($group, $userGroups)) $userGroups[] = $group; }
}
$datasets = [];
$palette = ['#CFB991', '#1D1D1B', '#8E6F3E', '#555960', '#6F727B', '#9D9795', '#DDB945', '#C4BFC0'];
foreach ($userGroups as $index => $group) {
    $data = [];
    foreach ($months as $month) { $data[] = $usageReport[$month][$group] ?? 0; }
    $datasets[] = ['label' => $group, 'data' => $data, 'backgroundColor' => $palette[$index % count($palette)], 'borderWidth' => 1];
}
$graphData = ['labels' => $months, 'datasets' => $datasets];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Usage Report</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <script src="session.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Admin styles now in styles.css -->
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Admin Panel</h1>
        <div class="header-buttons">
            <a href="index.php" class="button">Back to Homepage</a>
            <a href="?logout=1" class="button">Logout</a>
        </div>
    </div>

    <div class="calendar-section">
        <div class="calendar-controls">
            <button onclick="navigateToPreviousMonth()">&lt; Previous</button>
            <h2 id="calendar-title"></h2>
            <button onclick="navigateToNextMonth()">&gt; Next</button>
        </div>
        <div class="calendar-grid"></div>
    </div>
    
    <div class="graph-container"><canvas id="usageGraph"></canvas></div>

    <div class="usage-report">
        <h2>Monthly Usage</h2>
        <table>
            <thead><tr><th>Month</th><th>User Group</th><th>Check-ins</th></tr></thead>
            <tbody>
                <?php foreach ($usageReport as $month => $groups): ?>
                    <?php foreach ($groups as $userGroup => $count): ?>
                        <tr><td><?php echo htmlspecialchars($month); ?></td><td><?php echo htmlspecialchars($userGroup); ?></td><td><?php echo htmlspecialchars($count); ?></td></tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="log-section" id="log-section">
        <h2>Check-in Log</h2>
        <div class="log-controls">
            <button id="view-log-btn">View Log</button>
            <button id="edit-log-btn">Edit Log</button>
            <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><input type="hidden" name="debug_action" value="download_log"><button type="submit" class="download-button">Download Full Log</button></form>
        </div>

        <div class="log-viewer" id="log-viewer">
            <div class="log-search-bar">
                <input type="text" id="log-viewer-search" placeholder="Search by name, group, department...">
                <span class="result-count" id="log-viewer-count"></span>
            </div>
            <table>
                <thead>
                    <tr><th>Timestamp</th><th>Full Name</th><th>User Group</th><th>Department</th><th>Classification</th><th>Visit #</th><th>Agreement</th></tr>
                </thead>
                <tbody id="log-viewer-body">
                    <tr><td colspan="7" style="text-align:center; padding:20px;">Click "View Log" to load entries...</td></tr>
                </tbody>
            </table>
            <div class="log-pagination" id="log-viewer-pagination"></div>
        </div>

        <div class="log-editor" id="log-editor">
            <div class="log-search-bar">
                <input type="text" id="log-editor-search" placeholder="Search by name, group, department...">
                <span class="result-count" id="log-editor-count"></span>
            </div>
            <table>
                <thead>
                    <tr><th>Timestamp</th><th>Full Name</th><th>User Group</th><th>Department</th><th>Classification</th><th>Visit #</th><th>Actions</th></tr>
                </thead>
                <tbody id="log-editor-body">
                    <tr><td colspan="7" style="text-align:center; padding:20px;">Click "Edit Log" to load entries...</td></tr>
                </tbody>
            </table>
            <div class="log-pagination" id="log-editor-pagination"></div>
        </div>
    </div>

    <div class="debug-section">
        <h2>Debug Controls</h2>
        <div class="log-controls">
            <button id="view-debug-log-btn">View Debug Log</button>
            <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><input type="hidden" name="debug_action" value="download_debug_log"><button type="submit" class="download-button">Download Debug Log</button></form>
            <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><input type="hidden" name="debug_action" value="clear_log"><button type="submit" class="clear-button" onclick="return confirm('Are you sure?')">Clear Debug Log</button></form>
        </div>
        <div class="debug-log-viewer" id="debug-log-viewer">
            <table>
                <thead><tr><th>Timestamp</th><th>Level</th><th>Message</th></tr></thead>
                <tbody>
                    <?php foreach (getDebugLogEntries($config) as $entry): ?>
                        <tr class="log-level-<?php echo strtolower($entry['level']); ?>">
                            <td><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($entry['level']); ?></td>
                            <td><?php echo htmlspecialchars($entry['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
        const ITEMS_PER_PAGE = 50;
        let currentMonth = '<?php echo date('Y-m'); ?>';
        let dailyData = {};
        let logEntriesCache = null;
        let viewerState = { page: 1, search: '', filtered: [] };
        let editorState = { page: 1, search: '', filtered: [] };

        // ===== Log Data Loading (AJAX - Fix #5) =====
        async function loadLogEntries() {
            if (logEntriesCache) return logEntriesCache;
            const response = await fetch('admin.php?action=get_log_entries');
            if (!response.ok) throw new Error('Failed to load log entries');
            const data = await response.json();
            logEntriesCache = data.entries || [];
            return logEntriesCache;
        }

        // ===== Search & Pagination (Fix #11) =====
        function filterEntries(entries, search) {
            if (!search) return entries;
            const q = search.toLowerCase();
            return entries.filter(e =>
                (e.fullName || '').toLowerCase().includes(q) ||
                (e.userGroup || '').toLowerCase().includes(q) ||
                (e.department || '').toLowerCase().includes(q) ||
                (e.classification || '').toLowerCase().includes(q) ||
                (e.timestamp || '').toLowerCase().includes(q) ||
                (e.purdueId || '').toLowerCase().includes(q)
            );
        }

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str ?? '';
            return d.innerHTML;
        }

        function renderPagination(containerId, state, renderFn) {
            const container = document.getElementById(containerId);
            const totalPages = Math.max(1, Math.ceil(state.filtered.length / ITEMS_PER_PAGE));
            if (totalPages <= 1) { container.innerHTML = ''; return; }
            container.innerHTML = `
                <button onclick="changePage('${containerId}', -1)" ${state.page <= 1 ? 'disabled' : ''}>&laquo; Prev</button>
                <span class="page-info">Page ${state.page} of ${totalPages}</span>
                <button onclick="changePage('${containerId}', 1)" ${state.page >= totalPages ? 'disabled' : ''}>Next &raquo;</button>
            `;
        }

        function changePage(paginationId, direction) {
            const isViewer = paginationId === 'log-viewer-pagination';
            const state = isViewer ? viewerState : editorState;
            const totalPages = Math.ceil(state.filtered.length / ITEMS_PER_PAGE);
            state.page = Math.max(1, Math.min(totalPages, state.page + direction));
            isViewer ? renderViewerTable() : renderEditorTable();
        }

        function renderViewerTable() {
            const tbody = document.getElementById('log-viewer-body');
            const start = (viewerState.page - 1) * ITEMS_PER_PAGE;
            const pageEntries = viewerState.filtered.slice(start, start + ITEMS_PER_PAGE);
            document.getElementById('log-viewer-count').textContent = `${viewerState.filtered.length} entries`;

            if (pageEntries.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">No entries found.</td></tr>';
            } else {
                tbody.innerHTML = pageEntries.map(e => `<tr>
                    <td>${esc(e.timestamp)}</td><td>${esc(e.fullName)}</td><td>${esc(e.userGroup)}</td>
                    <td>${esc(e.department)}</td><td>${esc(e.classification)}</td><td>${esc(e.visitCount)}</td>
                    <td>${esc(e.agreementStatus)}</td>
                </tr>`).join('');
            }
            renderPagination('log-viewer-pagination', viewerState, renderViewerTable);
        }

        function renderEditorTable() {
            const tbody = document.getElementById('log-editor-body');
            const start = (editorState.page - 1) * ITEMS_PER_PAGE;
            const pageEntries = editorState.filtered.slice(start, start + ITEMS_PER_PAGE);
            document.getElementById('log-editor-count').textContent = `${editorState.filtered.length} entries`;

            if (pageEntries.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">No entries found.</td></tr>';
            } else {
                tbody.innerHTML = pageEntries.map(e => `
                    <tr id="view-row-${e.id}">
                        <td>${esc(e.timestamp)}</td><td>${esc(e.fullName)}</td><td>${esc(e.userGroup)}</td>
                        <td>${esc(e.department)}</td><td>${esc(e.classification)}</td><td>${esc(e.visitCount)}</td>
                        <td class="actions">
                            <button type="button" class="button" onclick="showEditForm(${e.id})">Edit</button>
                            <form method="POST" style="display:inline; margin:0; padding:0;">
                                <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                                <input type="hidden" name="entry_id" value="${e.id}">
                                <button type="submit" name="delete_entry" value="delete" class="button" onclick="return confirm('Are you sure you want to delete this entry?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <tr id="edit-row-${e.id}" class="edit-form">
                        <td colspan="7">
                            <form method="POST" style="display:inline; margin:0; padding:0;">
                                <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                                <input type="hidden" name="entry_id" value="${e.id}">
                                <input type="text" name="timestamp" value="${esc(e.timestamp)}" readonly>
                                <input type="text" name="fullName" value="${esc(e.fullName)}">
                                <input type="text" name="userGroup" value="${esc(e.userGroup)}">
                                <input type="text" name="department" value="${esc(e.department)}">
                                <input type="text" name="classification" value="${esc(e.classification)}">
                                <input type="number" name="visitCount" value="${esc(e.visitCount)}">
                                <button type="submit" name="save_entry" value="save" class="button">Save</button>
                                <button type="button" class="button" onclick="hideEditForm(${e.id})">Cancel</button>
                            </form>
                        </td>
                    </tr>
                `).join('');
            }
            renderPagination('log-editor-pagination', editorState, renderEditorTable);
        }

        // ===== Button Listeners =====
        function setupButtonListeners() {
            const viewLogBtn = document.getElementById('view-log-btn');
            const editLogBtn = document.getElementById('edit-log-btn');
            const viewDebugLogBtn = document.getElementById('view-debug-log-btn');

            if (viewLogBtn) {
                viewLogBtn.addEventListener('click', async () => {
                    document.getElementById('log-viewer').style.display = 'block';
                    document.getElementById('log-editor').style.display = 'none';
                    try {
                        const entries = await loadLogEntries();
                        viewerState.filtered = filterEntries(entries, viewerState.search);
                        viewerState.page = 1;
                        renderViewerTable();
                    } catch (e) { console.error(e); }
                });
            }
            if (editLogBtn) {
                editLogBtn.addEventListener('click', async () => {
                    document.getElementById('log-viewer').style.display = 'none';
                    document.getElementById('log-editor').style.display = 'block';
                    try {
                        const entries = await loadLogEntries();
                        editorState.filtered = filterEntries(entries, editorState.search);
                        editorState.page = 1;
                        renderEditorTable();
                    } catch (e) { console.error(e); }
                });
            }
            if (viewDebugLogBtn) {
                viewDebugLogBtn.addEventListener('click', () => {
                    const viewer = document.getElementById('debug-log-viewer');
                    viewer.style.display = viewer.style.display === 'block' ? 'none' : 'block';
                });
            }

            // Search inputs with debounce
            let viewerTimer, editorTimer;
            document.getElementById('log-viewer-search')?.addEventListener('input', (e) => {
                clearTimeout(viewerTimer);
                viewerTimer = setTimeout(async () => {
                    viewerState.search = e.target.value;
                    const entries = await loadLogEntries();
                    viewerState.filtered = filterEntries(entries, viewerState.search);
                    viewerState.page = 1;
                    renderViewerTable();
                }, 300);
            });
            document.getElementById('log-editor-search')?.addEventListener('input', (e) => {
                clearTimeout(editorTimer);
                editorTimer = setTimeout(async () => {
                    editorState.search = e.target.value;
                    const entries = await loadLogEntries();
                    editorState.filtered = filterEntries(entries, editorState.search);
                    editorState.page = 1;
                    renderEditorTable();
                }, 300);
            });
        }

        // ===== Calendar =====
        async function loadCalendarAndGraph() {
            try {
                dailyData = await fetchMonthData(currentMonth);
                updateCalendar();
                await ensureChartJsLoaded();
                initializeGraph();
            } catch (error) {
                console.error('Initial data load failed:', error);
                const calendarGrid = document.querySelector('.calendar-grid');
                if (calendarGrid) {
                    calendarGrid.parentElement.insertAdjacentHTML('afterbegin', `<div class="error-message">Failed to load calendar: ${error.message}</div>`);
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setupButtonListeners();
            loadCalendarAndGraph();
            document.getElementById('log-viewer').style.display = 'none';
            document.getElementById('log-editor').style.display = 'none';
            document.getElementById('debug-log-viewer').style.display = 'none';
        });

        function navigateToPreviousMonth() { changeMonth('prev'); }
        function navigateToNextMonth() { changeMonth('next'); }

        async function fetchMonthData(monthStr) {
            const calendarSection = document.querySelector('.calendar-section');
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'loading-indicator';
            loadingDiv.textContent = 'Loading...';
            calendarSection.style.position = 'relative';
            calendarSection.appendChild(loadingDiv);
            try {
                const response = await fetch(`admin.php?action=get_month_data&month=${monthStr}`);
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ error: 'Invalid server response' }));
                    throw new Error(errorData.error || `HTTP error ${response.status}`);
                }
                return await response.json();
            } finally {
                loadingDiv.remove();
            }
        }

        function updateCalendar(monthStr = currentMonth) {
            const [year, month] = monthStr.split('-');
            const date = new Date(year, month - 1);
            document.querySelector('.calendar-section .error-message')?.remove();
            document.getElementById('calendar-title').textContent = date.toLocaleString('default', { month: 'long', year: 'numeric' });
            const firstDay = new Date(year, month - 1, 1).getDay();
            const totalDays = new Date(year, month, 0).getDate();
            const prevMonthDays = new Date(year, month - 1, 0).getDate();
            let calendarHtml = '';
            for (let i = 0; i < firstDay; i++) { calendarHtml += `<div class="calendar-day other-month"><div class="day-number">${prevMonthDays - firstDay + i + 1}</div></div>`; }
            for (let day = 1; day <= totalDays; day++) {
                const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayData = dailyData[dateStr] || { total: 0, groups: {} };
                let heatLevel = 0;
                if (dayData.total > 0) heatLevel = 1; if (dayData.total >= 5) heatLevel = 2; if (dayData.total >= 10) heatLevel = 3; if (dayData.total >= 20) heatLevel = 4; if (dayData.total >= 30) heatLevel = 5;
                const now = new Date(); const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
                const todayClass = dateStr === todayStr ? ' calendar-today' : '';
                let breakdownHtml = '<ul style="margin: 0; padding-left: 20px;">';
                for (const [group, count] of Object.entries(dayData.groups)) { breakdownHtml += `<li>${group}: ${count}</li>`; }
                breakdownHtml += '</ul>';
                calendarHtml += `<div class="calendar-day heat-${heatLevel}${todayClass}"><div class="day-number">${day}</div><div class="usage-count">${dayData.total}</div><div class="usage-breakdown"><strong>${dateStr}</strong><p>Total: ${dayData.total}</p>${breakdownHtml}</div></div>`;
            }
            const grid = document.querySelector('.calendar-grid');
            const totalCells = firstDay + totalDays;
            const remainingDays = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (let day = 1; day <= remainingDays; day++) { calendarHtml += `<div class="calendar-day other-month"><div class="day-number">${day}</div></div>`; }
            grid.innerHTML = `<div class="calendar-header">Sun</div><div class="calendar-header">Mon</div><div class="calendar-header">Tue</div><div class="calendar-header">Wed</div><div class="calendar-header">Thu</div><div class="calendar-header">Fri</div><div class="calendar-header">Sat</div>`;
            grid.insertAdjacentHTML('beforeend', calendarHtml);

            // Fix #9: Tooltip edge-awareness
            grid.querySelectorAll('.calendar-day').forEach(cell => {
                const tooltip = cell.querySelector('.usage-breakdown');
                if (!tooltip) return;
                cell.addEventListener('mouseenter', () => {
                    tooltip.style.left = '';
                    tooltip.style.right = '';
                    tooltip.style.top = '';
                    tooltip.style.bottom = '';
                    const cellRect = cell.getBoundingClientRect();
                    const gridRect = grid.getBoundingClientRect();
                    // Flip horizontally if too close to right edge
                    if (cellRect.right + 160 > window.innerWidth) {
                        tooltip.style.right = '0';
                    } else {
                        tooltip.style.left = '0';
                    }
                    // Flip vertically if too close to bottom
                    if (cellRect.bottom + 100 > window.innerHeight) {
                        tooltip.style.bottom = '100%';
                    } else {
                        tooltip.style.top = '100%';
                    }
                });
            });
        }

        async function changeMonth(direction) {
            const [year, month] = currentMonth.split('-');
            const newDate = new Date(year, (direction === 'next' ? parseInt(month) : parseInt(month) - 2));
            currentMonth = `${newDate.getFullYear()}-${String(newDate.getMonth() + 1).padStart(2, '0')}`;
            const buttons = document.querySelectorAll('.calendar-controls button');
            buttons.forEach(btn => btn.disabled = true);
            try {
                dailyData = await fetchMonthData(currentMonth);
                updateCalendar();
            } catch (error) {
                console.error(`Error changing month:`, error);
                document.querySelector('.calendar-grid').parentElement.insertAdjacentHTML('afterbegin', `<div class="error-message">Failed to load data for ${currentMonth}: ${error.message}</div>`);
            } finally {
                buttons.forEach(btn => btn.disabled = false);
            }
        }

        function ensureChartJsLoaded() {
            return new Promise((resolve, reject) => {
                if (window.Chart) return resolve(window.Chart);
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.onload = () => resolve(window.Chart);
                script.onerror = () => reject(new Error('Failed to load Chart.js'));
                document.head.appendChild(script);
            });
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
                        title: { display: true, text: 'Check-ins by User Group' },
                        legend: { position: 'bottom' }
                    },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        function showEditForm(id) {
            document.getElementById(`view-row-${id}`).style.display = 'none';
            document.getElementById(`edit-row-${id}`).style.display = 'table-row';
        }

        function hideEditForm(id) {
            document.getElementById(`view-row-${id}`).style.display = 'table-row';
            document.getElementById(`edit-row-${id}`).style.display = 'none';
        }
    </script>
</body>
</html>
