<?php
// Only show fatal errors, ignore warnings/notices
error_reporting(E_ERROR | E_PARSE);

// Include database connection (adjust path relative to this file)
require_once __DIR__ . '/../config/db.php';

// Tell AJAX that this is JSON
header('Content-Type: application/json');

if (isset($_POST['store_code'])) {

    $store_code = trim($_POST['store_code']);

    // Prepare query using store_id
    $stmt = $conn->prepare("SELECT store_id FROM stores WHERE store_code = ?");
    $stmt->bind_param("s", $store_code);
    $stmt->execute();
    $stmt->store_result();

    // Return JSON response
    if ($stmt->num_rows > 0) {
        echo json_encode(['status' => 'valid']);
    } else {
        echo json_encode(['status' => 'invalid']);
    }

    $stmt->close();
    exit;

} else {
    // store_code not sent
    echo json_encode(['status' => 'error', 'message' => 'No store_code sent']);
    exit;
}
