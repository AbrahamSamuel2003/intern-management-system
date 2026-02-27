<?php
// Start session and database connection
session_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'imsjr';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if user is logged in and is intern
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'intern') {
    header('Location: ../login.php');
    exit();
}

$intern_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = '';
$error = '';

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// If task ID is provided, get task details
if ($task_id > 0) {
    $task_query = "
        SELECT t.*, u.full_name as assigned_by_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_by = u.id
        WHERE t.id = $task_id AND t.assigned_to = $intern_id AND t.status = 'pending'
    ";

    $task_result = mysqli_query($conn, $task_query);
    $task = mysqli_fetch_assoc($task_result);
    
    // If task doesn't exist or not assigned to this intern
    if (!$task) {
        header('Location: my_tasks.php?error=invalid_task');
        exit();
    }
}

// Get pending tasks for selection (if no specific task is selected)
$pending_tasks_query = "
    SELECT t.id, t.title, t.deadline, u.full_name as assigned_by_name,
           DATEDIFF(t.deadline, CURDATE()) as days_left
    FROM tasks t
    LEFT JOIN users u ON t.assigned_by = u.id
    WHERE t.assigned_to = $intern_id AND t.status = 'pending'
    ORDER BY 
        CASE 
            WHEN t.deadline < CURDATE() THEN 0
            ELSE 1
        END,
        t.deadline ASC
";

$pending_tasks_result = mysqli_query($conn, $pending_tasks_query);
$has_pending_tasks = mysqli_num_rows($pending_tasks_result) > 0;

