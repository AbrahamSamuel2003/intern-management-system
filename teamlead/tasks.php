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

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$intern_filter = isset($_GET['intern']) ? intval($_GET['intern']) : 0;

// Build tasks query
$tasks_query = "
    SELECT 
        t.id,
        t.title,
        t.description,
        t.status,
        t.assigned_date,
        t.deadline,
        t.submitted_date,
        u.full_name as intern_name,
        u.id as intern_id
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.assigned_by = $team_lead_id
";

// Apply filters
if ($status_filter != 'all') {
    $tasks_query .= " AND t.status = '$status_filter'";
}

if ($intern_filter > 0) {
    $tasks_query .= " AND t.assigned_to = $intern_filter";
}

$tasks_query .= " ORDER BY t.assigned_date DESC";

$tasks_result = mysqli_query($conn, $tasks_query);

// Get interns for filter dropdown
$interns_query = "
    SELECT id, full_name 
    FROM users 
    WHERE role = 'intern' 
    AND domain_id = $domain_id 
    AND is_active = TRUE 
    ORDER BY full_name ASC
";
$interns_result = mysqli_query($conn, $interns_query);

// Get task statistics
$task_stats_query = "
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'not_completed' THEN 1 ELSE 0 END) as not_completed_tasks
    FROM tasks 
    WHERE assigned_by = $team_lead_id
";
$task_stats_result = mysqli_query($conn, $task_stats_query);
$task_stats = mysqli_fetch_assoc($task_stats_result);

// Get Summary for each intern (from task_history.php)
$summary_query = "
    SELECT 
        u.id as intern_id,
        u.full_name,
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN t.status != 'completed' THEN 1 ELSE 0 END) as not_completed_count
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to AND t.assigned_by = $team_lead_id
    WHERE u.role = 'intern' AND u.domain_id = $domain_id
    GROUP BY u.id
