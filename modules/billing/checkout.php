<?php
// MUST be first line - before any output!
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
session_start();
date_default_timezone_set('Asia/Kolkata');

// Suppress warnings/notices - only show errors
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/* ==================================================
   1. AUTH CHECK
================================================== */
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

/* ==================================================
   2. FETCH STORE BILLING FIELDS
================================================== */
$store_stmt = $conn->prepare("SELECT billing_fields FROM stores WHERE store_id=?");
$store_stmt->bind_param("i", $store_id);
$store_stmt->execute();
$store_res = $store_stmt->get_result();
$store = $store_res->fetch_assoc();
$storeFields = json_decode($store['billing_fields'] ?? '{}', true) ?: [];
$store_stmt->close();

/* ==================================================
   3. READ FRONTEND INPUT
================================================== */
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete request data']);
    exit();
}

/* ==================================================
   4. SANITIZE CUSTOMER DATA (Set NULL if empty)
================================================== */
$customer_name    = !empty(trim($data['customer_name'] ?? '')) ? trim($data['customer_name']) : null;
$customer_email   = !empty(trim($data['customer_email'] ?? '')) ? trim($data['customer_email']) : null;
$customer_mobile  = !empty(trim($data['customer_mobile'] ?? '')) ? trim($data['customer_mobile']) : null;
$customer_address = !empty(trim($data['customer_address'] ?? '')) ? trim($data['customer_address']) : null;

/* Default name if not provided */
if (empty($customer_name)) {
    $customer_name = '--';
}

/* ==================================================
   5. SANITIZE ITEMS
================================================== */
$items = [];
foreach ($data['items'] as $item) {
    $items[] = [
        'product_id'  => (int)($item['product_id'] ?? 0),
        'quantity'    => max(1, (int)($item['quantity'] ?? 1)),
        'price'       => (float)($item['price'] ?? 0),
        'gst_percent' => (float)($item['gst_percent'] ?? 0)
    ];
}

$product_ids = array_values(array_filter(array_column($items, 'product_id'), fn($id) => $id > 0));
if (empty($product_ids)) {
    echo json_encode(['status' => 'error', 'message' => 'No valid products in cart']);
    exit();
}

/* ==================================================
   6. FETCH PRODUCTS FROM DB
================================================== */
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$types = str_repeat('i', count($product_ids));
$sql = "SELECT p.product_id, p.product_name, p.sell_price, p.purchase_price, p.gst_percent, p.stock, c.category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE p.product_id IN ($placeholders) AND p.store_id=?";
$stmt = $conn->prepare($sql);

$params = array_merge($product_ids, [$store_id]);
$bind_params = [];
$bind_params[] = ($types . 'i');
foreach ($params as $i => &$val) $bind_params[] = &$val;
call_user_func_array([$stmt, 'bind_param'], $bind_params);

$stmt->execute();
$res = $stmt->get_result();
$dbProducts = [];
while ($p = $res->fetch_assoc()) {
    $dbProducts[(int)$p['product_id']] = $p;
}
$stmt->close();

/* ==================================================
   7. CALCULATE TOTALS & VALIDATE STOCK
================================================== */
$subtotal = 0.0;
$total_tax = 0.0;

foreach ($items as &$item) {
    if (!isset($dbProducts[$item['product_id']])) {
        echo json_encode(['status' => 'error', 'message' => "Product ID {$item['product_id']} not found"]);
        exit();
    }

    $p = $dbProducts[$item['product_id']];
    $stock = (int)$p['stock'];

    if ($item['quantity'] > $stock) {
        echo json_encode(['status' => 'error', 'message' => "Insufficient stock for product ID {$item['product_id']}"]);
        exit();
    }

    $item['price'] = (float)$p['sell_price'];
    $item['gst_percent'] = (float)$p['gst_percent'];

    // compute unit price including GST and store line total (inclusive)
    $unit_price_with_gst = round($item['price'] + ($item['price'] * $item['gst_percent'] / 100), 2);
    $item['total_price'] = round($unit_price_with_gst * $item['quantity'], 2);

    $line_total = $item['price'] * $item['quantity']; // exclusive total (used for subtotal)
    $line_tax = $line_total * ($item['gst_percent'] / 100);

    $subtotal += $line_total;
    $total_tax += $line_tax;
}
unset($item);

$subtotal = round($subtotal, 2);
$total_tax = round($total_tax, 2);
$total_amount = round($subtotal + $total_tax, 2);
$invoice_id = uniqid('INV');

/* ==================================================
   8. BEGIN TRANSACTION
================================================== */
$conn->begin_transaction();

