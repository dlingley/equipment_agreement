<?php
// ===== Session Management =====
// Prevent HTML error output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON content type early to ensure proper response
header('Content-Type: application/json');

try {
    // Load configuration
    $config = require 'config.php';

    // Set CORS headers for security - only allow from our domain
    $allowed_origin = 'https://webapps.lib.purdue.edu';
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

    if ($origin === $allowed_origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    } else {
        error_log('Invalid origin attempted access: ' . $origin);
    }

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }

    // Handle session keepalive requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Start or resume session
            if (session_status() === PHP_SESSION_NONE) {
                // Set session configuration using loaded config values first
                ini_set('session.gc_maxlifetime', $config['SESSION_CONFIG']['TIMEOUT']);
                session_set_cookie_params([
                    'lifetime' => $config['SESSION_CONFIG']['TIMEOUT'],
                    'secure' => $config['SESSION_CONFIG']['SECURE'],
                    'httponly' => $config['SESSION_CONFIG']['HTTP_ONLY'],
                    'samesite' => 'Strict'
                ]);
                session_start();
            }

            // Log session status
            error_log('Session status: ' . print_r($_SESSION, true));
        
            // Verify user is logged in
            if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
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
            echo json_encode([
                'status' => 'success',
                'timestamp' => time()
            ]);
            exit();
        } catch (Exception $e) {
            error_log('Session error in keepalive.php: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error'
            ]);
            exit();
        }
    } else {
        // Only allow POST requests
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
        exit();
    }
} catch (Exception $e) {
    error_log('Configuration error in keepalive.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
    exit();
}
