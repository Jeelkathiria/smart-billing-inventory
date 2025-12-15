<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // ✅ 1. Session validation
    if (!isset($_SESSION['store_id'])) {
        echo json_encode(['success' => false, 'msg' => 'Session expired. Please log in again.']);
        exit;
    }
    $store_id = $_SESSION['store_id'];

    // ✅ 2. Collect fields (checkboxes)
        $fields = $_POST['fields'] ?? [];

        // If this is a full update coming from the Billing Fields form, ensure it cannot modify
        // the `print_store_email` setting (it's managed via the Store Info toggle).
        if (!empty($_POST['full_update']) && isset($fields['print_store_email'])) {
            unset($fields['print_store_email']);
        }
    $json_fields = json_encode($fields, JSON_UNESCAPED_UNICODE);

    // ✅ 3. Update billing_fields JSON column
    $stmt = $conn->prepare("UPDATE stores SET billing_fields = ? WHERE store_id = ?");
    $stmt->bind_param("si", $json_fields, $store_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'msg' => 'Billing fields updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to update billing fields.']);
    }

    $stmt->close();
    $conn->close();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'msg' => 'Server error: ' . $e->getMessage()]);
}
?>
