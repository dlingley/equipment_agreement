<?php
// ===== Session Management =====
// Start a new session or resume the existing session
session_start();

ini_set('session.gc_maxlifetime', $config['SESSION_CONFIG']['TIMEOUT']);
session_set_cookie_params([
    'lifetime' => $config['SESSION_CONFIG']['TIMEOUT'],
    'secure' => $config['SESSION_CONFIG']['SECURE'],
    'httponly' => $config['SESSION_CONFIG']['HTTP_ONLY']
]);

<?php


// ===== Authentication Check =====
// If user is already logged in, redirect them to their appropriate dashboard
// Admins go to admin.php, regular users go to index.php
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// Initialize error message variable
$error = '';

// ===== Login Form Processing =====
// Handle POST request when login form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Load configuration settings containing valid credentials
    $config = require 'config.php';
    
    // Get submitted credentials using null coalescing operator
    // This provides a default empty string if the POST values aren't set
    $submitted_username = $_POST['username'] ?? '';
    $submitted_password = $_POST['password'] ?? '';
    
    // ===== Credential Validation =====
    // Check if credentials match admin account
    if ($submitted_username === $config['ADMIN_USERNAME'] && $submitted_password === $config['ADMIN_PASSWORD']) {
        // Set session variables for admin user
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'admin';
        header('Location: admin.php');
        exit();
    }
    // Check if credentials match regular user account
    else if ($submitted_username === $config['USER_USERNAME'] && $submitted_password === $config['USER_PASSWORD']) {
        // Set session variables for regular user
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'user';
        header('Location: index.php');
        exit();
    }
    // Invalid credentials
    else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Purdue Libraries Equipment Agreement</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Styles for login form layout and appearance */
        .login-form {
            max-width: 400px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        /* Style for displaying error messages */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            margin: 1rem auto;
            max-width: 400px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Login</h1>
    </div>

    <!-- Error Message Display -->
    <?php if ($error): ?>
        <div class="error-message">
            <b>Error:</b> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" class="login-form">
        <!-- Username Input -->
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <!-- Password Input -->
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <!-- Submit Button -->
        <div class="button-group">
            <input type="submit" value="Login">
        </div>
    </form>
</body>
</html>
