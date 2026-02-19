<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ===========================================
   AUTH CHECK
=========================================== */
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized access']);
    exit;
}

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

/* ===========================================
   VALIDATE METHOD
=========================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Invalid request method']);
    exit;
}

/* ===========================================
   STAGE CHECK
=========================================== */
$stage = $_POST['stage'] ?? 'verify'; // 'verify' or 'update'

if ($stage === 'verify') {
    // Step 1: Verify current password
    $current_password = trim($_POST['current_password'] ?? '');

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $user_id, $store_id);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

    if (!$hashed_password || !password_verify($current_password, $hashed_password)) {
        echo json_encode(['success' => false, 'msg' => 'Incorrect current password']);
        exit;
    }

    echo json_encode(['success' => true, 'msg' => 'Password verified']);
    exit;
}

if ($stage === 'update') {
    // Step 2: Update new password
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'msg' => 'All fields are required']);
        exit;
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'msg' => 'Passwords do not match']);
        exit;
    }

    $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ? AND store_id = ?");
    $stmt->bind_param("sii", $hashed_new, $user_id, $store_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'msg' => 'Password updated successfully']);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Database update failed: ' . $conn->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Invalid stage']);
