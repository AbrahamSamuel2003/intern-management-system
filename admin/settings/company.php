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


// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get company details
$company_query = "SELECT * FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company = mysqli_fetch_assoc($company_result);

$company_name = $company['company_name'] ?? 'Intern Management System';
$internship_duration = $company['internship_duration'] ?? '6 months';
$office_timing = $company['office_timing'] ?? '9:00 AM - 6:00 PM';
$min_score_for_job = $company['min_score_for_job'] ?? 70;

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $new_internship_duration = mysqli_real_escape_string($conn, $_POST['internship_duration']);
    $new_office_timing = mysqli_real_escape_string($conn, $_POST['office_timing']);
    $new_min_score = intval($_POST['min_score_for_job']);
    
    // Validation
    if (empty($new_company_name)) {
        $error = 'Company name is required';
    } elseif ($new_min_score < 0 || $new_min_score > 100) {
        $error = 'Minimum score must be between 0 and 100';
    } else {
        // Update company details
        $update_query = "
            UPDATE company_details SET 
            company_name = '$new_company_name',
            internship_duration = '$new_internship_duration',
            office_timing = '$new_office_timing',
            min_score_for_job = $new_min_score,
            updated_at = NOW()
            WHERE id = {$company['id']}
        ";
        
        if (mysqli_query($conn, $update_query)) {
            $success = 'Company settings updated successfully!';
            
            // Update the local variables
            $company_name = $new_company_name;
            $internship_duration = $new_internship_duration;
            $office_timing = $new_office_timing;
            $min_score_for_job = $new_min_score;
            
            // Update company in $company array
            $company['company_name'] = $new_company_name;
            $company['internship_duration'] = $new_internship_duration;
            $company['office_timing'] = $new_office_timing;
            $company['min_score_for_job'] = $new_min_score;
        } else {
            $error = 'Error updating settings: ' . mysqli_error($conn);
        }

    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Company Settings</title>
    
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
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
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
            color: #4e73df;
            font-weight: bold;
            margin-right: 10px;
        }
        
        /* Top Navigation */
        .navbar-top {
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        /* Form Styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            border: 2px solid #eef2f7;
            border-radius: 10px;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 2px solid #eef2f7;
        }
        
        /* Settings Card */
        .settings-card {
            border-left: 5px solid #4e73df;
        }
        
        /* Current Settings Display */
        .current-settings {
            background-color: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .setting-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="company-logo">
                <i class="fas fa-graduation-cap me-2"></i><?php echo $company_name; ?>
            </a>
            <small class="text-white-50 d-block mt-2">Admin Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="../domains/manage.php" class="nav-link">
                <i class="fas fa-layer-group"></i>Domains
            </a>
            
            <a href="../teamlead/manage.php" class="nav-link">
                <i class="fas fa-user-tie"></i>Team Leads
            </a>
            
            <a href="../intern/manage.php" class="nav-link">
                <i class="fas fa-user-graduate"></i>Interns
            </a>
            
            <a href="../performance/overview.php" class="nav-link">
                <i class="fas fa-chart-line"></i>Performance
            </a>
            
            <a href="company.php" class="nav-link active">
                <i class="fas fa-cog"></i>Settings
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-white-50">Administrator</small>
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
                        <h4 class="mb-0">Company Settings</h4>
                        <small class="text-muted">Configure system-wide settings</small>
                    </div>
                    <div>
                        <a href="../dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>

                    </div>
                </div>
            </div>
        </nav>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Settings Display -->
        <div class="current-settings mb-4">
            <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Current Settings</h5>
            <div class="setting-item">
                <div>
                    <small class="text-muted d-block">Company Name</small>
                    <strong><?php echo htmlspecialchars($company_name); ?></strong>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block">Updated</small>
                    <small><?php echo date('d M Y, h:i A', strtotime($company['updated_at'] ?? $company['created_at'])); ?></small>
                </div>
            </div>
            <div class="setting-item">
                <div>
                    <small class="text-muted d-block">Internship Duration</small>
                    <strong><?php echo htmlspecialchars($internship_duration); ?></strong>
                </div>
            </div>
            <div class="setting-item">
                <div>
                    <small class="text-muted d-block">Office Timing</small>
                    <strong><?php echo htmlspecialchars($office_timing); ?></strong>
                </div>
            </div>
            <div class="setting-item">
                <div>
                    <small class="text-muted d-block">Minimum Score for Job</small>
                    <strong><?php echo $min_score_for_job; ?>%</strong>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block">System Default</small>
                    <small>70%</small>
                </div>
            </div>
        </div>

        <!-- Settings Form -->
        <div class="form-container">
            <div class="card settings-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Update Company Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="settingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label">Company Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-building"></i>
                                        </span>
                                        <input type="text" class="form-control" 
                                               name="company_name" 
                                               value="<?php echo htmlspecialchars($company_name); ?>"
                                               placeholder="Enter company name"
                                               required>
                                    </div>
                                    <small class="text-muted">This name appears throughout the system</small>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Internship Duration</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-calendar-alt"></i>
                                        </span>
                                        <input type="text" class="form-control" 
                                               name="internship_duration" 
                                               value="<?php echo htmlspecialchars($internship_duration); ?>"
                                               placeholder="e.g., 6 months">
                                    </div>
                                    <small class="text-muted">Default duration for all internships</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label">Office Timing</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-clock"></i>
                                        </span>
                                        <input type="text" class="form-control" 
                                               name="office_timing" 
                                               value="<?php echo htmlspecialchars($office_timing); ?>"
                                               placeholder="e.g., 9:00 AM - 6:00 PM">
                                    </div>
                                    <small class="text-muted">Standard office hours</small>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Minimum Score for Job (%) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-chart-line"></i>
                                        </span>
                                        <input type="number" class="form-control" 
                                               name="min_score_for_job" 
                                               value="<?php echo $min_score_for_job; ?>"
                                               min="0" 
                                               max="100"
                                               required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Interns need this score to be eligible for job offer (0-100)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Information Card -->
                        <div class="card bg-light mt-4">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Important Notes</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted">• Company name changes affect login page and all dashboards</small>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted">• Changing minimum score affects all existing intern eligibility</small>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted">• Settings are applied system-wide immediately</small>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted">• Changes are logged for security purposes</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-undo me-2"></i>Reset Changes
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- System Information Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-server me-2"></i>System Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        // Get system stats
                        $system_stats = [
                            'Total Domains' => "SELECT COUNT(*) FROM domains",
                            'Active Team Leads' => "SELECT COUNT(*) FROM users WHERE role = 'team_lead' AND is_active = TRUE",
                            'Active Interns' => "SELECT COUNT(*) FROM users WHERE role = 'intern' AND is_active = TRUE",
                            'Total Tasks' => "SELECT COUNT(*) FROM tasks",
                            'Completed Tasks' => "SELECT COUNT(*) FROM tasks WHERE status = 'completed'",
                            'Eligible Interns' => "SELECT COUNT(*) FROM performance WHERE eligibility = 'eligible'"
                        ];
                        
                        foreach ($system_stats as $label => $query):
                            $result = mysqli_query($conn, $query);
                            $count = mysqli_fetch_row($result)[0];
                        ?>
                        <div class="col-md-4 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h4 class="mb-1 text-primary"><?php echo $count; ?></h4>
                                <small class="text-muted"><?php echo $label; ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>System Version:</strong> Intern Management System v1.0<br>
                            <strong>Database:</strong> MySQL<br>
                            <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?><br>
                            <strong>Admin Contact:</strong> <?php echo $_SESSION['email'] ?? 'admin@company.com'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            const companyName = document.querySelector('input[name="company_name"]').value.trim();
            const minScore = document.querySelector('input[name="min_score_for_job"]').value;
            
            if (companyName === '') {
                e.preventDefault();
                alert('Company name is required');
                return false;
            }
            
            if (minScore < 0 || minScore > 100) {
                e.preventDefault();
                alert('Minimum score must be between 0 and 100');
                return false;
            }
            
            if (!confirm('Are you sure you want to update company settings?\n\nThese changes will affect the entire system.')) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;
        });
        
        // Reset form confirmation
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            if (!confirm('Reset all changes to current settings?')) {
                e.preventDefault();
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
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
                hamburger.className = 'btn btn-primary me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
            }
        });
    </script>
</body>
</html>