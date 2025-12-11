<?php
header('Content-Type: application/json; charset=utf-8');
// Keep strict errors but capture messages in JSON to help diagnose 500 errors on frontend
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Capture fatal shutdown errors and return JSON to help frontend parse errors
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null) {
        http_response_code(500);
        // Log for service operators
        error_log("Fatal error in update_store.php: " . print_r($err, true));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Server error during update',
            'error' => $err
        ]);
    }
});

require_once __DIR__ . '/../../config/db.php';
session_start();

// Auth check
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

// Validate admin role
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Only admins can update store settings']);
    exit;
}

// Get POST data
$store_name = trim($_POST['store_name'] ?? '');
$gstin = trim($_POST['gstin'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$store_address = trim($_POST['store_address'] ?? '');
$note = trim($_POST['note'] ?? '');

// ============ VALIDATION: Only store_name is mandatory ============
if (empty($store_name)) {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Store name is required']);
    exit;
}

// GSTIN validation (optional field, but if provided must be 15 chars, alphanumeric)
if (!empty($gstin)) {
    if (strlen($gstin) !== 15) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'GSTIN must be exactly 15 characters']);
        exit;
    }

    if (!preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'GSTIN must contain only letters (A-Z) and numbers (0-9)']);
        exit;
    }

    // Check if GSTIN already exists (different store)
    $check = $conn->prepare("SELECT store_id FROM stores WHERE gstin = ? AND store_id != ?");
    $check->bind_param("si", $gstin, $store_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'This GSTIN is already registered']);
        exit;
    }
    $check->close();
}

// ============ UPDATE: stores table (build SQL based on available columns) ============
$availableCols = [];
$res = $conn->query("SHOW COLUMNS FROM stores");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $availableCols[$row['Field']] = true;
    }
}
// collect schema modification errors for response
$schemaError = [];

// If neither 'note' nor legacy 'notice' column exists and a note value is provided, add 'note' column automatically
if (empty($availableCols['note']) && empty($availableCols['notice']) && !empty($note)) {
    // Best-effort: re-check columns to avoid race conditions, then attempt to add 'note' column safely.
    try {
        $re = $conn->query("SHOW COLUMNS FROM stores LIKE 'note'");
        if ($re && $re->num_rows) {
            $availableCols['note'] = true;
        } else {
            $alterSql = "ALTER TABLE stores ADD COLUMN note TEXT NULL";
            $conn->query($alterSql);
            $availableCols['note'] = true;
        }
    } catch (mysqli_sql_exception $e) {
        // 1060: Duplicate column name, ignore and mark as available
        if ($e->getCode() == 1060) {
            $availableCols['note'] = true;
        } else {
            $schemaError[] = "Failed to add 'note' column: " . $e->getMessage();
        }
    }
}

// Also add 'store_address' column if missing and a value is provided
if (empty($availableCols['store_address']) && !empty($store_address)) {
    try {
        $re = $conn->query("SHOW COLUMNS FROM stores LIKE 'store_address'");
        if ($re && $re->num_rows) {
            $availableCols['store_address'] = true;
        } else {
            $alterSql = "ALTER TABLE stores ADD COLUMN store_address TEXT NULL";
            $conn->query($alterSql);
            $availableCols['store_address'] = true;
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1060) { // duplicate column
            $availableCols['store_address'] = true;
        } else {
            $schemaError[] = "Failed to add 'store_address' column: " . $e->getMessage();
        }
    }
}

$updateParts = ['store_name = ?', 'gstin = ?'];
$types = 'ss';
$params = [$store_name, $gstin];

if (!empty($availableCols['contact_number'])) {
    $updateParts[] = 'contact_number = ?';
    $types .= 's';
    $params[] = $contact_number;
}

if (!empty($availableCols['store_address'])) {
    $updateParts[] = 'store_address = ?';
    $types .= 's';
    $params[] = $store_address;
}

if (!empty($availableCols['note'])) {
    $updateParts[] = 'note = ?';
    $types .= 's';
    $params[] = $note;
} elseif (!empty($availableCols['notice'])) {
    // fallback to older column name
    $updateParts[] = 'notice = ?';
    $types .= 's';
    $params[] = $note;
}

$updateSql = 'UPDATE stores SET ' . implode(', ', $updateParts) . ' WHERE store_id = ?';
$types .= 'i';
$params[] = $store_id;

$stmt = $conn->prepare($updateSql);
if (!$stmt) {
    $err = $conn->error;
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Failed to prepare update query', 'sql' => $updateSql, 'db_error' => $err, 'debug' => $schemaError ?? []]);
    exit;
}

// Bind params dynamically
$bind_names = [];
$bind_names[] = $types;
foreach ($params as $key => $value) {
    $bind_name = 'bind' . $key;
    $$bind_name = $value;
    $bind_names[] = &$$bind_name;
}
// Validate param count matches number of placeholders
$expectedParams = substr_count($updateSql, '?');
if ($expectedParams !== count($params)) {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Parameter count mismatch', 'expected_placeholders' => $expectedParams, 'params' => $params, 'sql' => $updateSql]);
    exit;
}

call_user_func_array([$stmt, 'bind_param'], $bind_names);

if (!$stmt->execute()) {
    $err = $stmt->error ?: $conn->error;
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Failed to update store settings', 'stmt_error' => $err, 'sql' => $updateSql, 'debug' => $schemaError ?? []]);
    $stmt->close();
    exit;
}
$stmt->close();

$warnings = [];
if (!empty($schemaError)) {
    $warnings = array_merge($warnings, $schemaError);
}

// If admin attempted to submit note but it couldn't be stored due to missing column privileges
if (!empty($note) && empty($availableCols['note']) && empty($availableCols['notice'])) {
    $warnings[] = "Note not saved: 'note' column doesn't exist and could not be created. Run the migration or grant ALTER privileges.";
}
if (!empty($store_address) && empty($availableCols['store_address'])) {
    $warnings[] = "Store address not saved: 'store_address' column doesn't exist and could not be created. Run the migration or grant ALTER privileges.";
}

echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Store settings updated successfully', 'warnings' => $warnings]);
$conn->close();
?>