<?php
// Start session and database connection
session_start();

// Include database configuration
require_once '../config/database.php';

// Check if user is logged in and is intern
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'intern') {
    header('Location: ../login.php');
    exit();
}

$intern_id = $_SESSION['user_id'];

// Get intern details with team lead information
$sql = "SELECT u.*, d.domain_name, 
               tl.id as team_lead_id,
               tl.full_name as team_lead_name, 
               tl.email as team_lead_email,
               tl.username as team_lead_username
        FROM users u 
        LEFT JOIN domains d ON u.domain_id = d.id 
        LEFT JOIN users tl ON u.domain_id = tl.domain_id AND tl.role = 'team_lead'
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

// Get statistics for dashboard
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = $intern_id) as total_tasks,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = $intern_id AND status != 'completed' AND deadline >= CURDATE()) as active_tasks,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = $intern_id AND status = 'completed') as completed_tasks,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = $intern_id AND status = 'not_completed') as not_completed_tasks,
        (SELECT COUNT(*) FROM messages WHERE receiver_id = $intern_id AND read_status = FALSE) as unread_messages,
        (SELECT performance_score FROM performance WHERE intern_id = $intern_id) as performance_score,
        (SELECT eligibility FROM performance WHERE intern_id = $intern_id) as eligibility,
        (SELECT AVG(rating) FROM tasks WHERE assigned_to = $intern_id AND status = 'completed') as avg_rating
";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent tasks
$recent_tasks_sql = "SELECT t.*, 
                        u.full_name as assigned_by_name,
                        DATEDIFF(t.deadline, CURDATE()) as days_left
                    FROM tasks t
                    JOIN users u ON t.assigned_by = u.id
                    WHERE t.assigned_to = ? AND t.status != 'completed' AND t.deadline >= CURDATE()
                    ORDER BY 
                        CASE 
                            WHEN status = 'pending' THEN 0
                            WHEN status = 'submitted' THEN 1
                            ELSE 2
                        END,
                        deadline ASC
                    LIMIT 5";
$stmt = mysqli_prepare($conn, $recent_tasks_sql);
mysqli_stmt_bind_param($stmt, "i", $intern_id);
mysqli_stmt_execute($stmt);
$recent_tasks = mysqli_stmt_get_result($stmt);

// Get recent messages
$recent_messages_sql = "SELECT m.*, u.full_name as sender_name
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       WHERE m.receiver_id = ?
                       ORDER BY m.created_at DESC
                       LIMIT 5";
