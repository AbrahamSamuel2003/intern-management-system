<?php
// Start session and database connection
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$intern_id = isset($_GET['intern_id']) ? intval($_GET['intern_id']) : 0;
if ($intern_id <= 0) {
    header('Location: overview.php');
    exit();
}

// Get company details
$company_query = "SELECT company_name, min_score_for_job FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';
$min_score = $company_data['min_score_for_job'] ?? 70;

// Get intern details
$intern_query = "
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.email,
        d.domain_name,
        p.performance_score,
        p.eligibility,
        p.last_updated,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id) as total_tasks,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'completed') as completed_tasks_count,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'not_completed') as not_completed_count
    FROM users u
    LEFT JOIN domains d ON u.domain_id = d.id
    LEFT JOIN performance p ON u.id = p.intern_id
    WHERE u.id = $intern_id AND u.role = 'intern' AND u.is_active = TRUE
";

$intern_result = mysqli_query($conn, $intern_query);
$intern = mysqli_fetch_assoc($intern_result);

if (!$intern) {
    header('Location: overview.php');
    exit();
}

// Get intern's performance details
$performance_query = "
    SELECT 
        p.total_tasks_assigned,
        p.tasks_completed,
        p.tasks_not_completed,
        p.tasks_submitted,
        p.tasks_pending,
        p.on_time_submissions,
        p.performance_score,
        p.eligibility,
        p.last_updated
    FROM performance p
    WHERE p.intern_id = $intern_id
";

$performance_result = mysqli_query($conn, $performance_query);
$performance = mysqli_fetch_assoc($performance_result);

// Get intern's tasks - REMOVED rating column
$tasks_query = "
    SELECT 
        t.title,
        t.description,
        t.status,
        t.assigned_date,
        t.deadline,
        t.submitted_date,
        tl.full_name as assigned_by_name
    FROM tasks t
    LEFT JOIN users tl ON t.assigned_by = tl.id
    WHERE t.assigned_to = $intern_id
    ORDER BY t.assigned_date DESC
";

$tasks_result = mysqli_query($conn, $tasks_query);
$tasks = mysqli_fetch_all($tasks_result, MYSQLI_ASSOC);

