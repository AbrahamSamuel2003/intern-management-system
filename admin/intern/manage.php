<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../../config/database.php';

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Handle status toggle (activate/deactivate)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $intern_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'deactivate') {
        $update_query = "UPDATE users SET is_active = FALSE WHERE id = $intern_id AND role = 'intern'";
        mysqli_query($conn, $update_query);
        $_SESSION['message'] = 'Intern deactivated successfully';
        $_SESSION['message_type'] = 'success';
    } elseif ($_GET['action'] === 'activate') {
        $update_query = "UPDATE users SET is_active = TRUE WHERE id = $intern_id AND role = 'intern'";
        mysqli_query($conn, $update_query);
        $_SESSION['message'] = 'Intern activated successfully';
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: manage.php');
    exit();
}

// Handle single deletion
if (isset($_GET['delete'])) {
    $intern_id = intval($_GET['delete']);
    
    // Start transaction to delete related data
    mysqli_begin_transaction($conn);
    
    try {
        // Delete related performance records
        mysqli_query($conn, "DELETE FROM performance WHERE intern_id = $intern_id");
        
        // Delete related tasks
        mysqli_query($conn, "DELETE FROM tasks WHERE assigned_to = $intern_id");
        
        // Delete related messages
        mysqli_query($conn, "DELETE FROM messages WHERE sender_id = $intern_id OR receiver_id = $intern_id");
        
        // Delete the user
        $delete_query = "DELETE FROM users WHERE id = $intern_id AND role = 'intern'";
        mysqli_query($conn, $delete_query);
        
        mysqli_commit($conn);
        $_SESSION['message'] = 'Intern and all related data deleted successfully';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = 'Error deleting intern: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: manage.php');
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $selected_ids = array_map('intval', explode(',', $_POST['selected_ids']));
    $ids_string = implode(',', $selected_ids);
    
    if (empty($selected_ids)) {
        $_SESSION['message'] = 'No interns selected';
        $_SESSION['message_type'] = 'warning';
        header('Location: manage.php');
        exit();
    }

    if ($_POST['bulk_action'] === 'activate') {
        $update_query = "UPDATE users SET is_active = TRUE WHERE id IN ($ids_string) AND role = 'intern'";
        mysqli_query($conn, $update_query);
        $_SESSION['message'] = 'Selected interns activated successfully';
        $_SESSION['message_type'] = 'success';
    } elseif ($_POST['bulk_action'] === 'deactivate') {
        $update_query = "UPDATE users SET is_active = FALSE WHERE id IN ($ids_string) AND role = 'intern'";
        mysqli_query($conn, $update_query);
        $_SESSION['message'] = 'Selected interns deactivated successfully';
        $_SESSION['message_type'] = 'success';
    } elseif ($_POST['bulk_action'] === 'delete') {
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM performance WHERE intern_id IN ($ids_string)");
            mysqli_query($conn, "DELETE FROM tasks WHERE assigned_to IN ($ids_string)");
            mysqli_query($conn, "DELETE FROM messages WHERE sender_id IN ($ids_string) OR receiver_id IN ($ids_string)");
            mysqli_query($conn, "DELETE FROM users WHERE id IN ($ids_string) AND role = 'intern'");
            
            mysqli_commit($conn);
            $_SESSION['message'] = 'Selected interns and their data deleted successfully';
            $_SESSION['message_type'] = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['message'] = 'Error in bulk deletion';
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header('Location: manage.php');
    exit();
}

// Get session messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Build query with filters
$where_conditions = ["role = 'intern'"];

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(u.full_name LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%')";
}

// Domain filter
$domain_filter = isset($_GET['domain']) ? intval($_GET['domain']) : 0;
if ($domain_filter > 0) {
    $where_conditions[] = "u.domain_id = $domain_filter";
}

// Status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
if ($status_filter === 'active') {
    $where_conditions[] = "u.is_active = TRUE";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "u.is_active = FALSE";
}

$where_clause = implode(' AND ', $where_conditions);

// Get all interns with their details
$interns_query = "
    SELECT 
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        u.domain_id,
        u.is_active,
        u.join_date,
        u.end_date,
        u.created_at,
        d.domain_name,
        (
            SELECT COUNT(*) 
            FROM tasks t 
            WHERE t.assigned_to = u.id
        ) as total_tasks,
        (
            SELECT COUNT(*) 
            FROM tasks t 
            WHERE t.assigned_to = u.id AND t.status = 'completed'
        ) as completed_tasks,
        (
            SELECT p.performance_score 
            FROM performance p 
            WHERE p.intern_id = u.id 
            ORDER BY p.id DESC LIMIT 1
        ) as performance_score
    FROM users u
    LEFT JOIN domains d ON u.domain_id = d.id
    WHERE $where_clause
    ORDER BY u.is_active DESC, u.full_name ASC
";
$interns_result = mysqli_query($conn, $interns_query);

// Get all domains for filter dropdown
$domains_query = "SELECT id, domain_name FROM domains ORDER BY domain_name";
$domains_result = mysqli_query($conn, $domains_query);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_interns,
        SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_interns,
        SUM(CASE WHEN domain_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_interns
    FROM users 
    WHERE role = 'intern'
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$total_interns = mysqli_num_rows($interns_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Manage Interns</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
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
            transition: all 0.3s;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
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
            margin-bottom: 25px;
            border-radius: 10px;
        }
        
        /* Sections */
        .section-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #4e73df;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-left: 4px solid #4e73df;
        }

        .stat-card.active { border-left-color: #1cc88a; }
        .stat-card.assigned { border-left-color: #36b9cc; }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #5a5c69;
        }
        
        .stat-label {
            color: #4e73df;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Filter Controls */
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: flex-end;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #5a5c69;
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid #d1d3e2;
            padding: 8px 12px;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            padding: 8px 20px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #224abe;
            border-color: #224abe;
        }

        .btn-outline-secondary {
            border-color: #d1d3e2;
            color: #858796;
        }

        .btn-outline-secondary:hover {
            background-color: #eaecf4;
            color: #5a5c69;
            border-color: #d1d3e2;
        }

        /* Table Styles */
        .table thead th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            border-bottom: 2px solid #e3e6f0;
            padding: 12px;
        }

        .table td {
            vertical-align: middle;
            padding: 15px 12px;
            color: #5a5c69;
            border-bottom: 1px solid #e3e6f0;
        }

        .intern-profile {
            display: flex;
            align-items: center;
        }

        .intern-avatar {
            width: 40px;
            height: 40px;
            background-color: #4e73df;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 12px;
        }

        .intern-name {
            font-weight: 700;
            color: #4e73df;
            margin-bottom: 2px;
            display: block;
            text-decoration: none;
        }

        .intern-email {
            font-size: 12px;
            color: #858796;
        }

        .badge-domain {
            background-color: #eaecf4;
            color: #4e73df;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 11px;
        }

        .status-badge {
            font-weight: 700;
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .bg-success-light { background-color: #1cc88a; color: white; }
        .bg-danger-light { background-color: #e74a3b; color: white; }

        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #eaecf4;
        }

        .progress-bar {
            background-color: #4e73df;
        }

        /* Bulk Component */
        .bulk-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-action-edit { background-color: #4e73df; color: white; }
        .btn-action-delete { background-color: #e74a3b; color: white; }
        .btn-action-status { background-color: #f6c23e; color: white; }

        .btn-action:hover {
            opacity: 0.8;
            color: white;
            transform: scale(1.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; }
            .stats-row { grid-template-columns: 1fr; }
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
            <a href="manage.php" class="nav-link active">
                <i class="fas fa-user-graduate"></i>Interns
            </a>
            <a href="../performance/overview.php" class="nav-link">
                <i class="fas fa-chart-line"></i>Performance
            </a>
            <a href="../settings/company.php" class="nav-link">
                <i class="fas fa-cog"></i>Settings
            </a>
        
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0 text-white"><?php echo $_SESSION['full_name'] ?? 'Admin'; ?></h6>
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
                        <h4 class="mb-0 text-gray-800">Intern Management</h4>
                        <small class="text-muted">Welcome back, Administrator</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="me-3 text-gray-600">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo date('F j, Y'); ?>
                        </span>
                        <a href="add.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Add New Intern
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Total Interns</div>
                <div class="stat-number"><?php echo $stats['total_interns'] ?? 0; ?></div>
            </div>
            <div class="stat-card active">
                <div class="stat-label">Active Interns</div>
                <div class="stat-number"><?php echo $stats['active_interns'] ?? 0; ?></div>
            </div>
            <div class="stat-card assigned">
                <div class="stat-label">Domain Assigned</div>
                <div class="stat-number"><?php echo $stats['assigned_interns'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-search"></i>Filter Interns
            </div>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Name, Username, Email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div>
                        <label class="form-label">Domain</label>
                        <select name="domain" class="form-select">
                            <option value="">All Domains</option>
                            <?php 
                            mysqli_data_seek($domains_result, 0);
                            while($domain = mysqli_fetch_assoc($domains_result)): 
                            ?>
                                <option value="<?php echo $domain['id']; ?>" 
                                    <?php echo $domain_filter == $domain['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($domain['domain_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            Apply
                        </button>
                        <a href="manage.php" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Section -->
        <div class="section-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="section-title mb-0">
                    <i class="fas fa-users"></i>Intern List
                </div>
                
                <form method="POST" id="bulkForm" class="bulk-container" onsubmit="return confirmBulkAction()">
                    <select name="bulk_action" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <input type="hidden" name="selected_ids" id="selectedIds">
                </form>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleAll()">
                            </th>
                            <th>Intern</th>
                            <th>Domain</th>
                            <th>Progress</th>
                            <th>Performance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($interns_result && mysqli_num_rows($interns_result) > 0): ?>
                            <?php while($intern = mysqli_fetch_assoc($interns_result)): 
                                $performance = intval($intern['performance_score'] ?? 0);
                                $total = $intern['total_tasks'] ?? 0;
                                $completed = $intern['completed_tasks'] ?? 0;
                                $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input intern-checkbox" 
                                               value="<?php echo $intern['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="intern-profile">
                                            <div class="intern-avatar">
                                                <?php echo strtoupper(substr($intern['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="edit.php?id=<?php echo $intern['id']; ?>" class="intern-name">
                                                    <?php echo htmlspecialchars($intern['full_name']); ?>
                                                </a>
                                                <div class="intern-email">
                                                    @<?php echo htmlspecialchars($intern['username']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($intern['domain_name']): ?>
                                            <span class="badge-domain">
                                                <?php echo htmlspecialchars($intern['domain_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td width="150">
                                        <div class="d-flex flex-column">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="small text-muted"><?php echo $completed; ?>/<?php echo $total; ?></span>
                                                <span class="small font-weight-bold"><?php echo $progress; ?>%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold <?php echo $performance >= 80 ? 'text-success' : ($performance >= 50 ? 'text-warning' : 'text-danger'); ?>">
                                            <?php echo $performance; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $intern['is_active'] ? 'bg-success-light' : 'bg-danger-light'; ?>">
                                            <?php echo $intern['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="edit.php?id=<?php echo $intern['id']; ?>" class="btn-action btn-action-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($intern['is_active']): ?>
                                                <a href="manage.php?action=deactivate&id=<?php echo $intern['id']; ?>" 
                                                   class="btn-action btn-action-status" title="Deactivate"
                                                   onclick="return confirm('Deactivate this intern?');">
                                                    <i class="fas fa-user-slash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="manage.php?action=activate&id=<?php echo $intern['id']; ?>" 
                                                   class="btn-action btn-action-status" title="Activate"
                                                   onclick="return confirm('Activate this intern?');">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="manage.php?delete=<?php echo $intern['id']; ?>" 
                                               class="btn-action btn-action-delete" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this intern?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">No interns found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer pb-4">
            <p class="mb-0 text-muted">
                &copy; <?php echo date('Y'); ?> <?php echo $company_name; ?> | Intern Management System
            </p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Select all checkboxes
        function toggleAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.intern-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
        }
        
        // Bulk action confirmation
        function confirmBulkAction() {
            const selected = document.querySelectorAll('.intern-checkbox:checked');
            const bulkAction = document.querySelector('select[name="bulk_action"]').value;
            
            if (selected.length === 0) {
                alert('Please select at least one intern.');
                return false;
            }
            if (!bulkAction) {
                alert('Please select an action.');
                return false;
            }
            
            if (!confirm(`Are you sure you want to ${bulkAction} ${selected.length} selected intern(s)?`)) {
                return false;
            }
            
            const ids = Array.from(selected).map(cb => cb.value);
            document.getElementById('selectedIds').value = ids.join(',');
            return true;
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Mobile Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar.style.width === '0px' || sidebar.style.width === '') {
                sidebar.style.width = '250px';
                mainContent.style.marginLeft = '250px';
            } else {
                sidebar.style.width = '0px';
                mainContent.style.marginLeft = '0px';
            }
        }
        
        // Add hamburger menu for mobile
        window.addEventListener('DOMContentLoaded', () => {
            if (window.innerWidth <= 768) {
                const navbar = document.querySelector('.navbar-top .d-flex');
                const hamburger = document.createElement('button');
                hamburger.className = 'btn btn-primary me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                navbar.insertBefore(hamburger, navbar.firstChild);
            }
        });
    </script>
</body>
</html>
</body>
</html>