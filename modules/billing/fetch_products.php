<?php
require_once __DIR__ . '/../../config/db.php';
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['store_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$store_id = $_SESSION['store_id'];
$category_id = $_GET['category_id'] ?? '';

if ($category_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing category_id']);
    exit;
}

$stmt = $conn->prepare("SELECT product_id, product_name, sell_price, gst_percent, stock 
                        FROM products 
                        WHERE category_id = ? AND store_id = ?");
$stmt->bind_param("ii", $category_id, $store_id);
$stmt->execute();
$res = $stmt->get_result();

$products = [];
while ($row = $res->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode(['status' => 'success', 'products' => $products]);
exit;
