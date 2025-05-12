<?php
require_once 'config.php';

// Initialize session
startSession();

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process attendance record
    if (isset($_POST['employee_id']) && isset($_POST['check_type']) && isset($_POST['image_data'])) {
        $employee_id = test_input($_POST['employee_id']);
        $check_type = test_input($_POST['check_type']);
        
        // Get client IP
        $ip_address = getClientIP();
        
        // Get current date
        $date = date('Y-m-d');
        
        // Check if this employee already has checked in/out today
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = :employee_id AND date = :date AND check_type = :check_type");
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':check_type', $check_type);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $error = "You've already " . ($check_type == 'check_in' ? 'checked in' : 'checked out') . " today!";
        } else {
            // Check if image data is valid
            if (preg_match('/^data:image\/(\w+);base64,/', $_POST['image_data'], $type)) {
                $image_data = substr($_POST['image_data'], strpos($_POST['image_data'], ',') + 1);
                $image_data = str_replace(' ', '+', $image_data);
                $image_data = base64_decode($image_data);
                
                if ($image_data !== false) {
                    // Create photos directory if it doesn't exist
                    $photos_dir = 'photos';
                    if (!file_exists($photos_dir)) {
                        mkdir($photos_dir, 0755, true);
                    }
                    
                    // Generate file name and path
                    $file_name = $employee_id . '_' . $check_type . '_' . time() . '.jpg';
                    $file_path = $photos_dir . '/' . $file_name;
                    
                    // Save image to file
                    if (file_put_contents($file_path, $image_data)) {
                        // Save attendance record in database
                        try {
                            $stmt = $db->prepare("INSERT INTO attendance (employee_id, check_type, photo_path, ip_address, date) 
                                                VALUES (:employee_id, :check_type, :photo_path, :ip_address, :date)");
                            $stmt->bindParam(':employee_id', $employee_id);
                            $stmt->bindParam(':check_type', $check_type);
                            $stmt->bindParam(':photo_path', $file_path);
                            $stmt->bindParam(':ip_address', $ip_address);
                            $stmt->bindParam(':date', $date);
                            $stmt->execute();
                            
                            $message = "You've successfully " . ($check_type == 'check_in' ? 'checked in' : 'checked out') . "!";
                        } catch (PDOException $e) {
                            $error = "Database error: " . $e->getMessage();
                        }
                    } else {
                        $error = "Failed to save photo.";
                    }
                } else {
                    $error = "Invalid image data.";
                }
            } else {
                $error = "Invalid image format.";
            }
        }
    } else {
        $error = "Missing required data.";
    }
}

