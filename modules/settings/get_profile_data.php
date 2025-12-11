<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT username, role, personal_contact_number FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'username' => $user['username'] ?? '',
    'role' => $user['role'] ?? '',
    'personal_contact_number' => $user['personal_contact_number'] ?? ''
]);
?>
