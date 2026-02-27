<?php
// teamlead/reports.php

session_start();
require_once '../config/database.php';

// Check if user is logged in and is team lead
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'team_lead') {
    header('Location: ../login.php');
    exit();
}

$team_lead_id = $_SESSION['user_id'];
$domain_id = $_SESSION['domain_id'];

// Get team lead's domain
$domain_query = "SELECT domain_name FROM domains WHERE id = $domain_id";
$domain_result = mysqli_query($conn, $domain_query);
$domain_data = mysqli_fetch_assoc($domain_result);
$domain_name = $domain_data['domain_name'] ?? 'General';

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Overall Statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT u.id) as total_interns,
        SUM(CASE WHEN p.eligibility = 'eligible' THEN 1 ELSE 0 END) as eligible_interns,
        SUM(CASE WHEN p.eligibility = 'not_eligible' THEN 1 ELSE 0 END) as not_eligible_interns,
        COUNT(DISTINCT t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN t.status = 'submitted' THEN 1 ELSE 0 END) as submitted_tasks
    FROM users u
    LEFT JOIN performance p ON u.id = p.intern_id
    LEFT JOIN tasks t ON u.id = t.assigned_to
    WHERE u.role = 'intern' 
    AND u.domain_id = $domain_id
    AND u.is_active = TRUE
";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Task completion rate
$completion_rate = $stats['total_tasks'] > 0 
    ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) 
    : 0;

// Intern performance summary
$performance_query = "
    SELECT 
        u.id,
        u.full_name,
        COALESCE(p.performance_score, 0) as performance_score,
        COALESCE(p.eligibility, 'pending') as eligibility,
        COUNT(DISTINCT t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'submitted' THEN 1 ELSE 0 END) as submitted_tasks
    FROM users u
    LEFT JOIN performance p ON u.id = p.intern_id
    LEFT JOIN tasks t ON u.id = t.assigned_to
    WHERE u.role = 'intern' 
    AND u.domain_id = $domain_id
    AND u.is_active = TRUE
    GROUP BY u.id
    ORDER BY p.performance_score DESC
";

$performance_result = mysqli_query($conn, $performance_query);

