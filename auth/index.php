<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // MySQLi connection

// -------- Handle Login --------
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $login_error = "Please fill all fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['store_id'] = $user['store_id'];
                $_SESSION['login_time'] = time();

                // Absolute path redirect to dashboard
                header("Location: /modules/dashboard.php");
                exit();
            } else {
                $login_error = "Invalid password.";
            }
        } else {
            $login_error = "User not found.";
        }
    }
}

// -------- Handle Registration --------
if (isset($_POST['register'])) {
    $store_name = trim($_POST['store_name']);
    $store_email = trim($_POST['store_email']);
    $contact_number = trim($_POST['contact_number']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = 'admin';

    if (empty($store_name) || empty($username) || empty($password)) {
        $register_error = "Please fill all required fields.";
    } else {
        // Check store
        $stmt = $conn->prepare("SELECT store_id FROM stores WHERE store_name = ?");
        $stmt->bind_param("s", $store_name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $store = $result->fetch_assoc();
            $store_id = $store['store_id'];
        } else {
            // Insert new store
            $stmt = $conn->prepare("INSERT INTO stores (store_name, store_email, contact_number, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $store_name, $store_email, $contact_number);
            $stmt->execute();
            $store_id = $conn->insert_id;
        }

        // Check username
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $register_error = "Username already exists!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, store_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssi", $username, $hashedPassword, $role, $store_id);

            if ($stmt->execute()) {
                $register_success = "Registration successful! Please login.";
            } else {
                $register_error = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Smart Billing & Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin:0; padding:0; min-height:100vh;
            background:#f3f4f6 url('https://www.transparenttextures.com/patterns/store-icon-pattern.svg') repeat;
            display:flex; align-items:center; justify-content:center; font-family:'Segoe UI', sans-serif;
        }
        .container-custom {
            display:flex; flex-wrap:wrap; max-width:1100px; width:100%;
            background:#fff; box-shadow:0 8px 30px rgba(0,0,0,0.1); border-radius:16px; overflow:hidden;
        }
        .left-panel {
            background:linear-gradient(135deg, #0f172a, #2563eb); color:white;
            padding:40px; flex:1; min-width:300px; display:flex; flex-direction:column; justify-content:center;
        }
        .left-panel h1 {font-size:2.8rem; font-weight:bold;}
        .left-panel p {font-size:1.1rem; margin-top:10px;}
        .left-panel ul {margin-top:25px; list-style:none; padding:0;}
        .left-panel ul li {margin-bottom:10px;}
        .left-panel ul li::before {content:"✔️"; margin-right:8px;}
        .right-panel {background:#fff; padding:40px; flex:1; min-width:300px;}
        .nav-tabs .nav-link.active {background-color:#2563eb; color:white !important; border:none;}
        .nav-tabs .nav-link {border:none; font-weight:500; color:#333;}
        .form-control:focus {border-color:#2563eb; box-shadow:0 0 0 0.2rem rgba(37,99,235,0.25);}
        .btn-primary {background-color:#2563eb; border:none;}
        .btn-success {background-color:#10b981; border:none;}
        .btn:hover {opacity:0.95;}
        .form-title {font-size:1.5rem; font-weight:600; margin-bottom:20px; color:#333;}
        @media (max-width:768px){.container-custom{flex-direction:column;}.left-panel,.right-panel{padding:30px;text-align:center;}}
    </style>
</head>
<body>
<div class="container-custom">
    <div class="left-panel">
        <h1>Smart Billing System</h1>
        <p>Perfect for electronics, clothing, grocery, tools, and more!</p>
        <ul>
            <li>Multi-user secure login</li>
            <li>Live GST billing system</li>
            <li>Real-time inventory control</li>
            <li>Full financial reports</li>
        </ul>
    </div>
    <div class="right-panel">
        <ul class="nav nav-tabs mb-4" id="formTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#loginTab" type="button">Login</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#registerTab" type="button">Register</button>
            </li>
        </ul>
        <div class="tab-content">
            <!-- Login Tab -->
            <div class="tab-pane fade show active" id="loginTab">
                <?php if(isset($login_error)) echo "<div class='alert alert-danger'>$login_error</div>"; ?>
                <form method="POST">
                    <input type="text" name="username" class="form-control mb-3" placeholder="Username" required>
                    <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
                    <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
            <!-- Register Tab -->
            <div class="tab-pane fade" id="registerTab">
                <?php if(isset($register_error)) echo "<div class='alert alert-danger'>$register_error</div>"; ?>
                <?php if(isset($register_success)) echo "<div class='alert alert-success'>$register_success</div>"; ?>
                <form method="POST">
                    <input type="text" name="store_name" class="form-control mb-2" placeholder="Store Name" required>
                    <input type="email" name="store_email" class="form-control mb-2" placeholder="Store Email">
                    <input type="text" name="contact_number" class="form-control mb-2" placeholder="Contact Number">
                    <input type="text" name="username" class="form-control mb-2" placeholder="Admin Username" required>
                    <input type="password" name="password" class="form-control mb-2" placeholder="Password" required>
                    <button type="submit" name="register" class="btn btn-success w-100">Register</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
