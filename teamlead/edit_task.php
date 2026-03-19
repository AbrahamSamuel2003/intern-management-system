<?php
// teamlead/edit_task.php

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

// Get task details
$task_query = "SELECT * FROM tasks WHERE id = $task_id AND assigned_by = $team_lead_id";
$task_result = mysqli_query($conn, $task_query);
$task = mysqli_fetch_assoc($task_result);

if (!$task) {
    header('Location: tasks.php');
    exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $deadline = mysqli_real_escape_string($conn, $_POST['deadline']);
    
    if (empty($title) || empty($deadline)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        $update_query = "UPDATE tasks SET title = ?, description = ?, deadline = ? WHERE id = ? AND assigned_by = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sssii", $title, $description, $deadline, $task_id, $team_lead_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Update performance tracking
            $intern_id_calc = $task['assigned_to'];
            if ($intern_id_calc) {
                require_once '../config/performance_helper.php';
                updateInternPerformance($conn, $intern_id_calc);
            }
            $success_msg = "Task updated successfully!";
            // Redirect after 2 seconds
            header("refresh:2;url=tasks.php");
        } else {
            $error_msg = "Error updating task: " . mysqli_error($conn);
        }
    }
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
    <title><?php echo $company_name; ?> - Edit Task</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { background-color: #f8f9fc; font-family: 'Segoe UI', sans-serif; }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 250px; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); color: white; z-index: 1000; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .main-content { margin-left: 250px; padding: 20px; min-height: 100vh; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .company-logo { font-size: 20px; font-weight: 700; color: white; text-decoration: none; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; margin: 5px 10px; border-radius: 5px; transition: 0.3s; text-decoration: none; display: block; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.2); }
        .navbar-top { background: white; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); padding: 15px 20px; margin-bottom: 20px; border-radius: 10px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
        .form-control:focus { border-color: #4e73df; box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.1); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="company-logo"><i class="fas fa-graduation-cap me-2"></i><?php echo $company_name; ?></a>
        </div>
        <div class="sidebar-menu mt-4">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a href="tasks.php" class="nav-link active"><i class="fas fa-tasks me-2"></i>Tasks</a>
            <a href="assign_task.php" class="nav-link"><i class="fas fa-plus-circle me-2"></i>Assign Task</a>
        </div>
    </div>

    <div class="main-content">
        <nav class="navbar-top">
            <h4 class="mb-0"><a href="tasks.php" class="text-decoration-none text-dark"><i class="fas fa-arrow-left me-2"></i></a>Edit Task</h4>
        </nav>

        <?php if($success_msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Task Title *</label>
                        <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description" rows="5"><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Deadline *</label>
                        <input type="date" class="form-control" name="deadline" value="<?php echo $task['deadline']; ?>" required>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                        <a href="tasks.php" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
