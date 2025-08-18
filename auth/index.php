<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Smart Billing & Inventory System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      margin: 0;
      padding: 0;
      min-height: 100vh;
      background: #f3f4f6;
      background-image: url('https://www.transparenttextures.com/patterns/store-icon-pattern.svg');
      background-size: 200px;
      background-repeat: repeat;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }

    .container-custom {
      display: flex;
      flex-wrap: wrap;
      max-width: 1100px;
      width: 100%;
      background: #ffffff;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
      border-radius: 16px;
      overflow: hidden;
    }

    .left-panel {
      background: linear-gradient(135deg, #0f172a, #2563eb);
      color: white;
      padding: 40px;
      flex: 1;
      min-width: 300px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .left-panel h1 {
      font-size: 2.8rem;
      font-weight: bold;
    }

    .left-panel p {
      font-size: 1.1rem;
      margin-top: 10px;
    }

    .left-panel ul {
      margin-top: 25px;
      list-style: none;
      padding: 0;
    }

    .left-panel ul li {
      margin-bottom: 10px;
    }

    .left-panel ul li::before {
      content: "✔️";
      margin-right: 8px;
    }

    .right-panel {
      background: #ffffff;
      padding: 40px;
      flex: 1;
      min-width: 300px;
    }

    .nav-tabs .nav-link.active {
      background-color: #2563eb;
      color: white !important;
      border: none;
    }

    .nav-tabs .nav-link {
      border: none;
      font-weight: 500;
      color: #333;
    }

    .form-control:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
    }

    .btn-primary {
      background-color: #2563eb;
      border: none;
    }

    .btn-success {
      background-color: #10b981;
      border: none;
    }

    .btn:hover {
      opacity: 0.95;
    }

    .form-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 20px;
      color: #333;
    }

    @media (max-width: 768px) {
      .container-custom {
        flex-direction: column;
      }

      .left-panel,
      .right-panel {
        padding: 30px;
        text-align: center;
      }
    }
  </style>
</head>

<body>
  <div class="container-custom">

    <!-- Left Info Panel -->
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

    <!-- Right Panel -->
    <div class="right-panel">
      <ul class="nav nav-tabs mb-4" id="formTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#loginTab" type="button"
            role="tab">Login</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#registerTab" type="button"
            role="tab">Register</button>
        </li>
      </ul>

      <div class="tab-content" id="formTabsContent">

        <!-- Login Tab -->
        <div class="tab-pane fade show active" id="loginTab" role="tabpanel">
          <div class="text-center mb-3">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-gradient"
              style="width:60px;height:60px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="white" class="bi bi-receipt"
                viewBox="0 0 16 16">
                <path d="M1.92.506a.5.5 0 0 1 .58 0l...Z" />
                <path d="M3 4.5a.5.5 0 0 1 .5-.5h9...Z" />
              </svg>
            </div>
          </div>

          <h5 class="mb-3 text-center fw-semibold text-primary">Smart Billing & Inventory Login</h5>

          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
          <?php endif; ?>

          <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
          <?php endif; ?>

          <form action="auth/process_login.php" method="POST">
            <div class="mb-3">
              <input type="text" name="username" class="form-control form-control-lg" placeholder="Username" required>
            </div>
            <div class="mb-3">
              <input type="password" name="password" class="form-control form-control-lg" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Login</button>
          </form>

          <div class="mt-3 text-center text-muted small">
            &copy; <?= date('Y') ?> Smart Billing
          </div>
        </div>

        <!-- Register Tab -->
        <div class="tab-pane fade" id="registerTab" role="tabpanel">
          <div class="form-title">Register Your Store</div>
          <form method="POST" action="auth/process_register.php">
            <div class="mb-3">
              <label class="form-label">Store Name</label>
              <input type="text" class="form-control" name="store_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Store Email</label>
              <input type="email" class="form-control" name="store_email" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Number</label>
              <input type="text" class="form-control" name="contact_number" pattern="[0-9]{10}" maxlength="10" required title="Please enter exactly 10 digits">
            </div>
            <hr>
            <div class="mb-3">
              <label class="form-label">Admin Name</label>
              <input type="text" class="form-control" name="admin_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <button class="btn btn-success w-100">Create Store</button>
          </form>
        </div>

      </div>
    </div>

  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
