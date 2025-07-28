<?php
require_once '../includes/db.php';
session_start();

$total_sales_result = $conn->query("SELECT SUM(total_amount) AS total_sales FROM sales");
$total_sales = $total_sales_result->fetch_assoc()['total_sales'] ?? 0;

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
  body {
    background-color: #f8f9fa;
  }
  
  .navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 60px;
  z-index: 1030; /* stays above sidebar */
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  align-items: center;
  padding: 0 20px;
}


  .sidebar {
    width: 220px;
    position: fixed;
    top: 0;
    bottom: 0;
    background: #ffffff;
    border-right: 1px solid #dee2e6;
    padding-top: 60px;
    display: flex;
    flex-direction: column;
  }

  .sidebar .nav-links {
    flex-grow: 1;
  }

  .sidebar a {
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s;
  }

  .sidebar a:hover {
    background-color: #f0f0f0;
    border-left: 4px solid #007bff;
  }

  .sidebar-footer {
    padding: 12px 20px;
    margin-bottom: 10px;
    margin-top: auto;
  }

  .content {
    margin-left: 220px;
    padding: 20px;
  }

  .card {
    border: none;
    border-radius: 10px;
    transition: transform 0.2s;
  }

  .card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }

  .alert-warning {
    font-weight: 500;
  }
  </style>
</head>

<body>

  <!-- Navbar -->
  <nav class="navbar navbar-light bg-white shadow-sm px-4">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <div class="bg-primary rounded-circle d-flex justify-content-center align-items-center me-2"
          style="width: 40px; height: 40px;">
          <i class="bi bi-receipt text-white fs-5"></i>
        </div>
        <span class="navbar-brand mb-0 h5">Smart Billing & Inventory</span>
      </div>
      <a href="../auth/logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </nav>

  <!-- Sidebar -->
  <!-- Sidebar -->
<div class="sidebar">
  <div class="nav-links">
    <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="sales.php"><i class="bi bi-currency-dollar"></i> Sales</a>
    <a href="products.php"><i class="bi bi-box-seam"></i> Inventory</a>
    <a href="billing.php"><i class="bi bi-receipt"></i> Billing</a>
    <a href="categories.php"><i class="bi bi-tags"></i> Categories</a>
    <a href="customers.php"><i class="bi bi-people"></i> Customers</a>
    <a href="sales_report.php"><i class="bi bi-graph-up"></i> Reports</a>
  </div>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <a href="admin_panel.php"><i class="bi bi-gear"></i> Settings</a>
  </div>
</div>


  <!-- Main Content -->
  <div class="content">
    <h3 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h3>

    <div class="row g-4">
      <div class="col-md-3 col-sm-6">
        <div class="card bg-light shadow-sm text-center">
          <div class="card-body">
            <h6 class="text-muted">Total Categories</h6>
            <h3><?= $total_categories ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card bg-light shadow-sm text-center">
          <div class="card-body">
            <h6 class="text-muted">Total Products</h6>
            <h3><?= $total_products ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card <?= $low_stock_count > 0 ? 'bg-warning' : 'bg-light' ?> shadow-sm text-center">
          <div class="card-body">
            <h6 class="text-muted">Low Stock Alerts</h6>
            <h3><?= $low_stock_count ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card bg-light shadow-sm text-center">
          <div class="card-body">
            <h6 class="text-muted">Total Sales</h6>
            <h3>₹<?= number_format((float)$total_sales, 2) ?></h3>

          </div>
        </div>
      </div>
    </div>

    <?php if ($low_stock_count > 0): ?>
    <div class="alert alert-warning mt-4">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <?= $low_stock_count ?> product(s) have low stock. Please restock.
    </div>
    <?php endif; ?>

    <div class="mt-4">
      <p class="lead">Use the sidebar to navigate through inventory, sales, and billing operations with ease.</p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>