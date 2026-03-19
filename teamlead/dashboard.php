<?php
// teamlead/dashboard.php

// Start session and database connection
session_start();

// Include database configuration
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

// Get statistics for dashboard
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'intern' AND domain_id = $domain_id AND is_active = TRUE) as total_interns,
        (SELECT COUNT(*) FROM tasks WHERE assigned_by = $team_lead_id AND status = 'pending') as pending_tasks,
        (SELECT COUNT(*) FROM tasks WHERE assigned_by = $team_lead_id AND status = 'completed') as completed_tasks,
        (SELECT COUNT(*) FROM tasks WHERE assigned_by = $team_lead_id AND status = 'submitted') as submitted_tasks,
        (SELECT COUNT(*) FROM messages WHERE receiver_id = $team_lead_id AND read_status = FALSE) as unread_messages,
        (SELECT COUNT(*) FROM tasks WHERE assigned_by = $team_lead_id) as total_tasks_assigned
";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent tasks
$recent_tasks_query = "
    SELECT 
        t.id,
        t.title,
        t.status,
        t.assigned_date,
        t.deadline,
        u.full_name as intern_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.assigned_by = $team_lead_id
    ORDER BY t.assigned_date DESC
    LIMIT 5
";

$recent_tasks_result = mysqli_query($conn, $recent_tasks_query);

// Get recent interns
$recent_interns_query = "
    SELECT 
        id,
        full_name,
        email,
        username,
        created_at
    FROM users
    WHERE role = 'intern' AND domain_id = $domain_id AND is_active = TRUE
    ORDER BY created_at DESC
    LIMIT 5
";

