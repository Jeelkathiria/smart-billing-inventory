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
      background: linear-gradient(135deg, #4f46e5, #22d3ee);
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
      background: linear-gradient(135deg, #4f46e5, #3b82f6);
      color: white;
      padding: 40px;
      flex: 1;
      min-width: 300px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .left-panel h1 {
      font-size: 2.7rem;
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
      background-color: #4f46e5;
      color: white !important;
      border: none;
    }

    .nav-tabs .nav-link {
      border: none;
      font-weight: 500;
    }

    .form-control:focus {
      border-color: #4f46e5;
      box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
    }

    .btn-primary {
      background-color: #4f46e5;
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

      .left-panel, .right-panel {
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
      <p>A powerful solution for electronics, grocery, clothing, and more.</p>
      <ul>
        <li>Multi-user secure login</li>
        <li>Live GST billing system</li>
        <li>Dynamic inventory tracking</li>
        <li>Daily, Monthly, Yearly reports</li>
      </ul>
    </div>

    <!-- Right Login/Register Panel -->
    <div class="right-panel">
      <ul class="nav nav-tabs mb-4" id="formTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#loginTab" type="button" role="tab">Login</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#registerTab" type="button" role="tab">Register</button>
        </li>
      </ul>

      <div class="tab-content" id="formTabsContent">
        <!-- Login Tab -->
        <div class="tab-pane fade show active" id="loginTab" role="tabpanel">
          <div class="form-title">Login to your store</div>
          <form method="POST" action="login.php">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <button class="btn btn-primary w-100">Login</button>
          </form>
        </div>

        <!-- Register Tab -->
        <div class="tab-pane fade" id="registerTab" role="tabpanel">
          <div class="form-title">Register Your Store</div>
          <form method="POST" action="register.php">
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
              <input type="text" class="form-control" name="contact_number" required>
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
