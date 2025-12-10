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
        DAY(s.sale_date) AS day,
        SUM(s.total_amount) AS total_sales
    FROM sales s
    WHERE s.store_id = ? 
    AND YEAR(s.sale_date) = ? 
    AND MONTH(s.sale_date) = ?
    GROUP BY DAY(s.sale_date)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $store_id, $year, $month);
$stmt->execute();
$result = $stmt->get_result();

$sales = [];
while ($row = $result->fetch_assoc()) {
    $dayKey = str_pad($row['day'], 2, '0', STR_PAD_LEFT);
    $sales[$dayKey] = (float)$row['total_sales'];
}

echo json_encode(['sales' => $sales]);
$stmt->close();
$conn->close();
?>
