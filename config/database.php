<?php
// Database configuration
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = (defined('DB_NAME') && DB_NAME !== '') ? DB_NAME : null;

// Create connection
if ($database) {
    $conn = mysqli_connect($host, $username, $password, $database);
} else {
    // If no database defined yet, connect to MySQL server only
    $conn = mysqli_connect($host, $username, $password);
}

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");

// Utility functions
if (!function_exists('tableExists')) {
    function tableExists($conn, $tableName) {
        global $database;
        if (!$database) {
            return false;
        }
        $tableName = mysqli_real_escape_string($conn, $tableName);
        $query = "SHOW TABLES LIKE '$tableName'";
        try {
            $result = mysqli_query($conn, $query);
            return ($result && mysqli_num_rows($result) > 0);
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}

if (!function_exists('isCompanySetup')) {
    function isCompanySetup($conn) {
        global $database;
        if (!$database || !tableExists($conn, 'company_details')) {
            return false;
        }
        try {
            $query = "SELECT COUNT(*) as count FROM company_details";
            $result = mysqli_query($conn, $query);
            if (!$result) {
                return false;
            }
            $row = mysqli_fetch_assoc($result);
            return isset($row['count']) && ((int)$row['count'] > 0);
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
}
?>