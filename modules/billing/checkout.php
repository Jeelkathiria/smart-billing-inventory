<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Read and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['customer_name'], $data['items'], $data['tax'], $data['total'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

$customer_name = $data['customer_name'];
$tax = $data['tax'];
$total = $data['total'];
$items = $data['items'];
$user_id = $_SESSION['user_id'];

// Start transaction to maintain consistency
$conn->begin_transaction();

try {
    // Insert into sales table
    $stmt = $conn->prepare("INSERT INTO sales (customer_name,sale_id ,subtotal, tax, total, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sdddi", $customer_name, $sale_id, $tax, $total, $user_id);
    $stmt->execute();
    $sale_id = $stmt->insert_id;
    $stmt->close();

    // Insert sale items and update product stock
    $stmt_item = $conn->prepare("INSERT INTO sale_items (store_id, sale_id, product_id, quantity, gst_percent, price) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_update = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");

    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $gst_percent = isset($item['gst_percent']) ? $item['gst_percent'] : 0;
        $store_id = 1; // If you have multiple stores, replace with correct ID

        // Insert into sale_items
        $stmt_item->bind_param("iiidid", $store_id, $sale_id, $product_id, $quantity, $gst_percent, $price);
        $stmt_item->execute();

        // Update product quantity
        $stmt_update->bind_param("iii", $quantity, $product_id, $quantity);
        $stmt_update->execute();

        // If no rows updated, rollback and fail (stock issue)
        if ($stmt_update->affected_rows === 0) {
            throw new Exception("Insufficient stock for product ID $product_id");
        }
    }

    $stmt_item->close();
    $stmt_update->close();

    // Commit transaction
    $conn->commit();

    echo json_encode(['status' => 'success', 'sale_id' => $sale_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
