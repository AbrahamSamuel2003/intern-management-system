<?php
// admin/performance/details.php

session_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'imsjr';

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}