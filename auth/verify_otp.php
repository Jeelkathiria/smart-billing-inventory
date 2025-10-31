<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// -------------------- GET SESSION DATA --------------------
$otp = trim($_POST['otp'] ?? '');
$sessionOtp = $_SESSION['otp'] ?? '';
$register_data = $_SESSION['register_data'] ?? null;

if (!$register_data) {
    echo json_encode(['status' => 'session_expired']);
    exit;
}

// -------------------- VERIFY OTP --------------------
if ((string)$otp === (string)$sessionOtp) {

    $username = trim($register_data['username']);
    $email = trim($register_data['email']);
    $password = password_hash($register_data['password'], PASSWORD_DEFAULT);
    $role = $register_data['role'] ?? 'user';

    $store_id = null;
    $store_code = null;

    /* ======================================================
       1️⃣ ADMIN → Create new store
    ====================================================== */
    if ($role === 'admin') {
        $store_name = trim($register_data['store_name'] ?? '');
        $store_email = trim($register_data['store_email'] ?? '');
        $contact_number = trim($register_data['contact_number'] ?? '');

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

        // ✅ Get auto-generated store_id
        $store_id = $conn->insert_id;
    }

    /* ======================================================
       2️⃣ MANAGER / CASHIER → Use existing store_code
    ====================================================== */
    elseif (in_array($role, ['manager', 'cashier'])) {
        $entered_code = trim($register_data['store_code'] ?? '');

        if (empty($entered_code)) {
            echo json_encode(['status' => 'missing_store_code']);
            exit;
        }

        $stmtFetch = $conn->prepare("SELECT store_id FROM stores WHERE store_code = ?");
        $stmtFetch->bind_param('s', $entered_code);
        $stmtFetch->execute();
        $res = $stmtFetch->get_result();

        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $store_id = $row['store_id'];
        } else {
            echo json_encode(['status' => 'invalid_store_code']);
            exit;
        }
    }

    /* ======================================================
       3️⃣ INSERT USER
    ====================================================== */
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
        unset($_SESSION['otp'], $_SESSION['register_data'], $_SESSION['otp_expiry']);

        echo json_encode([
            'status' => 'success',
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'store_id' => $store_id,
            'store_code' => $store_code
        ]);
    } else {
        echo json_encode(['status' => 'db_error', 'error' => $stmtUser->error]);
    }

} else {
    echo json_encode(['status' => 'invalid_otp']);
}
?>
