<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// -------------------- GET SESSION DATA --------------------
$otp = trim($_POST['otp'] ?? '');
$sessionOtp = $_SESSION['otp'] ?? '';
$register_data = $_SESSION['register_data'] ?? null;

// Debug logs (only for localhost)
error_log("POST OTP: " . $otp);
error_log("SESSION OTP: " . $sessionOtp);
error_log("REGISTER DATA: " . print_r($register_data, true));

if (!$register_data) {
    echo json_encode(['status' => 'session_expired']);
    exit;
}

// -------------------- VERIFY OTP --------------------
if ((string)$otp === (string)$sessionOtp) {

    $username = $register_data['username'];
    $email = $register_data['email'];
    $password = password_hash($register_data['password'], PASSWORD_DEFAULT);
    $role = $register_data['role'] ?? 'user';

    $store_id = null; // default null (for users)
    $store_code = null;

    // -------------------- IF ADMIN: CREATE STORE --------------------
    if ($role === 'admin') {
        $store_name = $register_data['store_name'] ?? '';
        $store_email = $register_data['store_email'] ?? '';
        $contact_number = $register_data['contact_number'] ?? '';

        // Generate unique store code
        $store_code = 'STR' . rand(1000, 9999);

        $stmtStore = $conn->prepare("
            INSERT INTO stores (store_name, store_email, contact_number, store_code, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        if (!$stmtStore) {
            echo json_encode(['status' => 'sql_error', 'error' => $conn->error]);
            exit;
        }

        $stmtStore->bind_param('ssss', $store_name, $store_email, $contact_number, $store_code);
        if (!$stmtStore->execute()) {
            echo json_encode(['status' => 'db_error', 'error' => $stmtStore->error]);
            exit;
        }

        // ✅ Get the auto-generated store_id
        $store_id = $conn->insert_id;
    }

    // -------------------- INSERT USER --------------------
    $stmtUser = $conn->prepare("
        INSERT INTO users (username, email, password, role, store_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmtUser) {
        echo json_encode(['status' => 'sql_error', 'error' => $conn->error]);
        exit;
    }

    $stmtUser->bind_param('ssssi', $username, $email, $password, $role, $store_id);

    if ($stmtUser->execute()) {
        // ✅ Cleanup
        unset($_SESSION['otp']);
        unset($_SESSION['register_data']);

        echo json_encode([
            'status' => 'success',
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'store_id' => $store_id,
            'store_code' => $store_code
        ]);
        exit;
    } else {
        echo json_encode(['status' => 'db_error', 'error' => $stmtUser->error]);
        exit;
    }

} else {
    echo json_encode(['status' => 'invalid_otp']);
    exit;
}
