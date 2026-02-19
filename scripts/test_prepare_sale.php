<?php
/**
 * File: scripts/test_prepare_sale.php
 * Purpose: Dev helper to prepare a minimal sale record for testing flows.
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Comments only.
 */
require_once __DIR__ . '/../config/db.php';
$urow = $conn->query('SELECT user_id FROM users LIMIT 1')->fetch_assoc();
$u = $urow['user_id'] ?? null;
$srow = $conn->query('SELECT store_id FROM stores LIMIT 1')->fetch_assoc();
$s = $srow['store_id'] ?? null;
$invoice_id = uniqid('T');
$customer_id = null;
$customer_name = '--';
$subtotal = 10.00;
$tax = 0.00;
$total = 10.00;
$sale_date = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO sales (invoice_id, store_id, customer_id, customer_name, subtotal, tax_amount, total_amount, created_by, sale_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) { echo "Prepare failed: (".$conn->errno.") ".$conn->error."\n"; exit; }
$stmt->bind_param('siissddis', $invoice_id, $s, $customer_id, $customer_name, $subtotal, $tax, $total, $u, $sale_date);
if (!$stmt->execute()) { echo "Execute failed: (".$stmt->errno.") ".$stmt->error."\n"; } else { echo "Execute OK\n"; }
$stmt->close();
