<?php
// Session configuration
session_start();

// Set session timeout (1 hour)
$timeout = 3600;

// Check if session is expired
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    // Last request was more than 1 hour ago
    session_unset();     // Unset $_SESSION variable
    session_destroy();   // Destroy session data
}

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();

// Regenerate session ID every 30 minutes for security
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > 1800) {
    // Session started more than 30 minutes ago
    session_regenerate_id(true);    // Change session ID
    $_SESSION['CREATED'] = time();  // Update creation time
}
?>