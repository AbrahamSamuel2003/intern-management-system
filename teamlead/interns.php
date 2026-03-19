<?php
// Start session and database connection
session_start();
require_once '../config/database.php';

// Check if user is logged in and is team lead
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'team_lead') {
    header('Location: ../login.php');
    exit();
}

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get team lead's domain
$team_lead_id = $_SESSION['user_id'];
$domain_query = "SELECT d.domain_name, d.id as domain_id FROM domains d 
                 LEFT JOIN users u ON d.id = u.domain_id 
                 WHERE u.id = $team_lead_id";
$domain_result = mysqli_query($conn, $domain_query);
$domain_data = mysqli_fetch_assoc($domain_result);
$domain_name = $domain_data['domain_name'] ?? 'No Domain Assigned';
$domain_id = $domain_data['domain_id'] ?? 0;

// Get interns in the same domain
$interns_query = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.username,
        u.created_at,
        p.performance_score,
        p.eligibility,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id) as total_tasks,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'completed') as completed_tasks,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND status = 'submitted') as submitted_tasks,
        (SELECT COUNT(*) FROM messages m WHERE m.receiver_id = u.id AND m.read_status = FALSE) as unread_messages
    FROM users u
    LEFT JOIN performance p ON u.id = p.intern_id
    WHERE u.role = 'intern' 
    AND u.domain_id = $domain_id 
    AND u.is_active = TRUE
    ORDER BY u.full_name ASC
";

$interns_result = mysqli_query($conn, $interns_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - My Interns</title>
    
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
        
        /* Intern Cards */
        .intern-card {
            transition: transform 0.3s;
            border: 1px solid #e3e6f0;
        }
        
        .intern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Status Badges */
        .badge-eligible {
            background-color: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-not-eligible {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-task {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .badge-message {
            background-color: #f3e8ff;
            color: #6b21a8;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
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
        
        /* Domain Badge */
        .domain-badge {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        /* Stats Box */
        .stats-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
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
            <small class="text-white-50 d-block mt-2">Team Lead Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="interns.php" class="nav-link active">
                <i class="fas fa-user-graduate"></i>My Interns
            </a>
            
            <a href="tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>Tasks
            </a>
            
            <a href="submitted_tasks.php" class="nav-link">
                <i class="fas fa-paper-plane"></i>Submitted Tasks
            </a>
            
            <a href="assign_task.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>Assign Task
            </a>
            
            <a href="messages.php" class="nav-link">
                <i class="fas fa-envelope"></i>Messages
            </a>
            
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Reports
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-white-50">Team Lead</small>
                    <small class="text-white-50 d-block"><?php echo $domain_name; ?></small>
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
                        <h4 class="mb-0">
                            <a href="dashboard.php" class="text-decoration-none text-dark">
                                <i class="fas fa-arrow-left me-2"></i>
                            </a>
                            My Interns
                        </h4>
                        <small class="text-muted">
                            Manage interns in 
                            <span class="domain-badge ms-1"><?php echo $domain_name; ?></span>
                        </small>
                    </div>
                    <div>
                        <a href="assign_task.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Assign Task
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Statistics Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-box">
                    <div class="display-6 fw-bold text-primary">
                        <?php echo mysqli_num_rows($interns_result); ?>
                    </div>
                    <div class="text-muted">Total Interns</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <div class="display-6 fw-bold text-success">
                        <?php 
                        $eligible_count = 0;
                        mysqli_data_seek($interns_result, 0);
                        while($intern = mysqli_fetch_assoc($interns_result)) {
                            if ($intern['eligibility'] == 'eligible') $eligible_count++;
                        }
                        mysqli_data_seek($interns_result, 0);
                        echo $eligible_count;
                        ?>
                    </div>
                    <div class="text-muted">Eligible for Job</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <div class="display-6 fw-bold text-warning">
                        <?php 
                        $submitted_total = 0;
                        mysqli_data_seek($interns_result, 0);
                        while($intern = mysqli_fetch_assoc($interns_result)) {
                            $submitted_total += $intern['submitted_tasks'];
                        }
                        mysqli_data_seek($interns_result, 0);
                        echo $submitted_total;
                        ?>
                    </div>
                    <div class="text-muted">Tasks Submitted</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <div class="display-6 fw-bold text-info">
                        <?php 
                        $unread_total = 0;
                        mysqli_data_seek($interns_result, 0);
                        while($intern = mysqli_fetch_assoc($interns_result)) {
                            $unread_total += $intern['unread_messages'];
                        }
                        mysqli_data_seek($interns_result, 0);
                        echo $unread_total;
                        ?>
                    </div>
                    <div class="text-muted">Unread Messages</div>
                </div>
            </div>
        </div>

        <!-- Interns List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-user-graduate me-2"></i>Interns List
                </h5>
                <span class="badge bg-primary">
                    <?php echo mysqli_num_rows($interns_result); ?> Interns
                </span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($interns_result) > 0): ?>
                    <div class="row">
                        <?php while($intern = mysqli_fetch_assoc($interns_result)): 
                            $completion_rate = $intern['total_tasks'] > 0 
                                ? ($intern['completed_tasks'] / $intern['total_tasks'] * 100) 
                                : 0;
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card intern-card h-100">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                                             style="width: 80px; height: 80px;">
                                            <i class="fas fa-user-graduate fa-2x text-primary"></i>
                                        </div>
                                    </div>
                                    
                                    <h5 class="text-center mb-1"><?php echo htmlspecialchars($intern['full_name']); ?></h5>
                                    <p class="text-center text-muted mb-3">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($intern['email']); ?>
                                    </p>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="text-muted">Task Completion</small>
                                            <small class="text-muted"><?php echo round($completion_rate); ?>%</small>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $completion_rate; ?>%">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <div class="fw-bold"><?php echo $intern['total_tasks']; ?></div>
                                            <small class="text-muted">Total</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold"><?php echo $intern['completed_tasks']; ?></div>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold"><?php echo $intern['submitted_tasks']; ?></div>
                                            <small class="text-muted">Submitted</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <?php if ($intern['eligibility'] == 'eligible'): ?>
                                                <span class="badge-eligible">
                                                    <i class="fas fa-check-circle me-1"></i>Eligible
                                                </span>
                                            <?php elseif ($intern['eligibility'] == 'not_eligible'): ?>
                                                <span class="badge-not-eligible">
                                                    <i class="fas fa-times-circle me-1"></i>Not Eligible
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-pending">
                                                    <i class="fas fa-clock me-1"></i>Pending
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <?php if ($intern['unread_messages'] > 0): ?>
                                            <span class="badge-message">
                                                <i class="fas fa-envelope me-1"></i><?php echo $intern['unread_messages']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($intern['performance_score']): ?>
                                    <div class="text-center mb-3">
                                        <small class="text-muted">Performance Score: </small>
                                        <span class="fw-bold <?php echo $intern['performance_score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo round($intern['performance_score'], 1); ?>%
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="assign_task.php?intern_id=<?php echo $intern['id']; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-tasks"></i> Assign Task
                                        </a>
                                        <a href="messages.php?intern_id=<?php echo $intern['id']; ?>" 
                                           class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-envelope"></i> Message
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-graduate fa-4x text-muted mb-3"></i>
                        <h5>No Interns Assigned</h5>
                        <p class="text-muted mb-4">
                            There are no interns in your domain yet. Contact administrator.
                        </p>
                        <a href="../logout.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Back to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
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