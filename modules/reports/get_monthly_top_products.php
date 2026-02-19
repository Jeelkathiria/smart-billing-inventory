<?php
/**
 * File: modules/reports/get_monthly_top_products.php
 * Purpose: Returns top-selling products for a month (JSON) for charts and reports.
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Comment-only changes.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_SESSION['store_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$store_id = $_SESSION['store_id'];
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// detect availability of product_name in sale_items
$has_product_name = false;
$col_stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'product_name'");
if ($col_stmt) {
  $col_stmt->execute();
  $col_stmt->bind_result($col_count);
  $col_stmt->fetch();
  $col_stmt->close();
  $has_product_name = ($col_count > 0);
}

$product_name_expr = $has_product_name
  ? "COALESCE(si.product_name, p.product_name, CONCAT('Deleted product (ID:', si.product_id, ')'))"
  : "COALESCE(p.product_name, CONCAT('Deleted product (ID:', si.product_id, ')'))";

$sql = "SELECT ANY_VALUE(" . $product_name_expr . ") AS product_name, SUM(si.quantity) AS total_sold
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    LEFT JOIN products p ON si.product_id = p.product_id
    WHERE s.store_id = ? 
    AND YEAR(s.sale_date) = ? 
    AND MONTH(s.sale_date) = ?
    GROUP BY si.product_id
    ORDER BY total_sold DESC
    LIMIT 6";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $store_id, $year, $month);
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$data = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['product_name'];
    $data[] = (int)$row['total_sold'];
}

echo json_encode(['labels' => $labels, 'data' => $data]);
$stmt->close();
$conn->close();
?>
