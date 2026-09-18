<?php
/**
 * Knowledge Lab Equipment Agreement — Automated Test Suite & Health Check
 * 
 * Usage:
 *   php tests/run_tests.php
 * 
 * Exit Codes:
 *   0 = All tests passed
 *   1 = One or more tests failed
 */

// Terminal colors
$cGreen  = "\033[32m";
$cRed    = "\033[31m";
$cYellow = "\033[33m";
$cCyan   = "\033[36m";
$cBold   = "\033[1m";
$cReset  = "\033[0m";

$passCount = 0;
$failCount = 0;
$warnCount = 0;

function reportPass($name) {
    global $cGreen, $cReset, $passCount;
    $passCount++;
    echo "  {$cGreen}✔ PASS{$cReset} - $name\n";
}

function reportFail($name, $details = '') {
    global $cRed, $cReset, $failCount;
    $failCount++;
    echo "  {$cRed}✖ FAIL{$cReset} - $name\n";
    if ($details) {
        echo "         Details: $details\n";
    }
}

function reportWarn($name, $details = '') {
    global $cYellow, $cReset, $warnCount;
    $warnCount++;
    echo "  {$cYellow}⚠ WARN{$cReset} - $name\n";
    if ($details) {
        echo "         Details: $details\n";
    }
}

$rootDir = dirname(__DIR__);
chdir($rootDir);

echo "\n{$cBold}{$cCyan}==================================================================={$cReset}\n";
echo "{$cBold}{$cCyan} Knowledge Lab Equipment Agreement — Test & Verification Suite{$cReset}\n";
echo "{$cBold}{$cCyan} Root Directory: {$rootDir}{$cReset}\n";
echo "{$cBold}{$cCyan} Running as User: " . (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : get_current_user()) . " (UID: " . (function_exists('posix_geteuid') ? posix_geteuid() : 'N/A') . "){$cReset}\n";
echo "{$cBold}{$cCyan}==================================================================={$cReset}\n\n";

// ============================================================================
// SUITE 1: Configuration & Filesystem Permissions
// ============================================================================
echo "{$cBold}Suite 1: Filesystem & Permission Integrity{$cReset}\n";

// 1.1 config.php
$configFile = $rootDir . '/config.php';
if (file_exists($configFile) && is_readable($configFile)) {
    $config = include($configFile);
    if (is_array($config) && !empty($config['ALMA_API_KEY'])) {
        reportPass("config.php exists and contains valid Alma API configuration");
    } else {
        reportFail("config.php is invalid or missing ALMA_API_KEY");
    }
} else {
    reportFail("config.php not found or not readable at $configFile");
}

// 1.2 allowed_users.txt & backup
$aclFile = $rootDir . '/allowed_users.txt';
if (file_exists($aclFile)) {
    if (is_readable($aclFile) && is_writable($aclFile)) {
        reportPass("allowed_users.txt is readable and writable (Mode: " . substr(sprintf('%o', fileperms($aclFile)), -4) . ")");
    } else {
        reportFail("allowed_users.txt lacks write permissions for current process", "Run: sudo chmod 666 $aclFile");
    }
} else {
    reportFail("allowed_users.txt does not exist at $aclFile");
}

$aclBakFile = $rootDir . '/allowed_users.txt.bak';
if (file_exists($aclBakFile)) {
    if (is_writable($aclBakFile)) {
        reportPass("allowed_users.txt.bak is writable");
    } else {
        reportWarn("allowed_users.txt.bak is not writable", "Run: sudo chmod 666 $aclBakFile");
    }
} else {
    reportPass("allowed_users.txt.bak does not exist yet (will be created automatically on first save)");
}

// 1.3 logs/ directory
$logDir = $rootDir . '/logs';
if (is_dir($logDir)) {
    if (is_writable($logDir)) {
        reportPass("logs/ directory exists and is writable (Mode: " . substr(sprintf('%o', fileperms($logDir)), -4) . ")");
    } else {
        reportFail("logs/ directory is NOT writable by current user", "Run: sudo chmod -R 777 $logDir");
    }
} else {
    reportFail("logs/ directory not found at $logDir");
}