// Get all employees from database
$db = getDB();
$stmt = $db->prepare("SELECT id, name, department_id FROM employees ORDER BY name");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Attendance System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #3e8e41;
            --secondary-color: #2196F3;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --border-color: #e9ecef;
            --success-color: #d4edda;
            --success-text: #155724;
            --error-color: #f8d7da;
            --error-text: #721c24;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4eaf5 100%);
            min-height: 100vh;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: white;
            color: var(--text-color);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 20px 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            margin: 0;
            font-size: 28px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        header h1 i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .header-links a {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .header-links a:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            text-decoration: none;
        }
        
        main {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        section.attendance-form {
            max-width: 700px;
            margin: 0 auto;
        }
        
        h2 {
            color: var(--primary-color);
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 24px;
            text-align: center;
            position: relative;
        }
        
        h2:after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -2px;
            width: 80px;
            height: 4px;
            background-color: var(--primary-color);
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .message i {
            font-size: 20px;
            margin-right: 10px;
        }
        
        .success {
            background-color: var(--success-color);
            color: var(--success-text);
        }
        
        .error {
            background-color: var(--error-color);
            color: var(--error-text);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        select, input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: white;
        }
        
        select:focus, input[type="text"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.1);
            outline: none;
        }
        
        .radio-group {
            display: flex;
            gap: 30px;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            justify-content: center;
        }
        
        .radio-option:hover {
            border-color: var(--primary-color);
            background-color: rgba(76, 175, 80, 0.05);
        }
        
        .radio-option input[type="radio"] {
            margin-right: 10px;
            accent-color: var(--primary-color);
        }
        
        .radio-option.check-in {
            border-color: #28a745;
        }
        
        .radio-option.check-in.active {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
        }
        
        .radio-option.check-out {
            border-color: #dc3545;
        }
        
        .radio-option.check-out.active {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
        }
        
        #camera-container {
            width: 100%;
            height: 300px;
            border: 2px dashed #ced4da;
            margin: 20px auto;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            transition: border-color 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
        }
        
        #camera-container.ready {
            border-color: var(--primary-color);
            border-style: solid;
        }
        
        #video, #canvas {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        #photo-preview {
            display: none;
            margin: 20px auto;
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            justify-content: center;
        }
        
        button {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        button i {
            margin-right: 8px;
        }
        
        #capture-btn {
            background-color: var(--secondary-color);
            color: white;
        }
        
        #capture-btn:hover {
            background-color: #0b7dda;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        #retake-btn {
            background-color: #6c757d;
            color: white;
        }
        
        #retake-btn:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .form-actions {
            margin-top: 30px;
            text-align: center;
        }
        
        #submit-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 40px;
            font-size: 18px;
            min-width: 200px;
        }
        
        #submit-btn:hover:not(:disabled) {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        #submit-btn:disabled {
            background-color: #ced4da;
            cursor: not-allowed;
            color: #6c757d;
        }
        
        footer {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        /* Camera status indicators */
        .camera-status {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
        }
        
        .camera-status i {
            margin-right: 5px;
        }
        
        .pulse-animation {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        
        /* Time display */
        .current-time {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--primary-color);
        }
        
        .current-date {
            text-align: center;
            font-size: 16px;
            color: #6c757d;
            margin-top: -20px;
            margin-bottom: 30px;
        }
        
        /* Loading animation for submit */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
            
            header h1 {
                margin-bottom: 15px;
                justify-content: center;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
            
            #camera-container {
                height: 250px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            #submit-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-user-clock"></i> HR Attendance System</h1>
            <div class="header-links">
                <a href="admin/login.php"><i class="fas fa-user-shield"></i> Admin Login</a>
            </div>
        </header>
        
        <main>
            <section class="attendance-form">
                <h2><i class="fas fa-clipboard-check"></i> Employee Attendance</h2>
                
                <div class="current-time" id="current-time"></div>
                <div class="current-date" id="current-date"></div>
                
                <?php if (!empty($message)): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <form id="attendance-form" method="post" action="">
                    <div class="form-group">
                        <label for="employee"><i class="fas fa-user"></i> Select Your Name:</label>
                        <select id="employee" name="employee_id" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clipboard-list"></i> Check Type:</label>
                        <div class="radio-group">
                            <label class="radio-option check-in active">
                                <input type="radio" name="check_type" value="check_in" checked>
                                <i class="fas fa-sign-in-alt"></i> Check In
                            </label>
                            <label class="radio-option check-out">
                                <input type="radio" name="check_type" value="check_out">
                                <i class="fas fa-sign-out-alt"></i> Check Out
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Take Photo:</label>
                        <div id="camera-container">
                            <div class="camera-status"><i class="fas fa-circle pulse-animation"></i> Camera initializing...</div>
                            <video id="video" autoplay playsinline></video>
                            <canvas id="canvas" style="display:none;"></canvas>
                        </div>
                        <img id="photo-preview" src="" alt="Photo Preview">
                        <input type="hidden" id="image-data" name="image_data">
                        <div class="button-group">
                            <button type="button" id="capture-btn"><i class="fas fa-camera"></i> Capture Photo</button>
                            <button type="button" id="retake-btn" style="display:none;"><i class="fas fa-redo"></i> Retake Photo</button>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="submit-btn" disabled><i class="fas fa-check"></i> Submit Attendance</button>
                    </div>
                </form>
            </section>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> HR Attendance System. All rights reserved.</p>
        </footer>
    </div>
    
    <script>
        // Display current time and date
        function updateClock() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            const options = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
            timeElement.innerHTML = now.toLocaleTimeString('en-US', options);
            
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateElement.innerHTML = now.toLocaleDateString('en-US', dateOptions);
        }
        
        setInterval(updateClock, 1000);
        updateClock();
        
        // Toggle radio options
        const radioOptions = document.querySelectorAll('.radio-option');
        const radioInputs = document.querySelectorAll('.radio-option input');
        
        radioOptions.forEach(option => {
            option.addEventListener('click', function() {
                radioOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
        
        // Camera functionality
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const captureBtn = document.getElementById('capture-btn');
        const retakeBtn = document.getElementById('retake-btn');
        const photoPreview = document.getElementById('photo-preview');
        const imageDataInput = document.getElementById('image-data');
        const submitBtn = document.getElementById('submit-btn');
        const cameraContainer = document.getElementById('camera-container');
        const cameraStatus = document.querySelector('.camera-status');
        
        // Initialize camera
        async function initCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                video.srcObject = stream;
                
                cameraStatus.innerHTML = '<i class="fas fa-circle" style="color: #4CAF50;"></i> Camera ready';
                cameraStatus.classList.remove('pulse-animation');
                cameraContainer.classList.add('ready');
                
                captureBtn.disabled = false;
            } catch (err) {
                cameraStatus.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Camera error';
                console.error("Error accessing camera: ", err);
            }
        }
        
        // Initialize when the page loads
        window.addEventListener('load', initCamera);
        
        // Capture photo
        captureBtn.addEventListener('click', function() {
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = canvas.toDataURL('image/jpeg');
            photoPreview.src = imageData;
            imageDataInput.value = imageData;
            
            video.style.display = 'none';
            photoPreview.style.display = 'block';
            captureBtn.style.display = 'none';
            retakeBtn.style.display = 'block';
            
            submitBtn.disabled = false;
        });
        
        // Retake photo
        retakeBtn.addEventListener('click', function() {
            video.style.display = 'block';
            photoPreview.style.display = 'none';
            captureBtn.style.display = 'block';
            retakeBtn.style.display = 'none';
            imageDataInput.value = '';
            submitBtn.disabled = true;
        });
        
        // Handle form submission
        document.getElementById('attendance-form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.innerHTML = '<span class="loading"></span> Processing...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>