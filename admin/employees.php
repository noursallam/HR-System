<?php
include '../config.php';

// Start session first
startSession();

// Then check if user is logged in
requireAdminLogin();

$message = '';
$error = '';
$db = getDB();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle employee addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $name = test_input($_POST['name']);
        $email = test_input($_POST['email']);
        $department_id = (int)$_POST['department_id'];
        
        // Validate input
        if (empty($name) || empty($email) || empty($department_id)) {
            $error = "All fields are required";
        } else {
            try {
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM employees WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $error = "Email already exists";
                } else {
                    // Insert new employee
                    $stmt = $db->prepare("INSERT INTO employees (name, email, department_id) VALUES (:name, :email, :department_id)");
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':department_id', $department_id);
                    
                    if ($stmt->execute()) {
                        $message = "Employee added successfully";
                    } else {
                        $error = "Error adding employee";
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle employee deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $employee_id = (int)$_POST['employee_id'];
        
        try {
            // Delete employee
            $stmt = $db->prepare("DELETE FROM employees WHERE id = :employee_id");
            $stmt->bindParam(':employee_id', $employee_id);
            
            if ($stmt->execute()) {
                $message = "Employee deleted successfully";
            } else {
                $error = "Error deleting employee";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch all departments
$stmt = $db->prepare("SELECT id, name FROM departments ORDER BY name");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all employees with department names
$stmt = $db->prepare("
    SELECT e.id, e.name, e.email, e.created_at, d.name as department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY e.name
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - HR Attendance System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="admin-container">        <div class="admin-sidebar">
            <div class="admin-logo">
                <h2><i class="fas fa-users-cog"></i> HR System</h2>
            </div>
            <ul class="admin-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li class="active"><a href="employees.php"><i class="fas fa-user-tie"></i> <span>Employees</span></a></li>
                <li><a href="attendance.php"><i class="fas fa-clipboard-check"></i> <span>Attendance</span></a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> <span>Analytics</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-user-tie"></i> Manage Employees</h1>
                <div class="admin-user">
                    <i class="fas fa-user-circle"></i> Welcome, <?php echo $_SESSION['admin_username']; ?>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <div class="add-form-container">
                    <h2><i class="fas fa-user-plus"></i> Add New Employee</h2>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Name:</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="department_id"><i class="fas fa-building"></i> Department:</label>
                            <select id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit"><i class="fas fa-plus-circle"></i> Add Employee</button>
                        </div>
                    </form>
                </div>
                
                <div class="data-container">
                    <h2><i class="fas fa-list"></i> Employee List</h2>
                    <?php if (count($employees) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo $employee['id']; ?></td>
                                        <td><i class="fas fa-user"></i> <?php echo htmlspecialchars($employee['name']); ?></td>
                                        <td><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($employee['email']); ?></td>
                                        <td><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['department_name']); ?></td>
                                        <td><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                        <td>
                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this employee?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                <button type="submit" class="btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data"><i class="fas fa-info-circle"></i> No employees found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