// Calculate stats
$total_tasks = $intern['total_tasks'] ?: 0;
$completed_tasks = $intern['completed_tasks_count'] ?: 0;
$performance_score = $intern['performance_score'] ?: 0;
$eligibility = $intern['eligibility'] ?: 'pending';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Performance: <?php echo $intern['full_name']; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { 
            background-color: #f8f9fc; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
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
        
        .navbar-top {
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .intern-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        .task-card {
            border-left: 5px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 5px;
        }
        
        .task-completed {
            border-left-color: #10b981;
            background-color: #f0fff4;
        }
        
        .task-not-completed {
            border-left-color: #ef4444;
            background-color: #fff5f5;
        }
        
        .task-pending {
            border-left-color: #f59e0b;
            background-color: #fffbeb;
        }
        
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
        
        .performance-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            margin: 0 auto;
        }
        
        .circle-excellent {
            border: 5px solid #10b981;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
        
        .circle-good {
            border: 5px solid #3b82f6;
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        
        .circle-average {
            border: 5px solid #f59e0b;
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }
        
        .circle-poor {
            border: 5px solid #ef4444;
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .stats-box {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
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
            <a href="overview.php" class="nav-link active">
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
        <!-- Navigation -->
        <nav class="navbar-top">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <h4 class="mb-0">
                            <a href="overview.php" class="text-decoration-none text-dark">
                                <i class="fas fa-arrow-left me-2"></i>
                            </a>
                            Performance Details
                        </h4>
                        <small class="text-muted"><?php echo htmlspecialchars($intern['full_name']); ?></small>
                    </div>
                    <div>
                        <a href="overview.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>Back to Overview
                        </a>
                        <a href="../intern/edit.php?id=<?php echo $intern_id; ?>" class="btn btn-outline-info ms-2">
                            <i class="fas fa-edit me-2"></i>Edit Intern
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Intern Header -->
        <div class="intern-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px;">
                                <i class="fas fa-user-graduate fa-2x"></i>
                            </div>
                        </div>
                        <div>
                            <h3 class="mb-1"><?php echo htmlspecialchars($intern['full_name']); ?></h3>
                            <p class="mb-1">
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($intern['email']); ?>
                                <span class="mx-2">|</span>
                                <i class="fas fa-user me-1"></i>@<?php echo htmlspecialchars($intern['username']); ?>
                                <span class="mx-2">|</span>
                                <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($intern['domain_name'] ?? 'No Domain'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-end align-items-center">
                        <div class="text-center">
                            <div class="performance-circle <?php
                                if ($performance_score >= 90) echo 'circle-excellent';
                                elseif ($performance_score >= 70) echo 'circle-good';
                                elseif ($performance_score >= 50) echo 'circle-average';
                                else echo 'circle-poor';
                            ?>">
                                <?php echo round($performance_score, 1); ?>%
                            </div>
                            <div class="mt-2">
                                <?php if ($eligibility == 'eligible'): ?>
                                    <span class="badge-eligible">
                                        <i class="fas fa-check-circle me-1"></i>Eligible for Job
                                    </span>
                                <?php elseif ($eligibility == 'not_eligible'): ?>
                                    <span class="badge-not-eligible">
                                        <i class="fas fa-times-circle me-1"></i>Not Eligible
                                    </span>
                                <?php else: ?>
                                    <span class="badge-pending">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-box">
                    <div class="display-6 fw-bold text-primary">
                        <?php echo $completed_tasks; ?>/<?php echo $total_tasks; ?>
                    </div>
                    <h6 class="text-muted mb-2">Tasks Completed</h6>
                    <div class="progress">
                        <div class="progress-bar bg-success" 
                             style="width: <?php echo $total_tasks > 0 ? ($completed_tasks / $total_tasks * 100) : 0; ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php echo $total_tasks > 0 ? round($completed_tasks / $total_tasks * 100, 1) : 0; ?>% Success Rate
                    </small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stats-box">
                    <div class="display-6 fw-bold <?php echo $performance_score >= $min_score ? 'text-success' : 'text-danger'; ?>">
                        <?php echo round($performance_score, 1); ?>%
                    </div>
                    <h6 class="text-muted mb-2">Performance Score</h6>
                    <div class="progress">
                        <div class="progress-bar <?php echo $performance_score >= $min_score ? 'bg-success' : 'bg-danger'; ?>" 
                             style="width: <?php echo $performance_score; ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        Min required: <?php echo $min_score; ?>%
                        <?php if ($performance_score < $min_score): ?>
                            <br><span class="text-danger">
                                Needs <?php echo $min_score - round($performance_score, 1); ?>% more
                            </span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stats-box">
                    <div class="display-6 fw-bold text-info">
                        <?php echo $intern['not_completed_count'] ?? 0; ?>
                    </div>
                    <h6 class="text-muted mb-2">Not Completed Tasks</h6>
                    <div class="progress">
                        <div class="progress-bar bg-danger" 
                             style="width: <?php echo $total_tasks > 0 ? (($intern['not_completed_count'] ?? 0) / $total_tasks * 100) : 0; ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php 
                        $not_completed_rate = $total_tasks > 0 
                            ? round(($intern['not_completed_count'] ?? 0) / $total_tasks * 100, 1) 
                            : 0;
                        echo $not_completed_rate; ?>% of tasks
                    </small>
                </div>
            </div>
        </div>

        <!-- Performance Details -->
        <?php if ($performance): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Performance Breakdown</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Task Statistics</h6>
                        <div class="mb-3">
                            <small class="text-muted d-block">Total Assigned: <span class="fw-bold"><?php echo $performance['total_tasks_assigned']; ?></span></small>
                            <small class="text-success d-block">✓ Completed: <span class="fw-bold"><?php echo $performance['tasks_completed']; ?></span></small>
                            <small class="text-info d-block">⏳ Submitted: <span class="fw-bold"><?php echo $performance['tasks_submitted']; ?></span></small>
                            <small class="text-warning d-block">🔄 Pending: <span class="fw-bold"><?php echo $performance['tasks_pending']; ?></span></small>
                            <small class="text-danger d-block">✗ Not Completed: <span class="fw-bold"><?php echo $performance['tasks_not_completed']; ?></span></small>
                        </div>
                        
                        <h6>Performance Metrics</h6>
                        <div class="mb-3">
                            <small class="text-muted d-block">On-time Submissions: <span class="fw-bold"><?php echo $performance['on_time_submissions']; ?></span></small>
                            <small class="text-muted d-block">Last Updated: <span class="fw-bold">
                                <?php 
                                if ($performance['last_updated']) {
                                    echo date('d M Y, h:i A', strtotime($performance['last_updated']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </span></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Eligibility Status</h6>
                        <div class="mb-3">
                            <?php if ($performance['eligibility'] == 'eligible'): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>This intern is eligible for job offer</strong><br>
                                    <small>Performance score meets the minimum requirement of <?php echo $min_score; ?>%</small>
                                </div>
                            <?php elseif ($performance['eligibility'] == 'not_eligible'): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-times-circle me-2"></i>
                                    <strong>This intern is not eligible for job offer</strong><br>
                                    <small>Performance score is below the minimum requirement of <?php echo $min_score; ?>%</small>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>Eligibility pending evaluation</strong><br>
                                    <small>Performance score needs to be calculated or reviewed</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h6>Recommendation</h6>
                        <div class="alert <?php echo $performance_score >= $min_score ? 'alert-success' : 'alert-danger'; ?>">
                            <?php if ($performance_score >= $min_score): ?>
                                <i class="fas fa-thumbs-up me-2"></i>
                                <strong>Recommendation: Hire</strong><br>
                                <small>Performance meets company standards for job eligibility</small>
                            <?php else: ?>
                                <i class="fas fa-thumbs-down me-2"></i>
                                <strong>Recommendation: Do Not Hire</strong><br>
                                <small>Performance does not meet minimum requirements</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Task History -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Task History</h5>
                        <div>
                            <span class="badge bg-primary"><?php echo count($tasks); ?> Total Tasks</span>
                            <span class="badge bg-success ms-1"><?php echo $completed_tasks; ?> Completed</span>
                            <span class="badge bg-danger ms-1"><?php echo $intern['not_completed_count'] ?? 0; ?> Not Completed</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): 
                                $status_class = '';
                                if ($task['status'] == 'completed') {
                                    $status_class = 'task-completed';
                                } elseif ($task['status'] == 'not_completed') {
                                    $status_class = 'task-not-completed';
                                } else {
                                    $status_class = 'task-pending';
                                }
                            ?>
                            <div class="task-card <?php echo $status_class; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                        <p class="mb-2 text-muted"><?php echo htmlspecialchars($task['description']); ?></p>
                                        
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <?php if ($task['assigned_by_name']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-user-tie me-1"></i>Assigned by: <?php echo htmlspecialchars($task['assigned_by_name']); ?>
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['assigned_date']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-calendar-plus me-1"></i>Assigned: <?php echo date('M d, Y', strtotime($task['assigned_date'])); ?>
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['deadline']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-clock me-1"></i>Deadline: <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['submitted_date']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-paper-plane me-1"></i>Submitted: <?php echo date('M d, Y', strtotime($task['submitted_date'])); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                    </div>
                                    
                                    <div class="ms-3 text-end">
                                        <?php if ($task['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($task['status'] == 'not_completed'): ?>
                                            <span class="badge bg-danger">Not Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                                <h5>No Tasks Assigned Yet</h5>
                                <p class="text-muted mb-4">This intern hasn't been assigned any tasks yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                hamburger.className = 'btn btn-primary me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
            }
        });
        
        // Print functionality
        function printPerformanceReport() {
            window.print();
        }
        
        // Add print button
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar-top .d-flex');
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-outline-secondary ms-2';
            printBtn.innerHTML = '<i class="fas fa-print me-2"></i>Print Report';
            printBtn.onclick = printPerformanceReport;
            navbar.appendChild(printBtn);
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>