$stmt = mysqli_prepare($conn, $recent_messages_sql);
mysqli_stmt_bind_param($stmt, "i", $intern_id);
mysqli_stmt_execute($stmt);
$recent_messages = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Intern Dashboard</title>
    
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
        
        /* Statistics Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .stat-card.primary { border-left-color: #4e73df; }
        .stat-card.success { border-left-color: #1cc88a; }
        .stat-card.warning { border-left-color: #f6c23e; }
        .stat-card.info { border-left-color: #36b9cc; }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stat-icon.primary { background: linear-gradient(45deg, #4e73df, #224abe); }
        .stat-icon.success { background: linear-gradient(45deg, #1cc88a, #13855c); }
        .stat-icon.warning { background: linear-gradient(45deg, #f6c23e, #dda20a); }
        .stat-icon.info { background: linear-gradient(45deg, #36b9cc, #258391); }
        
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
        
        /* Task Status Badges */
        .badge-pending { background-color: #f6c23e; color: #000; }
        .badge-submitted { background-color: #36b9cc; color: #fff; }
        .badge-completed { background-color: #1cc88a; color: #fff; }
        .badge-not-completed { background-color: #e74a3b; color: #fff; }
        .badge-overdue { background-color: #858796; color: #fff; }
        
        /* Progress Bar */
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        /* Eligibility Status */
        .eligibility-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .eligible { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .not-eligible { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .pending { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
        
        /* Performance Meter */
        .performance-meter {
            height: 120px;
            width: 120px;
            position: relative;
            margin: 0 auto;
        }
        
        .performance-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 28px;
            font-weight: 700;
        }
        
        /* Table Styles */
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        /* Message List */
        .message-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .message-item:last-child {
            border-bottom: none;
        }
        
        .message-unread {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        /* Team Lead Card */
        .team-lead-card {
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            padding: 20px;
            background: white;
        }
        
        .team-lead-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #4e73df, #224abe);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 15px;
        }
        
        /* Domain Badge */
        .domain-badge {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
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
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="my_tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>My Tasks
            </a>
            
            <a href="submit_task.php" class="nav-link">
                <i class="fas fa-paper-plane"></i>Submit Task
            </a>
            
            
            <a href="messages.php" class="nav-link">
                <i class="fas fa-envelope"></i>Messages
                <?php if($stats['unread_messages'] > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $stats['unread_messages']; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="profile.php" class="nav-link">
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
                        <h4 class="mb-0">Dashboard</h4>
                        <small class="text-muted">Welcome, <?php echo $intern['full_name']; ?></small>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <?php 
                            $eligibility_class = '';
                            switch($stats['eligibility']) {
                                case 'eligible': $eligibility_class = 'eligible'; break;
                                case 'not_eligible': $eligibility_class = 'not-eligible'; break;
                                default: $eligibility_class = 'pending'; break;
                            }
                            ?>
                            <span class="eligibility-badge <?php echo $eligibility_class; ?>">
                                <i class="fas fa-<?php echo $stats['eligibility'] == 'eligible' ? 'check-circle' : ($stats['eligibility'] == 'not_eligible' ? 'times-circle' : 'clock'); ?> me-1"></i>
                                <?php echo ucfirst($stats['eligibility'] ?? 'Pending'); ?>
                            </span>
                        </div>
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

        <!-- Team Lead Information -->
        <?php if($intern['team_lead_name']): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>My Team Lead</h6>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center">
                                    <div class="team-lead-icon">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-1"><?php echo $intern['team_lead_name']; ?></h4>
                                        <p class="mb-1 text-muted">
                                            <i class="fas fa-envelope me-1"></i><?php echo $intern['team_lead_email']; ?>
                                        </p>
                                        <p class="mb-0 text-muted">
                                            <i class="fas fa-layer-group me-1"></i>Domain: <?php echo $intern['domain_name'] ?? 'Not Assigned'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="messages.php?compose=1" class="btn btn-primary">
                                    <i class="fas fa-envelope me-2"></i>Send Message
                                </a>
                                <a href="view_team_lead.php" class="btn btn-outline-primary mt-2">
                                    <i class="fas fa-eye me-2"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Performance Overview -->
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3 class="mb-2"><?php echo $stats['total_tasks'] ?? 0; ?></h3>
                    <p class="mb-0 text-muted">Total Tasks Assigned</p>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="mb-2"><?php echo $stats['completed_tasks'] ?? 0; ?></h3>
                    <p class="mb-0 text-muted">Tasks Completed</p>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="mb-2"><?php echo $stats['active_tasks'] ?? 0; ?></h3>
                    <p class="mb-0 text-muted">Active Tasks</p>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="mb-2"><?php echo $stats['unread_messages'] ?? 0; ?></h3>
                    <p class="mb-0 text-muted">Unread Messages</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <!-- Recent Tasks -->
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>Recent Tasks</h6>
                        <a href="my_tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($recent_tasks) > 0): ?>
                                        <?php while($task = mysqli_fetch_assoc($recent_tasks)): 
                                            $status_class = 'badge-' . $task['status'];
                                            if($task['status'] == 'pending' && $task['days_left'] < 0) {
                                                $status_class = 'badge-overdue';
                                                $task['status'] = 'overdue';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars(substr($task['title'], 0, 40)); ?><?php echo strlen($task['title']) > 40 ? '...' : ''; ?></div>
                                                <small class="text-muted">Assigned by: <?php echo htmlspecialchars($task['assigned_by_name']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo date('M d', strtotime($task['deadline'])); ?></div>
                                                <small class="<?php echo $task['days_left'] < 3 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                    <?php echo $task['days_left'] > 0 ? $task['days_left'] . ' days left' : ($task['days_left'] < 0 ? 'Overdue' : 'Today'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($task['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($task['status'] == 'pending' || $task['status'] == 'overdue'): ?>
                                                <a href="submit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-paper-plane me-1"></i>Submit
                                                </a>
                                                <?php else: ?>
                                                <a href="my_tasks.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="fas fa-tasks fa-2x text-muted mb-3"></i>
                                            <p class="text-muted mb-0">No tasks assigned yet</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-envelope me-2"></i>Recent Messages</h6>
                        <a href="messages.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if(mysqli_num_rows($recent_messages) > 0): ?>
                                <?php while($message = mysqli_fetch_assoc($recent_messages)): ?>
                                <a href="messages.php?view=<?php echo $message['id']; ?>" class="list-group-item list-group-item-action border-0 <?php echo !$message['read_status'] ? 'message-unread' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($message['sender_name']); ?></h6>
                                        <small class="text-muted"><?php echo date('M d', strtotime($message['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars(substr($message['message'], 0, 80)); ?><?php echo strlen($message['message']) > 80 ? '...' : ''; ?></p>
                                    <?php if(!$message['read_status']): ?>
                                    <small class="text-primary"><i class="fas fa-circle fa-xs"></i> New</small>
                                    <?php endif; ?>
                                </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-envelope fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No messages</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="submit_task.php" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane me-1"></i>Submit Task
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="messages.php?compose=1" class="btn btn-success w-100">
                                    <i class="fas fa-envelope me-1"></i>New Message
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="my_tasks.php?filter=pending" class="btn btn-warning w-100">
                                    <i class="fas fa-clock me-1"></i>Pending Tasks
                                </a>
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
                hamburger.className = 'btn btn-dark me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
            }
            
            // Auto-refresh dashboard every 2 minutes
            setTimeout(function() {
                window.location.reload();
            }, 120000);
        });
    </script>
</body>
</html>