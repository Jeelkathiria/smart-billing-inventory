<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$view = $_GET['view'] ?? 'monthly';
$labels = [];
$data = [];

switch ($view) {
    case 'daily':
        $sql = "
            SELECT DATE(sale_date) AS label, SUM(total_amount) AS total 
            FROM sales 
            GROUP BY DATE(sale_date) 
            ORDER BY DATE(sale_date) DESC 
            LIMIT 7
        ";
        break;

    case 'yearly':
        $sql = "
            SELECT YEAR(sale_date) AS label, SUM(total_amount) AS total 
            FROM sales 
            GROUP BY YEAR(sale_date) 
            ORDER BY YEAR(sale_date)
        ";
        break;

    case 'monthly':
    default:
        $sql = "
            SELECT DATE_FORMAT(sale_date, '%Y-%m') AS label, SUM(total_amount) AS total 
            FROM sales 
            GROUP BY label 
            ORDER BY label DESC 
            LIMIT 12
        ";
        break;
}

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['error' => $conn->error]);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['label'];
    $data[] = $row['total'];
}

echo json_encode([
    'labels' => array_reverse($labels),
    'totals' => array_reverse($data)
]);
?>
