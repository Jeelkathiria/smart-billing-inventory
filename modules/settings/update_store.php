<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/db.php';
session_start();

// Auth check
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

// Validate admin role
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only admins can update store settings']);
    exit;
}

// Get POST data
$store_name = trim($_POST['store_name'] ?? '');
$gstin = trim($_POST['gstin'] ?? '');

// ============ VALIDATION: Only store_name is mandatory ============
if (empty($store_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Store name is required']);
    exit;
}

// GSTIN validation (optional field, but if provided must be 15 chars, alphanumeric)
if (!empty($gstin)) {
    if (strlen($gstin) !== 15) {
        echo json_encode(['status' => 'error', 'message' => 'GSTIN must be exactly 15 characters']);
        exit;
    }

    if (!preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
        echo json_encode(['status' => 'error', 'message' => 'GSTIN must contain only letters (A-Z) and numbers (0-9)']);
        exit;
    }

    // Check if GSTIN already exists (different store)
    $check = $conn->prepare("SELECT store_id FROM stores WHERE gstin = ? AND store_id != ?");
    $check->bind_param("si", $gstin, $store_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This GSTIN is already registered']);
        exit;
    }
    $check->close();
}

// ============ UPDATE: stores table (only store_name and gstin) ============
$stmt = $conn->prepare("UPDATE stores SET store_name = ?, gstin = ? WHERE store_id = ?");
$stmt->bind_param("ssi", $store_name, $gstin, $store_id);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update store settings']);
    $stmt->close();
    exit;
}
$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Store settings updated successfully']);
$conn->close();
?>