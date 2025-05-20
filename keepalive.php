<?php
// ===== Session Management =====
// Load configuration first
$config = require 'config.php';

// Start a new session or resume the existing session
session_start();

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
        http_response_code(401);
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

    // Return success status
    http_response_code(200);
    exit();
} else {
    // Only allow POST requests
    http_response_code(405);
    exit();
}
