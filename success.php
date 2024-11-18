<?php
// ===== Session Management =====
// Start session to maintain user state
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Auto-redirect to homepage after 5 seconds -->
    <meta http-equiv="refresh" content="5;url=index.php">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <title>Success</title>
    <!-- Include main stylesheet -->
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Container for success message content */
        .success-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        /* Style for the main success message */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
        }
        /* Style for redirect notification */
        .redirect-message {
            color: #6c757d;
            font-style: italic;
            margin-top: 1rem;
        }
        /* Style for thank you message */
        .thank-you-message {
            margin-top: 1rem;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Success</h1>
    </div>

    <!-- Success Message Container -->
    <div class="success-container">
        <!-- Main success message -->
        <div class="success-message">
            Your Equipment Agreement has been successfully submitted.
        </div>
        <!-- Thank you message -->
        <div class="thank-you-message">
            Thank you for using the Knowledge Lab!
        </div>
        <!-- Redirect notification -->
        <div class="redirect-message">
            You will be redirected to the homepage in 5 seconds...
        </div>
    </div>
</body>
</html>
