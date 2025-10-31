<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================= AUTH CHECK =================
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized access']);
    exit;
}

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

// ================= VALIDATE REQUEST =================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Invalid request method']);
    exit;
}

// ================= INPUTS =================
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');

if (empty($username) || empty($email)) {
    echo json_encode(['success' => false, 'msg' => 'Username and email are required']);
    exit;
}

// ================= UPDATE QUERY =================
$stmt = $conn->prepare("UPDATE users 
                        SET username = ?, email = ?
                        WHERE user_id = ? AND store_id = ?");
$stmt->bind_param("ssii", $username, $email, $user_id, $store_id);


if ($stmt->execute()) {
    echo json_encode(['success' => true, 'msg' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'msg' => 'Database update failed: ' . $conn->error]);
}

$stmt->close();
$conn->close();