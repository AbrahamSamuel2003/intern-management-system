<?php
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

// Check if user is logged in and is intern
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'intern') {
    header('Location: ../login.php');
    exit();
}

$intern_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get task details with feedback
$task_query = "
    SELECT 
        t.*,
        u.full_name as assigned_by_name,
        u.email as team_lead_email,
        d.domain_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_by = u.id
    LEFT JOIN domains d ON t.domain_id = d.id
    WHERE t.id = $task_id AND t.assigned_to = $intern_id
    AND t.status IN ('completed', 'not_completed', 'submitted')
";

$task_result = mysqli_query($conn, $task_query);
$task = mysqli_fetch_assoc($task_result);

if (!$task) {
    header('Location: my_tasks.php?error=invalid_task');
    exit();
}

// Get all completed tasks for this intern to show performance trend
$completed_tasks_query = "
    SELECT 
        t.id,
        t.title,
        t.status,
        t.submitted_date,
        t.rating,
        t.feedback,
        u.full_name as assigned_by_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_by = u.id
    WHERE t.assigned_to = $intern_id 
    AND t.status IN ('completed', 'not_completed')
    ORDER BY t.submitted_date DESC
    LIMIT 10
";

$completed_tasks_result = mysqli_query($conn, $completed_tasks_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Task Feedback</title>
    
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
        
        /* Task Info Card */
        .task-info-card {
            border-left: 4px solid;
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .task-completed { border-left-color: #1cc88a; }
        .task-not-completed { border-left-color: #e74a3b; }
        .task-submitted { border-left-color: #36b9cc; }
        
        /* Rating Stars */
        .rating-stars {
            color: #f6c23e;
            font-size: 20px;
        }
        
        /* Feedback Box */
        .feedback-box {
            background: #f8f9fa;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        /* Status Badges */
        .badge-completed {
            background-color: #d1fae5;
            color: #065f46;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .badge-not-completed {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .badge-submitted {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        /* File Download */
        .file-download {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
            background: #e3f2fd;
            border-radius: 8px;
            color: #1565c0;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .file-download:hover {
            background: #bbdefb;
            color: #0d47a1;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e3e6f0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4e73df;
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
            <small class="text-white-50 d-block mt-2">Intern Portal</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="my_tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>My Tasks
            </a>
            
            <a href="submit_task.php" class="nav-link">
                <i class="fas fa-paper-plane"></i>Submit Task
            </a>
            
            <a href="view_feedback.php" class="nav-link active">
                <i class="fas fa-comment-dots"></i>Feedback
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
                        <h4 class="mb-0">Task Feedback</h4>
                        <small class="text-muted">View feedback and ratings for your tasks</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <a href="my_tasks.php" class="btn btn-outline-dark btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Tasks
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="row">
            <!-- Main Feedback Content -->
            <div class="col-lg-8">
                <!-- Task Information -->
                <div class="task-info-card task-<?php echo $task['status']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h3 class="mb-2"><?php echo htmlspecialchars($task['title']); ?></h3>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user-tie me-1"></i>
                                Reviewed by: <?php echo htmlspecialchars($task['assigned_by_name']); ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge-<?php echo $task['status']; ?>">
                                <i class="fas fa-<?php echo $task['status'] == 'completed' ? 'check-circle' : ($task['status'] == 'not_completed' ? 'times-circle' : 'paper-plane'); ?> me-1"></i>
                                <?php echo ucfirst($task['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if(!empty($task['description'])): ?>
                    <div class="mb-4">
                        <h6>Task Description:</h6>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Task Timeline -->
                    <div class="mb-4">
                        <h6>Task Timeline:</h6>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="text-muted small">Assigned on</div>
                                <strong><?php echo date('F j, Y', strtotime($task['assigned_date'])); ?></strong>
                            </div>
                            <div class="timeline-item">
                                <div class="text-muted small">Deadline</div>
                                <strong><?php echo date('F j, Y', strtotime($task['deadline'])); ?></strong>
                            </div>
                            <?php if($task['submitted_date']): ?>
                            <div class="timeline-item">
                                <div class="text-muted small">Submitted on</div>
                                <strong><?php echo date('F j, Y', strtotime($task['submitted_date'])); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Submitted File -->
                    <?php if($task['submitted_file']): ?>
                    <div class="mb-4">
                        <h6>Your Submission:</h6>
                        <a href="../assets/uploads/tasks/<?php echo $task['submitted_file']; ?>" class="file-download" target="_blank">
                            <i class="fas fa-file me-2"></i>
                            Download Submitted File
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Rating & Feedback -->
                    <?php if($task['status'] == 'completed' || $task['status'] == 'not_completed'): ?>
                    <div class="feedback-box">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Feedback from Team Lead</h5>
                            <?php if($task['rating']): ?>
                            <div class="rating-stars">
                                <?php 
                                $rating = $task['rating'];
                                for($i = 1; $i <= 5; $i++):
                                    if($i <= $rating):
                                        echo '<i class="fas fa-star"></i>';
                                    else:
                                        echo '<i class="far fa-star"></i>';
                                    endif;
                                endfor;
                                ?>
                                <span class="ms-2">(<?php echo $rating; ?>/5)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(!empty($task['feedback'])): ?>
                        <div class="mb-4">
                            <h6>Comments:</h6>
                            <div class="p-3 bg-white rounded">
                                <?php echo nl2br(htmlspecialchars($task['feedback'])); ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No detailed feedback provided.
                        </div>
                        <?php endif; ?>
                        
                        <?php if($task['status'] == 'completed'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Task Marked as Completed!</strong> Great work on this task.
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Task Marked as Not Completed.</strong> Please review the feedback and consider improvements.
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php elseif($task['status'] == 'submitted'): ?>
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Awaiting Review</h5>
                                <p class="mb-0">Your task has been submitted and is awaiting review by your team lead. You'll receive feedback here once it's reviewed.</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex gap-2 mt-4">
                        <?php if($task['status'] == 'not_completed'): ?>
                        <a href="messages.php?compose=1&task_id=<?php echo $task_id; ?>" class="btn btn-primary">
                            <i class="fas fa-envelope me-2"></i>Ask for Clarification
                        </a>
                        <?php endif; ?>
                        <a href="my_tasks.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tasks me-2"></i>Back to Tasks
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar - Recent Feedback -->
            <div class="col-lg-4">
                <!-- Performance Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Summary</h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Calculate performance metrics
                        $total_completed = 0;
                        $total_rating = 0;
                        $rated_tasks = 0;
                        
                        mysqli_data_seek($completed_tasks_result, 0);
                        while($ct = mysqli_fetch_assoc($completed_tasks_result)) {
                            if($ct['status'] == 'completed') {
                                $total_completed++;
                                if($ct['rating']) {
                                    $total_rating += $ct['rating'];
                                    $rated_tasks++;
                                }
                            }
                        }
                        
                        $avg_rating = $rated_tasks > 0 ? $total_rating / $rated_tasks : 0;
                        ?>
                        
                        <div class="text-center mb-3">
                            <div class="display-4 fw-bold text-primary"><?php echo number_format($avg_rating, 1); ?></div>
                            <div class="text-muted">Average Rating</div>
                            <div class="rating-stars">
                                <?php 
                                $rounded_rating = round($avg_rating);
                                for($i = 1; $i <= 5; $i++):
                                    if($i <= $rounded_rating):
                                        echo '<i class="fas fa-star"></i>';
                                    else:
                                        echo '<i class="far fa-star"></i>';
                                    endif;
                                endfor;
                                ?>
                            </div>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <h5 class="mb-1"><?php echo $total_completed; ?></h5>
                                    <small class="text-muted">Tasks Completed</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <h5 class="mb-1"><?php echo $rated_tasks; ?></h5>
                                    <small class="text-muted">Tasks Rated</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Feedback -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Feedback</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php 
                            mysqli_data_seek($completed_tasks_result, 0);
                            $count = 0;
                            while($recent_task = mysqli_fetch_assoc($completed_tasks_result) && $count < 5):
                                if($recent_task['id'] != $task_id): // Don't show current task
                            ?>
                            <a href="view_feedback.php?id=<?php echo $recent_task['id']; ?>" class="list-group-item list-group-item-action border-0 <?php echo $recent_task['id'] == $task_id ? 'active' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars(substr($recent_task['title'], 0, 30)); ?><?php echo strlen($recent_task['title']) > 30 ? '...' : ''; ?></h6>
                                    <?php if($recent_task['rating']): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-star"></i> <?php echo $recent_task['rating']; ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M d', strtotime($recent_task['submitted_date'])); ?> 
                                    • <?php echo htmlspecialchars($recent_task['assigned_by_name']); ?>
                                </small>
                            </a>
                            <?php 
                                $count++;
                                endif;
                            endwhile; 
                            
                            if($count == 0):
                            ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comment-slash fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No other feedback yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Tips -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6><i class="fas fa-lightbulb me-2"></i>Feedback Tips</h6>
                        <ul class="mb-0 small text-muted">
                            <li class="mb-2">Read feedback carefully and note areas for improvement</li>
                            <li class="mb-2">Use ratings to track your performance trends</li>
                            <li class="mb-2">Ask for clarification if feedback is unclear</li>
                            <li>Apply learnings to future tasks</li>
                        </ul>
                    </div>
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
                
                if (navbar && navbar.querySelector('.d-flex')) {
                    navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
                }
            }
        });
        
        // File download progress
        document.querySelectorAll('.file-download').forEach(link => {
            link.addEventListener('click', function() {
                const fileName = this.textContent;
                console.log(`Downloading: ${fileName}`);
                // You could add download tracking here
            });
        });
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>