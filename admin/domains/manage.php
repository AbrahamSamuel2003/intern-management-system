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

// Handle domain deletion
if (isset($_GET['delete'])) {
    $domain_id = intval($_GET['delete']);
    
    // Check if domain has users assigned
    $check_query = "SELECT COUNT(*) as count FROM users WHERE domain_id = $domain_id";
    $check_result = mysqli_query($conn, $check_query);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        $error = "Cannot delete domain. There are users assigned to it.";
    } else {
        // Delete domain
        $delete_query = "DELETE FROM domains WHERE id = $domain_id";
        if (mysqli_query($conn, $delete_query)) {
            $success = "Domain deleted successfully!";
        } else {
            $error = "Error deleting domain: " . mysqli_error($conn);
        }
    }
}

// Get all domains with user counts
$domains_query = "
    SELECT d.*, 
           (SELECT COUNT(*) FROM users u WHERE u.domain_id = d.id AND u.role = 'team_lead') as teamlead_count,
           (SELECT COUNT(*) FROM users u WHERE u.domain_id = d.id AND u.role = 'intern') as intern_count
    FROM domains d 
    ORDER BY d.domain_name ASC
";
$domains_result = mysqli_query($conn, $domains_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Manage Domains</title>
    
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
        
        /* Badge Colors */
        .badge-tl {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-intern {
            background-color: #f3e5f5;
            color: #7b1fa2;
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
            
            <a href="manage.php" class="nav-link active">
                <i class="fas fa-layer-group"></i>Domains
            </a>
            
            <a href="../teamlead/manage.php" class="nav-link">
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
                        <h4 class="mb-0">Manage Domains</h4>
                        <small class="text-muted">Create and manage work domains for team leads and interns</small>
                    </div>
                    <div>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Domain
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

        <!-- Domains Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Domains</h5>
                <span class="badge bg-primary">
                    <?php echo mysqli_num_rows($domains_result); ?> Domains
                </span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($domains_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Domain Name</th>
                                    <th>Description</th>
                                    <th>Team Leads</th>
                                    <th>Interns</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $row_number = 1;
                                while($domain = mysqli_fetch_assoc($domains_result)): 
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $row_number; ?></strong></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($domain['domain_name']); ?></div>
                                        <small class="text-muted">ID: DOM<?php echo str_pad($row_number++, 3, '0', STR_PAD_LEFT); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $description = $domain['description'] ?? 'No description';
                                        if (strlen($description) > 50) {
                                            echo htmlspecialchars(substr($description, 0, 50)) . '...';
                                        } else {
                                            echo htmlspecialchars($description);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-tl">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo $domain['teamlead_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-intern">
                                            <i class="fas fa-user-graduate me-1"></i>
                                            <?php echo $domain['intern_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('d M Y', strtotime($domain['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit.php?id=<?php echo $domain['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="confirmDelete(<?php echo $domain['id']; ?>)" class="btn btn-outline-danger">
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
                        <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                        <h5>No Domains Found</h5>
                        <p class="text-muted mb-4">Start by creating your first domain to organize team leads and interns</p>
                        <a href="add.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus me-2"></i>Create Your First Domain
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help Card -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Domains</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                <i class="fas fa-sitemap text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Organization</h6>
                                <small class="text-muted">Group team leads and interns by work areas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                <i class="fas fa-users text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Team Management</h6>
                                <small class="text-muted">Assign team leads to specific domains</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="fas fa-tasks text-info"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Task Assignment</h6>
                                <small class="text-muted">Tasks are organized by domains</small>
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
        // Confirm before deleting domain
        function confirmDelete(domainId) {
            if (confirm('Are you sure you want to delete this domain?\n\nNote: You can only delete domains with no assigned users.')) {
                window.location.href = 'manage.php?delete=' + domainId;
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
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