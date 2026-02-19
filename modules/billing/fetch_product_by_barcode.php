<?php
// modules/sales/fetch_product_by_barcode.php
require_once __DIR__ . '/../../config/db.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['store_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
$store_id = $_SESSION['store_id'];

$barcode = trim($_GET['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing barcode']);
    exit;
}

/*
 Try common barcode column names. If your products table uses a different column,
 add it to $candidates. We will try them one by one with prepared statements
 to avoid SQL errors on missing columns.
*/
$candidates = ['barcode', 'product_barcode', 'sku', 'product_code', 'barcode_number'];

$product = null;
foreach ($candidates as $col) {
    // Build a safe query and attempt to run it. Use INFORMATION_SCHEMA to check column existence optionally.
    // First check if column exists for this table (avoid SQL errors)
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = ?");
    $check->bind_param('s', $col);
    $check->execute();
    $res = $check->get_result();
    $row = $res->fetch_assoc();
    $check->close();
    if (empty($row) || intval($row['cnt']) === 0) continue;

    $sql = "SELECT product_id, product_name, sell_price, gst_percent, stock, category_id
            FROM products
            WHERE $col = ? AND store_id = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('si', $barcode, $store_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r && $r->num_rows > 0) {
            $product = $r->fetch_assoc();
            $stmt->close();
            break;
        }
        $stmt->close();
    }
}

if (!$product) {
    // fallback: try searching by product_id if barcode is numeric product id
    if (ctype_digit($barcode)) {
        $pid = (int)$barcode;
        $stmt = $conn->prepare("SELECT product_id, product_name, sell_price, gst_percent, stock, category_id FROM products WHERE product_id = ? AND store_id = ? LIMIT 1");
        $stmt->bind_param('ii', $pid, $store_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r && $r->num_rows > 0) $product = $r->fetch_assoc();
        $stmt->close();
    }
}

if (!$product) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit;
}

// Normalize numeric fields
$product['sell_price'] = (float)$product['sell_price'];
$product['gst_percent'] = (float)$product['gst_percent'];
$product['stock'] = (int)$product['stock'];

echo json_encode(['status' => 'success', 'product' => $product]);
exit;
