<?php
// teamlead/assign_task.php

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

// Get interns in team lead's domain
$interns_query = "SELECT id, full_name, email FROM users 
                 WHERE role = 'intern' AND domain_id = $domain_id AND is_active = TRUE 
                 ORDER BY full_name ASC";
$interns_result = mysqli_query($conn, $interns_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $instructions = mysqli_real_escape_string($conn, $_POST['instructions'] ?? '');
    $assigned_to = intval($_POST['assigned_to']);
    $deadline = mysqli_real_escape_string($conn, $_POST['deadline']);
    
    $assigned_date = date('Y-m-d');
    
    // Insert task
    $insert_query = "INSERT INTO tasks (title, description, instructions, assigned_to, assigned_by, domain_id, 
                      status, assigned_date, deadline) 
                     VALUES ('$title', '$description', '$instructions', $assigned_to, $team_lead_id, $domain_id, 
                             'pending', '$assigned_date', '$deadline')";
    
    if (mysqli_query($conn, $insert_query)) {
        $task_id = mysqli_insert_id($conn);
        
        // Update performance tracking
        $update_perf_query = "INSERT INTO performance (intern_id, total_tasks_assigned) 
                              VALUES ($assigned_to, 1) 
                              ON DUPLICATE KEY UPDATE 
                              total_tasks_assigned = total_tasks_assigned + 1";
        mysqli_query($conn, $update_perf_query);
        
        $_SESSION['success'] = "Task assigned successfully!";
        header('Location: tasks.php');
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Assign Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; }
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
            <a href="tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>Tasks
            </a>
            <a href="assign_task.php" class="nav-link active">
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
                            <a href="tasks.php" class="text-decoration-none text-dark">
                                <i class="fas fa-arrow-left me-2"></i>
                            </a>
                            Assign New Task
                        </h4>
                        <small class="text-muted">Create and assign tasks to your interns</small>
                    </div>
                    <div>
                        <a href="tasks.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tasks me-2"></i>View All Tasks
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Task Assignment Form -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>Task Details</h6>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Task Title *</label>
                                    <input type="text" class="form-control" name="title" required 
                                           placeholder="Enter clear and concise task title">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Assign to Intern *</label>
                                    <select class="form-select" name="assigned_to" required>
                                        <option value="">Select Intern</option>
                                        <?php while($intern = mysqli_fetch_assoc($interns_result)): ?>
                                            <option value="<?php echo $intern['id']; ?>">
                                                <?php echo htmlspecialchars($intern['full_name']); ?> 
                                                (<?php echo htmlspecialchars($intern['email']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Deadline *</label>
                                    <input type="date" class="form-control" name="deadline" required 
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Assigned Date</label>
                                    <input type="text" class="form-control" value="<?php echo date('F j, Y'); ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Expected Duration</label>
                                    <select class="form-select" name="expected_duration">
                                        <option value="">Select Duration</option>
                                        <option value="1_day">1 Day</option>
                                        <option value="2_days">2 Days</option>
                                        <option value="3_days">3 Days</option>
                                        <option value="1_week">1 Week</option>
                                        <option value="2_weeks">2 Weeks</option>
                                        <option value="1_month">1 Month</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Task Description *</label>
                                    <textarea class="form-control" name="description" rows="6" required 
                                              placeholder="Provide detailed instructions, requirements, and expected outcomes..."></textarea>
                                    <small class="text-muted">Be specific about what you expect from the intern</small>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Additional Instructions (Optional)</label>
                                    <textarea class="form-control" name="instructions" rows="3" 
                                              placeholder="Any special requirements, reference materials, or constraints..."></textarea>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="tasks.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Assign Task
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Task Guidelines -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Task Assignment Guidelines</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="text-center p-3 border rounded">
                                    <i class="fas fa-bullseye fa-2x text-primary mb-3"></i>
                                    <h6>Clear Objectives</h6>
                                    <p class="small text-muted mb-0">Define clear goals and expected outcomes</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="text-center p-3 border rounded">
                                    <i class="fas fa-calendar-check fa-2x text-success mb-3"></i>
                                    <h6>Realistic Deadlines</h6>
                                    <p class="small text-muted mb-0">Set achievable deadlines based on task complexity</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="text-center p-3 border rounded">
                                    <i class="fas fa-comments fa-2x text-info mb-3"></i>
                                    <h6>Provide Support</h6>
                                    <p class="small text-muted mb-0">Be available for questions and guidance</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Date picker
        flatpickr("input[type=date]", {
            minDate: "today",
            dateFormat: "Y-m-d",
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            const assignedTo = document.querySelector('select[name="assigned_to"]').value;
            const deadline = document.querySelector('input[name="deadline"]').value;
            
            if (!title || !description || !assignedTo || !deadline) {
                e.preventDefault();
                alert('Please fill all required fields!');
                return false;
            }
            
            // Check deadline is not in past
            const today = new Date().toISOString().split('T')[0];
            if (deadline < today) {
                e.preventDefault();
                alert('Deadline cannot be in the past!');
                return false;
            }
            
            return true;
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
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>