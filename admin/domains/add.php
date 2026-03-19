<?php
// Start session and database connection
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Handle form submission
$error = '';
$success = '';
$domain_name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain_name = mysqli_real_escape_string($conn, $_POST['domain_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // Validation
    if (empty($domain_name)) {
        $error = 'Domain name is required';
    } else {
        // Check if domain already exists
        $check_query = "SELECT id FROM domains WHERE domain_name = '$domain_name'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Domain name already exists';
        } else {
            // Insert new domain
            $admin_id = $_SESSION['user_id'];
            $insert_query = "INSERT INTO domains (domain_name, description, created_by) 
                             VALUES ('$domain_name', '$description', $admin_id)";
            
            if (mysqli_query($conn, $insert_query)) {
                $success = 'Domain created successfully!';
                $domain_name = '';
                $description = '';
            } else {
                $error = 'Error creating domain: ' . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Add Domain</title>
    
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
        
        /* Form Styles */
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .form-control, .form-select {
            border: 2px solid #eef2f7;
            border-radius: 10px;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
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
            
            <a href="manage.php" class="nav-link">
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
            
            <a href="../settings/company.php" class="nav-link">
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
                        <h4 class="mb-0">Add New Domain</h4>
                        <small class="text-muted">Create a new work domain for organizing teams</small>
                    </div>
                    <div>
                        <a href="manage.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Domains
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

        <!-- Add Domain Form -->
        <div class="form-container">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Domain</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="addDomainForm">
                        <div class="mb-4">
                            <label class="form-label">Domain Name *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-layer-group"></i>
                                </span>
                                <input type="text" class="form-control" 
                                       name="domain_name" 
                                       value="<?php echo htmlspecialchars($domain_name); ?>"
                                       placeholder="e.g., Web Development, AI/ML, Mobile Apps"
                                       required>
                            </div>
                            <small class="text-muted">Enter a descriptive name for the domain (e.g., Web Development, AI/ML)</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-align-left"></i>
                                </span>
                                <textarea class="form-control" 
                                          name="description" 
                                          rows="4"
                                          placeholder="Describe what this domain covers..."><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                            <small class="text-muted">Optional: Describe the skills, technologies, or focus areas of this domain</small>
                        </div>
                        
                        <div class="mb-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="fas fa-lightbulb me-2"></i>Domain Examples</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <small class="text-muted">• Web Development</small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <small class="text-muted">• Artificial Intelligence</small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <small class="text-muted">• Mobile Development</small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <small class="text-muted">• Data Science</small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <small class="text-muted">• Cyber Security</small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <small class="text-muted">• UI/UX Design</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="manage.php" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Domain
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Help Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Why Create Domains?</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="d-flex">
                                <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                    <i class="fas fa-sitemap text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Organization</h6>
                                    <small class="text-muted">Group team leads and interns by expertise</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="d-flex">
                                <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                    <i class="fas fa-tasks text-success"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Task Management</h6>
                                    <small class="text-muted">Assign relevant tasks to each domain</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="d-flex">
                                <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                    <i class="fas fa-chart-line text-info"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Performance Tracking</h6>
                                    <small class="text-muted">Track performance by domain</small>
                                </div>
                            </div>
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
        document.getElementById('addDomainForm').addEventListener('submit', function(e) {
            const domainName = document.querySelector('input[name="domain_name"]').value.trim();
            
            if (domainName === '') {
                e.preventDefault();
                alert('Please enter a domain name');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
            submitBtn.disabled = true;
        });
        
        // Auto-dismiss alerts after 5 seconds
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