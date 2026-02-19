<?php
/**
 * File: modules/reports/get_sales_by_date.php
 * Purpose: Returns sales/profit/tax details for a specific date (JSON).
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Comment-only changes.
 */
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit;
}

$store_id = $_SESSION['store_id'];
$date = $_GET['date'] ?? '';

if (!$date) {
    echo json_encode(['success' => false, 'msg' => 'Date missing']);
    exit;
}

// detect product_name column
$has_product_name = false;
$col_stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'product_name'");
if ($col_stmt) {
  $col_stmt->execute();
  $col_stmt->bind_result($col_count);
  $col_stmt->fetch();
  $col_stmt->close();
  $has_product_name = ($col_count > 0);
}

// Fetch totals (use sale_items for revenue and tax)
$sql = "
    SELECT 
        SUM(si.total_price) AS revenue,
        SUM(si.profit) AS profit,
        SUM(si.total_price * (si.gst_percent / (100 + si.gst_percent))) AS tax
    FROM sales s
    JOIN sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.product_id
    WHERE s.store_id = ? AND DATE(s.sale_date) = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $store_id, $date);
$stmt->execute();
$stmt->bind_result($revenue, $profit, $tax);
$stmt->fetch();
$stmt->close();

// Fetch product-wise details
$product_name_expr = $has_product_name
    ? "COALESCE(si.product_name, p.product_name, CONCAT('Deleted product (ID:', si.product_id, ')'))"
    : "COALESCE(p.product_name, CONCAT('Deleted product (ID:', si.product_id, ')'))";

$sql2 = "
    SELECT ANY_VALUE(" . $product_name_expr . ") AS product_name, SUM(si.quantity) AS quantity, SUM(si.total_price) AS total
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.product_id
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.store_id = ? AND DATE(s.sale_date) = ?
    GROUP BY si.product_id
";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("is", $store_id, $date);
$stmt2->execute();
$result = $stmt2->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    "success" => true,
    "revenue" => $revenue ?: 0,
    "profit" => $profit ?: 0,
    "tax" => $tax ?: 0,
    "items" => $items
]);

$stmt2->close();
$conn->close();
?>

