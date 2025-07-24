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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background: #f8f9fa;
      margin: 0;
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
    .sidebar a i {
  font-size: 1rem;
  vertical-align: middle;
}
.sidebar {
  width: 220px;
  position: fixed;
  height: 100vh;
  background: #fff;
  border-right: 1px solid #ddd;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 20px 0;
}

.sidebar-header {
  text-align: center;
  font-weight: bold;
  padding-bottom: 10px;
  border-bottom: 1px solid #eee;
}

.sidebar-menu {
  display: flex;
  flex-direction: column;
  gap: 5px;
  padding: 10px 0;
}

.sidebar a {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #000;
  padding: 10px 20px;
  text-decoration: none;
  transition: background 0.2s;
}

.sidebar a:hover {
  background: #f1f1f1;
  border-radius: 6px;
}

.sidebar-footer {
  padding: 10px 20px;
  margin-bottom: 5vh;
  border-top: 1px solid #eee;
}

  </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm px-4">
  <div class="container-fluid d-flex align-items-center justify-content-between">
    
    <!-- Left side: Logo + Brand -->
    <div class="d-flex align-items-center">
      
      <!-- Circular Logo Wrapper -->
      <div class="bg-primary rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 40px; height: 40px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="white" class="bi bi-receipt"
          viewBox="0 0 16 16">
          <path
            d="M1.92.506a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627A.5.5 0 0 1 15 1v14a.5.5 0 0 1-.79.407l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627A.5.5 0 0 1 1 15V1a.5.5 0 0 1 .92-.494ZM2 1.934v12.132l.44-.293a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.94.627.94-.627a.5.5 0 0 1 .58 0l.44.293V1.934l-.44.293a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0l-.94-.627-.94.627a.5.5 0 0 1-.58 0L2 1.934Z" />
          <path
            d="M3 4.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5Zm0 2a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5Zm0 2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Z" />
        </svg>
      </div>

      <!-- App Name -->
      <span class="navbar-brand mb-0 h5">Smart Billing & Inventory</span>
    </div>

    <!-- Right side: Logout -->
    <a href="../auth/logout.php" class="btn btn-outline-danger">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</nav>


<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-menu">
    <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="sales.php"><i class="bi bi-currency-dollar"></i> Sales</a>
    <a href="products.php"><i class="bi bi-box-seam"></i> Inventory</a>
    <a href="categories.php"><i class="bi bi-tags"></i> Categories</a> <!-- New Menu Item -->
    <a href="customers.php"><i class="bi bi-people"></i> Customers</a>
    <a href="sales_report.php"><i class="bi bi-graph-up"></i> Reports</a>
  </div>
  <div class="sidebar-footer">
    <a href="admin_panel.php"><i class="bi bi-gear"></i> Settings</a>
  </div>
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
