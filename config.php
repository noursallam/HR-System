<?php
// SQLite database configuration
define('DB_PATH', __DIR__ . '/hr_attendance.db');

// Create/connect to SQLite database
function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        // Set error mode to exceptions
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Enable foreign keys support
        $db->exec('PRAGMA foreign_keys = ON;');
        
        return $db;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Initialize database if it doesn't exist
function initializeDB() {
    if (!file_exists(DB_PATH)) {
        $db = getDB();
        
        // Read and execute SQL from database.sql file
        $sql = file_get_contents(__DIR__ . '/database.sql');
        $db->exec($sql);
        
        return true;
    }
    return false;
}

// Initialize the database
initializeDB();

// Function to validate and sanitize input data
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Start session if not already started
function startSession() {
    if (session_status() == PHP_SESSION_NONE) {
        // Set session parameters for better security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        
        // Set session timeout to 30 minutes (1800 seconds)
        ini_set('session.gc_maxlifetime', 1800);
        session_start();
        
        // Check if session has expired
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            // Session expired, destroy it
            session_unset();
            session_destroy();
            
            // Redirect to login page if on an admin page
            $current_path = $_SERVER['PHP_SELF'];
            if (strpos($current_path, '/admin/') !== false && strpos($current_path, '/admin/login.php') === false) {
                header("Location: login.php");
                exit;
            }
        }
        
        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
    }
}

// Check if user is logged in as admin
function isAdminLoggedIn() {
    // The session should already be started before calling this
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Redirect to login page if not logged in (for admin pages)
function requireAdminLogin() {
    // No need to call startSession() here since it should be called before this function
    if (!isAdminLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}
?>