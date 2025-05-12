<?php
include '../config.php';

// Start the session
startSession();

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Check if it's an AJAX request or if redirect parameter is set
if (isset($_GET['redirect']) && $_GET['redirect'] == 'true') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - HR Attendance System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        
        .logout-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            text-align: center;
        }
        
        .logout-icon {
            font-size: 56px;
            color: #4CAF50;
            margin-bottom: 20px;
            animation: fadeInOut 2s infinite;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }
        
        .logout-message {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .logout-info {
            color: #6c757d;
            margin-bottom: 30px;
        }
        
        .logout-link {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .logout-link:hover {
            background-color: #3e8e41;
            text-decoration: none;
        }
        
        .logout-spinner {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 5px solid rgba(76, 175, 80, 0.2);
            border-radius: 50%;
            border-top-color: #4CAF50;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-spinner"></div>
        <p class="logout-message">You have been successfully logged out</p>
        <p class="logout-info">Thank you for using the HR Attendance System</p>
        <a href="login.php" class="logout-link"><i class="fas fa-sign-in-alt"></i> Return to Login</a>
    </div>
    
    <script>
        // Automatically redirect after 3 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
    </script>
</body>
</html>