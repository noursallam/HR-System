<?php
include '../config.php';

// Start session first
startSession();

// Then check if user is logged in
requireAdminLogin();

// Get the database connection
$db = getDB();

// Get employee count
$stmt = $db->query("SELECT COUNT(*) as employee_count FROM employees");
$employeeCount = $stmt->fetchColumn();

// Get today's attendance count
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(DISTINCT employee_id) as today_attendance FROM attendance WHERE date = :today");
$stmt->bindParam(':today', $today);
$stmt->execute();
$todayAttendance = $stmt->fetchColumn();

// Get department count
$stmt = $db->query("SELECT COUNT(*) as department_count FROM departments");
$departmentCount = $stmt->fetchColumn();

// Get recent attendance activities
$stmt = $db->prepare("
    SELECT a.id, e.name as employee_name, a.check_type, a.timestamp, a.ip_address 
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    ORDER BY a.timestamp DESC
    LIMIT 10
");
$stmt->execute();
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HR Attendance System</title>
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
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="employees.php"><i class="fas fa-user-tie"></i> <span>Employees</span></a></li>
                <li><a href="attendance.php"><i class="fas fa-clipboard-check"></i> <span>Attendance</span></a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> <span>Analytics</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <div class="admin-user">
                    <i class="fas fa-user-circle"></i> Welcome, <?php echo $_SESSION['admin_username']; ?>
                </div>
            </div>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <i class="fas fa-user-tie fa-2x" style="color: #4CAF50; margin-bottom: 15px;"></i>
                    <div class="stat-value"><?php echo $employeeCount; ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clipboard-check fa-2x" style="color: #2196F3; margin-bottom: 15px;"></i>
                    <div class="stat-value"><?php echo $todayAttendance; ?></div>
                    <div class="stat-label">Today's Attendance</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-building fa-2x" style="color: #FF9800; margin-bottom: 15px;"></i>
                    <div class="stat-value"><?php echo $departmentCount; ?></div>
                    <div class="stat-label">Departments</div>
                </div>
            </div>
            <div class="dashboard-recent">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Activity</th>
                            <th>Time</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentActivities) > 0): ?>
                            <?php foreach($recentActivities as $activity): ?>
                                <tr>
                                    <td><i class="fas fa-user"></i> <?php echo htmlspecialchars($activity['employee_name']); ?></td>
                                    <td>
                                        <?php if ($activity['check_type'] == 'check_in'): ?>
                                            <span style="color: #4CAF50;"><i class="fas fa-sign-in-alt"></i> Check In</span>
                                        <?php else: ?>
                                            <span style="color: #F44336;"><i class="fas fa-sign-out-alt"></i> Check Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?></td>
                                    <td><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="no-data"><i class="fas fa-info-circle"></i> No recent activities found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="dashboard-links">
                <a href="employees.php" class="dashboard-link-card">
                    <h3><i class="fas fa-user-edit"></i> Manage Employees</h3>
                    <p>Add, edit, or remove employees and assign departments</p>
                </a>
                <a href="attendance.php" class="dashboard-link-card">
                    <h3><i class="fas fa-calendar-alt"></i> View Attendance</h3>
                    <p>View and export attendance records by date or employee</p>
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Add current date to the dashboard
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('.admin-header');
            const dateElement = document.createElement('div');
            dateElement.classList.add('current-date');
            dateElement.style.fontSize = '14px';
            dateElement.style.color = '#6c757d';
            dateElement.style.marginTop = '5px';
            
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateElement.innerHTML = '<i class="far fa-calendar-alt"></i> ' + now.toLocaleDateString('en-US', options);
            
            header.appendChild(dateElement);
        });
    </script>
</body>
</html>