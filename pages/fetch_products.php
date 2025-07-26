<?php
require_once '../includes/db.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);

    // Include barcode in the SELECT query
    $result = $conn->query("SELECT product_id, name, price, stock, gst_percent, barcode FROM products WHERE category_id = $category_id");

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
}
?>
