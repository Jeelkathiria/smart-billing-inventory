<?php
require_once __DIR__ . '/../../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

// If cart is empty, redirect back
if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
  header('Location: billing.php');
  exit();
}

// Calculate Total Amount Safely
$total_amount = 0;
foreach ($_SESSION['cart'] as &$item) {
  // Calculate total per item if not already done
  if (!isset($item['total'])) {
    $gst_amount = round($item['price'] * $item['gst'] / 100, 2);
    $item['total'] = round(($item['price'] + $gst_amount) * $item['quantity'], 2);
  }
  $total_amount += $item['total'];
}
// Insert Sale
$stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, gst_percent, price) 
                        VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iisidd", $sale_id, $item['id'], $item['name'], $item['quantity'], $item['gst'], $item['price']);
$stmt->execute();

$sale_id = $conn->insert_id;

// Insert Sale Items & Update Stock
foreach ($_SESSION['cart'] as $item) {
    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, gst_percent, total_price) 
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiddd", $sale_id, $item['id'], $item['quantity'], $item['price'], $item['gst'], $item['total']);
    if (!$stmt->execute()) {
        die("Sale item insert failed: " . $stmt->error);
    }

    $stmt2 = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
    $stmt2->bind_param("ii", $item['quantity'], $item['id']);
    if (!$stmt2->execute()) {
        die("Stock update failed: " . $stmt2->error);
    }
}


// Clear Cart & Redirect
unset($_SESSION['cart']);
header('Location: billing.php?success=Bill Generated Successfully');
exit();
?>
