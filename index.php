<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

// Check if a table exists (avoids fatal errors when schema isn't initialized yet)
function tableExists($conn, $tableName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $query = "SHOW TABLES LIKE '$tableName'";
    $result = mysqli_query($conn, $query);
    return ($result && mysqli_num_rows($result) > 0);
}

// Check if company is already setup
$company_exists = false;
if (tableExists($conn, 'company_details')) {
    try {
        $check_query = "SELECT COUNT(*) as count FROM company_details";
        $result = mysqli_query($conn, $check_query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $company_exists = isset($row['count']) && ((int)$row['count'] > 0);
        }
    } catch (mysqli_sql_exception $e) {
        $company_exists = false;
    }
}

// If company already exists, redirect to login
if ($company_exists) {
    header('Location: login.php');
    exit();
}

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
        // Hash password
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        
        // SIMPLIFIED: Insert company details first
        $company_query = "INSERT INTO company_details 
            (company_name, admin_username, admin_password, internship_duration, office_timing, min_score_for_job) 
            VALUES ('$company_name', '$admin_username', '$hashed_password', '$internship_duration', '$office_timing', '$min_score_for_job')";
        
        if (mysqli_query($conn, $company_query)) {
            // Create default domain WITHOUT created_by for now
            $domain_query = "INSERT INTO domains (domain_name, description) VALUES ('General', 'Default domain')";
            if (mysqli_query($conn, $domain_query)) {
                $domain_id = mysqli_insert_id($conn);
                
                // Create admin user
                $admin_query = "INSERT INTO users 
                    (username, password, email, full_name, role, domain_id, is_active) 
                    VALUES ('$admin_username', '$hashed_password', 'admin@$company_name.com', 'Administrator', 'admin', $domain_id, TRUE)";
                
                if (mysqli_query($conn, $admin_query)) {
                    $admin_id = mysqli_insert_id($conn);
                    
                    // NOW update domain with created_by
                    $update_domain = "UPDATE domains SET created_by = $admin_id WHERE id = $domain_id";
                    mysqli_query($conn, $update_domain);
                    
                    $success = 'Company setup successful! Redirecting to login...';
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 2000);
                    </script>";
                } else {
                    $error = 'Failed to create admin user: ' . mysqli_error($conn);
                }
            } else {
                $error = 'Failed to create domain: ' . mysqli_error($conn);
            }
        } else {
            $error = 'Failed to save company details: ' . mysqli_error($conn);
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
