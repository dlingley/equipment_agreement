<?php
// ===== Session Management =====
// Load configuration first
$config = require 'config.php';

// Configure error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable HTML error output
ini_set('log_errors', 1);

// Ensure no output has been sent
if (headers_sent($filename, $linenum)) {
    error_log("Headers already sent in $filename on line $linenum");
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
    exit();
}

// Set CORS headers for security - only allow from our domain
$allowed_origins = [
    'https://webapps.lib.purdue.edu',
    'http://localhost',
    'http://127.0.0.1'
];
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Check if origin is allowed
$origin_is_allowed = false;
foreach ($allowed_origins as $allowed) {
    if (strpos($origin, $allowed) === 0) {
        $origin_is_allowed = true;
        break;
    }
}

if ($origin_is_allowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    error_log('Access granted to origin: ' . $origin);
} else {
    error_log('Invalid origin attempted access: ' . $origin);
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid origin'
    ]);
    exit();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Start a new session or resume the existing session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log session status
error_log('Session status: ' . print_r($_SESSION, true));

// Set session configuration using loaded config values
ini_set('session.gc_maxlifetime', $config['SESSION_CONFIG']['TIMEOUT']);
session_set_cookie_params([
    'lifetime' => $config['SESSION_CONFIG']['TIMEOUT'],
    'secure' => $config['SESSION_CONFIG']['SECURE'],
    'httponly' => $config['SESSION_CONFIG']['HTTP_ONLY'],
    'samesite' => 'Strict'
]);

// Handle session keepalive requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify user is logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized'
        ]);
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['last_regeneration']) || 
        (time() - $_SESSION['last_regeneration']) > 3600) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'timestamp' => time()
    ]);
    exit();
} else {
    // Only allow POST requests
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit();
}
