<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// ✅ Detect if request came from Fetch/AJAX
$isAjax = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

// =====================
// SESSION TIMEOUT CHECK
// =====================
$timeout_duration = 7 * 60 * 60; // 7 hours

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout_duration) {
    session_unset();
    session_destroy();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    } else {
        header('Location: /auth/index.php?timeout=1');
    }
    exit();
} else {
    $_SESSION['login_time'] = time(); // Refresh activity time
}

// =====================
// LOGIN VALIDATION
// =====================
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['store_id']) ||
    !isset($_SESSION['role'])
) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    } else {
        header('Location: /auth/index.php?error=Please%20login');
    }
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

if (isset($accessMap[$role]) && !in_array($currentPage, $accessMap[$role])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    } else {
        header('Location: /modules/dashboard.php?error=Access%20Denied');
    }
    exit();
}
?>
