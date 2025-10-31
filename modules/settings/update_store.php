<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Ensure session is valid
    if (!isset($_SESSION['store_id'])) {
        echo json_encode(['success' => false, 'msg' => 'Unauthorized: session missing']);
        exit;
    }

    $store_id = $_SESSION['store_id'];

    // Collect form inputs
    $store_name     = trim($_POST['store_name'] ?? '');
    $store_email    = trim($_POST['store_email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $gstin          = trim($_POST['gstin'] ?? '');

    // Validate
    if (empty($store_name) || empty($store_email) || empty($contact_number)) {
        echo json_encode(['success' => false, 'msg' => 'Please fill all required fields']);
        exit;
    }

    // Prepare SQL update
    $stmt = $conn->prepare("
        UPDATE stores 
        SET store_name = ?, store_email = ?, contact_number = ?, gstin = ?
        WHERE store_id = ?
    ");
    $stmt->bind_param("ssssi", $store_name, $store_email, $contact_number, $gstin, $store_id);

    // Execute and respond
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'msg' => 'Store information updated successfully']);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to update store information']);
    }

    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'msg' => 'Server error: ' . $e->getMessage()]);
}
?>
