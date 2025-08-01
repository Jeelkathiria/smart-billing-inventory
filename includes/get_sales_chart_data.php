<?php
require_once '../includes/db.php'; // adjust if needed

$data = [];
$dates = [];
$totals = [];

// Loop through last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date)); // Ex: Aug 01

    $query = $conn->query("SELECT SUM(total_amount) AS total FROM sales WHERE DATE(sale_date) = '$date'");
    $row = $query->fetch_assoc();

    $dates[] = $label;
    $totals[] = $row['total'] ? (float)$row['total'] : 0;
}

$data = [
    'dates' => $dates,
    'totals' => $totals
];

header('Content-Type: application/json');
echo json_encode($data);
