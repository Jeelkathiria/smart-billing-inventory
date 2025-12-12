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
$email    = isset($_POST['email']) ? trim($_POST['email']) : null; // optional
$personal_contact = isset($_POST['personal_contact_number']) ? trim($_POST['personal_contact_number']) : null; // optional

if (empty($username)) {
    echo json_encode(['success' => false, 'msg' => 'Username is required']);
    exit;
}

// Optional: basic contact validation
if ($personal_contact && !preg_match('/^\+?[0-9\-\s]{7,20}$/', $personal_contact)) {
    echo json_encode(['success' => false, 'msg' => 'Invalid personal contact number']);
    exit;
}

// ================= UPDATE QUERY =================
// Build update dynamically to avoid overwriting fields not present in the request
$updates = [];
$types = '';
$params = [];

$updates[] = 'username = ?'; $types .= 's'; $params[] = $username;
if ($email !== null) { $updates[] = 'email = ?'; $types .= 's'; $params[] = $email; }
if ($personal_contact !== null) { $updates[] = 'personal_contact_number = ?'; $types .= 's'; $params[] = $personal_contact; }

$updatesSql = implode(', ', $updates);
$sql = "UPDATE users SET $updatesSql WHERE user_id = ? AND store_id = ?";
$stmt = $conn->prepare($sql);
// bind params dynamically
$types .= 'ii'; // for user_id and store_id
$params[] = $user_id;
$params[] = $store_id;
// Convert to references for bind_param
$refs = array();
foreach ($params as $key => $val) {
    $refs[$key] = &$params[$key];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

if ($stmt->execute()) {

    // INSTANT UPDATE SESSION
    // Update session values only for fields that were set
    $_SESSION['username'] = $username;
    if ($email !== null) $_SESSION['email'] = $email;
    if ($personal_contact !== null) $_SESSION['personal_contact_number'] = $personal_contact;

    echo json_encode(['success' => true, 'msg' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'msg' => 'Database update failed: ' . $conn->error]);
}

$stmt->close();
$conn->close();
