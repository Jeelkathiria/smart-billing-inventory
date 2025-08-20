<?php
require_once '../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';

$total_sales_result = $conn->query("SELECT SUM(total_amount) AS total_sales FROM sales");
$total_sales = $total_sales_result->fetch_assoc()['total_sales'] ?? 0;

$total_categories = $conn->query("SELECT COUNT(*) AS total FROM categories")->fetch_assoc()['total'];
$total_products = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
$low_stock_count = $conn->query("SELECT COUNT(*) AS total FROM products WHERE stock < 5")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #eef2f7;
    }

    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 60px;
      z-index: 1030;
      background-color: #ffffff;
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
      margin-top: auto;
    }

    .content {
      margin-left: 220px;
      padding: 20px;
      padding-top: 80px; 
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

    .card-header h6 {
      font-weight: 600;
    }

    .table td, .table th {
      vertical-align: middle;
    }

    #salesChart {
      max-height: 100%;
    }

    .alert-warning {
      font-weight: 500;
    }

    .btn-outline-primary.btn-sm {
      margin-top: 5px;
    }

    @media (max-width: 768px) {
      .sidebar {
        display: none;
      }

      .content {
        margin-left: 0;
        padding: 15px;
        padding-top: 80px;
      }

      .navbar {
        justify-content: center;
      }
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <?php include '../components/navbar.php'; ?>


  <!-- Sidebar -->
 <?php include '../components/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="content">
    <h3 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h3>

    <!-- Summary Cards -->
    <div class="row g-4">
      <div class="col-md-3 col-sm-6">
        <div class="card text-white bg-primary shadow-sm">
          <div class="card-body text-center">
            <i class="bi bi-tags fs-2 mb-2"></i>
            <h6>Total Categories</h6>
            <h3><?= $total_categories ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card text-white bg-success shadow-sm">
          <div class="card-body text-center">
            <i class="bi bi-box-seam fs-2 mb-2"></i>
            <h6>Total Products</h6>
            <h3><?= $total_products ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card text-dark <?= $low_stock_count > 0 ? 'bg-warning' : 'bg-light' ?> shadow-sm">
          <div class="card-body text-center">
            <i class="bi bi-exclamation-circle fs-2 mb-2"></i>
            <h6>Low Stock Alerts</h6>
            <h3><?= $low_stock_count ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card text-white bg-dark shadow-sm">
          <div class="card-body text-center">
            <i class="bi bi-currency-rupee fs-2 mb-2"></i>
            <h6>Total Sales</h6>
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

    <!-- Recent Billings Card -->
    <div class="card shadow-sm mt-5">
      <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Billings</h6>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover table-bordered text-center mb-0" id="recentSalesTable">
          <thead class="table-light sticky-top bg-light">
            <tr>
              <th>Invoice ID</th>
              <th>Customer</th>
              <th>Date & Time</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody id="salesBody">
            <?php
            $sales = $conn->query("SELECT * FROM sales ORDER BY sale_date DESC LIMIT 8");
            while ($sale = $sales->fetch_assoc()):
            ?>
            <tr>
              <td><span class="badge bg-secondary"><?= $sale['invoice_id']; ?></span></td>
              <td><?= htmlspecialchars($sale['customer_name']); ?></td>
              <td><?= date('d-m-Y H:i:s', strtotime($sale['sale_date'])); ?></td>
              <td><strong>₹<?= number_format($sale['total_amount'], 2); ?></strong></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <div class="text-center py-2">
          <button id="loadMoreBtn" class="btn btn-outline-primary btn-sm">View More</button>
        </div>
      </div>
    </div>

    <!-- Sales Chart Card -->
    <div class="card shadow-sm my-4">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i> Sales (Last 7 Days)</h6>
      </div>
      <div class="card-body">
        <div style="height: 260px;">
          <canvas id="salesChart" height="100"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    let offset = 8;
    const loadMoreBtn = document.getElementById('loadMoreBtn');

    if (loadMoreBtn) {
      loadMoreBtn.addEventListener('click', function () {
        fetch(`/modules/sales/load_more_sales.php?offset=${offset}`)
          .then(response => response.text())
          .then(data => {
            if (data.trim() === "") {
              loadMoreBtn.innerText = "No more data";
              loadMoreBtn.disabled = true;
            } else {
              document.getElementById('salesBody').insertAdjacentHTML('beforeend', data);
              offset += 8;
            }
          });
      });
    }

    fetch('../includes/get_sales_chart_data.php')
      .then(response => response.json())
      .then(data => {
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: data.dates,
            datasets: [{
              label: 'Sales in ₹',
              data: data.totals,
              backgroundColor: 'rgba(54, 162, 235, 0.6)',
              borderColor: 'rgba(54, 162, 235, 1)',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true
              }
            }
          }
        });
      })
      .catch(err => console.error("Chart Fetch Error:", err));
  </script>
</body>
</html>
