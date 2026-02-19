<?php
/**
 * File: scripts/test_insert_sale_items.php
 * Purpose: Dev helper to insert sale items and validate sale insertion logic.
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Comments only.
 */
require_once __DIR__ . '/../config/db.php';

// Find a product with stock >=1
$res = $conn->query("SELECT p.product_id, p.product_name, p.sell_price, p.purchase_price, p.gst_percent, p.stock, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.stock >= 1 LIMIT 1");
$product = $res ? $res->fetch_assoc() : null;
if (!$product) {
    echo "No product with sufficient stock found.\n";
    exit(1);
}

// Pick a valid store_id from existing stores
$sres = $conn->query("SELECT store_id FROM stores LIMIT 1");
$srow = $sres ? $sres->fetch_assoc() : null;
$store_id = $srow['store_id'] ?? 1; // fallback to 1 if none found
// pick an existing user as created_by, or create a temporary one
$ures = $conn->query("SELECT user_id FROM users LIMIT 1");
$urow = $ures ? $ures->fetch_assoc() : null;
if ($urow) {
    $user_id = $urow['user_id'];
    echo "Using existing user_id=$user_id\n";
} else {
    $stmtu = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $uname = 'test_user_auto'; $uemail = 'test@example.com'; $upass = password_hash('password', PASSWORD_DEFAULT); $urole = 'admin';
    $stmtu->bind_param('ssss', $uname, $uemail, $upass, $urole);
    if (!$stmtu->execute()) {
        echo "Failed to create user: (" . $stmtu->errno . ") " . $stmtu->error . "\n";
        exit(1);
    }
    $user_id = $stmtu->insert_id;
    $stmtu->close();
    echo "Created user_id=$user_id\n";
}

// Create minimal sale row
$invoice_id = uniqid('TESTINV');
$stmt = $conn->prepare("INSERT INTO sales (invoice_id, store_id, customer_id, customer_name, subtotal, tax_amount, total_amount, created_by, sale_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$subtotal = floatval($product['sell_price']);
$tax = 0.0;
$total = $subtotal + $tax;
$customer_id = null;
$customer_name = '--';
$sale_date = date('Y-m-d H:i:s');
// bind created_by as nullable int if necessary
$created_by_param = $user_id;
echo "About to insert sale with values: invoice_id=$invoice_id, store_id=$store_id, customer_id=" . var_export($customer_id, true) . ", customer_name=$customer_name, subtotal=$subtotal, tax=$tax, total=$total, created_by=$created_by_param, sale_date=$sale_date\n";
$stmt->bind_param('siissddis', $invoice_id, $store_id, $customer_id, $customer_name, $subtotal, $tax, $total, $created_by_param, $sale_date);
try {
    if (!$stmt->execute()) {
        echo "Failed to create sale: (" . $stmt->errno . ") " . $stmt->error . "\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "Exception during sale insert: " . get_class($e) . " - " . $e->getMessage() . "\n";
    echo "mysqli error: (" . $stmt->errno . ") " . $stmt->error . "\n";
    exit(1);
}
$sale_id = $stmt->insert_id;
$stmt->close();

// Prepare items array
$items = [
    [ 'product_id' => (int)$product['product_id'], 'quantity' => 1, 'price' => (float)$product['sell_price'], 'gst_percent' => (float)$product['gst_percent'] ]
];

// compute total_price (inclusive) for each item
foreach ($items as &$item) {
    $unit_with_gst = round($item['price'] + ($item['price'] * $item['gst_percent'] / 100), 2);
    $item['total_price'] = round($unit_with_gst * $item['quantity'], 2);
}
unset($item);

// Now run insertion logic from checkout.php
// detect availability of product_name and category columns
$has_product_name = false;
$has_category = false;
$col_stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'product_name'");
if ($col_stmt) {
  $col_stmt->execute();
  $col_stmt->bind_result($col_count);
  $col_stmt->fetch();
  $col_stmt->close();
  $has_product_name = ($col_count > 0);
}
$col_stmt2 = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'category'");
if ($col_stmt2) {
    $col_stmt2->execute();
    $col_stmt2->bind_result($col_count2);
    $col_stmt2->fetch();
    $col_stmt2->close();
    $has_category = ($col_count2 > 0);
}

if ($has_product_name && $has_category) {
    $stmt_item = $conn->prepare("INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent, product_name, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
} elseif ($has_product_name) {
    $stmt_item = $conn->prepare("INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent, product_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
} elseif ($has_category) {
    $stmt_item = $conn->prepare("INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
} else {
    $stmt_item = $conn->prepare("INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
}

$stmt_update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND store_id = ? AND stock >= ?");

foreach ($items as $item) {
    $p = $product; // same product
    $purchase_price = (float)$p['purchase_price'];
    $profit = ($item['price'] - $purchase_price) * $item['quantity'];

    // ensure string values are variables for bind_param references
    $prod_name = $p['product_name'] ?? '';
    $prod_cat = $p['category_name'] ?? '';

    if ($has_product_name && $has_category) {
        $ok = $stmt_item->bind_param('iiiiddddss', $store_id, $sale_id, $item['product_id'], $item['quantity'], $item['total_price'], $purchase_price, $profit, $item['gst_percent'], $prod_name, $prod_cat);
    } elseif ($has_product_name) {
        $ok = $stmt_item->bind_param('iiiidddds', $store_id, $sale_id, $item['product_id'], $item['quantity'], $item['total_price'], $purchase_price, $profit, $item['gst_percent'], $prod_name);
    } elseif ($has_category) {
        $ok = $stmt_item->bind_param('iiiidddds', $store_id, $sale_id, $item['product_id'], $item['quantity'], $item['total_price'], $purchase_price, $profit, $item['gst_percent'], $prod_cat);
    } else {
        $ok = $stmt_item->bind_param('iiiidddd', $store_id, $sale_id, $item['product_id'], $item['quantity'], $item['total_price'], $purchase_price, $profit, $item['gst_percent']);
    }
    if (!$ok) {
        echo "bind_param failed: (" . $stmt_item->errno . ") " . $stmt_item->error . "\n";
        exit(1);
    }
    if (!$stmt_item->execute()) {
        echo "execute insert failed: (" . $stmt_item->errno . ") " . $stmt_item->error . "\n";
        exit(1);
    }
    echo "Inserted sale_item id=" . $stmt_item->insert_id . "\n";

    $stmt_update->bind_param('iiii', $item['quantity'], $item['product_id'], $store_id, $item['quantity']);
    $stmt_update->execute();
    echo "Updated stock, affected_rows=" . $stmt_update->affected_rows . "\n";
}

echo "Done\n";