$recent_interns_result = mysqli_query($conn, $recent_interns_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Team Lead Dashboard</title>
    
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
        
        .bg-gradient-danger {
            background: linear-gradient(45deg, #e74a3b, #be2617);
        }
        
        .bg-gradient-purple {
            background: linear-gradient(45deg, #6f42c1, #4d2d91);
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
        
        /* Task Status Badges */
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-submitted {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        /* Task Row */
        .task-row {
            transition: background-color 0.3s;
            cursor: pointer;
        }
        
        .task-row:hover {
            background-color: #f1f4f9;
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
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="interns.php" class="nav-link">
                <i class="fas fa-user-graduate"></i>My Interns
            </a>
            
            <a href="tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>Tasks
            </a>
            
            <a href="submitted_tasks.php" class="nav-link">
                <i class="fas fa-paper-plane"></i>Submitted Tasks
                <?php if ($stats['submitted_tasks'] > 0): ?>
                <span class="badge bg-info float-end"><?php echo $stats['submitted_tasks']; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="assign_task.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>Assign Task
            </a>
            
            <a href="messages.php" class="nav-link">
                <i class="fas fa-envelope"></i>Messages
                <?php if ($stats['unread_messages'] > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $stats['unread_messages']; ?></span>
                <?php endif; ?>
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
                        <h4 class="mb-0">Team Lead Dashboard</h4>
                        <small class="text-muted">
                            Welcome back, <?php echo $_SESSION['full_name']; ?>! 
                            <span class="domain-badge ms-2"><?php echo $domain_name; ?></span>
                        </small>
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
                            <h6 class="text-uppercase mb-0">My Interns</h6>
                            <h2 class="mb-0"><?php echo $stats['total_interns'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Under My Domain</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-primary">
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
                            <h6 class="text-uppercase mb-0">Pending Tasks</h6>
                            <h2 class="mb-0"><?php echo $stats['pending_tasks'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Awaiting Review</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-uppercase mb-0">Completed</h6>
                            <h2 class="mb-0"><?php echo $stats['completed_tasks'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Tasks Completed</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h6 class="text-uppercase mb-0">Messages</h6>
                            <h2 class="mb-0"><?php echo $stats['unread_messages'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Unread Messages</p>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon bg-gradient-info">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row">
            <!-- Left Column: Quick Actions & Recent Tasks -->
            <div class="col-lg-8 mb-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="assign_task.php" class="quick-action-item">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                                            <i class="fas fa-plus text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Assign New Task</h6>
                                            <small class="text-muted">Create and assign task</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <a href="interns.php" class="quick-action-item">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 p-2 rounded me-3">
                                            <i class="fas fa-users text-success"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">View Interns</h6>
                                            <small class="text-muted">See all your interns</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <a href="submitted_tasks.php" class="quick-action-item">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-warning bg-opacity-10 p-2 rounded me-3">
                                            <i class="fas fa-clipboard-check text-warning"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Review Tasks</h6>
                                            <small class="text-muted">Check submitted tasks</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <a href="messages.php" class="quick-action-item">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-info bg-opacity-10 p-2 rounded me-3">
                                            <i class="fas fa-comments text-info"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Messages</h6>
                                            <small class="text-muted">Check messages</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Tasks -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>Recent Tasks</h6>
                        <a href="tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($recent_tasks_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Task</th>
                                            <th>Intern</th>
                                            <th>Status</th>
                                            <th>Assigned Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($task = mysqli_fetch_assoc($recent_tasks_result)): ?>
                                        <tr class="task-row" onclick="window.location.href='view_task.php?id=<?php echo $task['id']; ?>'">
                                            <td>
                                                <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['intern_name']); ?></td>
                                            <td>
                                                <?php if ($task['status'] == 'pending'): ?>
                                                    <span class="badge badge-pending">Pending</span>
                                                <?php elseif ($task['status'] == 'submitted'): ?>
                                                    <span class="badge badge-submitted">Submitted</span>
                                                <?php elseif ($task['status'] == 'completed'): ?>
                                                    <span class="badge badge-completed">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo $task['status']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></td>
                                            <td onclick="event.stopPropagation();">
                                                <a href="review_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No tasks assigned yet.</p>
                                <a href="assign_task.php" class="btn btn-primary">Assign Your First Task</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Interns & System Info -->
            <div class="col-lg-4">
                <!-- Recent Interns -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-user-graduate me-2"></i>My Interns</h6>
                        <span class="badge bg-primary"><?php echo $stats['total_interns'] ?? 0; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($recent_interns_result) > 0): ?>
                            <div class="list-group">
                                <?php while($intern = mysqli_fetch_assoc($recent_interns_result)): ?>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($intern['full_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($intern['username']); ?></small>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($intern['email']); ?></small>
                                        </div>
                                        <div>
                                            <a href="messages.php?intern_id=<?php echo $intern['id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No interns in your domain yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Task Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Task Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total Tasks Assigned</span>
                                <span class="fw-bold"><?php echo $stats['total_tasks_assigned'] ?? 0; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Completed Tasks</span>
                                <span class="fw-bold"><?php echo $stats['completed_tasks'] ?? 0; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo $stats['total_tasks_assigned'] > 0 ? ($stats['completed_tasks'] / $stats['total_tasks_assigned'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Pending Review</span>
                                <span class="fw-bold"><?php echo $stats['pending_tasks'] ?? 0; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" 
                                     style="width: <?php echo $stats['total_tasks_assigned'] > 0 ? ($stats['pending_tasks'] / $stats['total_tasks_assigned'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Submitted Tasks</span>
                                <span class="fw-bold"><?php echo $stats['submitted_tasks'] ?? 0; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" 
                                     style="width: <?php echo $stats['total_tasks_assigned'] > 0 ? ($stats['submitted_tasks'] / $stats['total_tasks_assigned'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                        
                        <?php 
                        $completion_rate = $stats['total_tasks_assigned'] > 0 
                            ? round(($stats['completed_tasks'] / $stats['total_tasks_assigned'] * 100), 1) 
                            : 0;
                        ?>
                        
                        <div class="alert <?php echo $completion_rate >= 80 ? 'alert-success' : ($completion_rate >= 50 ? 'alert-warning' : 'alert-danger'); ?>">
                            <i class="fas fa-chart-line me-2"></i>
                            <strong>Completion Rate:</strong> <?php echo $completion_rate; ?>%
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
                hamburger.className = 'btn btn-primary me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
            }
        });
    </script>
</body>
</html>