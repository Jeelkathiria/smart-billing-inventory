<?php
/**
 * File: modules/settings/get_store_data.php
 * Purpose: Returns store profile (name, contact, GSTIN, optional fields) as JSON for the settings UI.
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: Comments only.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$store_id = $_SESSION['store_id'];

$selectCols = ['store_name','store_email','contact_number','gstin'];
// Check optional columns
$resA = $conn->query("SHOW COLUMNS FROM stores LIKE 'store_address'");
if ($resA && $resA->num_rows) $selectCols[] = 'store_address';
$resB = $conn->query("SHOW COLUMNS FROM stores LIKE 'note'");
if ($resB && $resB->num_rows) $selectCols[] = 'note';
// fallback
$resB = $conn->query("SHOW COLUMNS FROM stores LIKE 'notice'");
if ($resB && $resB->num_rows && !in_array('notice', $selectCols)) $selectCols[] = 'notice';

$colsSql = implode(', ', $selectCols);
$stmt = $conn->prepare("SELECT $colsSql FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch admin personal_contact_number if available
$admin_contact = '';
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $s2 = $conn->prepare("SELECT personal_contact_number FROM users WHERE user_id = ?");
    if ($s2) {
        $s2->bind_param('i', $uid);
        $s2->execute();
        $r = $s2->get_result();
        if ($r && $r->num_rows === 1) {
            $temp = $r->fetch_assoc();
            $admin_contact = $temp['personal_contact_number'] ?? '';
        }
            $s2->close();
    }
}

    $conn->close();

    echo json_encode([
    'success' => true,
    'store_name' => $store['store_name'] ?? '',
    'store_email' => $store['store_email'] ?? '',
    'contact_number' => $store['contact_number'] ?? '',
    'store_address' => $store['store_address'] ?? '',
    'note' => $store['note'] ?? $store['notice'] ?? '',
    'gstin' => $store['gstin'] ?? '',
    'admin_contact' => $admin_contact
]);
?>
