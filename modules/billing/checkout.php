<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
    header("Location: billing.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$customer_name = $_POST['customer_name'] ?? 'Walk-in';
$sale_date = date('Y-m-d H:i:s');
$invoice_id = uniqid('INV');

$total_amount = 0;

// Create sale entry with placeholder total_amount
$sale_stmt = $conn->prepare("INSERT INTO sales (invoice_id, customer_name, total_amount, created_by, sale_date, store_id) 
VALUES (?, ?, 0, ?, ?, ?)");
$sale_stmt->bind_param("ssisi", $invoice_id, $customer_name, $user_id, $sale_date, $store_id);
$sale_stmt->execute();
$sale_id = $sale_stmt->insert_id;

foreach ($_SESSION['cart'] as $item) {
    $product_id = (int) $item['id'];
    $quantity = (int) $item['qty'];

    // Fetch product
    $pstmt = $conn->prepare("SELECT name, price, gst_percent, stock FROM products WHERE product_id = ? AND store_id = ?");
    $pstmt->bind_param("ii", $product_id, $store_id);
    $pstmt->execute();
    $prod = $pstmt->get_result()->fetch_assoc();

    if (!$prod || $prod['stock'] < $quantity) {
        die("Insufficient stock for product ID: " . $product_id);
    }

    $price = $prod['price']; // base price
    $gst = $prod['gst_percent'];
    $amount = $price * $quantity;
    $gst_amount = $amount * ($gst / 100);
    $line_total = $amount + $gst_amount;
    $total_amount += $line_total;

    // Insert into sale_items
    $item_stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, gst_percent, price) 
    VALUES (?, ?, ?, ?, ?, ?)");
    $item_stmt->bind_param("iisidd", $sale_id, $product_id, $prod['name'], $quantity, $gst, $price);
    $item_stmt->execute();

    // Update stock
    $new_stock = $prod['stock'] - $quantity;
    $ustmt = $conn->prepare("UPDATE products SET stock = ? WHERE product_id = ? AND store_id = ?");
    $ustmt->bind_param("iii", $new_stock, $product_id, $store_id);
    $ustmt->execute();
}

// Update sales total
$update_stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE sale_id = ?");
$update_stmt->bind_param("di", $total_amount, $sale_id);
$update_stmt->execute();

// Clear cart & redirect
unset($_SESSION['cart']);
header("Location: view_invoice.php?sale_id=" . $sale_id);
exit();
?>
