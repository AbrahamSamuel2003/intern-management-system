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

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause based on filter
// Logic: Tasks only show if NOT completed and NOT past deadline
$base_where = "t.assigned_to = $intern_id AND t.status != 'completed' AND t.deadline >= CURDATE()";

if ($filter == 'pending') {
    $where_clause = "$base_where AND t.status = 'pending'";
} elseif ($filter == 'submitted') {
    $where_clause = "$base_where AND t.status = 'submitted'";
} else {
    $where_clause = $base_where;
}

// Add search if provided
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_clause .= " AND (t.title LIKE '%$search%' OR t.description LIKE '%$search%')";
}

// Get tasks for current intern
$tasks_query = "
    SELECT 
        t.*,
        u.full_name as assigned_by_name,
        d.domain_name,
        DATEDIFF(t.deadline, CURDATE()) as days_left
    FROM tasks t
    LEFT JOIN users u ON t.assigned_by = u.id
    LEFT JOIN domains d ON t.domain_id = d.id
    WHERE $where_clause
    ORDER BY 
        CASE 
            WHEN t.status = 'pending' AND t.deadline < CURDATE() THEN 0
            WHEN t.status = 'pending' THEN 1
            WHEN t.status = 'submitted' THEN 2
            ELSE 3
        END,
        t.deadline ASC
";

$tasks_result = mysqli_query($conn, $tasks_query);
$total_tasks = mysqli_num_rows($tasks_result);

// Get counts for filter badges
$counts_query = "
    SELECT 
        COUNT(*) as all_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_tasks
    FROM tasks 
    WHERE assigned_to = $intern_id AND status != 'completed' AND deadline >= CURDATE()
";

