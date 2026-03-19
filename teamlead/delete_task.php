<?php
// teamlead/delete_task.php

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

if ($task_id > 0) {
    // Delete task (only if it belongs to this team lead)
    // Get intern ID before deleting task to update performance
    $get_intern = "SELECT assigned_to FROM tasks WHERE id = ? AND assigned_by = ?";
    $stmt_get = mysqli_prepare($conn, $get_intern);
    mysqli_stmt_bind_param($stmt_get, "ii", $task_id, $team_lead_id);
    mysqli_stmt_execute($stmt_get);
    $res_get = mysqli_stmt_get_result($stmt_get);
    $task_data = mysqli_fetch_assoc($res_get);
    $intern_id = $task_data['assigned_to'] ?? 0;

    $delete_query = "DELETE FROM tasks WHERE id = ? AND assigned_by = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "ii", $task_id, $team_lead_id);

    if (mysqli_stmt_execute($stmt)) {
        // Update performance tracking after deletion
        if ($intern_id) {
            require_once '../config/performance_helper.php';
            updateInternPerformance($conn, $intern_id);
        }
        // Task deleted successfully
        header('Location: tasks.php?success=deleted');
    } else {
        // Error deleting task
        header('Location: tasks.php?error=delete_failed');
    }
} else {
    header('Location: tasks.php');
}
exit();
?>
