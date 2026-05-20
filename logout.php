<?php
// ===== Session Cleanup =====
$config = include('config.php');

// Set session configuration using loaded config values
if (!empty($config['SESSION_CONFIG']['SAVE_PATH'])) {
    ini_set('session.save_path', $config['SESSION_CONFIG']['SAVE_PATH']);
}

// Start the session to access existing session data
session_start();

// Remove all session variables
// This clears any user data stored in the session
session_unset();

// Destroy the session
// This completely removes the session from the server
session_destroy();

// Redirect user back to login page
// After logout, users must log in again to access the system
header('Location: login.php');
exit();
?>
