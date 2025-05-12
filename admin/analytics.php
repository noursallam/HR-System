<?php
include '../config.php';

// Start session first
startSession();

// Then check if user is logged in
requireAdminLogin();

$db = getDB();
$message = '';
$error = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get all employees for dropdown
$stmt = $db->prepare("SELECT id, name FROM employees ORDER BY name");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Default to current month if not specified
$selectedMonth = isset($_GET['month']) ? test_input($_GET['month']) : date('Y-m');

// Validate month format (YYYY-MM)
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $error = "Invalid month format. Using current month instead.";
    $selectedMonth = date('Y-m');
}

// Get month name for display
$monthName = date('F Y', strtotime($selectedMonth . '-01'));

// Get attendance analytics
$attendanceData = [];
$totalDays = [];

// Get all days in the selected month with attendance
$stmt = $db->prepare("
    SELECT DISTINCT date 
    FROM attendance 
    WHERE date LIKE :month_pattern 
    ORDER BY date
");
$stmt->bindValue(':month_pattern', $selectedMonth . '%');
$stmt->execute();
$daysWithAttendance = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get employee attendance counts
$stmt = $db->prepare("
    SELECT 
        e.id,
        e.name,
        COUNT(DISTINCT a.date) as days_present
    FROM 
        employees e
    LEFT JOIN 
        attendance a ON e.id = a.employee_id
    WHERE 
        a.date LIKE :month_pattern
        AND a.check_type = 'check_in'
    GROUP BY 
        e.id
    ORDER BY 
        e.name
");
$stmt->bindValue(':month_pattern', $selectedMonth . '%');
$stmt->execute();
$employeeAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees with no attendance records for this month
$employeesWithAttendance = array_column($employeeAttendance, 'id');
$employeesWithoutAttendance = [];
foreach ($employees as $employee) {
    if (!in_array($employee['id'], $employeesWithAttendance)) {
        $employeesWithoutAttendance[] = [
            'id' => $employee['id'],
            'name' => $employee['name'],
            'days_present' => 0
        ];
    }
}

// Merge both arrays
$allEmployeeAttendance = array_merge($employeeAttendance, $employeesWithoutAttendance);
usort($allEmployeeAttendance, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

// Count working days (excluding weekends) in the selected month
$firstDay = new DateTime($selectedMonth . '-01');
$lastDay = new DateTime($firstDay->format('Y-m-t'));
$interval = new DateInterval('P1D');
$dateRange = new DatePeriod($firstDay, $interval, $lastDay->modify('+1 day'));

$workingDays = 0;
foreach ($dateRange as $date) {
    $dayOfWeek = $date->format('N');
    // Skip weekends (6=Saturday, 7=Sunday)
    if ($dayOfWeek < 6) {
        $workingDays++;
    }
}

// Calculate attendance percentage
foreach ($allEmployeeAttendance as &$employee) {
    $employee['attendance_percentage'] = $workingDays > 0 ? round(($employee['days_present'] / $workingDays) * 100, 1) : 0;
}

// Handle export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_analysis_' . $selectedMonth . '.csv"');
    
    // Create file pointer connected to PHP output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['Employee ID', 'Employee Name', 'Days Present', 'Working Days', 'Attendance Percentage (%)']);
    
    // Output each row of the data
    foreach ($allEmployeeAttendance as $employee) {
        fputcsv($output, [
            $employee['id'],
            $employee['name'],
            $employee['days_present'],
            $workingDays,
            $employee['attendance_percentage']
        ]);
    }
    
    fclose($output);
    exit;
}

// Get department-wise attendance
$stmt = $db->prepare("
    SELECT 
        d.id,
        d.name,
        COUNT(DISTINCT e.id) as total_employees,
        COUNT(DISTINCT CASE WHEN a.date LIKE :month_pattern AND a.check_type = 'check_in' THEN a.employee_id END) as employees_present,
        COUNT(DISTINCT CASE WHEN a.date LIKE :month_pattern AND a.check_type = 'check_in' THEN a.date END) as attendance_days
    FROM 
        departments d
    LEFT JOIN 
        employees e ON d.id = e.department_id
    LEFT JOIN 
        attendance a ON e.id = a.employee_id
    GROUP BY 
        d.id
    ORDER BY 
        d.name
");
$stmt->bindValue(':month_pattern', $selectedMonth . '%');
$stmt->execute();
$departmentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Analytics - HR Attendance System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .analytics-header h2 {
            color: #2c3e50;
            font-size: 18px;
            margin: 0;
            padding: 0;
            border: none;
        }
        
        .analytics-filters {
            display: flex;
            gap: 15px;
        }
        
        .analytics-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .analytics-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
            padding: 20px;
            text-align: center;
        }
        
        .analytics-card h3 {
            font-size: 14px;
            color: #6c757d;
            margin-top: 0;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .analytics-card .value {
            font-size: 30px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .analytics-card .context {
            font-size: 12px;
            color: #6c757d;
        }
        
        .analytics-card:nth-child(2) {
            border-left-color: #2196F3;
        }
        
        .analytics-card:nth-child(3) {
            border-left-color: #FF9800;
        }
        
        .chart-container {
            height: 350px;
            margin-bottom: 30px;
        }
        
        .data-table th.sortable {
            cursor: pointer;
        }
        
        .data-table th.sortable:hover {
            background-color: #eaecef;
        }
        
        .attendance-percentage {
            position: relative;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin-top: 5px;
            width: 100%;
        }
        
        .attendance-percentage-bar {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .attendance-percentage-bar.warning {
            background: linear-gradient(90deg, #FF9800, #FFEB3B);
        }
        
        .attendance-percentage-bar.danger {
            background: linear-gradient(90deg, #F44336, #FF5722);
        }
        
        .export-button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .export-button i {
            margin-right: 8px;
        }
        
        .export-button:hover {
            background-color: #3e8e41;
            text-decoration: none;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            color: #495057;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            font-weight: 600;
        }
        
        .tab:hover:not(.active) {
            color: #6c757d;
            background-color: #f8f9fa;
            border-bottom-color: #dee2e6;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <div class="admin-logo">
                <h2><i class="fas fa-users-cog"></i> HR System</h2>
            </div>
            <ul class="admin-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="employees.php"><i class="fas fa-user-tie"></i> <span>Employees</span></a></li>
                <li><a href="attendance.php"><i class="fas fa-clipboard-check"></i> <span>Attendance</span></a></li>
                <li class="active"><a href="analytics.php"><i class="fas fa-chart-bar"></i> <span>Analytics</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-chart-bar"></i> Attendance Analytics</h1>
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
            
            <div class="analytics-container">
                <div class="analytics-header">
                    <h2><i class="fas fa-calendar-alt"></i> Attendance Analysis for <?php echo htmlspecialchars($monthName); ?></h2>
                    
                    <div class="analytics-filters">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="filter-form" style="margin-bottom: 0;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="month" id="month" name="month" value="<?php echo $selectedMonth; ?>" required pattern="\d{4}-\d{2}" style="width: auto;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <button type="submit" class="btn"><i class="fas fa-filter"></i> Apply</button>
                            </div>
                        </form>
                        
                        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?export=csv&month=' . $selectedMonth; ?>" class="export-button">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                    </div>
                </div>
                
                <div class="analytics-cards">
                    <div class="analytics-card">
                        <h3>Working Days</h3>
                        <div class="value"><?php echo $workingDays; ?></div>
                        <div class="context">Excluding weekends</div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3>Days with Attendance</h3>
                        <div class="value"><?php echo count($daysWithAttendance); ?></div>
                        <div class="context">Days with at least one check-in</div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3>Employees</h3>
                        <div class="value"><?php echo count($employees); ?></div>
                        <div class="context">Total registered employees</div>
                    </div>
                </div>
                
                <div class="tabs">
                    <div class="tab active" data-tab="employee-data"><i class="fas fa-users"></i> Employee Attendance</div>
                    <div class="tab" data-tab="department-data"><i class="fas fa-building"></i> Department Analysis</div>
                    <div class="tab" data-tab="charts"><i class="fas fa-chart-line"></i> Visual Reports</div>
                </div>
                
                <div class="tab-content active" id="employee-data">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="name"><i class="fas fa-user"></i> Employee Name</th>
                                <th class="sortable" data-sort="days"><i class="fas fa-calendar-check"></i> Days Present</th>
                                <th class="sortable" data-sort="percentage"><i class="fas fa-percentage"></i> Attendance %</th>
                                <th><i class="fas fa-chart-bar"></i> Attendance Visual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($allEmployeeAttendance) > 0): ?>
                                <?php foreach($allEmployeeAttendance as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                        <td><?php echo $employee['days_present']; ?> / <?php echo $workingDays; ?></td>
                                        <td><?php echo $employee['attendance_percentage']; ?>%</td>
                                        <td>
                                            <div class="attendance-percentage">
                                                <?php 
                                                $barClass = '';
                                                if ($employee['attendance_percentage'] < 50) {
                                                    $barClass = 'danger';
                                                } elseif ($employee['attendance_percentage'] < 75) {
                                                    $barClass = 'warning';
                                                }
                                                ?>
                                                <div class="attendance-percentage-bar <?php echo $barClass; ?>" style="width: <?php echo $employee['attendance_percentage']; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-data"><i class="fas fa-info-circle"></i> No employee attendance data available for this month</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="tab-content" id="department-data">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-building"></i> Department</th>
                                <th><i class="fas fa-users"></i> Total Employees</th>
                                <th><i class="fas fa-user-check"></i> Employees Present</th>
                                <th><i class="fas fa-calendar-check"></i> Attendance Days</th>
                                <th><i class="fas fa-percentage"></i> Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($departmentAttendance) > 0): ?>
                                <?php foreach($departmentAttendance as $dept): ?>
                                    <?php 
                                    $attendanceRate = $dept['total_employees'] > 0 && $workingDays > 0 ? 
                                        round(($dept['attendance_days'] / ($dept['total_employees'] * $workingDays)) * 100, 1) : 0;
                                    
                                    $barClass = '';
                                    if ($attendanceRate < 50) {
                                        $barClass = 'danger';
                                    } elseif ($attendanceRate < 75) {
                                        $barClass = 'warning';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td><?php echo $dept['total_employees']; ?></td>
                                        <td><?php echo $dept['employees_present']; ?></td>
                                        <td><?php echo $dept['attendance_days']; ?></td>
                                        <td>
                                            <?php echo $attendanceRate; ?>%
                                            <div class="attendance-percentage">
                                                <div class="attendance-percentage-bar <?php echo $barClass; ?>" style="width: <?php echo $attendanceRate; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data"><i class="fas fa-info-circle"></i> No department data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="tab-content" id="charts">
                    <div class="chart-container">
                        <canvas id="employeeAttendanceChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="departmentAttendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Sorting functionality
            const sortableHeaders = document.querySelectorAll('th.sortable');
            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const table = this.closest('table');
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const sortBy = this.getAttribute('data-sort');
                    const currentOrder = this.getAttribute('data-order') || 'asc';
                    
                    // Update sort direction
                    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                    this.setAttribute('data-order', newOrder);
                    
                    // Reset all headers
                    sortableHeaders.forEach(h => {
                        if (h !== this) {
                            h.removeAttribute('data-order');
                        }
                    });
                    
                    // Sort rows
                    rows.sort((a, b) => {
                        let aValue, bValue;
                        
                        if (sortBy === 'name') {
                            aValue = a.cells[0].textContent.trim().toLowerCase();
                            bValue = b.cells[0].textContent.trim().toLowerCase();
                            return newOrder === 'asc' ? 
                                aValue.localeCompare(bValue) : 
                                bValue.localeCompare(aValue);
                        } else if (sortBy === 'days') {
                            aValue = parseInt(a.cells[1].textContent.split('/')[0].trim());
                            bValue = parseInt(b.cells[1].textContent.split('/')[0].trim());
                        } else if (sortBy === 'percentage') {
                            aValue = parseFloat(a.cells[2].textContent);
                            bValue = parseFloat(b.cells[2].textContent);
                        }
                        
                        return newOrder === 'asc' ? aValue - bValue : bValue - aValue;
                    });
                    
                    // Reorder rows
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
            
            // Charts
            // Employee attendance chart
            const employeeCtx = document.getElementById('employeeAttendanceChart').getContext('2d');
            const employeeData = <?php echo json_encode(array_slice($allEmployeeAttendance, 0, 10)); ?>;
            
            new Chart(employeeCtx, {
                type: 'bar',
                data: {
                    labels: employeeData.map(e => e.name),
                    datasets: [{
                        label: 'Days Present',
                        data: employeeData.map(e => e.days_present),
                        backgroundColor: 'rgba(76, 175, 80, 0.7)',
                        borderColor: 'rgba(76, 175, 80, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Top 10 Employee Attendance (Days Present)'
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Days Present'
                            },
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Department attendance chart
            const deptCtx = document.getElementById('departmentAttendanceChart').getContext('2d');
            const deptData = <?php echo json_encode($departmentAttendance); ?>;
            
            new Chart(deptCtx, {
                type: 'doughnut',
                data: {
                    labels: deptData.map(d => d.name),
                    datasets: [{
                        label: 'Attendance Rate',
                        data: deptData.map(d => {
                            return d.total_employees > 0 && <?php echo $workingDays; ?> > 0 ? 
                                Math.round((d.attendance_days / (d.total_employees * <?php echo $workingDays; ?>)) * 100) : 0;
                        }),
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.7)',
                            'rgba(33, 150, 243, 0.7)',
                            'rgba(255, 152, 0, 0.7)',
                            'rgba(156, 39, 176, 0.7)',
                            'rgba(233, 30, 99, 0.7)'
                        ],
                        borderColor: [
                            'rgba(76, 175, 80, 1)',
                            'rgba(33, 150, 243, 1)',
                            'rgba(255, 152, 0, 1)',
                            'rgba(156, 39, 176, 1)',
                            'rgba(233, 30, 99, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Department Attendance Rate (%)'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
