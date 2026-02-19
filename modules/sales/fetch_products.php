<?php
error_reporting(0);
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

// Always return JSON
header('Content-Type: application/json');

// Check if store session exists
$store_id = $_SESSION['store_id'] ?? 0;
if (!$store_id) {
    echo json_encode([]);
    exit();
}

// Get category_id from GET or POST
$category_id = $_GET['category_id'] ?? $_POST['category_id'] ?? 0;
$category_id = (int)$category_id;

if ($category_id <= 0) {
    echo json_encode([]);
    exit();
}

// Fetch products for this store and category
$stmt = $conn->prepare("SELECT product_id, product_name, sell_price AS price, gst_percent, stock, barcode FROM products WHERE category_id=? AND store_id=? ORDER BY product_name ASC");
$stmt->bind_param("ii", $category_id, $store_id);
$stmt->execute();
$res = $stmt->get_result();

$products = [];
while ($row = $res->fetch_assoc()) {
    // Convert numeric fields properly
    $row['price'] = (float)$row['price'];
    $row['gst_percent'] = (float)$row['gst_percent'];
    $row['stock'] = (int)$row['stock'];
    $products[] = $row;
}

echo json_encode($products);
$stmt->close();
$conn->close();
