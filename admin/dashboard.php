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

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get statistics for dashboard
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'team_lead' AND is_active = TRUE) as total_tls,
        (SELECT COUNT(*) FROM users WHERE role = 'intern' AND is_active = TRUE) as total_interns,
        (SELECT COUNT(*) FROM domains) as total_domains,
        (SELECT COUNT(*) FROM tasks WHERE status = 'pending') as pending_tasks,
        (SELECT COUNT(*) FROM tasks WHERE status = 'completed') as completed_tasks,
        (SELECT COUNT(*) FROM performance WHERE eligibility = 'eligible') as eligible_interns
";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Admin Dashboard</title>
    
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
            color: #4e73df;
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
        
        /* Statistics Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(45deg, #4e73df, #224abe);
        }
        
        .bg-gradient-success {
            background: linear-gradient(45deg, #1cc88a, #13855c);
        }
        
        .bg-gradient-info {
            background: linear-gradient(45deg, #36b9cc, #258391);
        }
        
        .bg-gradient-warning {
            background: linear-gradient(45deg, #f6c23e, #dda20a);
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        
        /* Quick Actions */
        .quick-action-item {
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
            margin-bottom: 10px;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            display: block;
        }
        
        .quick-action-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
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
            <a href="dashboard.php" class="company-logo">
                <i class="fas fa-graduation-cap me-2"></i><?php echo $company_name; ?>
            </a>
            <small class="text-white-50 d-block mt-2">Admin Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="domains/manage.php" class="nav-link">
                <i class="fas fa-layer-group"></i>Domains
            </a>
            
            <a href="teamlead/manage.php" class="nav-link">
                <i class="fas fa-user-tie"></i>Team Leads
            </a>
            
            <a href="intern/manage.php" class="nav-link">
                <i class="fas fa-user-graduate"></i>Interns
            </a>
            
            <a href="performance/overview.php" class="nav-link">
                <i class="fas fa-chart-line"></i>Performance
            </a>
            
            <a href="settings/company.php" class="nav-link">
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
                    <small class="text-white-50 d-block"><?php echo $_SESSION['email'] ?? 'admin@company.com'; ?></small>
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
                        <h4 class="mb-0">Admin Dashboard</h4>
                        <small class="text-muted">Welcome back, <?php echo $_SESSION['full_name']; ?>!</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="me-3">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo date('F j, Y'); ?>
                        </span>
                        <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-uppercase mb-0">Team Leads</h6>
                            <h2 class="mb-0"><?php echo $stats['total_tls'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Active Team Leads</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-primary">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-uppercase mb-0">Interns</h6>
                            <h2 class="mb-0"><?php echo $stats['total_interns'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Active Interns</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-success">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-uppercase mb-0">Domains</h6>
                            <h2 class="mb-0"><?php echo $stats['total_domains'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Total Domains</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-info">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-uppercase mb-0">Eligible</h6>
                            <h2 class="mb-0"><?php echo $stats['eligible_interns'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Job Eligible Interns</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-warning">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row">
            <!-- Left Column: Quick Actions -->
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <a href="domains/add.php" class="quick-action-item">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                                    <i class="fas fa-plus text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Add New Domain</h6>
                                    <small class="text-muted">Create new work domain</small>
                                </div>
                            </div>
                        </a>
                        
                        <a href="teamlead/add.php" class="quick-action-item">
                            <div class="d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 p-2 rounded me-3">
                                    <i class="fas fa-user-plus text-success"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Add Team Lead</h6>
                                    <small class="text-muted">Create new team lead account</small>
                                </div>
                            </div>
                        </a>
                        
                        <a href="intern/add.php" class="quick-action-item">
                            <div class="d-flex align-items-center">
                                <div class="bg-info bg-opacity-10 p-2 rounded me-3">
                                    <i class="fas fa-user-graduate text-info"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Add Intern</h6>
                                    <small class="text-muted">Register new intern</small>
                                </div>
                            </div>
                        </a>
                        
                        <a href="performance/overview.php" class="quick-action-item">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning bg-opacity-10 p-2 rounded me-3">
                                    <i class="fas fa-chart-line text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">View Performance</h6>
                                    <small class="text-muted">Check intern performance</small>
                                </div>
                            </div>
                        </a>
                        
                        <a href="settings/company.php" class="quick-action-item">
                            <div class="d-flex align-items-center">
                                <div class="bg-danger bg-opacity-10 p-2 rounded me-3">
                                    <i class="fas fa-cog text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Company Settings</h6>
                                    <small class="text-muted">Update company information</small>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Activity & System Info -->
            <div class="col-lg-7">
                <!-- Welcome Message -->
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-3">Welcome to Intern Management System</h4>
                        <p class="text-muted mb-4">
                            You are logged in as Administrator. Use the sidebar or quick actions to manage the system.
                        </p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 bg-light rounded">
                                    <i class="fas fa-users fa-2x text-primary me-3"></i>
                                    <div>
                                        <h5 class="mb-0">Manage Users</h5>
                                        <small class="text-muted">Add team leads and interns</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 bg-light rounded">
                                    <i class="fas fa-chart-bar fa-2x text-success me-3"></i>
                                    <div>
                                        <h5 class="mb-0">Track Performance</h5>
                                        <small class="text-muted">Monitor intern progress</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 bg-light rounded">
                                    <i class="fas fa-tasks fa-2x text-info me-3"></i>
                                    <div>
                                        <h5 class="mb-0">Task Management</h5>
                                        <small class="text-muted">Assign and track tasks</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 bg-light rounded">
                                    <i class="fas fa-cog fa-2x text-warning me-3"></i>
                                    <div>
                                        <h5 class="mb-0">System Settings</h5>
                                        <small class="text-muted">Configure company settings</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                    <span>Completed Tasks</span>
                                    <span class="badge bg-success"><?php echo $stats['completed_tasks'] ?? 0; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                    <span>Pending Tasks</span>
                                    <span class="badge bg-warning"><?php echo $stats['pending_tasks'] ?? 0; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                    <span>System Date</span>
                                    <span class="badge bg-primary"><?php echo date('Y-m-d'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                    <span>System Time</span>
                                    <span class="badge bg-info"><?php echo date('H:i:s'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>System Status:</strong> All systems are operational.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Simple logout confirmation
        document.querySelector('a[href="../logout.php"]').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
        
        // Mobile menu toggle (for responsive design)
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