// Handle form submission (only if task ID is provided)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $task_id > 0 && isset($task)) {
    $submission_notes = mysqli_real_escape_string($conn, $_POST['submission_notes'] ?? '');
    $uploaded_file = null;
    
    // Handle file upload
    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] == 0) {
        $allowed_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'image/jpeg',
            'image/png',
            'application/zip',
            'application/x-rar-compressed'
        ];
        
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $file_type = $_FILES['task_file']['type'];
        $file_size = $_FILES['task_file']['size'];
        
        if (in_array($file_type, $allowed_types)) {
            if ($file_size <= $max_size) {
                $file_ext = pathinfo($_FILES['task_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'task_' . $task_id . '_' . time() . '.' . $file_ext;
                
                // FIX: Create directory step by step
                $base_dir = $_SERVER['DOCUMENT_ROOT'] . '/imsjr/';
                
                // Check if assets directory exists
                $assets_dir = $base_dir . 'assets/';
                if (!is_dir($assets_dir)) {
                    if (!mkdir($assets_dir, 0777, true)) {
                        $error = 'Failed to create assets directory.';
                    }
                }
                
                // Check if uploads directory exists
                $uploads_dir = $assets_dir . 'uploads/';
                if (!is_dir($uploads_dir) && empty($error)) {
                    if (!mkdir($uploads_dir, 0777, true)) {
                        $error = 'Failed to create uploads directory.';
                    }
                }
                
                // Check if tasks directory exists
                $tasks_dir = $uploads_dir . 'tasks/';
                if (!is_dir($tasks_dir) && empty($error)) {
                    if (!mkdir($tasks_dir, 0777, true)) {
                        $error = 'Failed to create tasks directory.';
                    }
                }
                
                $upload_path = $tasks_dir . $file_name;
                
                if (empty($error) && move_uploaded_file($_FILES['task_file']['tmp_name'], $upload_path)) {
                    $uploaded_file = $file_name;
                    
                    // Store relative path in database
                    $db_file_path = 'task_' . $task_id . '_' . time() . '.' . $file_ext;
                } else if (empty($error)) {
                    $error = 'Failed to upload file. Please try again.';
                    error_log('Upload error: ' . print_r(error_get_last(), true));
                }
            } else {
                $error = 'File size exceeds 10MB limit.';
            }
        } else {
            $error = 'Invalid file type. Allowed: PDF, DOC, DOCX, TXT, JPEG, PNG, ZIP, RAR';
        }
    } else {
        // Check if file upload error occurred
        if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] != 0 && $_FILES['task_file']['error'] != 4) {
            $upload_errors = [
                1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
                3 => 'The uploaded file was only partially uploaded',
                6 => 'Missing a temporary folder',
                7 => 'Failed to write file to disk',
                8 => 'A PHP extension stopped the file upload'
            ];
            $error = 'File upload error: ' . ($upload_errors[$_FILES['task_file']['error']] ?? 'Unknown error');
        } elseif (!isset($_FILES['task_file']) || $_FILES['task_file']['error'] == 4) {
            $error = 'Please select a file to upload.';
        }
    }
    
    if (empty($error) && isset($_FILES['task_file']) && $_FILES['task_file']['error'] == 0) {
    // Update task status using prepared statement
    $update_query = "
        UPDATE tasks 
        SET status = 'submitted',
            submitted_date = CURDATE(),
            submitted_file = ?,
            last_updated = NOW()
        WHERE id = ? AND assigned_to = ?
    ";
    
    $stmt = mysqli_prepare($conn, $update_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sii", $uploaded_file, $task_id, $intern_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Create notification message for team lead using prepared statement
            $intern_name = $_SESSION['full_name'];
            $message = "Task '" . $task['title'] . "' has been submitted by " . $intern_name . ".";
            if ($uploaded_file) {
                $message .= " A file has been uploaded.";
            }
            if (!empty($submission_notes)) {
                $message .= " Notes: " . $submission_notes;
            }
            
            $message_query = "
                INSERT INTO messages (sender_id, receiver_id, message, created_at)
                VALUES (?, ?, ?, NOW())
            ";
            
            $stmt_msg = mysqli_prepare($conn, $message_query);
            if ($stmt_msg) {
                mysqli_stmt_bind_param($stmt_msg, "iis", $intern_id, $task['assigned_by'], $message);
                mysqli_stmt_execute($stmt_msg);
                mysqli_stmt_close($stmt_msg);
            }
            
            mysqli_stmt_close($stmt);
            
            $success = 'Task submitted successfully!';
            
            // Redirect after 2 seconds
            echo '<script>
                setTimeout(function() {
                    window.location.href = "my_tasks.php";
                }, 2000);
            </script>';
        } else {
            $error = 'Failed to submit task. Please try again.';
        }
    } else {
        $error = 'Database error. Please try again.';
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Submit Task</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background: linear-gradient(180deg, #1a237e 10%, #283593 100%);
            color: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Sidebar Header */
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .company-logo {
            font-size: 20px;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        /* Sidebar Menu */
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        /* User Profile in Sidebar */
        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: rgba(0,0,0,0.1);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a237e;
            font-weight: bold;
            margin-right: 10px;
        }
        
        /* Top Navigation Bar */
        .navbar-top {
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        
        /* Task Info Card */
        .task-info-card {
            border-left: 4px solid #4e73df;
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        /* Form Styles */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #d1d3e2;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        /* File Upload */
        .file-upload {
            border: 2px dashed #d1d3e2;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            background: #f8f9fc;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: #4e73df;
            background: #f0f3ff;
        }
        
        .file-upload.dragover {
            border-color: #1cc88a;
            background: #f0fff4;
        }
        
        /* Task Selection Card */
        .task-selection-card {
            border: 2px dashed #d1d3e2;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f8f9fc;
            margin-bottom: 20px;
        }
        
        .task-item {
            border-left: 4px solid;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .task-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .task-overdue { border-left-color: #e74a3b; }
        .task-urgent { border-left-color: #f6c23e; }
        .task-normal { border-left-color: #36b9cc; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Deadline Warning */
        .deadline-warning {
            background: linear-gradient(45deg, #f6c23e, #dda20a);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .deadline-danger {
            background: linear-gradient(45deg, #e74a3b, #be2617);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* No Task Message */
        .no-task-message {
            text-align: center;
            padding: 50px 20px;
        }
        
        /* Alternative upload option */
        .alt-upload {
            margin-top: 15px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="company-logo">
                <i class="fas fa-graduation-cap me-2"></i><?php echo $company_name; ?>
            </a>
            <small class="text-white-50 d-block mt-2">Intern Portal</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="my_tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>My Tasks
            </a>
            
            <a href="submit_task.php" class="nav-link active">
                <i class="fas fa-paper-plane"></i>Submit Task
            </a>
            
            <a href="view_feedback.php" class="nav-link">
                <i class="fas fa-comment-dots"></i>Feedback
            </a>
            
            <a href="messages.php" class="nav-link">
                <i class="fas fa-envelope"></i>Messages
            </a>
            
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user"></i>Profile
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-white-50">Intern</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation Bar -->
        <nav class="navbar-top">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <h4 class="mb-0">Submit Task</h4>
                        <small class="text-muted">Submit your completed task for review</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <a href="my_tasks.php" class="btn btn-outline-dark btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Tasks
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Check if task is selected -->
        <?php if($task_id > 0 && isset($task)): ?>
        <!-- Task Information -->
        <div class="task-info-card">
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-2"><?php echo htmlspecialchars($task['title']); ?></h4>
                    <p class="text-muted mb-3">
                        <i class="fas fa-user-tie me-1"></i>Assigned by: <?php echo htmlspecialchars($task['assigned_by_name']); ?>
                    </p>
                    
                    <?php if(!empty($task['description'])): ?>
                    <div class="mb-3">
                        <h6>Description:</h6>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($task['instructions'])): ?>
                    <div class="mb-3">
                        <h6>Instructions:</h6>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($task['instructions'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="mb-3">Task Details</h6>
                            <div class="mb-2">
                                <small class="text-muted d-block">Assigned Date</small>
                                <strong><?php echo date('F j, Y', strtotime($task['assigned_date'])); ?></strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted d-block">Deadline</small>
                                <strong><?php echo date('F j, Y', strtotime($task['deadline'])); ?></strong>
                            </div>
                            <div>
                                <?php 
                                $days_left = floor((strtotime($task['deadline']) - time()) / (60 * 60 * 24));
                                if($days_left < 0): ?>
                                <div class="deadline-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Overdue by <?php echo abs($days_left); ?> days
                                </div>
                                <?php elseif($days_left < 3): ?>
                                <div class="deadline-warning">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo $days_left; ?> days left
                                </div>
                                <?php else: ?>
                                <div class="text-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $days_left; ?> days left
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submission Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Submit Your Work</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="submitForm">
                            <!-- File Upload -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Upload Completed Work *</label>
                                <div class="file-upload" id="fileUploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <h5>Drag & Drop your file here</h5>
                                    <p class="text-muted mb-3">or click to browse</p>
                                    <input type="file" name="task_file" id="taskFile" class="d-none" required>
                                    <div class="selected-file" id="selectedFile" style="display: none;">
                                        <i class="fas fa-file text-primary me-2"></i>
                                        <span id="fileName"></span>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFile()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Max file size: 10MB. Allowed: PDF, DOC, DOCX, TXT, JPEG, PNG, ZIP, RAR
                                    </small>
                                </div>
                                
                                <!-- Alternative upload method if directory creation fails -->
                                <div class="alt-upload" id="altUpload" style="display: none;">
                                    <h6><i class="fas fa-info-circle me-2"></i>Alternative Upload</h6>
                                    <p class="mb-2">If drag & drop doesn't work, use the browse button below:</p>
                                    <input type="file" name="task_file_alt" id="taskFileAlt" class="form-control">
                                </div>
                            </div>
                            
                            <!-- Submission Notes -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Submission Notes</label>
                                <textarea class="form-control" name="submission_notes" rows="4" placeholder="Add any notes or comments about your submission..."></textarea>
                                <small class="text-muted">Optional: Explain your approach, challenges faced, or additional information.</small>
                            </div>
                            
                            <!-- Confirmation Checkbox -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmSubmission" required>
                                    <label class="form-check-label" for="confirmSubmission">
                                        I confirm that this is my final submission and I'm ready for review.
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="submit_task.php" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-paper-plane me-1"></i>Submit Task
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Important Notes -->
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle me-2"></i>Important Notes:</h6>
                    <ul class="mb-0">
                        <li>Once submitted, you cannot edit your submission</li>
                        <li>Your team lead will review and provide feedback</li>
                        <li>Make sure your file contains all required work</li>
                        <li>Submission time will be recorded automatically</li>
                        <li>If upload fails, try using the alternative browse button</li>
                    </ul>
                </div>
                
                <!-- Manual Directory Creation Instructions -->
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>If Upload Fails:</h6>
                    <p class="mb-2">Please create the following directories manually:</p>
                    <code>C:\xampp\htdocs\imsjr\assets\uploads\tasks\</code>
                    <p class="mt-2 mb-0">Make sure the directories have write permissions (777 on Linux/Mac or full control on Windows).</p>
                </div>
            </div>
        </div>
        
        <?php elseif($has_pending_tasks): ?>
        <!-- Task Selection Page -->
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-list-check me-2"></i>Select Task to Submit</h6>
                    </div>
                    <div class="card-body">
                        <div class="task-selection-card mb-4">
                            <i class="fas fa-paper-plane fa-3x text-primary mb-3"></i>
                            <h4>Select a Task to Submit</h4>
                            <p class="text-muted">Choose from your pending tasks below to submit your work.</p>
                        </div>
                        
                        <h5 class="mb-3">Your Pending Tasks</h5>
                        <div class="row">
                            <?php 
                            mysqli_data_seek($pending_tasks_result, 0); // Reset pointer
                            while($pending_task = mysqli_fetch_assoc($pending_tasks_result)): 
                                // Determine task class based on urgency
                                if($pending_task['days_left'] < 0) {
                                    $task_class = 'task-overdue';
                                    $urgency_text = 'Overdue';
                                    $urgency_class = 'text-danger';
                                } elseif($pending_task['days_left'] < 3) {
                                    $task_class = 'task-urgent';
                                    $urgency_text = 'Urgent';
                                    $urgency_class = 'text-warning';
                                } else {
                                    $task_class = 'task-normal';
                                    $urgency_text = 'Normal';
                                    $urgency_class = 'text-info';
                                }
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="task-item <?php echo $task_class; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($pending_task['title']); ?></h6>
                                        <span class="badge <?php echo $urgency_class; ?>"><?php echo $urgency_text; ?></span>
                                    </div>
                                    <p class="text-muted mb-2">
                                        <small><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($pending_task['assigned_by_name']); ?></small>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php 
                                            if($pending_task['days_left'] > 0) {
                                                echo $pending_task['days_left'] . ' days left';
                                            } elseif($pending_task['days_left'] < 0) {
                                                echo abs($pending_task['days_left']) . ' days overdue';
                                            } else {
                                                echo 'Due today';
                                            }
                                            ?>
                                        </small>
                                        <a href="submit_task.php?id=<?php echo $pending_task['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>Submit
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="my_tasks.php" class="btn btn-outline-primary">
                                <i class="fas fa-tasks me-2"></i>View All Tasks
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- No Pending Tasks Message -->
        <div class="card">
            <div class="card-body no-task-message">
                <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                <h3>No Pending Tasks</h3>
                <p class="text-muted mb-4">
                    You don't have any pending tasks to submit. All your tasks are either submitted or completed.
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="my_tasks.php" class="btn btn-primary">
                        <i class="fas fa-tasks me-2"></i>View All Tasks
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File upload functionality
        const fileInput = document.getElementById('taskFile');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const selectedFile = document.getElementById('selectedFile');
        const fileName = document.getElementById('fileName');
        const altUpload = document.getElementById('altUpload');
        
        // Show alternative upload method after 5 seconds if main method fails
        setTimeout(() => {
            if (altUpload) altUpload.style.display = 'block';
        }, 5000);
        
        if (fileUploadArea) {
            fileUploadArea.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    fileName.textContent = file.name;
                    selectedFile.style.display = 'flex';
                    selectedFile.style.alignItems = 'center';
                    selectedFile.style.justifyContent = 'center';
                }
            });
        }
        
        function clearFile() {
            if (fileInput) {
                fileInput.value = '';
                selectedFile.style.display = 'none';
            }
        }
        
        // Drag and drop functionality
        if (fileUploadArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                fileUploadArea.classList.add('dragover');
            }
            
            function unhighlight() {
                fileUploadArea.classList.remove('dragover');
            }
            
            fileUploadArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                
                if (files.length > 0) {
                    fileName.textContent = files[0].name;
                    selectedFile.style.display = 'flex';
                    selectedFile.style.alignItems = 'center';
                    selectedFile.style.justifyContent = 'center';
                }
            }
        }
        
        // Form submission validation
        const submitForm = document.getElementById('submitForm');
        if (submitForm) {
            submitForm.addEventListener('submit', function(e) {
                const file = fileInput.files[0];
                const confirmCheck = document.getElementById('confirmSubmission');
                
                if (!file) {
                    e.preventDefault();
                    alert('Please select a file to upload.');
                    return false;
                }
                
                if (!confirmCheck.checked) {
                    e.preventDefault();
                    alert('Please confirm that this is your final submission.');
                    return false;
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
                submitBtn.disabled = true;
            });
        }
        
        // Mobile menu toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                if (sidebar.style.width === '250px') {
                    sidebar.style.width = '0';
                    mainContent.style.marginLeft = '0';
                } else {
                    sidebar.style.width = '250px';
                    mainContent.style.marginLeft = '250px';
                }
            }
        }
        
        // Add hamburger menu for mobile
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth <= 768) {
                const navbar = document.querySelector('.navbar-top');
                const hamburger = document.createElement('button');
                hamburger.className = 'btn btn-dark me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                if (navbar && navbar.querySelector('.d-flex')) {
                    navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
                }
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>