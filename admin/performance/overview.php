<?php
// Start session and database connection
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get company name
$company_query = "SELECT company_name, min_score_for_job FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';
$min_score = $company_data['min_score_for_job'] ?? 70;

// Get filter parameters
$domain_filter = isset($_GET['domain']) ? intval($_GET['domain']) : 0;
$eligibility_filter = isset($_GET['eligibility']) ? $_GET['eligibility'] : 'all';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Get domains for filter dropdown
$domains_query = "SELECT id, domain_name FROM domains ORDER BY domain_name";
$domains_result = mysqli_query($conn, $domains_query);

// Build performance query with filters - FIXED COLUMNS
$performance_query = "
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.is_active,
        d.domain_name,
        p.performance_score,
        p.eligibility,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id) as total_tasks,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'completed') as tasks_completed,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status != 'completed') as tasks_not_completed,
        (SELECT AVG(rating) FROM tasks t WHERE t.assigned_to = u.id) as average_rating,
        p.last_updated
    FROM users u
    LEFT JOIN domains d ON u.domain_id = d.id
    LEFT JOIN performance p ON u.id = p.intern_id
    WHERE u.role = 'intern' AND u.is_active = TRUE
";

// Apply filters
if ($domain_filter > 0) {
    $performance_query .= " AND u.domain_id = $domain_filter";
}

if ($eligibility_filter != 'all') {
    $performance_query .= " AND p.eligibility = '$eligibility_filter'";
}

if (!empty($search_query)) {
    $performance_query .= " AND (u.full_name LIKE '%$search_query%' OR u.username LIKE '%$search_query%' OR u.email LIKE '%$search_query%')";
}

$performance_query .= " ORDER BY p.performance_score DESC, u.full_name ASC";

$performance_result = mysqli_query($conn, $performance_query);

// Calculate overall statistics - FIXED QUERY
$stats_query = "
    SELECT 
        COUNT(*) as total_interns,
        SUM(CASE WHEN p.eligibility = 'eligible' THEN 1 ELSE 0 END) as eligible_count,
        SUM(CASE WHEN p.eligibility = 'not_eligible' THEN 1 ELSE 0 END) as not_eligible_count,
        SUM(CASE WHEN p.eligibility = 'pending' OR p.eligibility IS NULL THEN 1 ELSE 0 END) as pending_count,
        AVG(p.performance_score) as avg_score,
        MIN(p.performance_score) as min_score_val,
        MAX(p.performance_score) as max_score_val
    FROM users u
    LEFT JOIN performance p ON u.id = p.intern_id
    WHERE u.role = 'intern' AND u.is_active = TRUE
";

