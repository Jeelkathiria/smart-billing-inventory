<?php
require_once '../includes/db.php';

if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    $result = $conn->query("SELECT product_id, name, price, stock, gst_percent FROM products WHERE category_id = $category_id");

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => $row['product_id'],  // important: match key with JS
            'name' => $row['name'],
            'price' => $row['price'],
            'stock' => $row['stock'],
            'gst_percent' => $row['gst_percent']
        ];
    }

    echo json_encode($products);
}
?>
