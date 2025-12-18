<?php
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

// Query top categories (by quantity) for the given month
$sql = "SELECT COALESCE(c.category_name, 'Unknown') AS category_name, SUM(si.quantity) AS total_sold
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    LEFT JOIN products p ON si.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE s.store_id = ? 
      AND YEAR(s.sale_date) = ? 
      AND MONTH(s.sale_date) = ?
    GROUP BY p.category_id
    ORDER BY total_sold DESC
    LIMIT 6";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $store_id, $year, $month);
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$data = [];
while ($row = $result->fetch_assoc()) {
    $labels[] = $row['category_name'];
    $data[] = (int)$row['total_sold'];
}

echo json_encode(['labels' => $labels, 'data' => $data]);
$stmt->close();
$conn->close();
?>