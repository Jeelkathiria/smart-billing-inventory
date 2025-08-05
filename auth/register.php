<?php
session_start();
require_once '../includes/db.php'; // adjust path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitize input
  $store_name = trim($_POST['store_name']);
  $store_email = trim($_POST['store_email']);
  $contact_number = trim($_POST['contact_number']);
  $username = trim($_POST['username']);
  $password = $_POST['password'];
  $role = 'admin'; // default role

  // Validation
  if (empty($store_name) || empty($store_email) || empty($contact_number) || empty($username) || empty($password)) {
    header("Location: index.php?error=Please fill in all fields");
    exit();
  }

  // Check if username exists
  $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    header("Location: index.php?error=Username already exists");
    exit();
  }
  $stmt->close();

  // Insert into stores
  $stmt = $conn->prepare("INSERT INTO stores (store_name, store_email, contact_number) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $store_name, $store_email, $contact_number);
  if (!$stmt->execute()) {
    header("Location: index.php?error=Failed to create store");
    exit();
  }
  $store_id = $stmt->insert_id;
  $stmt->close();

  // Insert into users
  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO users (username, password, role, store_id) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("sssi", $username, $hashedPassword, $role, $store_id);
  if ($stmt->execute()) {
    header("Location: index.php?success=Store registered successfully! Please login.");
    exit();
  } else {
    header("Location: index.php?error=Failed to create user account");
    exit();
  }
}
?>
