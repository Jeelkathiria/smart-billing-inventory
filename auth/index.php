<?php
session_start();
$login_error = null;
$register_error = null;
$register_success = null;

// Check query parameter (after redirect)
if (isset($_GET['login_error'])) {
    $login_error = $_GET['login_error'];
}
require_once __DIR__ . '/../config/db.php'; // MySQLi connection

// -------- Handle Login --------
// -------- Handle Login (POST) --------
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $error = null;

    if (empty($username) || empty($password)) {
        $error = "Please fill all fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // ✅ Success
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['store_id'] = $user['store_id'];
                $_SESSION['login_time'] = time();

                // Redirect to dashboard
                header("Location: /modules/dashboard.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
    }

    // If error → redirect back with query parameter
    if ($error) {
        header("Location: /auth/index.php?login_error=" . urlencode($error));
        exit();
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SmartBiz – Smart Billing & Inventory</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <style>
     body { scroll-behavior: smooth; }

  /* Navbar Blur */
  .navbar.scrolled {
    backdrop-filter: blur(8px);
    background: rgba(255,255,255,0.85) !important;
    transition: background 0.3s ease;
  }

  /* Hero Text Animation */
  .hero h1 {
    font-size: 3rem; font-weight: bold;
    animation: fadeDown 1s ease-out;
  }
  @keyframes fadeDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Feature Card Hover */
  .feature-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .feature-card:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 10px 20px rgba(102, 16, 242, 0.2);
  }

  /* Glass Effect for Auth Box */
  .auth-box {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255,255,255,0.3);
  }

  /* Gradient Buttons */
  .btn-gradient {
    background: linear-gradient(45deg, #6f42c1, #6610f2);
    color: white;
    border: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 16, 242, 0.4);
  }
    .hero {
      background: linear-gradient(135deg, #6f42c1, #6610f2);
      color: white;
      padding: 120px 0;
      text-align: center;
    }
    .hero h1 { font-size: 3rem; font-weight: bold; }
    .feature-icon {
      font-size: 2rem; color: #6610f2;
    }
    .auth-section {
      display: none; /* initially hidden */
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.7);
      z-index: 9999;
      justify-content: center;
      align-items: center;
    }
    .auth-box {
      background: white;
      padding: 30px;
      border-radius: 10px;
      max-width: 400px;
      width: 100%;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm fixed-top">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="#">SmartBiz</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><button class="btn btn-primary ms-2" onclick="openAuth()">Login / Register</button></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero">
  <div class="container">
    <h1>Smart Billing & Inventory Management</h1>
    <p class="lead mt-3">Automate billing, track inventory in real-time, and make data-driven decisions — completely free.</p>
    <button class="btn btn-light btn-lg mt-4" onclick="openAuth()">Get Started</button>
  </div>
</section>

<!-- Features Section -->
<section class="py-5" id="features">
  <div class="container text-center">
    <h2 class="fw-bold mb-4">Powerful Features</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="p-4 shadow rounded h-100">
          <div class="feature-icon mb-3">⚡</div>
          <h5>Automated Reorder Alerts</h5>
          <p>Never run out of stock again. Get alerts before inventory runs low.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 shadow rounded h-100">
          <div class="feature-icon mb-3">📈</div>
          <h5>AI Demand Forecasting</h5>
          <p>Smart predictions help you stock the right products at the right time.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 shadow rounded h-100">
          <div class="feature-icon mb-3">🏬</div>
          <h5>Multi-Location Tracking</h5>
          <p>Manage inventory across all your stores seamlessly in one platform.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- About Section -->
<section class="py-5 bg-light" id="about">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6">
        <img src="https://tse2.mm.bing.net/th/id/OIP.0iDGsgQZIw4KdZhjdrQ1GQHaEK?rs=1&pid=ImgDetMain&o=7&rm=3" class="img-fluid rounded shadow" alt="Warehouse">
      </div>
      <div class="col-md-6">
        <h2 class="fw-bold">Why SmartBiz?</h2>
        <p class="lead">SmartBiz makes business operations simple, fast, and intelligent. From billing to inventory insights — everything in one dashboard.</p>
        <ul class="list-unstyled">
          <li>✔ Free forever — no hidden costs</li>
          <li>✔ Real-time data & analytics</li>
          <li>✔ Secure and user-friendly</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="py-4 text-center bg-dark text-light">
  <p class="mb-0">© 2025 SmartBiz. All rights reserved.</p>
</footer>

<!-- Auth Section (from your PHP code) -->
<section class="auth-section" id="auth">
  <div class="auth-box">
    <button class="btn-close float-end mb-2" onclick="closeAuth()"></button>
    <ul class="nav nav-tabs mb-4 justify-content-center">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#loginTab">Login</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#registerTab">Register</button></li>
    </ul>
    <div class="tab-content">
      <!-- Login Tab -->
      <div class="tab-pane fade show active" id="loginTab">
    <?php if($login_error): ?>
        <div class='alert alert-danger'><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
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
            <div class="mb-3"><label class="form-label fw-semibold">Store Name</label><input type="text" name="store_name" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Store Email</label><input type="email" name="store_email" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Contact Number</label><input type="text" name="contact_number" class="form-control"></div>
          </div>
          <div id="employeeFields" style="display:none;">
            <div class="mb-3"><label class="form-label fw-semibold">Store Code</label><input type="text" name="store_code" class="form-control"></div>
          </div>
          <div class="mb-3"><label class="form-label fw-semibold">Username</label><input type="text" name="username" class="form-control" required></div>
          <div class="mb-3"><label class="form-label fw-semibold">Password</label><input type="password" name="password" class="form-control" required></div>
          <button type="submit" name="register" class="btn btn-success w-100 py-2 fw-semibold">Register</button>
        </form>
      </div>
    </div>
  </div>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Initialize animations
  AOS.init({ duration: 800, once: true });

  // Navbar blur on scroll
  window.addEventListener('scroll', function() {
    const nav = document.querySelector('.navbar');
    nav.classList.toggle('scrolled', window.scrollY > 50);
  });

  // 🔹 Auth popup open/close
  function openAuth() {
    document.getElementById('auth').style.display = 'flex';
  }
  function closeAuth() {
    // Hide popup
    document.getElementById('auth').style.display = 'none';
    // Clear any alert messages when closed
    document.querySelectorAll('#auth .alert').forEach(el => el.remove());
  }

  // 🔹 Handle role-based fields in Register
  document.getElementById('roleSelect').addEventListener('change', function() {
    if (this.value === 'admin') {
      document.getElementById('adminFields').style.display = 'block';
      document.getElementById('employeeFields').style.display = 'none';
    } else {
      document.getElementById('adminFields').style.display = 'none';
      document.getElementById('employeeFields').style.display = 'block';
    }
  });

  // 🔹 Auto-open auth popup if there’s any PHP message
  <?php if ($login_error || $register_error || $register_success): ?>
    openAuth();

    <?php if ($login_error): ?>
      // Force Login tab active
      var loginTabTrigger = document.querySelector('[data-bs-target="#loginTab"]');
      if (loginTabTrigger) {
        var tab = new bootstrap.Tab(loginTabTrigger);
        tab.show();
      }
    <?php endif; ?>

    <?php if ($register_error || $register_success): ?>
      // Force Register tab active
      var registerTabTrigger = document.querySelector('[data-bs-target="#registerTab"]');
      if (registerTabTrigger) {
        var tab = new bootstrap.Tab(registerTabTrigger);
        tab.show();
      }
    <?php endif; ?>
  <?php endif; ?>

  // 🔹 Clean URL (remove ?login_error=... etc)
  if (window.location.search.length > 0) {
    const cleanUrl = window.location.href.split('?')[0];
    window.history.replaceState({}, document.title, cleanUrl);
  }
</script>

</body>
</html>
