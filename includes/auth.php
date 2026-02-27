<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Function to check if user has specific role
function checkRole($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        // Redirect to appropriate dashboard based on role
        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'team_lead':
                header('Location: ../teamlead/dashboard.php');
                break;
            case 'intern':
                header('Location: ../intern/dashboard.php');
                break;
            default:
                header('Location: ../login.php');
        }
        exit();
    }
}

// Function to get current user data
function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) === 1) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}
?>