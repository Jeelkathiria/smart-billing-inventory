<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$store_id = $_SESSION['store_id'];
$view = $_GET['view'] ?? 'monthly';
$labels = [];
$salesData = [];
$profitData = [];

switch ($view) {
    case 'daily':
        $groupBy = "DATE(s.sale_date)";
        $orderBy = "DATE(s.sale_date) DESC";
        $limit = "LIMIT 7";
        break;
    case 'yearly':
        $groupBy = "YEAR(s.sale_date)";
        $orderBy = "YEAR(s.sale_date)";
        $limit = "";
        break;
    case 'monthly':
    default:
        $groupBy = "DATE_FORMAT(s.sale_date, '%Y-%m')";
        $orderBy = "DATE_FORMAT(s.sale_date, '%Y-%m') DESC";
        $limit = "LIMIT 12";
        break;
}

// Fetch sales and profit
$sql = "
    SELECT 
        $groupBy AS label,
        SUM(si.total_price) AS total_sales,
        SUM(si.profit) AS total_profit
    FROM sales s
    JOIN sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.product_id
    WHERE s.store_id = ?
    GROUP BY label
    ORDER BY $orderBy
    $limit
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['label'];
    $salesData[] = (float)$row['total_sales'];
    $profitData[] = (float)$row['total_profit'];
}

echo json_encode([
    'labels' => array_reverse($labels),
    'sales' => array_reverse($salesData),
    'profit' => array_reverse($profitData)
]);
