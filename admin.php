<?php
session_start();
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Function to log debug information
function debugLog($message, $level = 'INFO') {
    $logFile = 'logs/equipment_agreement_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Function to get usage report per month
function getUsageReport() {
    $checkInLog = 'logs/checkin_log.csv';
    if (!file_exists($checkInLog)) {
        return array();
    }

    $logEntries = file($checkInLog);
    $usageReport = array();

    foreach ($logEntries as $entry) {
        list($purdueId, $timestamp) = explode(',', trim($entry));
        $month = date('Y-m', strtotime($timestamp));

        if (!isset($usageReport[$month])) {
            $usageReport[$month] = 0;
        }

        $usageReport[$month]++;
    }

    return $usageReport;
}

// Get the usage report
$usageReport = getUsageReport();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Usage Report</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Usage Report</h1>
    </div>
    <div class="usage-report">
        <h2>Monthly Usage</h2>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Check-ins</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usageReport as $month => $count): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($month); ?></td>
                        <td><?php echo htmlspecialchars($count); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>