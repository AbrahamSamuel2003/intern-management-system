<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'imsjr';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");

// Function to check if company is already setup
function isCompanySetup($conn) {
    $query = "SELECT COUNT(*) as count FROM company_details";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] > 0;
    }
    return false;
}
?>