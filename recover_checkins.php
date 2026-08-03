<?php

/**
 * One-Time Check-in Recovery Script
 *
 * This script reads the debug.log to find valid "Logged JSON check-in" entries
 * that are missing from the official checkin_log.json due to a past permissions
 * issue. It then safely appends only the missing entries to the check-in log.
 *
 * This script is safe to run multiple times; it will not create duplicates.
 *
 * HOW TO RUN:
 * 1. Place this file in your main application directory.
 * 2. Connect to your server via SSH and navigate to this directory.
 * 3. Execute from the command line: sudo php recover_checkins.php
 *    (sudo is required to write to the log file owned by the web server)
 */

ini_set('display_errors', 1);
ini_set('memory_limit', '1024M'); // Allow memory for indexing
error_reporting(E_ALL);

echo "=================================================\n";
echo "==      Missing Check-in Recovery Script       ==\n";
echo "=================================================\n\n";

// --- CONFIGURATION ---
$config = include(__DIR__ . '/config.php');
$debugLogPath = __DIR__ . '/' . $config['LOG_PATHS']['DEBUG'];
$checkinLogPath = __DIR__ . '/' . $config['LOG_PATHS']['CHECKIN'];
// --- END CONFIGURATION ---

if (!is_readable($debugLogPath) || !is_readable($checkinLogPath)) {
    die("ERROR: Cannot read source files. Ensure debug.log and checkin_log.json exist.\n");
}
if (!is_writable($checkinLogPath)) {
    die("ERROR: The checkin_log.json file is not writable. Please run this script with sudo.\n");
}

$archiveDir = __DIR__ . '/' . dirname($config['LOG_PATHS']['CHECKIN']) . '/archives';
if (!is_dir($archiveDir)) {
    mkdir($archiveDir, 0775, true);
    @chmod($archiveDir, 0775);
}

echo "Source Debug Log: $debugLogPath\n";
echo "Target Check-in Log: $checkinLogPath\n";
echo "Archive Directory: $archiveDir\n\n";

// 1. Index all existing check-ins across main log AND archive files
echo "Indexing existing entries in checkin_log.json and archives...\n";
$existingEntries = [];

$indexFile = function($filePath) use (&$existingEntries) {
    if (!is_readable($filePath)) return;
    $handle = fopen($filePath, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $data = json_decode(trim($line), true);
            if ($data && isset($data['purdueId'], $data['timestamp'])) {
                $key = "{$data['purdueId']}-{$data['timestamp']}";
                $existingEntries[$key] = true;
            }
        }
        fclose($handle);
    }
};

$indexFile($checkinLogPath);
foreach (glob($archiveDir . '/checkin_*.json') as $archFile) {
    $indexFile($archFile);
}
echo "Found " . number_format(count($existingEntries)) . " existing unique entries.\n\n";

// 2. Read the debug log line-by-line to find missing entries
echo "Scanning debug.log for recoverable entries...\n";
$recoveredEntriesByMonth = [];
$potentialEntriesFound = 0;
$currentMonthKey = date('Y_m');

$handle = fopen($debugLogPath, 'r');
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        if (preg_match('/\[INFO\] Logged JSON check-in: (\{.*\})/', $line, $matches)) {
            $potentialEntriesFound++;
            $jsonString = $matches[1];
            $data = json_decode($jsonString, true);

            if ($data && isset($data['purdueId'], $data['timestamp'])) {
                $key = "{$data['purdueId']}-{$data['timestamp']}";
                
                if (!isset($existingEntries[$key])) {
                    try {
                        $mKey = (new DateTime($data['timestamp']))->format('Y_m');
                    } catch (Exception $e) {
                        $mKey = $currentMonthKey;
                    }
                    if (!isset($recoveredEntriesByMonth[$mKey])) {
                        $recoveredEntriesByMonth[$mKey] = [];
                    }
                    $recoveredEntriesByMonth[$mKey][] = json_encode($data) . "\n";
                    $existingEntries[$key] = true;
                    echo " -> Found missing entry for user {$data['purdueId']} at {$data['timestamp']} (Month: $mKey)\n";
                }
            }
        }
    }
    fclose($handle);
}

$totalRecovered = array_sum(array_map('count', $recoveredEntriesByMonth));
echo "\nScan complete. Found $potentialEntriesFound potential entries in debug log. Total missing entries: $totalRecovered\n";

// 3. Write recovered entries to appropriate log/archive files
if ($totalRecovered === 0) {
    echo "\nCONCLUSION: No missing entries were found. Your check-in log is up to date!\n";
} else {
    foreach ($recoveredEntriesByMonth as $mKey => $lines) {
        if ($mKey === $currentMonthKey) {
            $targetFile = $checkinLogPath;
        } else {
            $targetFile = $archiveDir . '/checkin_' . $mKey . '.json';
        }
        
        echo "Writing " . count($lines) . " recovered entries to $targetFile...\n";
        $fp = fopen($targetFile, 'a');
        if ($fp) {
            foreach ($lines as $l) {
                fwrite($fp, $l);
            }
            fclose($fp);
            @chmod($targetFile, 0666);
        } else {
            echo "ERROR: Could not open $targetFile for appending!\n";
        }
    }
    
    echo "\n=================================================\n";
    echo "==              PROCESS COMPLETE               ==\n";
    echo "=================================================\n";
    echo "Successfully recovered and routed $totalRecovered entries.\n\n";
}

?>
