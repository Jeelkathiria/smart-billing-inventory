<?php
/**
 * File: scripts/check_users.php
 * Purpose: Dev helper that prints user counts and samples for quick verification.
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Comments only.
 */
require_once __DIR__ . '/../config/db.php';
$res = $conn->query('SELECT COUNT(*) AS c FROM users');
echo 'users: ' . ($res->fetch_assoc()['c'] ?? 0) . PHP_EOL;
$res2 = $conn->query('SELECT user_id, username, email, role, store_id FROM users LIMIT 5');
while ($row = $res2->fetch_assoc()) print_r($row);
