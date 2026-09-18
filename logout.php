<?php
/**
 * Knowledge Lab Equipment Agreement — Logout Endpoint
 * Destroys the centralized Auth Hub session and redirects to Auth Hub logout.
 */
$possibleGuards = [
    __DIR__ . '/../auth/auth_guard.php',
    '/var/www/html/webapps/alma/auth/auth_guard.php',
    '/Volumes/alma$/auth/auth_guard.php',
];

$authGuardLoaded = false;
foreach ($possibleGuards as $guardPath) {
    if (file_exists($guardPath)) {
        require_once $guardPath;
        $authGuardLoaded = true;
        break;
    }
}

if ($authGuardLoaded && function_exists('auth_destroy_session')) {
    auth_destroy_session();
} else {
    session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

header('Location: /alma/auth/logout.php');
exit();
