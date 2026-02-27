<?php
// teamlead/review_task.php

// Start session and database connection
session_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'imsjr';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

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
    $feedback = mysqli_real_escape_string($conn, $_POST['feedback']);
    
    // Validate inputs
    if (!in_array($status, ['completed', 'not_completed'])) {
        $error_msg = "Invalid status selected.";
    } else {
        // Update task
        $update_query = "UPDATE tasks SET 
                        status = ?, 
                        rating = ?, 
                        feedback = ?
                        WHERE id = ? AND assigned_by = ?";
        
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sisii", $status, $rating, $feedback, $task_id, $team_lead_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Task marked as " . ucfirst(str_replace('_', ' ', $status)) . " successfully!";
            
            // --- Performance Calculation Logic ---
            
            // 1. Get Intern ID for this task
            $get_intern_query = "SELECT assigned_to FROM tasks WHERE id = $task_id";
            $res = mysqli_query($conn, $get_intern_query);
            $row = mysqli_fetch_assoc($res);
            $intern_id_calc = $row['assigned_to'];
            
            if ($intern_id_calc) {
                // 2. Fetch all tasks for this intern
                $perf_sql = "SELECT * FROM tasks WHERE assigned_to = $intern_id_calc";
                $perf_res = mysqli_query($conn, $perf_sql);
                
                $total_tasks = 0;
                $completed_tasks = 0;
                $on_time_tasks = 0;
                $total_rating = 0;
                $rated_tasks_count = 0;
                $tasks_submitted_count = 0;
                $tasks_pending_count = 0;
                $tasks_not_completed_count = 0;
                
                while ($t = mysqli_fetch_assoc($perf_res)) {
                    $total_tasks++;
                    
                    if ($t['status'] == 'completed') {
                        $completed_tasks++;
                    } elseif ($t['status'] == 'submitted') {
                        $tasks_submitted_count++;
                    } elseif ($t['status'] == 'pending') {
                        $tasks_pending_count++;
                    } elseif ($t['status'] == 'not_completed') {
                        $tasks_not_completed_count++;
                    }
                    
                    // On-time check: submitted_date <= deadline (if both exist)
                    if (!empty($t['submitted_date']) && !empty($t['deadline'])) {
                        $sub_date = strtotime($t['submitted_date']);
                        $dead_date = strtotime($t['deadline']);
                        if ($sub_date <= $dead_date) {
                            $on_time_tasks++;
                        }
                    }
                    
                    // Rating
                    if (!empty($t['rating']) && $t['rating'] > 0) {
                        $total_rating += $t['rating'];
                        $rated_tasks_count++;
                    }
                }
                
                // 3. Calculate Metrics
                $completion_rate = ($total_tasks > 0) ? ($completed_tasks / $total_tasks) * 100 : 0;
                $on_time_rate = ($total_tasks > 0) ? ($on_time_tasks / $total_tasks) * 100 : 0;
                
                $avg_rating = ($rated_tasks_count > 0) ? ($total_rating / $rated_tasks_count) : 0;
                $normalized_rating_score = ($avg_rating / 5) * 100;
                
                // Weighted Score: 50% Completion, 30% On-time, 20% Rating
                $performance_score = ($completion_rate * 0.50) + ($on_time_rate * 0.30) + ($normalized_rating_score * 0.20);
                
                // 4. Determine Eligibility
                // Fetch min_score from company_details
                $comp_sql = "SELECT min_score_for_job FROM company_details LIMIT 1";
                $comp_res = mysqli_query($conn, $comp_sql);
                $comp_data = mysqli_fetch_assoc($comp_res);
                $min_score_req = $comp_data['min_score_for_job'] ?? 70;
                
                $eligibility = ($performance_score >= $min_score_req) ? 'eligible' : 'not_eligible';
                
                // 5. Update Performance Table
                // Check if record exists first
                $check_perf = "SELECT intern_id FROM performance WHERE intern_id = $intern_id_calc";
                $check_res = mysqli_query($conn, $check_perf);
                
                if (mysqli_num_rows($check_res) > 0) {
                    $update_perf = "UPDATE performance SET 
                                    total_tasks_assigned = $total_tasks,
                                    tasks_completed = $completed_tasks,
                                    tasks_not_completed = $tasks_not_completed_count,
                                    tasks_submitted = $tasks_submitted_count,
                                    tasks_pending = $tasks_pending_count,
                                    on_time_submissions = $on_time_tasks,
                                    performance_score = $performance_score,
                                    eligibility = '$eligibility',
                                    last_updated = NOW()
                                    WHERE intern_id = $intern_id_calc";
                    mysqli_query($conn, $update_perf);
                } else {
                    $insert_perf = "INSERT INTO performance 
                                    (intern_id, total_tasks_assigned, tasks_completed, tasks_not_completed, tasks_submitted, tasks_pending, on_time_submissions, performance_score, eligibility, last_updated)
                                    VALUES 
                                    ($intern_id_calc, $total_tasks, $completed_tasks, $tasks_not_completed_count, $tasks_submitted_count, $tasks_pending_count, $on_time_tasks, $performance_score, '$eligibility', NOW())";
                    mysqli_query($conn, $insert_perf);
                }
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
                            <h6 class="mb-3"><i class="fas fa-file-alt me-2"></i>Submission</h6>
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
                        <h6 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Review & Feedback</h6>
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
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Feedback / Comments</label>
                                <textarea name="feedback" class="form-control" rows="5" placeholder="Provide constructive feedback for the intern..." required><?php echo htmlspecialchars($task['feedback'] ?? ''); ?></textarea>
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