";
$summary_result = mysqli_query($conn, $summary_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Task Management</title>
    
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
        
        .badge-not-completed {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        /* Task Row */
        .task-row {
            transition: background-color 0.3s;
            cursor: pointer;
        }
        
        .task-row:hover {
            background-color: #f1f4f9;
        }
        
        /* Stats Cards */
        .stats-card {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stats-total {
            background: linear-gradient(45deg, #4e73df, #224abe);
        }
        
        .stats-pending {
            background: linear-gradient(45deg, #f6c23e, #dda20a);
        }
        
        .stats-submitted {
            background: linear-gradient(45deg, #36b9cc, #258391);
        }
        
        .stats-completed {
            background: linear-gradient(45deg, #1cc88a, #13855c);
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
        
        /* Action Buttons */
        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .action-view {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .action-edit {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .action-delete {
            background-color: #ffebee;
            color: #c62828;
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
                <?php if ($task_stats['submitted_tasks'] > 0): ?>
                <span class="badge bg-info float-end"><?php echo $task_stats['submitted_tasks']; ?></span>
                <?php endif; ?>
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
                            Task Management
                        </h4>
                        <small class="text-muted">
                            View and manage all tasks in 
                            <span class="domain-badge ms-1"><?php echo $domain_name; ?></span>
                        </small>
                    </div>
                    <div>
                        <a href="assign_task.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Assign New Task
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Task Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card stats-total">
                    <div class="display-6 fw-bold"><?php echo $task_stats['total_tasks'] ?? 0; ?></div>
                    <div>Total Tasks</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card stats-pending">
                    <div class="display-6 fw-bold"><?php echo $task_stats['pending_tasks'] ?? 0; ?></div>
                    <div>Pending Tasks</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card stats-submitted">
                    <div class="display-6 fw-bold"><?php echo $task_stats['submitted_tasks'] ?? 0; ?></div>
                    <div>Submitted Tasks</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card stats-completed">
                    <div class="display-6 fw-bold"><?php echo $task_stats['completed_tasks'] ?? 0; ?></div>
                    <div>Completed Tasks</div>
                </div>
            </div>
        </div>

        <!-- Intern Task Summary (Added from Task History) -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-pie me-2"></i>Intern Task Summary
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>Intern Name</th>
                                <th>Total Tasks Given</th>
                                <th>Completed</th>
                                <th>Pending/Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($summary_result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($summary_result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                    <td><?php echo $row['total_tasks']; ?></td>
                                    <td class="text-success"><strong><?php echo $row['completed_count']; ?></strong></td>
                                    <td class="text-danger"><strong><?php echo $row['not_completed_count']; ?></strong></td>
                                    <td>
                                        <a href="?intern=<?php echo $row['intern_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-filter me-1"></i>Filter Tasks
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No interns found in your domain.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="not_completed" <?php echo $status_filter == 'not_completed' ? 'selected' : ''; ?>>Not Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter by Intern</label>
                        <select class="form-select" name="intern">
                            <option value="0" <?php echo $intern_filter == 0 ? 'selected' : ''; ?>>All Interns</option>
                            <?php while($intern = mysqli_fetch_assoc($interns_result)): ?>
                                <option value="<?php echo $intern['id']; ?>" <?php echo $intern_filter == $intern['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($intern['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>All Tasks</h6>
                <span class="badge bg-primary"><?php echo mysqli_num_rows($tasks_result); ?> tasks</span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($tasks_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task Title</th>
                                    <th>Intern</th>
                                    <th>Assigned Date</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th>Submitted Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($task = mysqli_fetch_assoc($tasks_result)): ?>
                                    <tr class="task-row" onclick="window.location.href='view_task.php?id=<?php echo $task['id']; ?>'">
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                            <p class="text-muted mb-0 small"><?php echo substr(htmlspecialchars($task['description']), 0, 50); ?>...</p>
                                        </td>
                                        <td onclick="event.stopPropagation();">
                                            <?php if ($task['intern_name']): ?>
                                                <a href="view_intern.php?id=<?php echo $task['intern_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($task['intern_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></td>
                                        <td>
                                            <?php if ($task['deadline']): ?>
                                                <?php 
                                                $deadline = strtotime($task['deadline']);
                                                $today = strtotime('today');
                                                $diff = $deadline - $today;
                                                $days_left = floor($diff / (60 * 60 * 24));
                                                
                                                if ($days_left < 0) {
                                                    echo '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Overdue</span><br>';
                                                    echo '<small class="text-danger">' . date('M d, Y', $deadline) . '</small>';
                                                } elseif ($days_left == 0) {
                                                    echo '<span class="text-warning"><i class="fas fa-clock me-1"></i>Today</span><br>';
                                                    echo '<small class="text-warning">' . date('M d, Y', $deadline) . '</small>';
                                                } elseif ($days_left <= 3) {
                                                    echo '<span class="text-warning">' . $days_left . ' days left</span><br>';
                                                    echo '<small class="text-warning">' . date('M d, Y', $deadline) . '</small>';
                                                } else {
                                                    echo '<span class="text-success">' . $days_left . ' days left</span><br>';
                                                    echo '<small class="text-muted">' . date('M d, Y', $deadline) . '</small>';
                                                }
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">No deadline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $task['status'];
                                            $badge_class = '';
                                            switch($status) {
                                                case 'pending':
                                                    $badge_class = 'badge-pending';
                                                    $icon = 'fas fa-clock';
                                                    break;
                                                case 'submitted':
                                                    $badge_class = 'badge-submitted';
                                                    $icon = 'fas fa-paper-plane';
                                                    break;
                                                case 'completed':
                                                    $badge_class = 'badge-completed';
                                                    $icon = 'fas fa-check-circle';
                                                    break;
                                                case 'not_completed':
                                                    $badge_class = 'badge-not-completed';
                                                    $icon = 'fas fa-times-circle';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                                    $icon = 'fas fa-question-circle';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <i class="<?php echo $icon; ?> me-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($task['submitted_date']): ?>
                                                <?php echo date('M d, Y', strtotime($task['submitted_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not submitted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td onclick="event.stopPropagation();">
                                            <div class="btn-group" role="group">
                                                <a href="review_task.php?id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-sm <?php echo $task['status'] == 'submitted' ? 'btn-outline-warning' : 'btn-outline-info'; ?>" 
                                                   title="<?php echo $task['status'] == 'submitted' ? 'Review Task' : 'Update Status'; ?>">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                                <a href="edit_task.php?id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary" 
                                                   title="Edit Task">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger delete-task" 
                                                        data-task-id="<?php echo $task['id']; ?>" 
                                                        data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                        title="Delete Task">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tasks fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Tasks Found</h4>
                        <p class="text-muted mb-4">You haven't assigned any tasks yet or no tasks match your filter criteria.</p>
                        <a href="assign_task.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus-circle me-2"></i>Assign Your First Task
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the task "<span id="taskTitle"></span>"? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete Task</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Delete task confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-task');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const taskTitleSpan = document.getElementById('taskTitle');
            const confirmDeleteLink = document.getElementById('confirmDelete');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const taskTitle = this.getAttribute('data-task-title');
                    
                    taskTitleSpan.textContent = taskTitle;
                    confirmDeleteLink.href = `delete_task.php?id=${taskId}`;
                    
                    deleteModal.show();
                });
            });
            
            // Mobile menu toggle
            if (window.innerWidth <= 768) {
                const navbar = document.querySelector('.navbar-top');
                const hamburger = document.createElement('button');
                hamburger.className = 'btn btn-primary me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
            }
        });
        
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
        
        // Auto-refresh task status indicators
        function updateTaskStatusIndicators() {
            const deadlineCells = document.querySelectorAll('td:nth-child(4)');
            const now = new Date();
            
            deadlineCells.forEach(cell => {
                const deadlineText = cell.querySelector('small');
                if (deadlineText) {
                    const deadlineDate = new Date(deadlineText.textContent);
                    const timeDiff = deadlineDate.getTime() - now.getTime();
                    const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
                    
                    // Update the status text
                    const statusSpan = cell.querySelector('span');
                    if (statusSpan) {
                        if (daysDiff < 0) {
                            statusSpan.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Overdue';
                            statusSpan.className = 'text-danger';
                        } else if (daysDiff === 0) {
                            statusSpan.innerHTML = '<i class="fas fa-clock me-1"></i>Today';
                            statusSpan.className = 'text-warning';
                        } else if (daysDiff <= 3) {
                            statusSpan.textContent = daysDiff + ' days left';
                            statusSpan.className = 'text-warning';
                        }
                    }
                }
            });
        }
        
        // Update every minute
        setInterval(updateTaskStatusIndicators, 60000);
        
        // Initial update
        updateTaskStatusIndicators();
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>