<?php
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

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
        $entryParts = explode(',', trim($entry));
        
        // Ensure the log entry has the expected number of parts
        if (count($entryParts) < 3) {
            continue; // Skip this entry if it does not have enough parts
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

// Get the usage report
$usageReport = getUsageReport();

// Process data for the graph
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

// Generate random colors for each user group
$colors = array();
foreach ($userGroups as $group) {
    $colors[$group] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

// Prepare datasets for each user group
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Usage Report</h1>
        <div class="header-buttons">
            <a href="index.php" class="button">Back to Homepage</a>
            <a href="?logout=1" class="button">Logout</a>
        </div>
    </div>

    <div class="graph-container">
        <canvas id="usageGraph"></canvas>
    </div>

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

    <script>
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
    </script>
</body>
</html>
