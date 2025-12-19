<?php
/**
 * File: scripts/show_sales_schema.php
 * Purpose: Show the CREATE TABLE statement for the sales table (debugging/schema inspection).
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Comments only.
 */
require_once __DIR__ . '/../config/db.php';
$res = $conn->query('SHOW CREATE TABLE sales');
$row = $res->fetch_assoc();
print_r($row);
