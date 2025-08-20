<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
    // Absolute path redirect to login page
    header('Location: /auth/index.php?error=Please%20login');
;
    exit();
}

// Store session variables
$store_id = $_SESSION['store_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
?>
