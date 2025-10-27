<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // ensure DB connection for activity update

// =====================
// SESSION TIMEOUT CHECK
// =====================
$timeout_duration = 7 * 60 * 60; // 7 hours

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: /auth/index.php?timeout=1');
    exit();
} else {
    $_SESSION['login_time'] = time(); // refresh activity time
}

// =====================
// LOGIN VALIDATION
// =====================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id']) || !isset($_SESSION['role'])) {
    header('Location: /auth/index.php?error=Please%20login');
    exit();
}

// =====================
// ACTIVITY TRACKING
// =====================
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
}

// =====================
// STORE SESSION DATA
// =====================
$store_id = $_SESSION['store_id'];
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'];

// =====================
// ROLE-BASED ACCESS CONTROL
// =====================
$currentPage = basename($_SERVER['PHP_SELF']);

$accessMap = [
    'admin' => [
        'dashboard.php', 'billing.php', 'sales.php', 'products.php', 'categories.php',
        'report.php', 'settings.php', 'customers.php', 'get_sales_chart_data.php', 'users.php'
    ],
    'manager' => [
        'dashboard.php', 'billing.php', 'sales.php', 'products.php', 'categories.php',
        'report.php', 'customers.php', 'settings.php', 'get_sales_chart_data.php', 'users.php'
    ],
    'cashier' => [
        'dashboard.php', 'billing.php', 'sales.php', 'settings.php'
    ]
];

// Redirect if user tries to access unauthorized page
if (isset($accessMap[$role]) && !in_array($currentPage, $accessMap[$role])) {
    header('Location: /modules/dashboard.php?error=Access%20Denied');
    exit();
}
?>