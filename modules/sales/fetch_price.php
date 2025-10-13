<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Always return JSON
header('Content-Type: application/json');

// Check store session
$store_id = $_SESSION['store_id'] ?? 0;
if (!$store_id) {
    echo json_encode(['status' => 'error', 'message' => 'Store not found']);
    exit();
}

// Get product_id from GET
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product']);
    exit();
}

// Fetch price, gst, profit, stock from products
$stmt = $conn->prepare("
    SELECT sell_price, gst_percent, profit, stock
    FROM products
    WHERE product_id=? AND store_id=?
    LIMIT 1
");
$stmt->bind_param("ii", $product_id, $store_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if ($product) {
    // Convert numeric values
    $product['sell_price'] = (float)$product['sell_price'];
    $product['gst_percent'] = (float)$product['gst_percent'];
    $product['profit'] = (float)$product['profit'];
    $product['stock'] = (int)$product['stock'];

    echo json_encode(['status' => 'success', 'product' => $product]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
}

$stmt->close();
$conn->close();
