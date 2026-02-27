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
$success = '';
$error = '';



// Handle profile update (phone only as other details are read-only)


// Get intern details
$sql = "SELECT u.*, d.domain_name,
               tl.full_name as team_lead_name,
               tl.email as team_lead_email,
               tl.phone as team_lead_phone
        FROM users u 
        LEFT JOIN domains d ON u.domain_id = d.id 
        LEFT JOIN users tl ON d.id = tl.domain_id AND tl.role = 'team_lead'
        WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $intern_id);
mysqli_stmt_execute($stmt);
$intern_result = mysqli_stmt_get_result($stmt);
$intern = mysqli_fetch_assoc($intern_result);

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get message stats for badge
$msg_query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = $intern_id AND read_status = FALSE";
$msg_result = mysqli_query($conn, $msg_query);
$msg_stats = mysqli_fetch_assoc($msg_result);
$unread_messages = $msg_stats['unread'];

// Get task stats for badge
$task_query = "SELECT COUNT(*) as pending FROM tasks WHERE assigned_to = $intern_id AND status = 'pending'";
$task_result = mysqli_query($conn, $task_query);
$task_stats = mysqli_fetch_assoc($task_result);
$pending_tasks = $task_stats['pending'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Intern Profile</title>
    
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
        
        /* Profile Image Large */
        .profile-img-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #e8eaf6;
            color: #1a237e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto;
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(26, 35, 126, 0.25);
            border-color: #1a237e;
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
            <small class="text-white-50 d-block mt-2">Intern Portal</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="my_tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>My Tasks
                <?php if($pending_tasks > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $pending_tasks; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="submit_task.php" class="nav-link">
                <i class="fas fa-paper-plane"></i>Submit Task
            </a>
            
            <a href="view_feedback.php" class="nav-link">
                <i class="fas fa-comment-dots"></i>Feedback
            </a>
            
            <a href="messages.php" class="nav-link">
                <i class="fas fa-envelope"></i>Messages
                <?php if($unread_messages > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="profile.php" class="nav-link active">
                <i class="fas fa-user"></i>Profile
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($intern['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $intern['full_name']; ?></h6>
                    <small class="text-white-50">Intern</small>
                    <small class="text-white-50 d-block"><?php echo $intern['domain_name'] ?? 'No Domain'; ?></small>
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
                        <h4 class="mb-0">Team Lead Profile</h4>
                        <small class="text-muted">Contact details for your assigned Team Lead</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="me-3 text-end">
                            <small class="text-muted d-block">Today</small>
                            <strong><?php echo date('F j, Y'); ?></strong>
                        </div>
                        <a href="../logout.php" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
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

        <div class="row">
            <!-- Profile Card -->
            <div class="col-lg-12 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="profile-img-lg mb-3">
                            <?php echo strtoupper(substr($intern['team_lead_name'] ?? 'T', 0, 1)); ?>
                        </div>
                        <?php if(!empty($intern['team_lead_name'])): ?>
                            <h4 class="mb-1"><?php echo htmlspecialchars($intern['team_lead_name']); ?></h4>
                            <p class="text-muted mb-3">Team Lead</p>
                            
                            <div class="row justify-content-center">
                                <div class="col-md-6 text-start">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light p-3 rounded-circle me-3">
                                            <i class="fas fa-envelope text-primary"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Email Address</small>
                                            <strong><?php echo htmlspecialchars($intern['team_lead_email']); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light p-3 rounded-circle me-3">
                                            <i class="fas fa-phone text-primary"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Phone Number</small>
                                            <strong><?php echo htmlspecialchars($intern['team_lead_phone'] ?: 'Not provided'); ?></strong>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center">
                                        <div class="bg-light p-3 rounded-circle me-3">
                                            <i class="fas fa-layer-group text-primary"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Domain</small>
                                            <strong><?php echo htmlspecialchars($intern['domain_name']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <h4 class="mb-1">No Team Lead Assigned</h4>
                            <p class="text-muted">Please contact admin for more information.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Mobile menu toggle logic
        // ... include same toggle logic as dashboard ...
    </script>
</body>
</html>
