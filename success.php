<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5;url=index.php">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <title>Success</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .success-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
        }
        .redirect-message {
            color: #6c757d;
            font-style: italic;
            margin-top: 1rem;
        }
        .thank-you-message {
            margin-top: 1rem;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Success</h1>
    </div>

    <div class="success-container">
        <div class="success-message">
            Your Equipment Agreement has been successfully submitted.
        </div>
        <div class="thank-you-message">
            Thank you for using the Knowledge Lab!
        </div>
        <div class="redirect-message">
            You will be redirected to the homepage in 5 seconds...
        </div>
    </div>
</body>
</html>
