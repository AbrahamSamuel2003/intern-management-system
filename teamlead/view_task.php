<?php
// teamlead/view_task.php

// Start session and database connection
session_start();

// Include database configuration
require_once '../config/database.php';

// Check if user is logged in and is team lead
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'team_lead') {
    header('Location: ../login.php');
    exit();
}

$team_lead_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get task details
$task_query = "
    SELECT 
        t.*,
        u.full_name as intern_name,
        u.email as intern_email,
        d.domain_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN domains d ON t.domain_id = d.id
    WHERE t.id = $task_id AND t.assigned_by = $team_lead_id
";

$task_result = mysqli_query($conn, $task_query);
$task = mysqli_fetch_assoc($task_result);

if (!$task) {
    header('Location: tasks.php');
    exit();
}

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - View Task</title>
    
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
        
        /* Task Status Badges */
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-submitted { background-color: #dbeafe; color: #1e40af; }
        .badge-completed { background-color: #d1fae5; color: #065f46; }
        .badge-not-completed { background-color: #fee2e2; color: #991b1b; }
        
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
            <small class="text-white-50 d-block mt-2">Team Lead Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="interns.php" class="nav-link">
                <i class="fas fa-user-graduate"></i>My Interns
            </a>
            <a href="tasks.php" class="nav-link active">
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
                            <a href="tasks.php" class="text-decoration-none text-dark">
                                <i class="fas fa-arrow-left me-2"></i>
                            </a>
                            View Task
                        </h4>
                        <small class="text-muted">
                            Details for: <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <a href="review_task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-clipboard-check me-1"></i>Update Status
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Task Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5><?php echo htmlspecialchars($task['title']); ?></h5>
                            <hr>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Assigned To</label>
                                <strong><?php echo htmlspecialchars($task['intern_name']); ?></strong>
                                <div class="small text-muted"><?php echo htmlspecialchars($task['intern_email']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Domain</label>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($task['domain_name']); ?></span>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Assigned Date</label>
                                <strong><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></strong>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Deadline</label>
                                <strong class="<?php echo (strtotime($task['deadline']) < time() && $task['status'] != 'completed') ? 'text-danger' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
                                    <?php if(strtotime($task['deadline']) < time() && $task['status'] != 'completed'): ?>
                                        <small>(Overdue)</small>
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Status</label>
                                <span class="badge badge-<?php echo $task['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Rating</label>
                                <?php if($task['rating'] > 0): ?>
                                    <div class="text-warning">
                                        <?php for($i=1; $i<=5; $i++) echo $i<=$task['rating'] ? '★' : '☆'; ?>
                                        <span class="text-dark ms-1">(<?php echo $task['rating']; ?>/5)</span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Not rated yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Submission Info</h6>
                    </div>
                    <div class="card-body">
                        <?php if($task['submitted_date']): ?>
                            <div class="mb-3">
                                <label class="text-muted small d-block">Submitted Date</label>
                                <strong><?php echo date('M d, Y', strtotime($task['submitted_date'])); ?></strong>
                                <?php 
                                    $sub_time = strtotime($task['submitted_date']);
                                    $dead_time = strtotime($task['deadline']);
                                    if($sub_time > $dead_time):
                                ?>
                                    <span class="badge bg-danger ms-1">Late</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-1">On Time</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($task['submission_text']): ?>
                                <div class="mt-4 pt-3 border-top">
                                    <label class="text-muted small d-block mb-1">Intern's Notes/Submission Text</label>
                                    <div class="p-3 bg-light rounded text-dark">
                                        <?php echo nl2br(htmlspecialchars($task['submission_text'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if($task['submitted_file']): ?>
                                <div class="p-3 bg-light rounded mt-3">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-file-pdf text-danger fa-2x me-3"></i>
                                        <div class="overflow-hidden">
                                            <div class="text-truncate fw-bold"><?php echo htmlspecialchars($task['submitted_file']); ?></div>
                                            <div class="small text-muted">Uploaded by intern</div>
                                        </div>
                                    </div>
                                    <a href="../assets/uploads/tasks/<?php echo $task['submitted_file']; ?>" class="btn btn-outline-primary btn-sm w-100" download>
                                        <i class="fas fa-download me-1"></i>Download File
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning small py-2 mt-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i> No file attached.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No submission yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card bg-primary text-white">
                    <div class="card-header bg-transparent border-white border-opacity-10">
                        <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Help</h6>
                    </div>
                    <div class="card-body small">
                        <ul class="mb-0 ps-3">
                            <li class="mb-2">Review current task progress and details.</li>
                            <li class="mb-2">Click <strong>Edit</strong> to change deadline or task description.</li>
                            <li>Click <strong>Update Status</strong> to mark as completed or send back for re-work.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