try {

    /* ----------------------------------------------
       8a. CREATE / FIND CUSTOMER (only if data provided)
    ---------------------------------------------- */
    $customer_id = null;

    $hasCustomerData = (
        ($customer_name && $customer_name !== '--') ||
        !empty($customer_mobile) ||
        !empty($customer_email) ||
        !empty($customer_address)
    );

    if ($hasCustomerData) {
        // Try finding existing customer
        if (!empty($customer_mobile) || !empty($customer_email)) {
            $check_customer = $conn->prepare("
                SELECT customer_id FROM customers 
                WHERE (customer_mobile = ? OR customer_email = ?) AND store_id = ? LIMIT 1
            ");
            $check_customer->bind_param("ssi", $customer_mobile, $customer_email, $store_id);
            $check_customer->execute();
            $res = $check_customer->get_result();

            if ($res->num_rows > 0) {
                $customer_id = $res->fetch_assoc()['customer_id'];
            }
            $check_customer->close();
        }

        // If not found, insert new customer
        if (!$customer_id) {
            $insert_customer = $conn->prepare("
                INSERT INTO customers (customer_name, customer_mobile, customer_email, customer_address, store_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insert_customer->bind_param("ssssi", $customer_name, $customer_mobile, $customer_email, $customer_address, $store_id);
            $insert_customer->execute();
            $customer_id = $insert_customer->insert_id;
            $insert_customer->close();
        }
    }

    /* ----------------------------------------------
       8b. INSERT SALE
    ---------------------------------------------- */
    $sale_date = date('Y-m-d H:i:s');
    $stmt_sale = $conn->prepare("
        INSERT INTO sales 
        (invoice_id, store_id, customer_id, customer_name, subtotal, tax_amount, total_amount, created_by, sale_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_sale->bind_param(
        'siissddis',
        $invoice_id,
        $store_id,
        $customer_id,
        $customer_name,
        $subtotal,
        $total_tax,
        $total_amount,
        $user_id,
        $sale_date
    );
    $stmt_sale->execute();
    $sale_id = $stmt_sale->insert_id;
    $stmt_sale->close();

    /* ----------------------------------------------
       8c. INSERT SALE ITEMS & UPDATE STOCK
    ---------------------------------------------- */
    // detect availability of product_name and category columns in sale_items
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

    // Prepare INSERT SQL depending on available columns
    if ($has_product_name && $has_category) {
        $stmt_item = $conn->prepare("\n        INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent, product_name, category)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    } elseif ($has_product_name) {
        $stmt_item = $conn->prepare("\n        INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent, product_name)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    } elseif ($has_category) {
        $stmt_item = $conn->prepare("\n        INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent, category)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    } else {
        $stmt_item = $conn->prepare("\n        INSERT INTO sale_items (store_id, sale_id, product_id, quantity, total_price, purchase_price, profit, gst_percent)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    }

    $stmt_update = $conn->prepare("\n        UPDATE products SET stock = stock - ? WHERE product_id = ? AND store_id = ? AND stock >= ?\n    ");

    foreach ($items as $item) {
        $p = $dbProducts[$item['product_id']];
        $purchase_price = (float)$p['purchase_price'];
        $profit = ($item['price'] - $purchase_price) * $item['quantity'];

        // ensure we pass variables (not expressions) to bind_param which requires references
        $prod_name = $p['product_name'] ?? '';
        $prod_cat = $p['category_name'] ?? '';

        if ($has_product_name && $has_category) {
            // types: store_id(i), sale_id(i), product_id(i), quantity(i), total_price(d), purchase_price(d), profit(d), gst_percent(d), product_name(s), category(s)
            $stmt_item->bind_param(
                "iiiiddddss",
                $store_id,
                $sale_id,
                $item['product_id'],
                $item['quantity'],
                $item['total_price'],
                $purchase_price,
                $profit,
                $item['gst_percent'],
                $prod_name,
                $prod_cat
            );
        } elseif ($has_product_name) {
            // types: store_id(i), sale_id(i), product_id(i), quantity(i), total_price(d), purchase_price(d), profit(d), gst_percent(d), product_name(s)
            $stmt_item->bind_param(
                "iiiidddds",
                $store_id,
                $sale_id,
                $item['product_id'],
                $item['quantity'],
                $item['total_price'],
                $purchase_price,
                $profit,
                $item['gst_percent'],
                $p['product_name']
            );
        } elseif ($has_category) {
            // types: store_id(i), sale_id(i), product_id(i), quantity(i), total_price(d), purchase_price(d), profit(d), gst_percent(d), category(s)
            $stmt_item->bind_param(
                "iiiidddds",
                $store_id,
                $sale_id,
                $item['product_id'],
                $item['quantity'],
                $item['total_price'],
                $purchase_price,
                $profit,
                $item['gst_percent'],
                $prod_cat
            );
        } else {
            // types: store_id(i), sale_id(i), product_id(i), quantity(i), total_price(d), purchase_price(d), profit(d), gst_percent(d)
            $stmt_item->bind_param(
                "iiiidddd",
                $store_id,
                $sale_id,
                $item['product_id'],
                $item['quantity'],
                $item['total_price'],
                $purchase_price,
                $profit,
                $item['gst_percent']
            );
        }

        if (!$stmt_item->execute()) {
            throw new Exception("Sale item insert failed: " . $stmt_item->error);
        }

        $stmt_update->bind_param("iiii", $item['quantity'], $item['product_id'], $store_id, $item['quantity']);
        $stmt_update->execute();

        if ($stmt_update->affected_rows === 0) {
            throw new Exception("Stock update failed for product ID {$item['product_id']}");
        }
    }

    $stmt_item->close();
    $stmt_update->close();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'sale_id' => $sale_id,
        'invoice_id' => $invoice_id,
        'subtotal' => $subtotal,
        'tax' => $total_tax,
        'total' => $total_amount,
        'message' => 'Billing successful!'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
