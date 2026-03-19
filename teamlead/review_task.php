<?php
// teamlead/review_task.php

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
$success_msg = '';
$error_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $status = $_POST['status']; // 'completed' or 'not_completed'
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    
    // Validate inputs
    if (!in_array($status, ['completed', 'not_completed'])) {
        $error_msg = "Invalid status selected.";
    } else {
        // Check deadline if status is not_completed
        if ($status == 'not_completed') {
            $check_deadline_query = "SELECT deadline FROM tasks WHERE id = ?";
            $d_stmt = mysqli_prepare($conn, $check_deadline_query);
            mysqli_stmt_bind_param($d_stmt, "i", $task_id);
            mysqli_stmt_execute($d_stmt);
            $d_res = mysqli_stmt_get_result($d_stmt);
            $task_data = mysqli_fetch_assoc($d_res);
            $deadline = $task_data['deadline'];
            
            if (strtotime($deadline) >= strtotime(date('Y-m-d'))) {
                $status = 'pending'; // Re-open the task for the intern
                $display_status = 'Pending (Sent back for re-work)';
            } else {
                $display_status = 'Not Completed (Final)';
            }
        } else {
            $display_status = 'Completed';
        }

        // Update task
        $update_query = "UPDATE tasks SET 
                        status = ?, 
                        rating = ?
                        WHERE id = ? AND assigned_by = ?";
        
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "siii", $status, $rating, $task_id, $team_lead_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Task marked as " . $display_status . " successfully!";
            
            // --- Performance Calculation Logic ---
            $get_intern_query = "SELECT assigned_to FROM tasks WHERE id = $task_id";
            $res = mysqli_query($conn, $get_intern_query);
            $row = mysqli_fetch_assoc($res);
            $intern_id_calc = $row['assigned_to'];

            if ($intern_id_calc) {
                require_once '../config/performance_helper.php';
                updateInternPerformance($conn, $intern_id_calc);
            }
            
            // Redirect after short delay or show success
            header("refresh:2;url=tasks.php");
        } else {
            $error_msg = "Error updating task: " . mysqli_error($conn);
        }
    }
}

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
    <title><?php echo $company_name; ?> - Review Task</title>
    
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
        
        /* Star Rating */
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
        }

        .rating > input {
            display: none;
        }

        .rating > label {
            position: relative;
            width: 1.1em;
            font-size: 2rem;
            color: #FFD700;
            cursor: pointer;
        }

        .rating > label::before {
            content: "\2605";
            position: absolute;
            opacity: 0;
        }

        .rating > label:hover:before,
        .rating > label:hover ~ label:before {
            opacity: 1 !important;
        }

        .rating > input:checked ~ label:before {
            opacity: 1;
        }

        .rating:hover > input:checked ~ label:before {
            opacity: 0.4;
        }
        
        /* Simple Star Rating for Display */
        .star-rating {
            direction: rtl;
            display: inline-block;
            padding: 20px;
        }
        .star-rating input[type=radio] {
            display: none;
        }
        .star-rating label {
            color: #bbb;
            font-size: 24px;
            padding: 0;
            cursor: pointer;
            -webkit-transition: all .3s ease-in-out;
            transition: all .3s ease-in-out;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input[type=radio]:checked ~ label {
            color: #f2b600;
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
                            Review Task
                        </h4>
                        <small class="text-muted">
                            Review submission for: <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                        </small>
                    </div>
                </div>
            </div>
        </nav>

        <?php if($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Task Details & Submission -->
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Task Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5><?php echo htmlspecialchars($task['title']); ?></h5>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Assigned To</small>
                                <strong><?php echo htmlspecialchars($task['intern_name']); ?></strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Assigned Date</small>
                                <strong><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></strong>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Current Status</small>
                                <span class="badge badge-<?php echo $task['status']; ?>">
                                    <?php echo ucfirst($task['status']); ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Submitted Date</small>
                                <strong>
                                    <?php echo $task['submitted_date'] ? date('M d, Y', strtotime($task['submitted_date'])) : 'Not submitted'; ?>
                                </strong>
                            </div>
                        </div>
                        
                        <div class="border-top pt-3">
                            <h6 class="mb-3"><i class="fas fa-file-alt me-2"></i>Submission Info</h6>
                            
                            <?php if($task['submission_text']): ?>
                                <div class="mb-3">
                                    <label class="text-muted small d-block mb-1">Intern's Notes/Submission Text</label>
                                    <div class="p-3 bg-light rounded text-dark">
                                        <?php echo nl2br(htmlspecialchars($task['submission_text'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <h6 class="mb-3 small text-muted font-weight-bold">Attached File</h6>
                            <?php if ($task['submitted_file']): ?>
                                <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file-pdf text-danger fa-2x me-3"></i>
                                        <span class="fw-bold"><?php echo htmlspecialchars($task['submitted_file']); ?></span>
                                    </div>
                                    <a href="../assets/uploads/tasks/<?php echo $task['submitted_file']; ?>" class="btn btn-primary btn-sm" download>
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>No file was submitted for this task.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review Form -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Review Task</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Performance Rating</label>
                                <div class="text-center bg-light rounded p-2">
                                    <div class="star-rating">
                                        <input type="radio" id="star5" name="rating" value="5" <?php echo $task['rating'] == 5 ? 'checked' : ''; ?> /><label for="star5" title="Excellent">★</label>
                                        <input type="radio" id="star4" name="rating" value="4" <?php echo $task['rating'] == 4 ? 'checked' : ''; ?> /><label for="star4" title="Good">★</label>
                                        <input type="radio" id="star3" name="rating" value="3" <?php echo $task['rating'] == 3 ? 'checked' : ''; ?> /><label for="star3" title="Average">★</label>
                                        <input type="radio" id="star2" name="rating" value="2" <?php echo $task['rating'] == 2 ? 'checked' : ''; ?> /><label for="star2" title="Poor">★</label>
                                        <input type="radio" id="star1" name="rating" value="1" <?php echo $task['rating'] == 1 ? 'checked' : ''; ?> /><label for="star1" title="Very Poor">★</label>
                                    </div>
                                    <div class="small text-muted">Select 1-5 stars</div>
                                </div>
                            </div>
                            
                            
                            <hr>
                            
                            <div class="d-grid gap-2 mt-4">
                                <label class="form-label fw-bold">Update Task Status</label>
                                
                                <button type="submit" name="status" value="completed" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle me-2"></i>Mark as Completed
                                </button>
                                
                                <button type="submit" name="status" value="not_completed" class="btn btn-danger btn-lg">
                                    <i class="fas fa-times-circle me-2"></i>Mark as Not Completed
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle Sidebar
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
        if (window.innerWidth <= 768) {
            const navbar = document.querySelector('.navbar-top');
            const hamburger = document.createElement('button');
            hamburger.className = 'btn btn-primary me-2';
            hamburger.innerHTML = '<i class="fas fa-bars"></i>';
            hamburger.onclick = toggleSidebar;
            
            navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
        }
    </script>
</body>
</html>
