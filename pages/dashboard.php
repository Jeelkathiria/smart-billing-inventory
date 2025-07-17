<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

// Fetch summary data
$total_categories = $conn->query("SELECT COUNT(*) AS total FROM categories")->fetch_assoc()['total'];
$total_products = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
$low_stock_count = $conn->query("SELECT COUNT(*) AS total FROM products WHERE stock < 5")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa;
    }
    .sidebar {
      width: 220px;
      position: fixed;
      height: 100%;
      background: #fff;
      border-right: 1px solid #ddd;
      padding-top: 20px;
    }
    .sidebar a {
      display: block;
      color: #000;
      padding: 10px 20px;
      text-decoration: none;
    }
    .sidebar a:hover {
      background: #f1f1f1;
    }
    .content {
      margin-left: 220px;
      padding: 20px;
    }
    .navbar-brand {
      font-weight: bold;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm">
  <div class="container-fluid">
    <span class="navbar-brand">Smart Billing & Inventory</span>
    <a href="../auth/logout.php" class="btn btn-outline-danger">Logout</a>
  </div>
</nav>

<div class="sidebar">
  <a href="dashboard.php">Dashboard</a>
  <a href="categories.php">Categories</a>
  <a href="products.php">Products</a>
  <a href="billing.php">Billing</a>
  <a href="sales_report.php">Sales Report</a>
  <a href="admin_panel.php">Admin Settings</a>
</div>

<div class="content">
  <h3 class="mb-4">Admin Dashboard</h3>
  
  <div class="row g-3">
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h6>Total Categories</h6>
          <h3><?= $total_categories ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h6>Total Products</h6>
          <h3><?= $total_products ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h6>Low Stock Alerts</h6>
          <h3><?= $low_stock_count ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h6>Total Sales</h6>
          <h3>₹0.00</h3> <!-- You can calculate this later -->
        </div>
      </div>
    </div>
  </div>

  <?php if ($low_stock_count > 0) { ?>
    <div class="alert alert-warning mt-4">
      ⚠️ <?= $low_stock_count ?> product(s) have low stock. Please restock.
    </div>
  <?php } ?>

  <div class="mt-4">
    <p>Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>. Use the sidebar to navigate through management sections.</p>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
