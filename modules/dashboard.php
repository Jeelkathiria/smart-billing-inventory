<?php
require_once '../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';

$user_id  = $_SESSION['user_id'] ?? null;
$role     = $_SESSION['role'] ?? 'cashier';
$store_id = $_SESSION['store_id'] ?? 0;

// --- Store metadata & KPIs ---
$stmt = $conn->prepare("SELECT store_name, store_code, store_address FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$storeMeta = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

$store_name = $storeMeta['store_name'] ?? '';
$store_code = $storeMeta['store_code'] ?? '';
$store_address = $storeMeta['store_address'] ?? '';

// Additional KPIs
$stmt = $conn->prepare("SELECT COUNT(*) AS total_customers FROM customers WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$total_customers = $stmt->get_result()->fetch_assoc()['total_customers'] ?? 0;
$stmt->close();

// Online cashiers in the last 5 minutes
$stmt = $conn->prepare("SELECT COUNT(*) AS online_cashiers FROM users WHERE store_id = ? AND role = 'cashier' AND last_activity >= (NOW() - INTERVAL 300 SECOND)");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$online_cashiers = $stmt->get_result()->fetch_assoc()['online_cashiers'] ?? 0;
$stmt->close();

// Overall top product (all time)
$stmt = $conn->prepare("SELECT p.product_name, SUM(si.quantity) AS total_sold 
                        FROM sale_items si
                        JOIN products p ON si.product_id = p.product_id
                        JOIN sales s ON si.sale_id = s.sale_id
                        WHERE s.store_id = ?
                        GROUP BY si.product_id
                        ORDER BY total_sold DESC
                        LIMIT 1");
$stmt->bind_param('i', $store_id);
$stmt->execute();
$top_row = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();
$top_product_name_overall = $top_row['product_name'] ?? '';
$top_product_sold_overall = $top_row['total_sold'] ?? 0;

// Today's revenue (all stores for today)
$stmt = $conn->prepare("SELECT SUM(total_amount) AS today_revenue FROM sales WHERE DATE(sale_date) = CURDATE() AND store_id = ?");
$stmt->bind_param('i', $store_id);
$stmt->execute();
$today_revenue = $stmt->get_result()->fetch_assoc()['today_revenue'] ?? 0.0;
$stmt->close();

// low stock products count is already fetched in $low_stock_count.

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

//Todays invoice count by respective cashier
$today = date('Y-m-d');
$cashier_id = $_SESSION['user_id']; // logged-in cashier

$stmt = $conn->prepare("SELECT COUNT(*) AS sales_count 
                        FROM sales 
                        WHERE DATE(sale_date) = ? 
                          AND store_id = ? 
                          AND created_by = ?");
$stmt->bind_param("sii", $today, $store_id, $cashier_id);
$stmt->execute();
$todayRes = $stmt->get_result()->fetch_assoc();
$todaySalesCount = $todayRes['sales_count'] ?? 0;
$stmt->close();

// Fetch top 5 products (last 30 days)
$top_products_month = [];
$stmt = $conn->prepare("SELECT p.product_id, p.product_name, SUM(si.quantity) AS total_sold 
                        FROM sale_items si
                        JOIN products p ON si.product_id = p.product_id
                        JOIN sales s ON si.sale_id = s.sale_id
                        WHERE s.store_id = ? AND s.sale_date >= (NOW() - INTERVAL 30 DAY)
                        GROUP BY si.product_id
                        ORDER BY total_sold DESC
                        LIMIT 5");
$stmt->bind_param('i', $store_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $top_products_month[] = $row;
$stmt->close();

// Sales by cashier (7 days)
$sales_by_cashier = [];
$stmt = $conn->prepare("SELECT u.user_id, u.username, COUNT(s.sale_id) AS sales_count, SUM(s.total_amount) AS sales_total
                        FROM sales s
                        JOIN users u ON s.created_by = u.user_id
                        WHERE s.store_id = ? AND s.sale_date >= (NOW() - INTERVAL 7 DAY)
                        GROUP BY u.user_id
                        ORDER BY sales_total DESC
                        LIMIT 5");
$stmt->bind_param('i', $store_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $sales_by_cashier[] = $row;
$stmt->close();

// Low stock products list (blow 5)
$low_stock_products = [];
$stmt = $conn->prepare("SELECT product_id, product_name, stock FROM products WHERE store_id = ? AND stock < 5 ORDER BY stock ASC LIMIT 5");
$stmt->bind_param('i', $store_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $low_stock_products[] = $row;
$stmt->close();


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
    position: relative; /* allow centered store name */
  }

  .dashboard-header h3 {
    font-weight: 600;
    color: #333;
  }
  .header-left h3 { margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; text-align: left; }

  /* ========================= NEW BILLING BUTTON ========================= */
  .new-billing-btn {
    background-color: #0d6efd;
    color: #fff;
    border: none;
    font-weight: 500;
    border-radius: 0px;
    padding: 8px 15px;
    transition: 0.2s ease;
  }

  .new-billing-btn:hover {
    background-color: #0b5ed7;
    transform: translateY(-2px);
  }

  /* Header grid to center store name */
  .header-grid { display:grid; grid-template-columns: 1fr 1fr 1fr; align-items:center; gap:20px; width:100%; }
  .header-left { display:flex; align-items:center; gap:15px; justify-self:start; width:100%; }
  .store-info { text-align:center; justify-self:center; width:100%; }
  .header-right { display:flex; gap:8px; justify-self:end; width:100%; justify-content:flex-end; align-items:center; }
  .store-name { font-weight:700; color:#0b2545; font-size:1.125rem; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0 auto; }
  .store-code { font-size:0.85rem; color:#64748b; }
  .store-address { font-size:0.85rem; color:#6b7280; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .store-info { align-self: center; }

  /* Header button should fit the right column */
  .header-right { width: 100%; }

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
  .kpi-card-centered {
    display:flex;align-items:center;justify-content:center;flex-direction:column;height:100px;padding:1rem;min-height:100px;}
  .kpi-small { padding: 0.75rem 1rem; height:80px; }

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
    .header-grid { grid-template-columns: 1fr; }
    .header-right { justify-content:flex-start; }
  }

  /* ========================= SMALL SCREEN HANDLING ========================= */
  @media (max-width: 768px) {
    .content {
      margin-left: 0;
      padding: 15px;
    }
    .store-name { font-size: 1rem; max-width: 100%; }
    .kpi-card-centered { height: auto; min-height: 90px; padding: 0.8rem; }
    .header-left h3 { font-size: 1rem; }
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
      <div class="header-grid">
        <div class="header-left">
          <h3>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h3>
        </div>
        <div class="store-info">
          <div class="store-name"><?= htmlspecialchars($store_name ?: 'My Store') ?> <span class="store-code"><?= htmlspecialchars($store_code ?: '') ?></span></div>
          <?php if (!empty($store_address)): ?>
            <div class="store-address small"><?= htmlspecialchars($store_address) ?></div>
          <?php endif; ?>
        </div>
        <div class="header-right">
          <a href="/modules/billing/billing.php" class="btn new-billing-btn">
            <i class="bi bi-plus-circle me-1"></i> New Billing
          </a>
        </div>
      </div>
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

      <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
      <div class="col-md-3 col-sm-6">
        <a href="/modules/sales/sales.php" class="text-decoration-none">
          <div class="card text-white bg-dark shadow-sm text-center p-3 card-hover">
            <i class="bi bi-currency-rupee fs-2 mb-2"></i>
            <h6>Total Sales</h6>
            <h3>₹<?= number_format((float)$total_sales, 2) ?></h3>
          </div>
        </a>
      </div>
      <?php endif; ?>


<?php if ($_SESSION['role'] === 'cashier'): ?>
    <!-- Cashier: Invoices Today -->
    <div class="col-md-3 col-sm-6">
        <a href="/modules/sales/sales.php" class="text-decoration-none">
            <div class="card text-white bg-dark shadow-sm text-center p-3 card-hover">
                <i class="bi bi-receipt-cutoff fs-2 mb-2"></i>
                <h6>Invoices Today</h6>
                <h3><?= $todaySalesCount; ?></h3>
            </div>
        </a>
    </div>
<?php endif; ?>


    </div>

    <!-- Extra KPI Row -->
    <div class="row g-3 mt-3">
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm kpi-card-centered text-center">
          <div class="text-muted small">Total Customers</div>
          <div class="fw-bold fs-5"><?= (int)$total_customers ?></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm kpi-card-centered text-center">
          <div class="text-muted small">Top Product</div>
          <div class="fw-bold fs-5"><?= htmlspecialchars($top_product_name_overall ?: ($top_products_month[0]['product_name'] ?? '—')) ?></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm kpi-card-centered text-center">
          <div class="text-muted small">Cashiers Online</div>
          <div class="fw-bold fs-5"><?= (int)$online_cashiers ?></div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm kpi-card-centered text-center">
          <div class="text-muted small">Today Revenue</div>
          <div class="fw-bold fs-5">₹<?= number_format((float)$today_revenue, 2) ?></div>
        </div>
      </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if ($low_stock_count > 0): ?>

    <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
    <!-- Admin/Manager Message -->
    <div class="alert alert-warning mt-4">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <?= $low_stock_count ?> product(s) have low stock. Please restock soon.
    </div>

    <?php elseif ($_SESSION['role'] === 'cashier'): ?>
    <!-- Cashier Message -->
    <div class="alert alert-warning mt-4">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <?= $low_stock_count ?> product(s) have low stock. Please tell admin or manager to restock.
    </div>
    <?php endif; ?>

    <?php endif; ?>


    <!-- Dashboard Main Row: Left (Recent/Charts) + Right (Widgets) -->
    <div class="row g-3 mt-4">
      <div class="col-lg-8 col-md-12">
        <div class="card shadow-sm">
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

        <?php if ($_SESSION['role'] !== 'cashier'): ?>
          <div class="card shadow-sm my-3">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Sales (Last 7 Days)</h6>
            </div>
            <div class="card-body">
              <div style="height: 260px;">
                <canvas id="salesChart" height="100"></canvas>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-4 col-md-12">
        <!-- Right widgets -->
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong class="mb-0">Low Stock (Top 5)</strong>
            <small class="text-muted">Stock</small>
          </div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              <?php if (empty($low_stock_products)): ?>
                <li class="list-group-item text-center text-muted">All stocked up</li>
              <?php else: ?>
                <?php foreach ($low_stock_products as $item): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="text-truncate me-3"><?= htmlspecialchars($item['product_name']) ?></div>
                    <span class="badge bg-danger"><?= (int)$item['stock'] ?></span>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </div>
        </div>

        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong class="mb-0">Top Products (30d)</strong>
            <small class="text-muted">Qty</small>
          </div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              <?php if (empty($top_products_month)): ?>
                <li class="list-group-item text-center text-muted">No data</li>
              <?php else: ?>
                <?php foreach ($top_products_month as $p): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="text-truncate me-3"><?= htmlspecialchars($p['product_name']) ?></div>
                    <span class="fw-bold"><?= (int)$p['total_sold'] ?></span>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong class="mb-0">Top Cashiers (7d)</strong>
            <small class="text-muted">Sales</small>
          </div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              <?php if (empty($sales_by_cashier)): ?>
                <li class="list-group-item text-center text-muted">No cashier sales this week</li>
              <?php else: ?>
                <?php foreach ($sales_by_cashier as $c): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="text-truncate me-3"><?= htmlspecialchars($c['username']) ?></div>
                    <span class="fw-bold">₹<?= number_format((float)$c['sales_total'], 2) ?></span>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
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