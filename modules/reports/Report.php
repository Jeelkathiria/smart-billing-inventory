<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
  header('Location: ../../auth/index.php?error=Please%20login');
  exit();
}

$store_id = $_SESSION['store_id'];

// ------------------- SUMMARY METRICS -------------------
$total_revenue = $total_profit = $total_tax = 0;
$today_total = $today_profit = 0;
$month_total = $month_profit = 0;
$year_total = $year_profit = 0;

// General summary
$sql_summary = "
  SELECT 
      SUM(si.quantity * si.price) AS total_revenue,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS total_profit,
      SUM(si.quantity * si.price * (si.gst_percent / 100)) AS total_tax
  FROM sale_items si
  JOIN sales s ON si.sale_id = s.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ?
";
$stmt = $conn->prepare($sql_summary);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($total_revenue, $total_profit, $total_tax);
$stmt->fetch();
$stmt->close();

// Today's summary
$sql_today = "
  SELECT 
      SUM(s.total_amount) AS today_total,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS today_profit
  FROM sales s
  JOIN sale_items si ON s.sale_id = si.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ? AND DATE(s.sale_date) = CURDATE()
";
$stmt = $conn->prepare($sql_today);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($today_total, $today_profit);
$stmt->fetch();
$stmt->close();

// This Month
$sql_month = "
  SELECT 
      SUM(s.total_amount) AS month_total,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS month_profit
  FROM sales s
  JOIN sale_items si ON s.sale_id = si.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ? AND MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())
";
$stmt = $conn->prepare($sql_month);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($month_total, $month_profit);
$stmt->fetch();
$stmt->close();

// This Year
$sql_year = "
  SELECT 
      SUM(s.total_amount) AS year_total,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS year_profit
  FROM sales s
  JOIN sale_items si ON s.sale_id = si.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ? AND YEAR(s.sale_date) = YEAR(CURDATE())
";
$stmt = $conn->prepare($sql_year);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($year_total, $year_profit);
$stmt->fetch();
$stmt->close();

// Top selling products (Pie chart)
$top_products = [];
$sql_top = "
  SELECT p.product_name, SUM(si.quantity) AS total_sold
  FROM sale_items si
  JOIN products p ON si.product_id = p.product_id
  JOIN sales s ON si.sale_id = s.sale_id
  WHERE s.store_id = ?
  GROUP BY si.product_id
  ORDER BY total_sold DESC
  LIMIT 6
";
$stmt = $conn->prepare($sql_top);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
  $top_products[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Business Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="/assets/css/common.css">
  <style>

    h2 {
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 2rem;
      font-size: 2rem;
    }

    .card-custom {
      border: none;
      border-radius: 0.8rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      background: linear-gradient(135deg, #ffffff, #f8fafc);
      padding: 1.5rem;
      transition: all 0.2s ease;
    }

    .card-custom:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
    }

    .summary-icon {
      font-size: 1.8rem;
      margin-right: 1rem;
    }

    .chart-section {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 2rem;
      margin-top: 2rem;
    }

    .chart-card {
      padding: 1.5rem;
      border-radius: 0.8rem;
      background: #fff;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .chart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
    }

    .btn-outline-primary {
      border-radius: 0.5rem;
      font-size: 0.85rem;
      padding: 0.3rem 0.8rem;
    }

    @media(max-width:768px) {
      .chart-section {
        grid-template-columns: 1fr;
      }

      h2 {
        font-size: 1.5rem;
      }

      .card-custom {
        padding: 1rem;
      }
    }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="content">
    <div class="row g-3">
      <?php
      $cards = [
        ['Total Revenue', $total_revenue, 'success', 'bi-cash-stack'],
        ['Total Profit', $total_profit, 'info', 'bi-bar-chart-line-fill'],
        ['Total Tax Collected', $total_tax, 'danger', 'bi-receipt'],
        ["Today's Revenue", $today_total, 'primary', 'bi-calendar-day'],
        ["Today's Profit", $today_profit, 'primary', 'bi-calendar-check'],
        ['This Month Revenue', $month_total, 'warning', 'bi-calendar2'],
        ['This Month Profit', $month_profit, 'warning', 'bi-calendar2-check'],
        ['This Year Revenue', $year_total, 'secondary', 'bi-calendar3'],
        ['This Year Profit', $year_profit, 'secondary', 'bi-calendar3-check']
      ];

      foreach ($cards as [$label, $value, $color, $icon]) {
        echo "
        <div class='col-xl-4 col-lg-4 col-md-6 col-sm-12'>
          <div class='card card-custom'>
            <div class='d-flex align-items-center'>
              <i class='bi $icon summary-icon text-$color'></i>
              <div>
                <div class='text-muted small'>{$label}</div>
                <div class='fw-bold fs-5'>₹" . number_format((float)($value ?? 0), 2) . "</div>
              </div>
            </div>
          </div>
        </div>";
      }
      ?>
    </div>

    <div class="chart-section">
      <div class="chart-card">
        <div class="chart-header">
          <h6 class="mb-0 fw-semibold text-secondary">Sales & Profit Overview</h6>
          <div>
            <button class="btn btn-outline-primary btn-sm me-1" onclick="loadGraph('last7')">7-Day</button>
            <button class="btn btn-outline-primary btn-sm me-1" onclick="loadGraph('daily')">Daily</button>
            <button class="btn btn-outline-primary btn-sm me-1" onclick="loadGraph('monthly')">Monthly</button>
            <button class="btn btn-outline-primary btn-sm" onclick="loadGraph('yearly')">Yearly</button>
          </div>
        </div>
        <canvas id="reportChart"></canvas>
      </div>

      <div class="chart-card">
        <div class="chart-header">
          <h6 class="mb-0 fw-semibold text-secondary">Top Selling Products</h6>
        </div>
        <canvas id="topProductsChart"></canvas>
      </div>
    </div>
  </div>

  <script>
    let reportChart, topChart;

    async function loadGraph(view = 'monthly') {
      try {
        const res = await fetch('./get_sales_chart_data.php?view=' + view);
        const data = await res.json();

        const ctx = document.getElementById('reportChart').getContext('2d');
        if (reportChart) reportChart.destroy();

        reportChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: data.dates,
            datasets: [
              {
                label: 'Sales',
                data: data.sales || [],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0,123,255,0.1)',
                fill: true,
                tension: 0.3
              },
              {
                label: 'Profit',
                data: data.profit || [],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40,167,69,0.1)',
                fill: true,
                tension: 0.3
              }
            ]
          },
          options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
          }
        });
      } catch (err) {
        console.error('Error loading chart:', err);
      }
    }

    // Top Products Pie Chart
    const productNames = <?= json_encode(array_column($top_products, 'product_name')) ?>;
    const productQuantities = <?= json_encode(array_column($top_products, 'total_sold')) ?>;

    const pieCtx = document.getElementById('topProductsChart').getContext('2d');
    topChart = new Chart(pieCtx, {
      type: 'doughnut',
      data: {
        labels: productNames,
        datasets: [{
          data: productQuantities,
          backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#20c997'],
          borderWidth: 1
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 12 } } }
        }
      }
    });

    loadGraph('last7'); // default load
  </script>
</body>
</html>
