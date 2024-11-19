<?php
// ===== Session Management and Authentication =====
session_start();

// Load application configuration
$config = include('config.php');

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

// ===== Debug Operations =====
// Initialize debug message variables
$debugMessage = '';
$debugError = '';

// Handle debug actions (e.g., clearing log files)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug_action'])) {
    if ($_POST['debug_action'] === 'clear_log') {
        $debugLogFile = $config['LOG_PATHS']['DEBUG'] ?? null;
        if ($debugLogFile && file_exists($debugLogFile)) {
            file_put_contents($debugLogFile, '');
            $debugMessage = 'Debug log cleared successfully';
        } else {
            $debugError = 'Debug log file not found';
        }
    } elseif ($_POST['debug_action'] === 'download_log') {
        $checkInLog = $config['LOG_PATHS']['CHECKIN'] ?? null;
        if ($checkInLog && file_exists($checkInLog)) {
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="usage_log_' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            
            // Create output buffer to build CSV content
            ob_start();
            
            // Write header row
            echo "Purdue ID,Timestamp,User Group\n";
            
            // Write log contents
            readfile($checkInLog);
            
            // Get complete content and clean buffer
            $content = ob_get_clean();
            
            // Output the CSV content
            echo $content;
            exit();
        } else {
            $debugError = 'Check-in log file not found';
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
    
    $checkInLog = $config['LOG_PATHS']['CHECKIN'];
    if (!file_exists($checkInLog)) {
        error_log("Check-in log file not found at: $checkInLog");
        return array();
    }
    
    $entries = array();
    $lines = file($checkInLog);
    foreach ($lines as $index => $line) {
        $entry = str_getcsv(trim($line));
        if (count($entry) >= 3) {
            $entries[] = array(
                'id' => $index,
                'purdue_id' => $entry[0],
                'timestamp' => $entry[1],
                'user_group' => $entry[2]
            );
        }
    }
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

    $checkInLog = $config['LOG_PATHS']['CHECKIN'];
    $content = '';
    foreach ($entries as $entry) {
        $content .= implode(',', [
            $entry['purdue_id'],
            $entry['timestamp'],
            $entry['user_group']
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
        if (!saveCheckinLogEntries($entries, $config)) {
            $debugError = 'Failed to save changes to check-in log';
        }
        header('Location: admin.php#log-section');
        exit();
    }
}

// Get current log entries for display
$logEntries = getCheckinLogEntries($config);

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

    $checkInLog = $config['LOG_PATHS']['CHECKIN'];
    if (!file_exists($checkInLog)) {
        error_log("Check-in log file not found at: $checkInLog");
        return array();
    }

    $logEntries = file($checkInLog);
    $usageReport = array();

    // Process each log entry and group by month and user group
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
        /* Styles for debug messages */
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
            display: none; /* Optional: Hide editor by default if desired */
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
            <button onclick="showLogViewer()">View Log</button>
            <button onclick="showLogEditor()">Edit Log</button>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logEntries as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['purdue_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($entry['user_group']); ?></td>
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
        <form method="POST" class="debug-form">
            <input type="hidden" name="debug_action" value="clear_log">
            <button type="submit" onclick="return confirm('Are you sure you want to clear the debug log?')">Clear Debug Log</button>
        </form>
        <?php if ($debugMessage): ?>
            <div class="debug-message success"><?php echo htmlspecialchars($debugMessage); ?></div>
        <?php endif; ?>
        <?php if ($debugError): ?>
            <div class="debug-message error"><?php echo htmlspecialchars($debugError); ?></div>
        <?php endif; ?>
    </div>

    <!-- JavaScript for UI Functionality -->
    <script>
        // Initialize usage graph using Chart.js
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
    </script>
</body>
</html>
