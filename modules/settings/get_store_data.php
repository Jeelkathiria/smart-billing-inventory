<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$store_id = $_SESSION['store_id'];

$stmt = $conn->prepare("SELECT store_name, store_email, contact_number, gstin FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'store_name' => $store['store_name'] ?? '',
    'store_email' => $store['store_email'] ?? '',
    'contact_number' => $store['contact_number'] ?? '',
    'gstin' => $store['gstin'] ?? ''
]);
?>