$counts_result = mysqli_query($conn, $counts_query);
$counts = mysqli_fetch_assoc($counts_result);

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
    <title><?php echo $company_name; ?> - My Tasks</title>
    
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
        
        /* Task Status Badges */
        .badge-pending { background-color: #f6c23e; color: #000; }
        .badge-submitted { background-color: #36b9cc; color: #fff; }
        .badge-completed { background-color: #1cc88a; color: #fff; }
        .badge-overdue { background-color: #e74a3b; color: #fff; }
        
        /* Task Cards */
        .task-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .task-pending { border-left-color: #f6c23e; }
        .task-submitted { border-left-color: #36b9cc; }
        .task-completed { border-left-color: #1cc88a; }
        .task-overdue { border-left-color: #e74a3b; animation: pulse 2s infinite; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }
        
        /* Filter Badges */
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-badge.active {
            background-color: #4e73df !important;
            color: white !important;
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
        
        /* Table Styles */
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
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
            
            <a href="my_tasks.php" class="nav-link active">
                <i class="fas fa-tasks"></i>My Tasks
            </a>
            
            <a href="submit_task.php" class="nav-link">
                <i class="fas fa-paper-plane"></i>Submit Task
            </a>
            
            
            <a href="messages.php" class="nav-link">
                <i class="fas fa-envelope"></i>Messages
            </a>
            
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user"></i>Profile
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-white-50">Intern</small>
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
                        <h4 class="mb-0">My Tasks</h4>
                        <small class="text-muted">View and manage your assigned tasks</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="me-3 text-end">
                            <small class="text-muted d-block">Total Tasks</small>
                            <strong><?php echo $total_tasks; ?></strong>
                        </span>
                        <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Search and Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <!-- Search Box -->
                            <div class="col-md-6 mb-3">
                                <form method="GET" action="">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if(!empty($search)): ?>
                                        <a href="my_tasks.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Filter Badges -->
                            <div class="col-md-6">
                                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                    <a href="my_tasks.php?filter=all" class="badge bg-secondary text-decoration-none filter-badge <?php echo $filter == 'all' ? 'active' : ''; ?>">
                                        All <span class="badge bg-light text-dark"><?php echo $counts['all_tasks']; ?></span>
                                    </a>
                                    <a href="my_tasks.php?filter=pending" class="badge bg-warning text-dark text-decoration-none filter-badge <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                                        Pending <span class="badge bg-light text-dark"><?php echo $counts['pending_tasks']; ?></span>
                                    </a>
                                    <a href="my_tasks.php?filter=submitted" class="badge bg-info text-decoration-none filter-badge <?php echo $filter == 'submitted' ? 'active' : ''; ?>">
                                        Submitted <span class="badge bg-light text-dark"><?php echo $counts['submitted_tasks']; ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            <?php 
                            $filter_titles = [
                                'all' => 'Active Tasks',
                                'pending' => 'Pending Tasks',
                                'submitted' => 'Submitted Tasks'
                            ];
                            echo $filter_titles[$filter] . ' (' . $total_tasks . ')';
                            ?>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if($total_tasks > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Task</th>
                                            <th>Assigned By</th>
                                            <th>Assigned Date</th>
                                            <th>Deadline</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($task = mysqli_fetch_assoc($tasks_result)): 
                                            // Determine status class
                                            $status_class = 'badge-' . $task['status'];
                                            $task_class = 'task-' . $task['status'];
                                            
                                            if($task['status'] == 'pending' && $task['days_left'] < 0) {
                                                $status_class = 'badge-overdue';
                                                $task_class = 'task-overdue';
                                                $task['status'] = 'overdue';
                                            }
                                            
                                            // Format dates
                                            $assigned_date = date('M d, Y', strtotime($task['assigned_date']));
                                            $deadline_date = date('M d, Y', strtotime($task['deadline']));
                                        ?>
                                        <tr class="<?php echo $task_class; ?>">
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                                <?php if(!empty($task['description'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 80)); ?><?php echo strlen($task['description']) > 80 ? '...' : ''; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($task['assigned_by_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($task['domain_name']); ?></small>
                                            </td>
                                            <td><?php echo $assigned_date; ?></td>
                                            <td>
                                                <div><?php echo $deadline_date; ?></div>
                                                <?php if($task['status'] == 'pending' || $task['status'] == 'overdue'): ?>
                                                <small class="<?php echo $task['days_left'] < 3 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                    <?php 
                                                    if($task['days_left'] > 0) {
                                                        echo $task['days_left'] . ' days left';
                                                    } elseif($task['days_left'] < 0) {
                                                        echo abs($task['days_left']) . ' days overdue';
                                                    } else {
                                                        echo 'Today';
                                                    }
                                                    ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($task['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if($task['status'] == 'pending' || $task['status'] == 'overdue'): ?>
                                                    <a href="submit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-paper-plane me-1"></i>Submit
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="my_tasks.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-secondary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if($task['status'] == 'pending' && $task['days_left'] < 3): ?>
                                                    <a href="messages.php?compose=1&task_id=<?php echo $task['id']; ?>" class="btn btn-warning" title="Request Extension">
                                                        <i class="fas fa-clock"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <?php if(!empty($search)): ?>
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5>No tasks found</h5>
                                <p class="text-muted">No tasks match your search criteria.</p>
                                <a href="my_tasks.php" class="btn btn-primary">Clear Search</a>
                                <?php else: ?>
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <h5>No tasks assigned</h5>
                                <p class="text-muted">
                                    <?php 
                                    if($filter == 'pending') echo "You don't have any pending tasks!";
                                    elseif($filter == 'overdue') echo "Great! No overdue tasks.";
                                    elseif($filter == 'completed') echo "No completed tasks yet.";
                                    else echo "Your team lead hasn't assigned any tasks yet.";
                                    ?>
                                </p>
                                <?php if($filter != 'all'): ?>
                                <a href="my_tasks.php?filter=all" class="btn btn-primary">View All Tasks</a>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Task Summary -->
                    <?php if($total_tasks > 0): ?>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6 text-center">
                                <h5 class="mb-1"><?php echo $counts['pending_tasks']; ?></h5>
                                <small class="text-muted">Pending</small>
                            </div>
                            <div class="col-md-6 text-center">
                                <h5 class="mb-1"><?php echo $counts['submitted_tasks']; ?></h5>
                                <small class="text-muted">Submitted</small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
            
            // Auto-refresh for pending/overdue tasks
            <?php if($filter == 'pending' || $filter == 'overdue'): ?>
            setTimeout(function() {
                window.location.reload();
            }, 30000); // Refresh every 30 seconds
            <?php endif; ?>
        });
        
        // Task status animation for overdue tasks
        document.addEventListener('DOMContentLoaded', function() {
            const overdueTasks = document.querySelectorAll('.task-overdue');
            overdueTasks.forEach(task => {
                setInterval(() => {
                    task.style.opacity = task.style.opacity === '0.8' ? '1' : '0.8';
                }, 1000);
            });
        });
    </script>
</body>
</html>