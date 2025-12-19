<?php
/**
 * File: modules/sales/export_sales.php
 * Purpose: Exports per-item sales as an Excel-compatible TSV file (Invoice ID, Date, Product, Qty, Amount Excl/Incl GST, Billed By).
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Only comments added; no functional changes.
 */
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
echo "Invoice ID\tDate & Time\tProduct Name\tQuantity\tAmount (Excl. GST)\tAmount (Incl. GST)\tBilled By\n";

// Fetch sale items with sale info for this store (one row per item)
// product_name uses si.product_name if present, otherwise fallback to product table
$sql = "SELECT s.invoice_id, s.sale_date, COALESCE(si.product_name, p.product_name, CONCAT('Deleted product (ID:', si.product_id, ')')) AS product_name, si.quantity, si.total_price AS amount_incl, si.gst_percent, u.username AS billed_by
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.sale_id
        LEFT JOIN products p ON si.product_id = p.product_id
        LEFT JOIN users u ON s.created_by = u.user_id
        WHERE s.store_id = ?
        ORDER BY s.sale_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $amount_incl = (float)$row['amount_incl'];
    $gst = (float)$row['gst_percent'];
    $amount_excl = $amount_incl - ($amount_incl * ($gst / (100 + $gst)));
    echo $row['invoice_id'] . "\t" . $row['sale_date'] . "\t" . $row['product_name'] . "\t" . $row['quantity'] . "\t" . number_format($amount_excl, 2, '.', '') . "\t" . number_format($amount_incl, 2, '.', '') . "\t" . ($row['billed_by'] ?? '') . "\n";
}

$stmt->close();
$conn->close();
?>
