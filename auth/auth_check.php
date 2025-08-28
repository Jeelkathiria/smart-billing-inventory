<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id']) || !isset($_SESSION['role'])) {
    header('Location: /auth/index.php?error=Please%20login');
    exit();
}

// Store session variables for convenience
$store_id = $_SESSION['store_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Optional: enforce role-based page access
$currentPage = basename($_SERVER['PHP_SELF']);

// Define which pages each role can access
$accessMap = [
    'admin'   => ['dashboard.php', 'billing.php', 'sales.php', 'products.php', 'categories.php', 'report.php', 'settings.php', 'customers.php', 'get_sales_chart_data.php'],
    'manager' => ['dashboard.php', 'billing.php', 'sales.php', 'products.php', 'categories.php', 'report.php', 'customers.php'],
    'cashier' => ['dashboard.php', 'billing.php', 'sales.php']
];

// If the current page is not in the role's allowed list, redirect
if (isset($accessMap[$role]) && !in_array($currentPage, $accessMap[$role])) {
    header('Location: /modules/dashboard.php?error=Access%20Denied');
    exit();
}
