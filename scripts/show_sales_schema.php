<?php
require_once __DIR__ . '/../config/db.php';
$res = $conn->query('SHOW CREATE TABLE sales');
$row = $res->fetch_assoc();
print_r($row);
