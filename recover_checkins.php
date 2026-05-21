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

echo "Source Debug Log: $debugLogPath\n";
echo "Target Check-in Log: $checkinLogPath\n\n";

// 1. Index all existing check-ins for very fast lookups
echo "Indexing existing entries in checkin_log.json...\n";
$existingEntries = [];
$handle = fopen($checkinLogPath, 'r');
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
echo "Found " . number_format(count($existingEntries)) . " existing unique entries.\n\n";


// 2. Read the debug log line-by-line to find missing entries
echo "Scanning debug.log for recoverable entries...\n";
$recoveredEntries = [];
$potentialEntriesFound = 0;
$handle = fopen($debugLogPath, 'r');
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // Use a regular expression to find and capture the JSON object
        if (preg_match('/\[INFO\] Logged JSON check-in: (\{.*\})/', $line, $matches)) {
            $potentialEntriesFound++;
            $jsonString = $matches[1];
            $data = json_decode($jsonString, true);

            if ($data && isset($data['purdueId'], $data['timestamp'])) {
                // Create a unique fingerprint for this event
                $key = "{$data['purdueId']}-{$data['timestamp']}";
                
                // If this fingerprint does NOT exist in our index, it's a missing entry
                if (!isset($existingEntries[$key])) {
                    $recoveredEntries[] = $jsonString;
                    $existingEntries[$key] = true; // Add to index to prevent duplicates from within the debug log
                    echo " -> Found missing entry for user {$data['purdueId']} at {$data['timestamp']}\n";
                }
            }
        }
    }
    fclose($handle);
}

echo "\nScan complete. Found $potentialEntriesFound potential entries in the debug log.\n";

// 3. Append the missing entries to the check-in log
if (empty($recoveredEntries)) {
    echo "\nCONCLUSION: No missing entries were found. Your check-in log is already up to date!\n";
} else {
    echo "Found " . count($recoveredEntries) . " missing entries. Appending them to checkin_log.json...\n";
    
    // Open the log file in append mode
    $handleOut = fopen($checkinLogPath, 'a');
    if ($handleOut) {
        foreach ($recoveredEntries as $jsonLine) {
            fwrite($handleOut, $jsonLine . "\n");
        }
        fclose($handleOut);
        
        echo "\n=================================================\n";
        echo "==              PROCESS COMPLETE               ==\n";
        echo "=================================================\n";
        echo "Successfully recovered and appended " . count($recoveredEntries) . " entries.\n";
        echo "Your check-in log is now fully up to date.\n\n";
    } else {
        echo "\nERROR: Could not open checkin_log.json for writing. Recovery failed.\n";
    }
}

?>
