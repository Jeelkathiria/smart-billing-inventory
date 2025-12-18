<?php
require_once __DIR__ . '/../config/db.php';

$store_id = 1; // replace with valid store id if needed

// check column existence
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

$queries = [
    'summary' => "SELECT SUM(si.total_price) AS total_revenue, SUM(si.profit) AS total_profit, SUM(si.total_price * (si.gst_percent / (100 + si.gst_percent))) AS total_tax FROM sale_items si JOIN sales s ON si.sale_id = s.sale_id LEFT JOIN products p ON si.product_id = p.product_id WHERE s.store_id = ?",
    'today' => "SELECT SUM(si.total_price) AS today_total, SUM(si.profit) AS today_profit, SUM(si.total_price / (1 + si.gst_percent / 100)) AS today_total_excl FROM sales s JOIN sale_items si ON s.sale_id = si.sale_id LEFT JOIN products p ON si.product_id = p.product_id WHERE s.store_id = ? AND DATE(s.sale_date) = CURDATE()",
    'top' => "SELECT ANY_VALUE(" . $product_name_expr . ") AS product_name, SUM(si.quantity) AS total_sold FROM sale_items si LEFT JOIN products p ON si.product_id = p.product_id JOIN sales s ON si.sale_id = s.sale_id WHERE s.store_id = ? GROUP BY si.product_id ORDER BY total_sold DESC LIMIT 6",
];

foreach ($queries as $k => $sql) {
    echo "Preparing $k...\n";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "Failed to prepare $k: (" . $conn->errno . ") " . $conn->error . "\n";
    } else {
        echo "Prepared $k OK\n";
        $stmt->close();
    }
}

// Try top query with bind param
$sql = $queries['top'];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $store_id);
    $ok = $stmt->execute();
    if (!$ok) {
        echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error . "\n";
    } else {
        echo "Execute OK\n";
    }
    $stmt->close();
}

echo "done\n";
