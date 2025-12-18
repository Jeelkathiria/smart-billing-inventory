<?php
// Script to include report.php under a simulated session to detect runtime errors
require_once __DIR__ . '/../config/db.php';
session_start();
// Set minimal valid session
$_SESSION['user_id'] = 1;
// pick a store id from DB
$res = $conn->query("SELECT store_id FROM stores LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
$_SESSION['store_id'] = $row['store_id'] ?? 1;

ob_start();
try {
    include __DIR__ . '/../modules/reports/Report.php';
    $output = ob_get_clean();
    echo "Included report.php OK, output length: " . strlen($output) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "Caught throwable: " . get_class($e) . " - " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
