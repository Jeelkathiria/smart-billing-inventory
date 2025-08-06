<?php
require_once '../includes/db.php';
session_start(); // Required to get store_id from session

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_GET['category_id']) && isset($_SESSION['store_id'])) {
    $category_id = intval($_GET['category_id']);
    $store_id = intval($_SESSION['store_id']); // 👈 secure cast

    // Only fetch products belonging to the current store & category
    $stmt = $conn->prepare("
        SELECT product_id, name, price, stock, gst_percent, barcode 
        FROM products 
        WHERE category_id = ? AND store_id = ?
    ");
    $stmt->bind_param("ii", $category_id, $store_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => $row['product_id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'gst_percent' => $row['gst_percent'],
            'barcode' => $row['barcode'],
            'stock' => $row['stock']
        ];
    }

    echo json_encode($products);
} else {
    // Optional: send error response
    echo json_encode(['error' => 'Invalid request or store session missing']);
}
?>
