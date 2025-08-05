<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$store_id = $_SESSION['store_id'];
$view = $_GET['view'] ?? 'monthly';
$labels = [];
$data = [];

switch ($view) {
    case 'daily':
        $sql = "
            SELECT DATE(sale_date) AS label, SUM(total_amount) AS total 
            FROM sales 
            WHERE store_id = ?
            GROUP BY DATE(sale_date) 
            ORDER BY DATE(sale_date) DESC 
            LIMIT 7
        ";
        break;

    case 'yearly':
        $sql = "
            SELECT YEAR(sale_date) AS label, SUM(total_amount) AS total 
            FROM sales 
            WHERE store_id = ?
            GROUP BY YEAR(sale_date) 
            ORDER BY YEAR(sale_date)
        ";
        break;

    case 'monthly':
    default:
        $sql = "
            SELECT DATE_FORMAT(sale_date, '%Y-%m') AS label, SUM(total_amount) AS total 
            FROM sales 
            WHERE store_id = ?
            GROUP BY label 
            ORDER BY label DESC 
            LIMIT 12
        ";
        break;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['label'];
    $data[] = $row['total'];
}

echo json_encode([
    'labels' => array_reverse($labels),
    'totals' => array_reverse($data)
]);
