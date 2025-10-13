<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

if (!isset($_SESSION['store_id'])) {
    echo json_encode(['error' => 'Store not found']);
    exit;
}

$store_id = $_SESSION['store_id'];
$view = $_GET['view'] ?? 'monthly';

$labels = [];
$sales = [];
$profit = [];

if ($view === 'last7') {
    // Last 7 days aggregated in one query
    $sql = "
        SELECT DATE(s.sale_date) AS day,
               SUM(s.total_amount) AS total_sales,
               SUM((p.sell_price - p.purchase_price) * si.quantity) AS total_profit
        FROM sales s
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN products p ON si.product_id = p.product_id
        WHERE s.store_id = ? AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY day
        ORDER BY day
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Build a date => value map first
    $dataMap = [];
    while ($row = $result->fetch_assoc()) {
        $dataMap[$row['day']] = [
            'sales' => (float)$row['total_sales'],
            'profit' => (float)$row['total_profit']
        ];
    }

    // Fill all 7 days (even if no sales)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));
        $sales[] = $dataMap[$date]['sales'] ?? 0;
        $profit[] = $dataMap[$date]['profit'] ?? 0;
    }

} elseif ($view === 'daily') {
    // Last 30 days daily chart
    $sql = "
        SELECT DATE(s.sale_date) AS day,
               SUM(s.total_amount) AS total_sales,
               SUM((p.sell_price - p.purchase_price) * si.quantity) AS total_profit
        FROM sales s
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN products p ON si.product_id = p.product_id
        WHERE s.store_id = ? AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY day
        ORDER BY day
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $labels[] = date('d M', strtotime($row['day']));
        $sales[] = (float)$row['total_sales'];
        $profit[] = (float)$row['total_profit'];
    }

} elseif ($view === 'monthly') {
    // Last 12 months
    $sql = "
        SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS month,
               SUM(s.total_amount) AS total_sales,
               SUM((p.sell_price - p.purchase_price) * si.quantity) AS total_profit
        FROM sales s
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN products p ON si.product_id = p.product_id
        WHERE s.store_id = ? AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
        GROUP BY month
        ORDER BY month
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $labels[] = date('M Y', strtotime($row['month']));
        $sales[] = (float)$row['total_sales'];
        $profit[] = (float)$row['total_profit'];
    }

} else { // yearly
    // All years with sales
    $sql = "
        SELECT YEAR(s.sale_date) AS year,
               SUM(s.total_amount) AS total_sales,
               SUM((p.sell_price - p.purchase_price) * si.quantity) AS total_profit
        FROM sales s
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN products p ON si.product_id = p.product_id
        WHERE s.store_id = ?
        GROUP BY year
        ORDER BY year
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['year'];
        $sales[] = (float)$row['total_sales'];
        $profit[] = (float)$row['total_profit'];
    }
}

echo json_encode([
    'dates' => $labels,
    'sales' => $sales,
    'profit' => $profit
]);
