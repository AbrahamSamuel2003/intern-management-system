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
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Handle team lead deletion
if (isset($_GET['delete'])) {
    $tl_id = intval($_GET['delete']);
    
    // Check if team lead has interns assigned
    $check_query = "SELECT COUNT(*) as count FROM users WHERE domain_id = (SELECT domain_id FROM users WHERE id = $tl_id) AND role = 'intern'";
    $check_result = mysqli_query($conn, $check_query);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        $error = "Cannot delete team lead. There are interns assigned to their domain.";
    } else {
        // Delete team lead
        $delete_query = "DELETE FROM users WHERE id = $tl_id";
        if (mysqli_query($conn, $delete_query)) {
            $success = "Team Lead deleted successfully!";
        } else {
            $error = "Error deleting team lead: " . mysqli_error($conn);
        }
    }
}

// End of handlers

// Get all team leads with their domain info
$teamleads_query = "
    SELECT u.*, d.domain_name,
           (SELECT COUNT(*) FROM users i WHERE i.domain_id = u.domain_id AND i.role = 'intern' AND i.is_active = TRUE) as intern_count,
           (SELECT COUNT(*) FROM tasks t WHERE t.assigned_by = u.id) as tasks_assigned
    FROM users u
    LEFT JOIN domains d ON u.domain_id = d.id
    WHERE u.role = 'team_lead'
    ORDER BY u.is_active DESC, u.created_at DESC
";
$teamleads_result = mysqli_query($conn, $teamleads_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Manage Team Leads</title>
    
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
        
        
        /* Stats Badges */
        .badge-interns {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .badge-tasks {
            background-color: #f0f9ff;
            color: #0c4a6e;
        }
        
        /* Hover Effects */
        .user-card {
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: #e5e7eb;
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
            
            <a href="manage.php" class="nav-link active">
                <i class="fas fa-user-tie"></i>Team Leads
            </a>
            
            <a href="../intern/manage.php" class="nav-link">
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
                        <h4 class="mb-0">Manage Team Leads</h4>
                        <small class="text-muted">Create and manage team lead accounts</small>
                    </div>
                    <div>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Team Lead
                        </a>

                    </div>
                </div>
            </div>
        </nav>

        <!-- Messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Team Leads Cards -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Team Leads</h5>
                <div>
                    <span class="badge bg-primary">
                        <?php echo mysqli_num_rows($teamleads_result); ?> Team Leads
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($teamleads_result) > 0): ?>
                    <div class="row">
                        <?php while($tl = mysqli_fetch_assoc($teamleads_result)): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card user-card h-100">
                                <div class="card-body">
                                    <!-- Header with Status -->
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($tl['full_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($tl['username']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <!-- Contact Info -->
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($tl['email']); ?>
                                        </small>
                                        <?php if (!empty($tl['phone'])): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($tl['phone']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Domain Info -->
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Assigned Domain:</small>
                                        <?php if ($tl['domain_name']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-layer-group me-1"></i>
                                                <?php echo htmlspecialchars($tl['domain_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>No Domain
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Stats -->
                                    <div class="d-flex justify-content-between mb-3">
                                        <div class="text-center">
                                            <div class="badge badge-interns p-2">
                                                <i class="fas fa-user-graduate me-1"></i>
                                                <?php echo $tl['intern_count']; ?> Interns
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="badge badge-tasks p-2">
                                                <i class="fas fa-tasks me-1"></i>
                                                <?php echo $tl['tasks_assigned']; ?> Tasks
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Dates -->
                                    <div class="small text-muted mb-3">
                                        <div>
                                            <i class="fas fa-calendar-plus me-1"></i>
                                            Joined: <?php echo date('d M Y', strtotime($tl['join_date'] ?? $tl['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="btn-group w-100">
                                        <a href="edit.php?id=<?php echo $tl['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        
                                        <button onclick="confirmDelete(<?php echo $tl['id']; ?>)" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <h5>No Team Leads Found</h5>
                        <p class="text-muted mb-4">Start by adding team leads to manage interns in each domain</p>
                        <a href="add.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus me-2"></i>Add Your First Team Lead
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Card -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Team Leads Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Reset pointer and calculate stats
                    mysqli_data_seek($teamleads_result, 0);
                    $active_count = 0;
                    $inactive_count = 0;
                    $with_domain = 0;
                    $without_domain = 0;
                    
                    while($tl = mysqli_fetch_assoc($teamleads_result)) {
                        if ($tl['is_active']) $active_count++;
                        else $inactive_count++;
                        
                        if ($tl['domain_name']) $with_domain++;
                        else $without_domain++;
                    }
                    ?>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                            <h3 class="mb-0 text-primary"><?php echo mysqli_num_rows($teamleads_result); ?></h3>
                            <small class="text-muted">Total Team Leads</small>
                        </div>
                    </div>
                    
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                            <h3 class="mb-0 text-info"><?php echo $with_domain; ?></h3>
                            <small class="text-muted">With Domain</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Confirm before deleting team lead
        function confirmDelete(tlId) {
            if (confirm('Are you sure you want to delete this team lead? This action cannot be undone.\n\nNote: You can only delete team leads with no assigned interns.')) {
                window.location.href = 'manage.php?delete=' + tlId;
            }
        }
        
        
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
    </script>
</body>
</html>