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

                // Redirect to dashboard
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
function generateStoreCode() {
    return strtoupper(substr(uniqid('ST'), -6)); // e.g., ST9A2B1
}

if (isset($_POST['register'])) {
    $role = $_POST['role'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($role === 'admin') {
        $store_name = trim($_POST['store_name']);
        $store_email = trim($_POST['store_email']);
        $contact_number = trim($_POST['contact_number']);

        if (empty($store_name) || empty($username) || empty($password)) {
            $register_error = "Please fill all required fields.";
        } else {
            // Create store with unique code
            $store_code = generateStoreCode();
            $stmt = $conn->prepare("INSERT INTO stores (store_name, store_email, contact_number, store_code, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $store_name, $store_email, $contact_number, $store_code);
            $stmt->execute();
            $store_id = $conn->insert_id;

            // Create admin user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, store_id, created_at) VALUES (?, ?, 'admin', ?, NOW())");
            $stmt->bind_param("ssi", $username, $hashedPassword, $store_id);
            if ($stmt->execute()) {
                $register_success = "Admin registered! Your store code: <b>$store_code</b>. Share this with employees.";
            } else {
                $register_error = "Error: " . $conn->error;
            }
        }

    } else {
        // Employee (manager/cashier) registration
        $store_code_input = trim($_POST['store_code']);
        if (empty($store_code_input) || empty($username) || empty($password)) {
            $register_error = "Please fill all required fields.";
        } else {
            // Validate store code
            $stmt = $conn->prepare("SELECT store_id FROM stores WHERE store_code = ?");
            $stmt->bind_param("s", $store_code_input);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $register_error = "Invalid store code.";
            } else {
                $store_id = $res->fetch_assoc()['store_id'];
                // Create employee (manager or cashier)
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, store_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssi", $username, $hashedPassword, $role, $store_id);
                if ($stmt->execute()) {
                    $register_success = ucfirst($role) . " registered under store successfully!";
                } else {
                    $register_error = "Error: " . $conn->error;
                }
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
      margin: 0; padding: 0; min-height: 100vh;
      background: #f3f4f6 url('https://www.transparenttextures.com/patterns/store-icon-pattern.svg') repeat;
      display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif;
    }
    .container-custom {
      display: flex; flex-wrap: wrap; max-width: 1100px; width: 100%;
      background: #fff; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
      border-radius: 16px; overflow: hidden;
    }
    .left-panel { background: linear-gradient(135deg, #0f172a, #2563eb); color: white;
      padding: 40px; flex: 1; min-width: 300px; display: flex; flex-direction: column; justify-content: center;
    }
    .left-panel h1 { font-size: 2.8rem; font-weight: bold; }
    .left-panel ul { margin-top: 25px; list-style: none; padding: 0; }
    .left-panel ul li::before { content: "✔️"; margin-right: 8px; }
    .right-panel { background: #fff; padding: 40px; flex: 1; min-width: 300px; }
    .nav-tabs .nav-link.active { background-color: #2563eb; color: white !important; border: none; }
    .btn-primary { background-color: #2563eb; border: none; }
    .btn-success { background-color: #10b981; border: none; }
    .btn:hover { opacity: 0.95; }
    @media (max-width:768px) { .container-custom { flex-direction: column; } }
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
      <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#loginTab">Login</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#registerTab">Register</button></li>
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
          <?php if(isset($register_error)): ?><div class="alert alert-danger text-center"><?= $register_error ?></div><?php endif; ?>
          <?php if(isset($register_success)): ?><div class="alert alert-success text-center"><?= $register_success ?></div><?php endif; ?>

          <div class="card shadow-lg border-0 rounded-3">
            <div class="card-body p-4">
              <h4 class="text-center mb-4">Create Account</h4>
              <form method="POST" id="registerForm">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Select Role</label>
                  <select name="role" id="roleSelect" class="form-select" required>
                    <option value="" disabled selected>Choose Role</option>
                    <option value="admin">Admin (Create Store)</option>
                    <option value="manager">Manager</option>
                    <option value="cashier">Cashier</option>
                  </select>
                </div>
                <div id="adminFields">
                  <div class="mb-3"><label class="form-label fw-semibold">Store Name</label>
                    <input type="text" name="store_name" class="form-control"></div>
                  <div class="mb-3"><label class="form-label fw-semibold">Store Email</label>
                    <input type="email" name="store_email" class="form-control"></div>
                  <div class="mb-3"><label class="form-label fw-semibold">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control"></div>
                </div>
                <div id="employeeFields" style="display:none;">
                  <div class="mb-3"><label class="form-label fw-semibold">Store Code</label>
                    <input type="text" name="store_code" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label fw-semibold">Username</label>
                  <input type="text" name="username" class="form-control" required></div>
                <div class="mb-3"><label class="form-label fw-semibold">Password</label>
                  <input type="password" name="password" class="form-control" required></div>
                <button type="submit" name="register" class="btn btn-success w-100 py-2 fw-semibold">Register</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.getElementById('roleSelect').addEventListener('change', function() {
      if (this.value === 'admin') {
        document.getElementById('adminFields').style.display = 'block';
        document.getElementById('employeeFields').style.display = 'none';
      } else {
        document.getElementById('adminFields').style.display = 'none';
        document.getElementById('employeeFields').style.display = 'block';
      }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
