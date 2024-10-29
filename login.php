<?php
session_start();

// If already logged in, redirect to index.php
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = require 'config.php';
    $submitted_password = $_POST['password'] ?? '';
    
    if ($submitted_password === $config['ADMIN_PASSWORD']) {
        $_SESSION['logged_in'] = true;
        header('Location: index.php');
        exit();
    } else {
        $error = 'Invalid password';
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
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="button-group">
            <input type="submit" value="Login">
        </div>
    </form>
</body>
</html>
