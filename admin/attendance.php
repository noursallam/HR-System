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
$selectedEmployee = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

// Validate month format (YYYY-MM)
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $error = "Invalid month format. Using current month instead.";
    $selectedMonth = date('Y-m');
}

// Handle export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_' . $selectedMonth . '.csv"');
    
    // Create file pointer connected to PHP output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['Employee ID', 'Employee Name', 'Date', 'Check In Time', 'Check Out Time', 'IP Address (Check In)', 'IP Address (Check Out)']);
    
    // Build query based on filters
    $sql = "
        SELECT 
            e.id AS employee_id,
            e.name AS employee_name,
            a_in.date,
            a_in.timestamp AS check_in_time,
            a_out.timestamp AS check_out_time,
            a_in.ip_address AS check_in_ip,
            a_out.ip_address AS check_out_ip
        FROM 
            employees e
        LEFT JOIN 
            (SELECT * FROM attendance WHERE check_type = 'check_in' AND date LIKE :month_pattern) a_in 
            ON e.id = a_in.employee_id
        LEFT JOIN 
            (SELECT * FROM attendance WHERE check_type = 'check_out' AND date LIKE :month_pattern) a_out 
            ON e.id = a_out.employee_id AND a_in.date = a_out.date
        WHERE 
            a_in.id IS NOT NULL
    ";
    
    $params = [':month_pattern' => $selectedMonth . '%'];
    
    // Add employee filter if selected
    if ($selectedEmployee > 0) {
        $sql .= " AND e.id = :employee_id";
        $params[':employee_id'] = $selectedEmployee;
    }
    
    $sql .= " ORDER BY a_in.date DESC, e.name";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    // Output each row of the data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['employee_id'],
            $row['employee_name'],
            $row['date'],
            $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : 'N/A',
            $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : 'N/A',
            $row['check_in_ip'],
            $row['check_out_ip'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
    exit;
}

// Get attendance records with employee details
$sql = "
    SELECT 
        e.id AS employee_id,
        e.name AS employee_name,
        a.id,
        a.check_type,
        a.date,
        a.timestamp,
        a.ip_address,
        a.photo_path
    FROM 
        attendance a
    JOIN 
        employees e ON a.employee_id = e.id
    WHERE 
        a.date LIKE :month_pattern
";

$params = [':month_pattern' => $selectedMonth . '%'];

// Add employee filter if selected
if ($selectedEmployee > 0) {
    $sql .= " AND e.id = :employee_id";
    $params[':employee_id'] = $selectedEmployee;
}

$sql .= " ORDER BY a.date DESC, a.timestamp DESC";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique dates for the selected month to organize data
$dates = [];
foreach ($attendanceRecords as $record) {
    if (!in_array($record['date'], $dates)) {
        $dates[] = $record['date'];
    }
}

