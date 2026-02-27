<?php
// logout.php - Simple version without activity_logs

// Start session
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect immediately to login page
header('Location: login.php');
exit();
?>