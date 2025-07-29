<?php
require_once '../includes/db.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=sales_export_" . date("Ymd_His") . ".xls");

echo "Invoice ID\tCustomer Name\tDate\tTotal Amount\n";

$query = $conn->query("SELECT invoice_id, customer_name, sale_date, total_amount FROM sales ORDER BY sale_id DESC");

while ($row = $query->fetch_assoc()) {
    echo $row['invoice_id'] . "\t" . $row['customer_name'] . "\t" . $row['sale_date'] . "\t" . $row['total_amount'] . "\n";
}
?>
