<?php
include '../config.php';

// Start session at the beginning of the script
startSession();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $username = test_input($_POST['username']);
        $password = $_POST['password'];
        
        // Validate input
        if (empty($username) || empty($password)) {
            $error = "Username and password are required";
        } else {
            // Check if username exists
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id, username, password FROM admin_users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                  if ($user) {
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Password is correct, start a new session
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $user['id'];
                        $_SESSION['admin_username'] = $user['username'];
                        
                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        // Debug password verification
                        error_log("Password verification failed. Input: " . $password . ", Hash: " . $user['password']);
                        $error = "Invalid username or password";
                    }
                } else {
                    $error = "Invalid username or password";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }    }
}

// We've already started the session and generated the CSRF token at the beginning of the script
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - HR Attendance System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #3a4a5e 0%, #2c3e50 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            text-align: center;
            animation: fadeIn 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        .login-container:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #4CAF50, #2196F3);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
          .login-logo {
            margin-bottom: 35px;
        }
        
        .login-logo h1 {
            color: #2c3e50;
            font-size: 28px;
            margin: 10px 0 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-logo h1 i {
            margin-right: 10px;
            color: #4CAF50;
        }
        
        .login-logo p {
            margin-top: 8px;
            color: #6c757d;
            font-size: 16px;
            letter-spacing: 0.5px;
        }
            margin-bottom: 15px;
            display: block;
        }
          .login-form .form-group {
            margin-bottom: 25px;
        }
        
        .login-form label {
            display: block;
            text-align: left;
            margin-bottom: 10px;
            color: #495057;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .login-form .input-group {
            position: relative;
            margin-bottom: 5px;
        }
        
        .login-form .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 1;
        }
        
        .login-form input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 1px solid #e0e0e0;
            border-radius: 30px;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) inset;
            background-color: #f9f9f9;
        }
        
        .login-form input:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
            background-color: #ffffff;
        }
        
        .login-form button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #4CAF50, #3e8e41);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(76, 175, 80, 0.2);
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-form button:hover {
            background: linear-gradient(90deg, #3e8e41, #2d682f);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.3);
            transform: translateY(-2px);
        }
        
        .login-form button i {
            margin-right: 8px;
        }
          .error-message {
            background-color: #FFEBEE;
            color: #C62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            border-left: 4px solid #F44336;
            text-align: left;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .error-message i {
            margin-right: 10px;
            font-size: 18px;
            color: #F44336;
        }
        
        .login-footer {
            margin-top: 35px;
            font-size: 13px;
            color: #6c757d;
        }
        
        .back-to-site {
            display: inline-block;
            margin-top: 15px;
            color: #4CAF50;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .back-to-site:hover {
            color: #3e8e41;
            text-decoration: underline;
        }
        
        .back-to-site i {
            margin-right: 5px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">        <div class="login-logo">
            <h1><i class="fas fa-users-cog"></i> HR System</h1>
            <p>Admin Login Portal</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" id="password" name="password" required>
                </div>
            </div>
            
            <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
        
        <a href="../index.php" class="back-to-site"><i class="fas fa-arrow-left"></i> Back to Attendance System</a>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> HR Attendance System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>