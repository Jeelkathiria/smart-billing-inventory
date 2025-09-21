<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Always JSON response
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Read and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$invoice_id = uniqid('INV');

// Extract customer/billing fields
$customer_name    = $data['customer_name'] ?? null;
$customer_mobile  = $data['customer_mobile'] ?? null;
$customer_address = $data['customer_address'] ?? null;
$gstin            = $data['gstin'] ?? null;
$table_no         = $data['table_no'] ?? null;

// Prepare items
$items = [];
foreach ($data['items'] as $item) {
    $items[] = [
        'product_id'  => (int)$item['product_id'],
        'quantity'    => (int)$item['quantity'],
        'price'       => (float)$item['price'],
        'gst_percent' => isset($item['gst_percent']) ? (float)$item['gst_percent'] : 0
    ];
}

// Calculate totals
$subtotal = 0;
$total_tax = 0;
foreach ($items as $item) {
    $line_total = $item['price'] * $item['quantity'];
    $subtotal  += $line_total;
    $total_tax += ($line_total * ($item['gst_percent'] / 100));
}
$total_amount = $subtotal + $total_tax;

$conn->begin_transaction();

try {
    // Insert into sales table
    $stmt = $conn->prepare("
    INSERT INTO sales 
    (invoice_id, customer_name, total_amount, created_by, sale_date, store_id)
    VALUES (?, ?, ?, ?, NOW(), ?)
        ");

        // Bind 5 variables (sale_date is NOW())
        $stmt->bind_param(
            "ssdii",   // s=string, s=string, d=double, i=int, i=int
            $invoice_id,
            $customer_name,
            $total_amount,
            $user_id,
            $store_id
        );

    $stmt->execute();
    $sale_id = $stmt->insert_id;
    $stmt->close();

    // Insert sale_items and update stock
    $stmt_item = $conn->prepare("
        INSERT INTO sale_items (store_id, sale_id, product_id, quantity, gst_percent, price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt_update = $conn->prepare("
        UPDATE products 
        SET stock = stock - ? 
        WHERE product_id = ? AND stock >= ? AND store_id = ?
    ");

    foreach ($items as $item) {
        // Insert item
        $stmt_item->bind_param(
            "iiiidd",
            $store_id, $sale_id, $item['product_id'], $item['quantity'], $item['gst_percent'], $item['price']
        );
        $stmt_item->execute();

        // Update stock
        $stmt_update->bind_param(
            "iiii",
            $item['quantity'], $item['product_id'], $item['quantity'], $store_id
        );
        $stmt_update->execute();

        if ($stmt_update->affected_rows === 0) {
            throw new Exception("Insufficient stock for product ID " . $item['product_id']);
        }
    }

    $stmt_item->close();
    $stmt_update->close();

    $conn->commit();

    echo json_encode([
        'status'       => 'success',
        'sale_id'      => $sale_id,
        'invoice_id'   => $invoice_id,
        'subtotal'     => $subtotal,
        'tax'          => $total_tax,
        'total'        => $total_amount,
        'customer_name'=> $customer_name
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
