<?php

/**
 * FINAL Check-in Log Cleanup and Recount Script (v2)
 *
 * This script provides a definitive cleanup of the entire check-in log history.
 * It is designed to be run on a messy log directory to produce a single, clean,
 * de-duplicated, and correctly counted master log file.
 *
 * HOW TO RUN:
 * 1. Place this file in your main application directory.
 * 2. Execute from the command line: sudo php cleanup_and_recount.php
 * 3. Follow the "NEXT STEPS" printed at the end of the script to activate the clean log.
 */

ini_set('display_errors', 1);
ini_set('memory_limit', '1024M'); 
error_reporting(E_ALL);

echo "=================================================\n";
echo "==    Log Cleanup & Recount Script (v2)        ==\n";
echo "==       - Now with ID Normalization -         ==\n";
echo "=================================================\n\n";

// --- CONFIGURATION ---
$config = include(__DIR__ . '/config.php');
$logDir = __DIR__ . '/' . dirname($config['LOG_PATHS']['CHECKIN']);
$mainLogFile = $logDir . '/' . basename($config['LOG_PATHS']['CHECKIN']);
$archiveDir = $logDir . '/archives';
$outputFile = $logDir . '/checkin_log.FINAL_CLEAN.json';
$duplicateWindowSeconds = 300; // 5 minutes
// --- END CONFIGURATION ---

echo "Source Directory: $logDir\n";
echo "Clean Output File: $outputFile\n\n";

// 1. Gather all log files from the current messy directory
$filesToProcess = [];
if (is_readable($mainLogFile)) $filesToProcess[] = $mainLogFile;
if (is_dir($archiveDir)) {
    $archiveFiles = glob($archiveDir . '/*.json');
    if ($archiveFiles) $filesToProcess = array_merge($filesToProcess, $archiveFiles);
}
if (empty($filesToProcess)) die("ERROR: No log files found to process.\n");

// 2. Read all entries and NORMALIZE IDs on ingest
echo "Reading all log entries and normalizing Purdue IDs...\n";
$allEntries = [];
foreach ($filesToProcess as $file) {
    $handle = fopen($file, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $data = json_decode(trim($line), true);
            if ($data && isset($data['purdueId'], $data['timestamp'])) {
                if (strlen($data['purdueId']) > 10) {
                    $data['purdueId'] = substr($data['purdueId'], 0, 10);
                }
                $allEntries[] = $data;
            }
        }
        fclose($handle);
    }
}

echo "Read " . number_format(count($allEntries)) . " total entries. Now grouping by normalized ID...\n";

// 3. Group by the now-clean Purdue ID
$entriesByUser = [];
foreach ($allEntries as $entry) {
    $entriesByUser[$entry['purdueId']][] = $entry;
}

echo "Found " . number_format(count($entriesByUser)) . " unique users after normalization.\n";
echo "Filtering duplicates and recounting visits...\n\n";

// 4. Process each user's merged history
$finalCleanedEntries = [];
$entriesRemoved = 0;
foreach ($entriesByUser as $purdueId => $userEntries) {
    usort($userEntries, fn($a, $b) => strtotime($a['timestamp']) <=> strtotime($b['timestamp']));
    
    $keptEntries = [];
    $lastKeptTimestamp = 0;
    foreach ($userEntries as $entry) {
        $currentTimestamp = strtotime($entry['timestamp']);
        if ($currentTimestamp - $lastKeptTimestamp > $duplicateWindowSeconds) {
            $keptEntries[] = $entry;
            $lastKeptTimestamp = $currentTimestamp;
        } else {
            $entriesRemoved++;
        }
    }
    
    $newVisitCount = 1;
    foreach ($keptEntries as &$entry) {
        $entry['visitCount'] = $newVisitCount++;
        $finalCleanedEntries[] = $entry;
    }
}

echo "Filtering complete. Removed " . number_format($entriesRemoved) . " duplicate entries.\n";
echo "Sorting final log file by timestamp...\n";

// 5. Sort the final master list by timestamp
usort($finalCleanedEntries, fn($a, $b) => strtotime($a['timestamp']) <=> strtotime($b['timestamp']));

// 6. Write to the new output file
$handleOut = fopen($outputFile, 'w');
foreach ($finalCleanedEntries as $entry) {
    fwrite($handleOut, json_encode($entry) . "\n");
}
fclose($handleOut);

echo "\n=================================================\n";
echo "==              PROCESS COMPLETE               ==\n";
echo "=================================================\n";
echo "Total entries kept: " . number_format(count($finalCleanedEntries)) . "\n";
echo "Total duplicate entries removed: " . number_format($entriesRemoved) . "\n";
echo "Clean data has been written to: $outputFile\n\n";
echo "NEXT STEPS (IMPORTANT):\n";
echo "1. Verify the contents of the new file: tail '$outputFile'\n";
echo "2. Run the following commands to activate the new log:\n\n";
echo "   cd '$logDir'\n";
echo "   mkdir -p messy_logs_backup\n";
echo "   mv checkin_*.json* messy_logs_backup/  # Backs up old main log and archives\n";
echo "   if [ -d \"archives\" ]; then mv archives messy_logs_backup/; fi\n";
echo "   mv '" . basename($outputFile) . "' '" . basename($mainLogFile) . "' # Activates the new clean log\n\n";
echo "3. After activating, the 'logs' directory will be clean. You can delete the 'messy_logs_backup' when you are confident.\n";

?>