// 1.4 logs/archives/ directory
$archiveDir = $rootDir . '/logs/archives';
if (is_dir($archiveDir)) {
    if (is_writable($archiveDir)) {
        reportPass("logs/archives/ exists and is writable (Mode: " . substr(sprintf('%o', fileperms($archiveDir)), -4) . ")");
    } else {
        reportFail("logs/archives/ directory is NOT writable", "Run: sudo chmod -R 777 $archiveDir");
    }
} else {
    if (@mkdir($archiveDir, 0777, true)) {
        reportPass("logs/archives/ directory created successfully");
    } else {
        reportFail("logs/archives/ directory missing and could not be created");
    }
}

// 1.5 logs/debug.log
$debugLog = $rootDir . '/logs/debug.log';
if (file_exists($debugLog)) {
    if (is_writable($debugLog)) {
        reportPass("logs/debug.log is writable (Mode: " . substr(sprintf('%o', fileperms($debugLog)), -4) . ")");
    } else {
        reportFail("logs/debug.log is NOT writable by current process", "Run: sudo chmod 666 $debugLog");
    }
} else {
    if (@touch($debugLog)) {
        reportPass("logs/debug.log created successfully");
    } else {
        reportFail("logs/debug.log missing and could not be created");
    }
}

// 1.6 logs/checkin_log.json
$checkinLog = $rootDir . '/logs/checkin_log.json';
if (file_exists($checkinLog)) {
    if (is_writable($checkinLog)) {
        reportPass("logs/checkin_log.json is writable (Mode: " . substr(sprintf('%o', fileperms($checkinLog)), -4) . ")");
    } else {
        reportFail("logs/checkin_log.json is NOT writable by current process", "Run: sudo chmod 666 $checkinLog");
    }
} else {
    if (@touch($checkinLog)) {
        reportPass("logs/checkin_log.json created successfully");
    } else {
        reportFail("logs/checkin_log.json missing and could not be created");
    }
}

// 1.7 Historical Archive Files (Monthly Rotation Files)
$archiveFiles = glob($archiveDir . '/checkin_*.json');
$unwritableArchives = [];
$unreadableArchives = [];
foreach ($archiveFiles as $af) {
    if (!is_readable($af)) {
        $unreadableArchives[] = basename($af);
    }
    if (!is_writable($af)) {
        $unwritableArchives[] = basename($af);
    }
}

if (count($archiveFiles) > 0) {
    if (empty($unwritableArchives) && empty($unreadableArchives)) {
        reportPass("All " . count($archiveFiles) . " monthly archive files in logs/archives/ are readable and writable (ready for rotation & reports)");
    } else {
        $details = [];
        if (!empty($unreadableArchives)) $details[] = "Unreadable: " . implode(', ', $unreadableArchives);
        if (!empty($unwritableArchives)) $details[] = "Unwritable: " . implode(', ', $unwritableArchives);
        reportFail("Some monthly archive files have restrictive permissions", implode('; ', $details));
    }
} else {
    reportPass("No historical archive files found in logs/archives/ (directory is ready for first rotation)");
}

// ============================================================================
// SUITE 2: Fail-Safe Logging & Error Suppression Tests
// ============================================================================
echo "\n{$cBold}Suite 2: Fail-Safe Logging & Kiosk Screen Error Isolation{$cReset}\n";

// 2.1 confirm.php debugLog resilience test
// Test that calling debugLog writes cleanly and does not leak PHP errors
$testLogMarker = 'TEST_RUNNER_CHECK_' . time();
$confirmContent = file_get_contents($rootDir . '/confirm.php');

