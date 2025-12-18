<?php
require_once __DIR__ . '/../config/db.php';
$urow = $conn->query('SELECT user_id FROM users LIMIT 1')->fetch_assoc();
$u = $urow['user_id'] ?? null;
$srow = $conn->query('SELECT store_id FROM stores LIMIT 1')->fetch_assoc();
$s = $srow['store_id'] ?? null;
$sql = "INSERT INTO sales (invoice_id, store_id, customer_id, customer_name, subtotal, tax_amount, total_amount, created_by, sale_date) VALUES ('".uniqid('T')."', $s, NULL, '--', 10.00, 0.00, 10.00, $u, '".date('Y-m-d H:i:s')."')";
var_dump($sql);
if ($conn->query($sql)) echo "Insert OK\n"; else echo "Insert failed: (".$conn->errno.") ". $conn->error . "\n";