// Monthly task statistics
$monthly_stats_query = "
    SELECT 
        DATE_FORMAT(assigned_date, '%Y-%m') as month,
        COUNT(*) as tasks_assigned,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as tasks_completed
    FROM tasks
    WHERE assigned_by = $team_lead_id
    AND assigned_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE_FORMAT(assigned_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";

$monthly_stats_result = mysqli_query($conn, $monthly_stats_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 250px; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); color: white; box-shadow: 0 0 15px rgba(0,0,0,0.1); z-index: 1000; }
        .main-content { margin-left: 250px; padding: 20px; min-height: 100vh; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .company-logo { font-size: 20px; font-weight: 700; color: white; text-decoration: none; }
        .sidebar-menu { padding: 20px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; margin: 5px 10px; border-radius: 5px; transition: all 0.3s; text-decoration: none; display: block; }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.1); }
        .nav-link.active { color: white; background: rgba(255,255,255,0.2); font-weight: 600; }
        .nav-link i { width: 20px; margin-right: 10px; text-align: center; }
        .user-profile { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; background: rgba(0,0,0,0.1); border-top: 1px solid rgba(255,255,255,0.1); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; color: #4e73df; font-weight: bold; margin-right: 10px; }
        .navbar-top { background: white; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); padding: 15px 20px; margin-bottom: 20px; border-radius: 10px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 1px solid #e3e6f0; padding: 15px 20px; border-radius: 10px 10px 0 0 !important; font-weight: 600; }
        .stats-card { text-align: center; padding: 20px; border-radius: 10px; color: white; margin-bottom: 20px; }
        .stats-primary { background: linear-gradient(45deg, #4e73df, #224abe); }
        .stats-success { background: linear-gradient(45deg, #1cc88a, #13855c); }
        .stats-warning { background: linear-gradient(45deg, #f6c23e, #dda20a); }
        .stats-info { background: linear-gradient(45deg, #36b9cc, #258391); }
        .progress { height: 10px; border-radius: 5px; }
        .chart-container { position: relative; height: 300px; }
        .table th { font-weight: 600; background-color: #f8f9fa; }
        .badge-eligible { background-color: #d1fae5; color: #065f46; }
        .badge-not-eligible { background-color: #fee2e2; color: #991b1b; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-left: 0; } }
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
            <a href="tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>Tasks
            </a>
            <a href="assign_task.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>Assign Task
            </a>
            <a href="messages.php" class="nav-link">
                <i class="fas fa-envelope"></i>Messages
            </a>
            <a href="reports.php" class="nav-link active">
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
                            Reports & Analytics
                        </h4>
                        <small class="text-muted">Performance insights and analytics for your domain</small>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Date Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card stats-primary">
                    <div class="display-6 fw-bold"><?php echo $stats['total_interns'] ?? 0; ?></div>
                    <div>Total Interns</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card stats-success">
                    <div class="display-6 fw-bold"><?php echo $stats['eligible_interns'] ?? 0; ?></div>
                    <div>Eligible for Job</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card stats-warning">
                    <div class="display-6 fw-bold"><?php echo $stats['total_tasks'] ?? 0; ?></div>
                    <div>Tasks Assigned</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card stats-info">
                    <div class="display-6 fw-bold"><?php echo $completion_rate; ?>%</div>
                    <div>Completion Rate</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Performance Summary Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Overview</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Status Distribution -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Task Status Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="taskStatusChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Completed</span>
                                <span class="fw-bold"><?php echo $stats['completed_tasks'] ?? 0; ?></span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo $stats['total_tasks'] > 0 ? ($stats['completed_tasks'] / $stats['total_tasks'] * 100) : 0; ?>%">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-1">
                                <span>Submitted</span>
                                <span class="fw-bold"><?php echo $stats['submitted_tasks'] ?? 0; ?></span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-info" 
                                     style="width: <?php echo $stats['total_tasks'] > 0 ? ($stats['submitted_tasks'] / $stats['total_tasks'] * 100) : 0; ?>%">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-1">
                                <span>Pending</span>
                                <span class="fw-bold"><?php echo $stats['pending_tasks'] ?? 0; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" 
                                     style="width: <?php echo $stats['total_tasks'] > 0 ? ($stats['pending_tasks'] / $stats['total_tasks'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Intern Performance Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-table me-2"></i>Intern Performance Details</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Intern Name</th>
                                <th>Performance Score</th>
                                <th>Total Tasks</th>
                                <th>Completed</th>
                                <th>Submitted</th>
                                <th>Completion Rate</th>
                                <th>Eligibility Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($performance_result) > 0): ?>
                                <?php while($perf = mysqli_fetch_assoc($performance_result)): 
                                    $intern_completion_rate = $perf['total_tasks'] > 0 
                                        ? round(($perf['completed_tasks'] / $perf['total_tasks']) * 100, 1) 
                                        : 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($perf['full_name']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="fw-bold <?php echo $perf['performance_score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $perf['performance_score']; ?>%
                                        </div>
                                    </td>
                                    <td><?php echo $perf['total_tasks']; ?></td>
                                    <td><?php echo $perf['completed_tasks']; ?></td>
                                    <td><?php echo $perf['submitted_tasks']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2"><?php echo $intern_completion_rate; ?>%</div>
                                            <div class="progress flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar <?php echo $intern_completion_rate >= 70 ? 'bg-success' : 'bg-warning'; ?>" 
                                                     style="width: <?php echo $intern_completion_rate; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($perf['eligibility'] == 'eligible'): ?>
                                            <span class="badge badge-eligible">Eligible</span>
                                        <?php elseif ($perf['eligibility'] == 'not_eligible'): ?>
                                            <span class="badge badge-not-eligible">Not Eligible</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_intern.php?id=<?php echo $perf['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-user-graduate fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No performance data available</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Monthly Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Monthly Task Statistics</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Tasks Assigned</th>
                                <th>Tasks Completed</th>
                                <th>Completion Rate</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($monthly_stats_result) > 0): ?>
                                <?php while($monthly = mysqli_fetch_assoc($monthly_stats_result)): 
                                    $month_completion_rate = $monthly['tasks_assigned'] > 0 
                                        ? round(($monthly['tasks_completed'] / $monthly['tasks_assigned']) * 100, 1) 
                                        : 0;
                                ?>
                                <tr>
                                    <td>
                                        <?php echo date('F Y', strtotime($monthly['month'] . '-01')); ?>
                                    </td>
                                    <td><?php echo $monthly['tasks_assigned']; ?></td>
                                    <td><?php echo $monthly['tasks_completed']; ?></td>
                                    <td>
                                        <span class="fw-bold <?php echo $month_completion_rate >= 70 ? 'text-success' : 'text-warning'; ?>">
                                            <?php echo $month_completion_rate; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($month_completion_rate >= 80): ?>
                                            <i class="fas fa-arrow-up text-success"></i> Excellent
                                        <?php elseif ($month_completion_rate >= 60): ?>
                                            <i class="fas fa-arrow-up text-warning"></i> Good
                                        <?php else: ?>
                                            <i class="fas fa-arrow-down text-danger"></i> Needs Improvement
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="fas fa-chart-bar fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No monthly statistics available</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($performance_result, 0);
                    $names = [];
                    while($perf = mysqli_fetch_assoc($performance_result)) {
                        $names[] = "'" . addslashes($perf['full_name']) . "'";
                    }
                    echo implode(', ', $names);
                    ?>
                ],
                datasets: [{
                    label: 'Performance Score (%)',
                    data: [
                        <?php 
                        mysqli_data_seek($performance_result, 0);
                        $scores = [];
                        while($perf = mysqli_fetch_assoc($performance_result)) {
                            $scores[] = $perf['performance_score'];
                        }
                        echo implode(', ', $scores);
                        ?>
                    ],
                    backgroundColor: 'rgba(78, 115, 223, 0.5)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Score (%)'
                        }
                    }
                }
            }
        });

        // Task Status Chart
        const taskStatusCtx = document.getElementById('taskStatusChart').getContext('2d');
        const taskStatusChart = new Chart(taskStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Submitted', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $stats['completed_tasks'] ?? 0; ?>,
                        <?php echo $stats['submitted_tasks'] ?? 0; ?>,
                        <?php echo $stats['pending_tasks'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
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

        // Print function
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>