if (strpos($confirmContent, '@file_put_contents') !== false) {
    reportPass("confirm.php uses @file_put_contents to prevent runtime warning leaks to patrons");
} else {
    reportFail("confirm.php has unsuppressed file_put_contents calls that could leak to patrons");
}

if (strpos($confirmContent, "ini_set('display_errors', \$debugMode ? 1 : 0);") !== false || 
    strpos($confirmContent, "ini_set('display_errors', 0);") !== false) {
    reportPass("confirm.php suppresses display_errors by default for kiosk patrons");
} else {
    reportFail("confirm.php enables display_errors unconditionally for kiosk patrons");
}

if (strpos($confirmContent, "error_log(") !== false) {
    reportPass("confirm.php provides fallback to native PHP error_log if log file write fails");
} else {
    reportFail("confirm.php does not fall back to error_log on log write failure");
}

// 2.2 index.php error display test
$indexContent = file_get_contents($rootDir . '/index.php');
if (strpos($indexContent, "ini_set('display_errors', \$debugMode ? 1 : 0);") !== false ||
    strpos($indexContent, "ini_set('display_errors', 0);") !== false) {
    reportPass("index.php suppresses display_errors by default for kiosk patrons");
} else {
    reportFail("index.php has display_errors enabled for kiosk patrons");
}

// 2.3 Simulated Logging Execution Test
$emittedWarning = false;
set_error_handler(function($errno, $errstr) use (&$emittedWarning) {
    if ((error_reporting() & $errno) === 0) {
        return true; // Suppressed by @ operator
    }
    $emittedWarning = true;
    return true;
});

// Test write to debug.log with output buffering to ensure zero screen leakage
ob_start();
$testConfig = [
    'TIMEZONE' => 'America/Indianapolis',
    'LOG_PATHS' => [
        'DEBUG' => 'logs/debug.log'
    ]
];

$testLogFile = $rootDir . '/' . $testConfig['LOG_PATHS']['DEBUG'];
$testEntry = "[" . date('Y-m-d H:i:s') . "] [TEST] Automated Test Suite Execution: $testLogMarker\n";
$writeRes = @file_put_contents($testLogFile, $testEntry, FILE_APPEND | LOCK_EX);
$leakedOutput = ob_get_clean();

restore_error_handler();

if ($writeRes !== false && !$emittedWarning && empty($leakedOutput)) {
    reportPass("Direct write to debug.log succeeded with zero warning leakage or screen output");
} else {
    reportFail("Direct write to debug.log failed, emitted warnings, or leaked to screen");
}

// 2.4 Test write to unwritable path: verify @ operator suppresses unhandled PHP warnings
$unwritableWarning = false;
set_error_handler(function($errno, $errstr) use (&$unwritableWarning) {
    if ((error_reporting() & $errno) === 0) {
        return true; // Correctly suppressed by @
    }
    $unwritableWarning = true;
    return true;
});

ob_start();
$badPath = '/root/forbidden_test_' . time() . '.log';
$badWriteRes = @file_put_contents($badPath, "test", FILE_APPEND | LOCK_EX);
$badLeakedOutput = ob_get_clean();
restore_error_handler();

if ($badWriteRes === false && !$unwritableWarning && empty($badLeakedOutput)) {
    reportPass("Writing to restricted path fails gracefully with zero screen leakage");
} else {
    reportFail("Unwritable path test triggered an unhandled PHP warning or leaked output");
}

// 2.5 Verify admin.php monthly rotation logic uses error suppression and self-healing chmod
$adminContent = file_get_contents($rootDir . '/admin.php');
if (strpos($adminContent, '@file_put_contents($archiveFile') !== false &&
    strpos($adminContent, '@chmod($archiveFile, 0666)') !== false) {
    reportPass("admin.php monthly archive rotation enforces 0666 permissions and error suppression");
} else {
    reportFail("admin.php does not enforce 0666 or error suppression during monthly rotation");
}