// Organize data by employee and date
$organizedData = [];
foreach ($attendanceRecords as $record) {
    $employeeId = $record['employee_id'];
    $date = $record['date'];
    
    if (!isset($organizedData[$date])) {
        $organizedData[$date] = [];
    }
    
    if (!isset($organizedData[$date][$employeeId])) {
        $organizedData[$date][$employeeId] = [
            'employee_name' => $record['employee_name'],
            'check_in' => null,
            'check_out' => null
        ];
    }
    
    if ($record['check_type'] == 'check_in') {
        $organizedData[$date][$employeeId]['check_in'] = [
            'time' => $record['timestamp'],
            'ip' => $record['ip_address'],
            'photo' => $record['photo_path']
        ];
    } else {
        $organizedData[$date][$employeeId]['check_out'] = [
            'time' => $record['timestamp'],
            'ip' => $record['ip_address'],
            'photo' => $record['photo_path']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - HR Attendance System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .filter-form {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .export-link {
            margin-left: auto;
        }
        .attendance-photo {
            cursor: pointer;
            max-width: 50px;
            max-height: 50px;
            border-radius: 5px;
            transition: transform 0.2s;
        }
        .attendance-photo:hover {
            transform: scale(1.1);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        .modal-content {
            margin: 5% auto;
            max-width: 90%;
            max-height: 90%;
        }
        .modal img {
            display: block;
            margin: 0 auto;
            max-width: 100%;
            max-height: 80vh;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .close-modal {
            color: white;
            font-size: 30px;
            font-weight: bold;
            position: absolute;
            right: 25px;
            top: 10px;
            cursor: pointer;
            transition: color 0.3s;
        }
        .close-modal:hover {
            color: #f44336;
        }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-style: italic;
        }
        .date-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            padding: 20px;
        }
        .date-section h3 {
            color: #2c3e50;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .not-available {
            color: #dc3545;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="admin-container">        <div class="admin-sidebar">
            <div class="admin-logo">
                <h2><i class="fas fa-users-cog"></i> HR System</h2>
            </div>
            <ul class="admin-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="employees.php"><i class="fas fa-user-tie"></i> <span>Employees</span></a></li>
                <li class="active"><a href="attendance.php"><i class="fas fa-clipboard-check"></i> <span>Attendance</span></a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> <span>Analytics</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-clipboard-check"></i> Attendance Records</h1>
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
            
            <div class="filter-section">
                <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="filter-form">
                    <div class="form-group">
                        <label for="month"><i class="far fa-calendar-alt"></i> Month:</label>
                        <input type="month" id="month" name="month" value="<?php echo $selectedMonth; ?>" required pattern="\d{4}-\d{2}">
                    </div>
                    <div class="form-group">
                        <label for="employee_id"><i class="fas fa-user"></i> Employee:</label>
                        <select id="employee_id" name="employee_id">
                            <option value="0">All Employees</option>
                            <?php foreach($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo ($selectedEmployee == $employee['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                    <div class="export-link">
                        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?export=csv&month=' . $selectedMonth . '&employee_id=' . $selectedEmployee; ?>" class="btn"><i class="fas fa-file-csv"></i> Export CSV</a>
                    </div>
                </form>
            </div>
            
            <div class="attendance-list">
                <?php if (count($dates) > 0): ?>
                    <?php foreach($dates as $date): ?>
                        <div class="date-section">
                            <h3><i class="far fa-calendar-day"></i> <?php echo date('l, F d, Y', strtotime($date)); ?></h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user"></i> Employee</th>
                                        <th><i class="fas fa-sign-in-alt"></i> Check In Time</th>
                                        <th><i class="fas fa-camera"></i> Check In Photo</th>
                                        <th><i class="fas fa-sign-out-alt"></i> Check Out Time</th>
                                        <th><i class="fas fa-camera"></i> Check Out Photo</th>
                                        <th><i class="fas fa-network-wired"></i> IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($organizedData[$date] as $employeeId => $data): ?>
                                        <tr>
                                            <td><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($data['employee_name']); ?></td>
                                            <td>
                                                <?php if ($data['check_in']): ?>
                                                    <span style="color: #4CAF50;"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($data['check_in']['time'])); ?></span>
                                                <?php else: ?>
                                                    <span class="not-available"><i class="fas fa-times-circle"></i> N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($data['check_in'] && !empty($data['check_in']['photo']) && file_exists('../' . $data['check_in']['photo'])): ?>
                                                    <img src="../<?php echo $data['check_in']['photo']; ?>" class="attendance-photo" onclick="openPhotoModal('../<?php echo $data['check_in']['photo']; ?>')">
                                                <?php else: ?>
                                                    <span class="not-available"><i class="fas fa-image-slash"></i> N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($data['check_out']): ?>
                                                    <span style="color: #F44336;"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($data['check_out']['time'])); ?></span>
                                                <?php else: ?>
                                                    <span class="not-available"><i class="fas fa-times-circle"></i> N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($data['check_out'] && !empty($data['check_out']['photo']) && file_exists('../' . $data['check_out']['photo'])): ?>
                                                    <img src="../<?php echo $data['check_out']['photo']; ?>" class="attendance-photo" onclick="openPhotoModal('../<?php echo $data['check_out']['photo']; ?>')">
                                                <?php else: ?>
                                                    <span class="not-available"><i class="fas fa-image-slash"></i> N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($data['check_in']): ?>
                                                    <i class="fas fa-network-wired"></i> <?php echo $data['check_in']['ip']; ?>
                                                    <?php if ($data['check_out'] && $data['check_in']['ip'] != $data['check_out']['ip']): ?>
                                                        <br><small><i class="fas fa-sign-out-alt"></i> <?php echo $data['check_out']['ip']; ?></small>
                                                    <?php endif; ?>
                                                <?php elseif ($data['check_out']): ?>
                                                    <i class="fas fa-network-wired"></i> <?php echo $data['check_out']['ip']; ?>
                                                <?php else: ?>
                                                    <span class="not-available"><i class="fas fa-times-circle"></i> N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data"><i class="fas fa-info-circle"></i> No attendance records found for the selected criteria.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="photoModal" class="modal">
        <span class="close-modal" onclick="closePhotoModal()">&times;</span>
        <div class="modal-content">
            <img id="modalImage" src="">
        </div>
    </div>
    
    <script>
        // Photo modal functionality
        function openPhotoModal(photoSrc) {
            document.getElementById('modalImage').src = photoSrc;
            document.getElementById('photoModal').style.display = 'block';
        }
        
        function closePhotoModal() {
            document.getElementById('photoModal').style.display = 'none';
        }
        
        // Close modal when clicking outside the image
        window.onclick = function(event) {
            if (event.target == document.getElementById('photoModal')) {
                closePhotoModal();
            }
        }
    </script>
</body>
</html>
