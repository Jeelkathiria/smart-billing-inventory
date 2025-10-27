<?php
require_once __DIR__ . "/../../config/db.php";
session_start();

// Make sure store_id is available
$store_id = $_SESSION['store_id'] ?? null;

if (!$store_id) {
    die("Store ID not found. Please login.");
}

// Set headers for Excel export
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=sales_export_" . date("Ymd_His") . ".xls");

// Column headers
echo "Invoice ID\tCustomer Name\tDate\tTotal Amount\n";

// Fetch sales only for this store
$stmt = $conn->prepare("SELECT invoice_id, customer_name, sale_date, total_amount 
                        FROM sales 
                        WHERE store_id = ? 
                        ORDER BY sale_id DESC");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo $row['invoice_id'] . "\t" . $row['customer_name'] . "\t" . $row['sale_date'] . "\t" . $row['total_amount'] . "\n";
}

$stmt->close();
$conn->close();
?>
