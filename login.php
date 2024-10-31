<?php
session_start();

// If already logged in, redirect to appropriate page
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = require 'config.php';
    $submitted_username = $_POST['username'] ?? '';
    $submitted_password = $_POST['password'] ?? '';
    
    // Check for admin login
    if ($submitted_username === $config['ADMIN_USERNAME'] && $submitted_password === $config['ADMIN_PASSWORD']) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'admin';
        header('Location: admin.php');
        exit();
    }
    // Check for regular user login
    else if ($submitted_username === $config['USER_USERNAME'] && $submitted_password === $config['USER_PASSWORD']) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'user';
        header('Location: index.php');
        exit();
    }
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
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Login</h1>
    </div>

    <?php if ($error): ?>
        <div class="error-message">
            <b>Error:</b> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="login-form">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="button-group">
            <input type="submit" value="Login">
        </div>
    </form>
</body>
</html>
