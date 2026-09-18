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
            $authGuardPath = __DIR__ . '/../auth/auth_guard.php';
            if (!file_exists($authGuardPath)) {
                $authGuardPath = '/var/www/html/webapps/alma/auth/auth_guard.php';
            }

            if (!empty($config['SESSION_CONFIG']['TIMEOUT'])) {
                ini_set('session.gc_maxlifetime', $config['SESSION_CONFIG']['TIMEOUT']);
            }

            if (file_exists($authGuardPath)) {
                require_once $authGuardPath;
                auth_start_session();
            } else {
                session_start();
            }

            // Verify user is logged in via Auth Hub or session
            $authUser = function_exists('auth_user_from_session') 
                ? auth_user_from_session(['allowed_users_file' => __DIR__ . '/allowed_users.txt'])
                : null;

            $isLoggedIn = ($authUser !== null) || (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);

            if (!$isLoggedIn) {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ]);
                exit();
            }

            // Update last activity time
            $_SESSION['last_activity'] = time();

            // Return success response
            echo json_encode([
                'status' => 'success',
                'user' => $authUser ? $authUser->username : ($_SESSION['username'] ?? ''),
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
