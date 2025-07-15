<?php
session_start();
require_once '../includes/db.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
  header("Location: login.php?error=Please enter username and password");
  exit();
}

$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $user = $result->fetch_assoc();
  if (password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    header("Location: ../pages/dashboard.php");
    exit();
  } else {
    header("Location: login.php?error=Incorrect password");
    exit();
  }
} else {
  header("Location: login.php?error=User not found");
  exit();
}
?>