// ============================================================================
// SUITE 3: Access Control & Role Management Integrity
// ============================================================================
echo "\n{$cBold}Suite 3: Access Control & Role Management Integrity{$cReset}\n";

$rawAcl = file_get_contents($aclFile);
$parsedAcl = [];
$aclLines = explode("\n", $rawAcl);
$invalidLines = [];

foreach ($aclLines as $lineNum => $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0) {
        continue;
    }
    if (preg_match('/^([a-z0-9._-]+)(?::(superadmin|admin|user))?$/i', $line, $m)) {
        $u = strtolower($m[1]);
        $r = isset($m[2]) ? strtolower($m[2]) : 'user';
        $parsedAcl[$u] = $r;
    } else {
        $invalidLines[] = "Line " . ($lineNum + 1) . ": $line";
    }
}

if (empty($invalidLines)) {
    reportPass("allowed_users.txt has clean syntax across all " . count($parsedAcl) . " defined accounts");
} else {
    reportFail("allowed_users.txt contains invalid syntax lines", implode(', ', $invalidLines));
}

// Verify required superadmins
$requiredSuperAdmins = ['paswanso', 'huber47', 'dlingley', 'lampley'];
$missingAdmins = [];
foreach ($requiredSuperAdmins as $admin) {
    if (!isset($parsedAcl[$admin]) || $parsedAcl[$admin] !== 'superadmin') {
        $missingAdmins[] = $admin . " (current: " . ($parsedAcl[$admin] ?? 'none') . ")";
    }
}

if (empty($missingAdmins)) {
    reportPass("All required superadmins (paswanso, huber47, dlingley, lampley) are active with superadmin role");
} else {
    reportFail("Missing required superadmins in allowed_users.txt", implode(', ', $missingAdmins));
}

// Verify Git decoupling: ensure allowed_users.txt is ignored
$gitCheck = shell_exec('git -C ' . escapeshellarg($rootDir) . ' check-ignore allowed_users.txt 2>/dev/null');
if (trim($gitCheck) === 'allowed_users.txt') {
    reportPass("allowed_users.txt is correctly protected in .gitignore (safe from Git deployments)");
} else {
    reportWarn("allowed_users.txt is not matched by .gitignore (verify git protection)");
}

// ============================================================================
// SUITE 4: PHP Syntax & Linter
// ============================================================================
echo "\n{$cBold}Suite 4: PHP Syntax & Code Linter{$cReset}\n";

$phpFiles = glob($rootDir . '/*.php');
$lintErrors = [];

foreach ($phpFiles as $phpFile) {
    $output = [];
    $exitCode = 0;
    exec('php -l ' . escapeshellarg($phpFile) . ' 2>&1', $output, $exitCode);
    if ($exitCode === 0) {
        reportPass("Lint passed: " . basename($phpFile));
    } else {
        $lintErrors[] = basename($phpFile) . ": " . implode(' ', $output);
        reportFail("Lint failed: " . basename($phpFile), implode(' ', $output));
    }
}

// ============================================================================
// Test Summary
// ============================================================================
echo "\n{$cBold}{$cCyan}==================================================================={$cReset}\n";
echo "{$cBold}Test Results Summary:{$cReset}\n";
echo "  Passed:  {$cGreen}{$passCount}{$cReset}\n";
echo "  Failed:  " . ($failCount > 0 ? "{$cRed}{$failCount}{$cReset}" : "0") . "\n";
echo "  Warnings: " . ($warnCount > 0 ? "{$cYellow}{$warnCount}{$cReset}" : "0") . "\n";
echo "{$cBold}{$cCyan}==================================================================={$cReset}\n\n";

if ($failCount > 0) {
    echo "{$cRed}{$cBold}OVERALL STATUS: FAILURE{$cReset} — Please resolve the failed items above.\n\n";
    exit(1);
} else {
    echo "{$cGreen}{$cBold}OVERALL STATUS: ALL TESTS PASSED!{$cReset}\n\n";
    exit(0);
}
