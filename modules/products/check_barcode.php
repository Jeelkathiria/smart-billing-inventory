<?php
// No whitespace before this line
require_once __DIR__ . '/../../config/db.php';
session_start();

$store_id = $_SESSION['store_id'] ?? 0;
$barcode = $_POST['barcode'] ?? '';

$response = ['exists' => false];

if ($barcode) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE barcode = ? AND store_id = ?");
    $stmt->bind_param("si", $barcode, $store_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $prod = $result->fetch_assoc();
        $response = [
            'exists' => true,
            'product_name' => $prod['product_name'],
            'category_id' => $prod['category_id'],
            'purchase_price' => $prod['purchase_price'],
            'sell_price' => $prod['sell_price'],
            'gst_percent' => $prod['gst_percent'],
            'stock' => $prod['stock']
        ];
    }
}

header('Content-Type: application/json');
// No whitespace after this line
echo json_encode($response);
exit; // make sure nothing else is printed
