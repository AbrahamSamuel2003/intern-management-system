<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

// Check if company is already setup
$company_exists = isCompanySetup($conn);

// If company already exists, redirect to login
if ($company_exists) {
    header('Location: login.php');
    exit();
}

// If we are in setup mode, clear any old session data for a fresh start
session_unset();
session_destroy();
session_start();

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $admin_username = mysqli_real_escape_string($conn, $_POST['admin_username']);
    $admin_password = mysqli_real_escape_string($conn, $_POST['admin_password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    $internship_duration = mysqli_real_escape_string($conn, $_POST['internship_duration']);
    $office_timing = mysqli_real_escape_string($conn, $_POST['office_timing']);
    $min_score_for_job = mysqli_real_escape_string($conn, $_POST['min_score_for_job']);
    
    // Validation
    if (empty($company_name) || empty($admin_username) || empty($admin_password)) {
        $error = 'Please fill all required fields';
    } elseif ($admin_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($admin_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // 1. DYNAMIC DATABASE CREATION
        $db_name_clean = 'ims_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $company_name));
        $create_db_query = "CREATE DATABASE IF NOT EXISTS $db_name_clean";
        
        if (mysqli_query($conn, $create_db_query)) {
            // Select the newly created database
            mysqli_select_db($conn, $db_name_clean);
            
            // 2. SCHEMA INJECTION (Create Tables)
            $tables = [
                "CREATE TABLE IF NOT EXISTS company_details (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_name VARCHAR(255) NOT NULL,
                    admin_username VARCHAR(50) NOT NULL,
                    admin_password VARCHAR(255) NOT NULL,
                    internship_duration VARCHAR(100),
                    office_timing VARCHAR(100),
                    min_score_for_job INT DEFAULT 70,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS domains (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    domain_name VARCHAR(100) NOT NULL,
                    description TEXT,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    full_name VARCHAR(100) NOT NULL,
                    role ENUM('admin', 'team_lead', 'intern') NOT NULL,
                    domain_id INT,
                    phone VARCHAR(20),
                    join_date DATE,
                    end_date DATE,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS tasks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    instructions TEXT,
                    assigned_to INT,
                    assigned_by INT,
                    domain_id INT,
                    status ENUM('pending', 'submitted', 'completed', 'not_completed') DEFAULT 'pending',
                    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    deadline DATE,
                    submitted_date TIMESTAMP NULL,
                    submitted_file VARCHAR(255),
                    submission_text TEXT,
                    rating INT DEFAULT 0,
                    feedback TEXT
                )",
                "CREATE TABLE IF NOT EXISTS performance (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    intern_id INT,
                    total_tasks_assigned INT DEFAULT 0,
                    tasks_completed INT DEFAULT 0,
                    tasks_not_completed INT DEFAULT 0,
                    tasks_submitted INT DEFAULT 0,
                    tasks_pending INT DEFAULT 0,
                    on_time_submissions INT DEFAULT 0,
                    performance_score DECIMAL(5,2) DEFAULT 0.00,
                    total_score DECIMAL(5,2) DEFAULT 0.00,
                    attendance_percentage DECIMAL(5,2) DEFAULT 0.00,
                    eligibility ENUM('eligible', 'not_eligible', 'pending') DEFAULT 'pending',
                    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sender_id INT,
                    receiver_id INT,
                    message TEXT,
                    read_status BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )"
            ];

            $success_creating_tables = true;
            foreach ($tables as $sql) {
                if (!mysqli_query($conn, $sql)) {
                    $error = 'Failed to create table: ' . mysqli_error($conn);
                    $success_creating_tables = false;
                    break;
                }
            }

            if ($success_creating_tables) {
                // 3. PERSIST THE DATABASE NAME
                $config_content = "<?php\n// This file is automatically updated by the setup process.\ndefine('DB_NAME', '$db_name_clean');\n?>";
                file_put_contents('config/db_config.php', $config_content);

                // 4. CONTINUE WITH DATA INSERTION
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $company_query = "INSERT INTO company_details 
                    (company_name, admin_username, admin_password, internship_duration, office_timing, min_score_for_job) 
                    VALUES ('$company_name', '$admin_username', '$hashed_password', '$internship_duration', '$office_timing', '$min_score_for_job')";
                
                if (mysqli_query($conn, $company_query)) {
                    // Create admin user without a default domain
                    $admin_query = "INSERT INTO users 
                        (username, password, email, full_name, role, is_active) 
                        VALUES ('$admin_username', '$hashed_password', 'admin@$db_name_clean.com', 'Administrator', 'admin', TRUE)";
                    
                    if (mysqli_query($conn, $admin_query)) {
                        $success = 'Setup successful! Redirecting to login...';
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 2000);
                        </script>";
                    } else {
                        $error = 'Failed to create admin user: ' . mysqli_error($conn);
                    }
                } else {
                    $error = 'Failed to save company details: ' . mysqli_error($conn);
                }
            }
        } else {
            $error = 'Failed to create database: ' . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Setup - Intern Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .setup-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .setup-header h2 {
            color: #333;
            font-weight: 600;
        }
        .setup-header p {
            color: #666;
            margin-top: 10px;
        }
        .form-label {
            font-weight: 500;
            color: #333;
        }
        .btn-submit {
            background: #4e73df;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            width: 100%;
        }
        .btn-submit:hover {
            background: #2e59d9;
        }
        .input-group-text {
            background: #f8f9fc;
            border: 1px solid #d1d3e2;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <h2><i class="fas fa-building me-2"></i>Company Setup</h2>
                <p>First-time configuration for Intern Management System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Company Information</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name *</label>
                            <input type="text" class="form-control" name="company_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Internship Duration</label>
                            <input type="text" class="form-control" name="internship_duration" value="6 months">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Office Timing</label>
                            <input type="text" class="form-control" name="office_timing" value="9:00 AM - 6:00 PM">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="mb-3">Admin Account</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Username *</label>
                            <input type="text" class="form-control" name="admin_username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="admin_password" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Minimum Score for Job (%) *</label>
                            <input type="number" class="form-control" name="min_score_for_job" value="70" min="0" max="100" required>
                            <small class="text-muted">Interns need this score to be eligible for job</small>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I confirm that I am authorized to set up this system
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-submit">
                    <i class="fas fa-rocket me-2"></i>Complete Setup
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
