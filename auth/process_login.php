<?php
session_start();
require_once '../includes/db.php'; // adjust path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  if (empty($username) || empty($password)) {
    header("Location: index.php?error=Please fill in all fields");
    exit();
  }

  $stmt = $conn->prepare("SELECT user_id, password, role, store_id FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows === 1) {
    $stmt->bind_result($user_id, $hashedPassword, $role, $store_id);
    $stmt->fetch();

    if (password_verify($password, $hashedPassword)) {
      // Valid login
      $_SESSION['user_id'] = $user_id;
      $_SESSION['username'] = $username;
      $_SESSION['store_id'] = $store_id;
      $_SESSION['role'] = $role;

      header("Location: ../pages/dashboard.php");
      exit();
    } else {
      header("Location: index.php?error=Invalid credentials");
      exit();
    }
  } else {
    header("Location: index.php?error=User not found");
    exit();
  }
}
?>
