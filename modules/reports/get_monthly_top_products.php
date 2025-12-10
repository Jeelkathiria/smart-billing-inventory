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

$sql = "
    SELECT 
        p.product_name,
        SUM(si.quantity) AS total_sold
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE s.store_id = ? 
    AND YEAR(s.sale_date) = ? 
    AND MONTH(s.sale_date) = ?
    GROUP BY p.product_id, p.product_name
    ORDER BY total_sold DESC
    LIMIT 6
";

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
