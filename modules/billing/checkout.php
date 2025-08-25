<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Read and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['customer_name'], $data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

$customer_name = $data['customer_name'];
$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$invoice_id = uniqid('INV'); // Generate a unique invoice id

// Map frontend keys to backend expected keys
$items = [];
foreach ($data['items'] as $item) {
    $items[] = [
        'product_id' => $item['id'],
        'quantity' => $item['qty'],
        'price' => $item['rate'],
        'gst_percent' => isset($item['gst']) ? $item['gst'] : 0
    ];
}

// --- Calculate subtotal, tax, total on backend ---
$subtotal = 0;
$total_tax = 0;

foreach ($items as $item) {
    $line_total = $item['price'] * $item['quantity']; // Price * quantity
    $subtotal += $line_total;
    $total_tax += ($line_total * ($item['gst_percent'] / 100)); // GST per line
}

$total_amount = $subtotal + $total_tax;

$conn->begin_transaction();

try {
    // Insert into sales table with backend-calculated total
    $stmt = $conn->prepare("INSERT INTO sales (invoice_id, customer_name, total_amount, created_by, sale_date, store_id) VALUES (?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("ssdii", $invoice_id, $customer_name, $total_amount, $user_id, $store_id);
    $stmt->execute();
    $sale_id = $stmt->insert_id;
    $stmt->close();

    // Insert sale items and update product stock
    $stmt_item = $conn->prepare("INSERT INTO sale_items (store_id, sale_id, product_id, quantity, gst_percent, price) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ? AND store_id = ?");

    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $gst_percent = $item['gst_percent'];

        // Insert into sale_items
        $stmt_item->bind_param("iiidid", $store_id, $sale_id, $product_id, $quantity, $gst_percent, $price);
        $stmt_item->execute();

        // Update product stock
        $stmt_update->bind_param("iiii", $quantity, $product_id, $quantity, $store_id);
        $stmt_update->execute();

        if ($stmt_update->affected_rows === 0) {
            throw new Exception("Insufficient stock for product ID $product_id");
        }
    }

    $stmt_item->close();
    $stmt_update->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'sale_id' => $sale_id,
        'invoice_id' => $invoice_id,
        'subtotal' => $subtotal,
        'tax' => $total_tax,
        'total' => $total_amount
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
