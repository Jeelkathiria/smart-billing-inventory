<?php
session_start();
require_once __DIR__ . "/../config/db.php"; // adjust path if needed

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $store_name = trim($_POST['store_name']);
    $store_email = trim($_POST['store_email']);
    $contact_number = trim($_POST['contact_number']);

    // Validate required fields
    if (empty($username) || empty($password) || empty($store_name)) {
        $_SESSION['error'] = "All required fields must be filled.";
        header("Location: ../index.php");
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Check if store already exists
        $stmt = $pdo->prepare("SELECT store_id FROM stores WHERE store_name = :store_name LIMIT 1");
        $stmt->execute([':store_name' => $store_name]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($store) {
            $store_id = $store['store_id'];
        } else {
            // Insert new store
            $stmt = $pdo->prepare("INSERT INTO stores (store_name, store_email, contact_number, created_at) 
                                   VALUES (:store_name, :store_email, :contact_number, NOW())");
            $stmt->execute([
                ':store_name' => $store_name,
                ':store_email' => $store_email,
                ':contact_number' => $contact_number
            ]);
            $store_id = $pdo->lastInsertId();
        }

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username already exists!";
            header("Location: ../index.php");
            exit();
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, created_at, store_id) 
                               VALUES (:username, :password, :role, NOW(), :store_id)");
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':role' => $role,
            ':store_id' => $store_id
        ]);

        $pdo->commit();

        $_SESSION['success'] = "Registration successful! Please log in.";
        header("Location: ../index.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: ../index.php");
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