if ($domain_filter > 0) {
    $stats_query .= " AND u.domain_id = $domain_filter";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Performance Overview</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    
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
        
        /* Top Navigation */
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
        
        /* Performance Score Cards */
        .score-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }
        
        .score-card:hover {
            transform: translateY(-5px);
        }
        
        .score-excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .score-good {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }
        
        .score-average {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .score-poor {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .score-pending {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }
        
        /* Score Circle */
        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-weight: bold;
            font-size: 24px;
        }
        
        .circle-excellent { 
            background: rgba(16, 185, 129, 0.1); 
            color: #10b981; 
            border: 5px solid #10b981; 
        }
        .circle-good { 
            background: rgba(59, 130, 246, 0.1); 
            color: #3b82f6; 
            border: 5px solid #3b82f6; 
        }
        .circle-average { 
            background: rgba(245, 158, 11, 0.1); 
            color: #f59e0b; 
            border: 5px solid #f59e0b; 
        }
        .circle-poor { 
            background: rgba(239, 68, 68, 0.1); 
            color: #ef4444; 
            border: 5px solid #ef4444; 
        }
        .circle-pending { 
            background: rgba(107, 114, 128, 0.1); 
            color: #6b7280; 
            border: 5px solid #6b7280; 
        }
        
        /* Progress Bar */
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        /* Table Styles */
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
        
        /* Eligibility Badges */
        .badge-eligible {
            background-color: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .badge-not-eligible {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        /* Score Indicator */
        .score-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
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
        <!-- Top Navigation Bar -->
        <nav class="navbar-top">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <h4 class="mb-0">Performance Overview</h4>
                        <small class="text-muted">Track intern performance and job eligibility</small>
                    </div>
                    <div>
                        <a href="details.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-bar me-2"></i>Detailed Reports
                        </a>

                    </div>
                </div>
            </div>
        </nav>

        <!-- Performance Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="score-card score-excellent">
                    <div class="score-circle circle-excellent">
                        <?php echo $stats['eligible_count'] ?? 0; ?>
                    </div>
                    <h5 class="mb-2">Eligible</h5>
                    <small>Ready for Job Offer</small>
                    <?php if ($stats['total_interns'] > 0): ?>
                    <div class="progress mt-2 bg-white bg-opacity-25">
                        <div class="progress-bar bg-white" 
                             style="width: <?php echo ($stats['eligible_count'] / $stats['total_interns'] * 100); ?>%">
                        </div>
                    </div>
                    <small class="d-block mt-2">
                        <?php echo round(($stats['eligible_count'] / $stats['total_interns'] * 100), 1); ?>% of interns
                    </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="score-card score-average">
                    <div class="score-circle circle-average">
                        <?php echo $stats['not_eligible_count'] ?? 0; ?>
                    </div>
                    <h5 class="mb-2">Not Eligible</h5>
                    <small>Needs Improvement</small>
                    <?php if ($stats['total_interns'] > 0): ?>
                    <div class="progress mt-2 bg-white bg-opacity-25">
                        <div class="progress-bar bg-white" 
                             style="width: <?php echo ($stats['not_eligible_count'] / $stats['total_interns'] * 100); ?>%">
                        </div>
                    </div>
                    <small class="d-block mt-2">
                        <?php echo round(($stats['not_eligible_count'] / $stats['total_interns'] * 100), 1); ?>% of interns
                    </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="score-card score-pending">
                    <div class="score-circle circle-pending">
                        <?php echo $stats['pending_count'] ?? 0; ?>
                    </div>
                    <h5 class="mb-2">Pending</h5>
                    <small>Evaluation Needed</small>
                    <?php if ($stats['total_interns'] > 0): ?>
                    <div class="progress mt-2 bg-white bg-opacity-25">
                        <div class="progress-bar bg-white" 
                             style="width: <?php echo ($stats['pending_count'] / $stats['total_interns'] * 100); ?>%">
                        </div>
                    </div>
                    <small class="d-block mt-2">
                        <?php echo round(($stats['pending_count'] / $stats['total_interns'] * 100), 1); ?>% of interns
                    </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="score-card score-good">
                    <div class="score-circle circle-good">
                        <?php echo round($stats['avg_score'] ?? 0, 1); ?>%
                    </div>
                    <h5 class="mb-2">Average Score</h5>
                    <small>Across All Interns</small>
                    <div class="mt-3">
                        <small class="d-block">
                            <i class="fas fa-arrow-up text-white me-1"></i>
                            High: <?php echo round($stats['max_score_val'] ?? 0, 1); ?>%
                        </small>
                        <small class="d-block">
                            <i class="fas fa-arrow-down text-white me-1"></i>
                            Low: <?php echo round($stats['min_score_val'] ?? 0, 1); ?>%
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Interns</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   placeholder="Search by name or email">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Filter by Domain</label>
                        <select class="form-select" name="domain">
                            <option value="0">All Domains</option>
                            <?php mysqli_data_seek($domains_result, 0); ?>
                            <?php while($domain = mysqli_fetch_assoc($domains_result)): ?>
                            <option value="<?php echo $domain['id']; ?>" 
                                <?php echo ($domain_filter == $domain['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($domain['domain_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Filter by Eligibility</label>
                        <select class="form-select" name="eligibility">
                            <option value="all" <?php echo ($eligibility_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="eligible" <?php echo ($eligibility_filter == 'eligible') ? 'selected' : ''; ?>>Eligible Only</option>
                            <option value="not_eligible" <?php echo ($eligibility_filter == 'not_eligible') ? 'selected' : ''; ?>>Not Eligible Only</option>
                            <option value="pending" <?php echo ($eligibility_filter == 'pending') ? 'selected' : ''; ?>>Pending Only</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>



        <!-- Performance Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Intern Performance Details</h5>
                <div>
                    <span class="badge bg-primary">
                        <?php echo mysqli_num_rows($performance_result); ?> Interns
                    </span>
                    <?php if ($domain_filter > 0): ?>
                    <span class="badge bg-info ms-2">
                        Domain Filter Applied
                    </span>
                    <?php endif; ?>
                    <?php if ($eligibility_filter != 'all'): ?>
                    <span class="badge bg-warning ms-2">
                        <?php echo ucfirst($eligibility_filter); ?> Only
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($performance_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Intern Details</th>
                                    <th>Domain</th>
                                    <th>Task Stats</th>

                                    <th>Eligibility</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                mysqli_data_seek($performance_result, 0);
                                while($intern = mysqli_fetch_assoc($performance_result)): 
                                    $task_completion = ($intern['total_tasks'] > 0) 
                                        ? round(($intern['tasks_completed'] / $intern['total_tasks']) * 100) 
                                        : 0;
                                    
                                    // Determine score class
                                    $score_class = 'text-muted';
                                    $score_circle_class = 'circle-pending';
                                    if ($intern['performance_score'] !== null) {
                                        if ($intern['performance_score'] >= 90) {
                                            $score_class = 'text-success';
                                            $score_circle_class = 'circle-excellent';
                                        } elseif ($intern['performance_score'] >= 70) {
                                            $score_class = 'text-info';
                                            $score_circle_class = 'circle-good';
                                        } elseif ($intern['performance_score'] >= 50) {
                                            $score_class = 'text-warning';
                                            $score_circle_class = 'circle-average';
                                        } else {
                                            $score_class = 'text-danger';
                                            $score_circle_class = 'circle-poor';
                                        }
                                    }
                                ?>
                                <tr onclick="window.location='details.php?intern_id=<?php echo $intern['id']; ?>'" style="cursor: pointer;">
                                    <td>
                                        <div class="fw-bold text-center">
                                            #<?php echo $rank; ?>
                                            <?php if ($rank <= 3): ?>
                                            <div class="text-warning">
                                                <i class="fas fa-trophy"></i>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user-graduate text-primary"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($intern['full_name']); ?></h6>
                                                <small class="text-muted">@<?php echo htmlspecialchars($intern['username']); ?></small>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($intern['email']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($intern['domain_name']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-layer-group me-1"></i>
                                                <?php echo htmlspecialchars($intern['domain_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">No Domain</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mb-1">
                                            <small class="text-muted d-block">
                                                Total: <?php echo $intern['total_tasks']; ?>
                                            </small>
                                            <small class="text-success d-block">
                                                <i class="fas fa-check me-1"></i>Completed: <?php echo $intern['tasks_completed']; ?>
                                            </small>
                                            <small class="text-danger d-block">
                                                <i class="fas fa-times me-1"></i>Not Completed: <?php echo $intern['tasks_not_completed']; ?>
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $task_completion; ?>%">
                                            </div>
                                        </div>
                                    </td>

                                    <td>
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
                                        
                                        <?php if ($intern['performance_score'] !== null && $intern['performance_score'] < $min_score && $intern['eligibility'] != 'eligible'): ?>
                                        <div class="mt-1">
                                            <small class="text-danger">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Needs <?php echo $min_score - round($intern['performance_score']); ?>% more
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                            if ($intern['last_updated']) {
                                                echo date('d M Y', strtotime($intern['last_updated']));
                                            } else {
                                                echo 'Never';
                                            }
                                            ?>
                                        </small>
                                        <?php if ($intern['last_updated']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('h:i A', strtotime($intern['last_updated'])); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="details.php?intern_id=<?php echo $intern['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               onclick="event.stopPropagation()">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../intern/manage.php" 
                                               class="btn btn-outline-info"
                                               onclick="event.stopPropagation()">
                                                <i class="fas fa-user-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php $rank++; endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <h5>No Performance Data Found</h5>
                        <p class="text-muted mb-4">
                            <?php if ($domain_filter > 0 || $eligibility_filter != 'all' || !empty($search_query)): ?>
                                Try changing your filters or search term
                            <?php else: ?>
                                Performance data will appear when team leads start reviewing tasks
                            <?php endif; ?>
                        </p>
                        <a href="../intern/manage.php" class="btn btn-primary">
                            <i class="fas fa-user-graduate me-2"></i>View All Interns
                        </a>
                    </div>
                <?php endif; ?>
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
        
        // Add export functionality
        // Export to Excel (.xlsx)
        function exportToExcel() {
            const table = document.querySelector('table');
            const wb = XLSX.utils.table_to_book(table, { sheet: "Performance Report" });
            XLSX.writeFile(wb, "performance_report_<?php echo date('Y-m-d'); ?>.xlsx");
        }
        
        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'a4'); // Landscape, points, a4
            
            // Add title
            doc.setFontSize(20);
            doc.text("<?php echo $company_name; ?> - Performance Overview", 40, 40);
            doc.setFontSize(12);
            doc.text("Date: <?php echo date('F j, Y'); ?>", 40, 60);
            
            // Add statistics summary
            doc.setFontSize(14);
            doc.text("Summary Stats:", 40, 90);
            doc.setFontSize(10);
            doc.text("Total Interns: <?php echo $stats['total_interns'] ?? 0; ?>", 40, 110);
            doc.text("Eligible: <?php echo $stats['eligible_count'] ?? 0; ?>", 180, 110);
            doc.text("Average Score: <?php echo round($stats['avg_score'] ?? 0, 1); ?>%", 320, 110);
            
            // Extract table data - skipping some complex formatted columns for simplicity
            const table = document.querySelector('table');
            const data = [];
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const rowData = [];
                const cols = row.querySelectorAll('td');
                
                // Rank
                rowData.push(cols[0].innerText.replace(/\n/g, ' ').trim());
                // Name (extracting just the name from the details column)
                rowData.push(cols[1].querySelector('h6').innerText);
                // Domain
                rowData.push(cols[2].innerText.replace(/\n/g, ' ').trim());
                // Tasks
                rowData.push(cols[3].innerText.replace(/\n/g, ' ').trim());
                // Eligibility
                rowData.push(cols[4].innerText.replace(/\n/g, ' ').trim());
                // Date
                rowData.push(cols[5].innerText.replace(/\n/g, ' ').trim());
                
                data.push(rowData);
            });
            
            doc.autoTable({
                head: [['Rank', 'Intern Name', 'Domain', 'Task Stats', 'Eligibility', 'Last Updated']],
                body: data,
                startY: 130,
                theme: 'grid',
                headStyles: { fillColor: [78, 115, 223] }, // Matches company blue
                styles: { fontSize: 9 }
            });
            
            doc.save("performance_report_<?php echo date('Y-m-d'); ?>.pdf");
        }
        
        // Add export buttons to header
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('table');
            if (!table) return;
            
            const headerActions = document.querySelector('.card-header .d-flex') || document.querySelector('.card-header');
            
            // Container for export buttons
            const btnGroup = document.createElement('div');
            btnGroup.className = 'btn-group btn-group-sm ms-2';
            
            // Excel Button
            const excelBtn = document.createElement('button');
            excelBtn.className = 'btn btn-success';
            excelBtn.innerHTML = '<i class="fas fa-file-excel me-1"></i> Excel';
            excelBtn.onclick = exportToExcel;
            btnGroup.appendChild(excelBtn);
            
            // PDF Button
            const pdfBtn = document.createElement('button');
            pdfBtn.className = 'btn btn-danger';
            pdfBtn.innerHTML = '<i class="fas fa-file-pdf me-1"></i> PDF';
            pdfBtn.onclick = exportToPDF;
            btnGroup.appendChild(pdfBtn);
            
            headerActions.appendChild(btnGroup);
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>