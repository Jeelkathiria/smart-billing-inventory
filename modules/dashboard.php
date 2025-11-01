<?php
require_once '../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';

$user_id  = $_SESSION['user_id'] ?? null;
$role     = $_SESSION['role'] ?? 'cashier';
$store_id = $_SESSION['store_id'] ?? 0;

/* ========================= FETCH RECENT SALES ========================= */
if ($role === 'cashier') {
    $stmt = $conn->prepare("
        SELECT invoice_id, customer_name, sale_date, total_amount
        FROM sales
        WHERE created_by = ? AND store_id = ?
        ORDER BY sale_date DESC LIMIT 5
    ");
    $stmt->bind_param("ii", $user_id, $store_id);
} else {
    $stmt = $conn->prepare("
        SELECT invoice_id, customer_name, sale_date, total_amount
        FROM sales
        WHERE store_id = ?
        ORDER BY sale_date DESC LIMIT 5
    ");
    $stmt->bind_param("i", $store_id);
}
$stmt->execute();
$sales = $stmt->get_result();

/* ========================= SUMMARY CARDS ========================= */
$stmt = $conn->prepare("SELECT SUM(total_amount) AS total_sales FROM sales WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$total_sales = $stmt->get_result()->fetch_assoc()['total_sales'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM categories WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$total_categories = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE stock < 5 AND store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$low_stock_count = $stmt->get_result()->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Store Dashboard</title>

  <!-- Core Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/common.css">

  <style>
  /* ========================= BASE LAYOUT ========================= */
  body {
    background: #f8f9fa;
    overflow-x: hidden;
  }

  .dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
  }

  .dashboard-header h3 {
    font-weight: 600;
    color: #333;
  }

  /* ========================= NEW BILLING BUTTON ========================= */
  .new-billing-btn {
    background-color: #0d6efd;
    color: #fff;
    border: none;
    font-weight: 500;
    border-radius: 8px;
    padding: 10px 18px;
    transition: 0.2s ease;
  }

  .new-billing-btn:hover {
    background-color: #0b5ed7;
    transform: translateY(-2px);
  }

  /* ========================= CARD STYLING ========================= */
  .card {
    border: none;
    border-radius: 14px;
    transition: transform 0.2s, box-shadow 0.2s;
  }

  .card:hover {
    transform: translateY(-4px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
  }

  .card-body h6 {
    font-weight: 600;
  }

  .card-hover {
    cursor: pointer;
  }

  /* ========================= TABLE STYLING ========================= */
  .table td,
  .table th {
    vertical-align: middle;
    font-size: 0.95rem;
  }

  /* ========================= RESPONSIVE LAYOUT (Laptop Optimized) ========================= */
  @media (max-width: 1440px) {
    .content {
      padding: 1.5rem;
    }

    .card h3 {
      font-size: 1.6rem;
    }
  }

  @media (max-width: 1366px) {
    .dashboard-header h3 {
      font-size: 1.25rem;
    }

    .new-billing-btn {
      padding: 8px 16px;
      font-size: 0.9rem;
    }

    .card h3 {
      font-size: 1.4rem;
    }
  }

  @media (max-width: 1024px) {
    .content {
      margin-left: 12vw !important;
      padding: 1.2rem;
    }
  }

  /* ========================= SMALL SCREEN HANDLING ========================= */
  @media (max-width: 768px) {
    .content {
      margin-left: 0;
      padding: 15px;
    }
  }
  </style>
</head>

<body>
  <!-- Navbar -->
  <?php include '../components/navbar.php'; ?>

  <!-- Sidebar -->
  <?php include '../components/sidebar.php'; ?>

  <!-- ========================= MAIN CONTENT ========================= -->
  <div class="content">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
      <h3>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h3>
      <a href="/modules/billing/billing.php" class="btn new-billing-btn">
        <i class="bi bi-plus-circle me-1"></i> New Billing
      </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4">
      <div class="col-md-3 col-sm-6">
        <a href="/modules/categories.php" class="text-decoration-none">
          <div class="card text-white bg-primary shadow-sm card-hover text-center p-3">
            <i class="bi bi-tags fs-2 mb-2"></i>
            <h6>Total Categories</h6>
            <h3><?= $total_categories ?></h3>
          </div>
        </a>
      </div>

      <div class="col-md-3 col-sm-6">
        <a href="/modules/products/products.php" class="text-decoration-none">
          <div class="card text-white bg-success shadow-sm card-hover text-center p-3">
            <i class="bi bi-box-seam fs-2 mb-2"></i>
            <h6>Total Products</h6>
            <h3><?= $total_products ?></h3>
          </div>
        </a>
      </div>

      <div class="col-md-3 col-sm-6">
        <a href="/modules/products/products.php" class="text-decoration-none">
          <div
            class="card <?= $low_stock_count > 0 ? 'bg-warning text-dark' : 'bg-light text-dark' ?> shadow-sm card-hover text-center p-3">
            <i class="bi bi-exclamation-circle fs-2 mb-2"></i>
            <h6>Low Stock Alerts</h6>
            <h3><?= $low_stock_count ?></h3>
          </div>
        </a>
      </div>

      <div class="col-md-3 col-sm-6">
        <a href="/modules/sales/sales.php" class="text-decoration-none">
          <div class="card text-white bg-dark shadow-sm text-center p-3 card-hover">
            <i class="bi bi-currency-rupee fs-2 mb-2"></i>
            <h6>Total Sales</h6>
            <h3>₹<?= number_format((float)$total_sales, 2) ?></h3>
          </div>
        </a>
      </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if ($low_stock_count > 0): ?>
    <div class="alert alert-warning mt-4">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <?= $low_stock_count ?> product(s) have low stock. Please restock soon.
    </div>
    <?php endif; ?>

    <!-- Recent Billings -->
    <div class="card shadow-sm mt-5">
      <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Billings</h6>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover table-bordered text-center mb-0">
          <thead class="table-light sticky-top bg-light">
            <tr>
              <th>Invoice ID</th>
              <th>Customer</th>
              <th>Date & Time</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($sale = $sales->fetch_assoc()): ?>
            <tr>
              <td><span class="badge bg-secondary"><?= htmlspecialchars($sale['invoice_id']) ?></span></td>
              <td><?= htmlspecialchars($sale['customer_name'] ?? '--') ?></td>
              <td><?= date('d-m-Y H:i:s', strtotime($sale['sale_date'])) ?></td>
              <td><strong>₹<?= number_format((float)$sale['total_amount'], 2) ?></strong></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <div class="text-center p-2">
          <a href="/modules/sales/sales.php" class="btn btn-sm btn-outline-info">View All Sales</a>
        </div>
      </div>
    </div>

    <!-- Sales Chart -->
    <div class="card shadow-sm my-4">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Sales (Last 7 Days)</h6>
      </div>
      <div class="card-body">
        <div style="height: 260px;">
          <canvas id="salesChart" height="100"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================= SCRIPTS ========================= -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const res = await fetch("/modules/reports/get_sales_chart_data.php");
      const data = await res.json();

      const ctx = document.getElementById("salesChart").getContext("2d");
      new Chart(ctx, {
        type: "line",
        data: {
          labels: data.dates,
          datasets: [{
            label: "Sales (₹)",
            data: data.sales,
            borderColor: "#0d6efd",
            backgroundColor: "rgba(13,110,253,0.1)",
            tension: 0.3,
            fill: true
          }]
        },
        options: {
          scales: {
            y: {
              beginAtZero: true
            }
          },
          plugins: {
            legend: {
              display: true,
              labels: {
                color: '#333'
              }
            }
          }
        }
      });
    } catch (err) {
      console.error("Chart Fetch Error:", err);
    }
  });
  </script>
</body>

</html>