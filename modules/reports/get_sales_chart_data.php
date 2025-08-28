<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';
$store_id = $_SESSION['store_id'];

$data = [];
$dates = [];
$totals = [];

// Loop through last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date)); // Ex: Aug 01

    $query = $conn->prepare("SELECT SUM(total_amount) AS total FROM sales WHERE DATE(sale_date) = ? AND store_id = ?");
    $query->bind_param("si", $date, $store_id);
    $query->execute();
    $result = $query->get_result()->fetch_assoc();

    $dates[] = $label;
    $totals[] = $result['total'] ? (float)$result['total'] : 0;
}

$data = [
    'dates' => $dates,
    'totals' => $totals
];

header('Content-Type: application/json');
echo json_encode($data);
