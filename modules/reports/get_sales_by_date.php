<?php
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

// Fetch totals
$sql = "
    SELECT 
        SUM(s.total_amount) AS revenue,
        SUM((p.sell_price - p.purchase_price) * si.quantity) AS profit,
        SUM(si.quantity * si.price * (si.gst_percent/100)) AS tax
    FROM sales s
    JOIN sale_items si ON s.sale_id = si.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE s.store_id = ? AND DATE(s.sale_date) = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $store_id, $date);
$stmt->execute();
$stmt->bind_result($revenue, $profit, $tax);
$stmt->fetch();
$stmt->close();

// Fetch product-wise details
$sql2 = "
    SELECT p.product_name, si.quantity, (si.quantity * si.price) AS total
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.store_id = ? AND DATE(s.sale_date) = ?
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

