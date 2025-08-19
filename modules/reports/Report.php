<?php
require_once __DIR__ . '/../../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$store_id = $_SESSION['store_id'];
$fixed_cost_price = 500;

// Summary
$stmt = $conn->prepare("
    SELECT 
        SUM(si.quantity * si.price) AS total_revenue,
        SUM(si.quantity * (si.price - ?)) AS total_profit,
        SUM(si.quantity * si.price * 0.05) AS total_tax
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.store_id = ?
");
$stmt->bind_param("ii", $fixed_cost_price, $store_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

$total_revenue = $data['total_revenue'] ?? 0;
$total_profit = $data['total_profit'] ?? 0;
$total_tax = $data['total_tax'] ?? 0;

// Daily
$stmt = $conn->prepare("
    SELECT SUM(si.quantity * si.price) AS today_total
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.store_id = ? AND DATE(s.sale_date) = CURDATE()
");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$today_total = $stmt->get_result()->fetch_assoc()['today_total'] ?? 0;

// Monthly
$stmt = $conn->prepare("
    SELECT SUM(si.quantity * si.price) AS month_total
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.store_id = ? AND MONTH(s.sale_date) = MONTH(CURRENT_DATE())
    AND YEAR(s.sale_date) = YEAR(CURRENT_DATE())
");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$month_total = $stmt->get_result()->fetch_assoc()['month_total'] ?? 0;

// Yearly
$stmt = $conn->prepare("
    SELECT SUM(si.quantity * si.price) AS year_total
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.store_id = ? AND YEAR(s.sale_date) = YEAR(CURRENT_DATE())
");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$year_total = $stmt->get_result()->fetch_assoc()['year_total'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Business Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
  .card-custom {
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  }

  .card-header {
    font-weight: bold;
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

  .content {
    margin-left: 220px;
    padding: 20px;
    padding-top: 80px;
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
  </style>
</head>

<body class="bg-light">
  <!-- Navbar -->
  <?php include '../../components/navbar.php'; ?>


  <!-- Sidebar -->
  <?php include '../../components/sidebar.php'; ?>

  <div class="container my-4 content">
    <h2 class="mb-4">📈 Business Performance Report</h2>

    <!-- Summary Cards -->
    <div class="row g-3">
      <?php
        $cards = [
            ['Total Revenue', $total_revenue, 'success'],
            ['Total Profit', $total_profit, 'primary'],
            ['Total Tax Collected (5%)', $total_tax, 'danger'],
            ["Today's Revenue", $today_total, 'secondary'],
            ['This Month', $month_total, 'secondary'],
            ['This Year', $year_total, 'secondary']
        ];
        foreach ($cards as [$label, $value, $color]) {
            echo "
            <div class='col-md-4'>
                <div class='card card-custom bg-white p-3'>
                    <div class='card-header'>$label</div>
                    <div class='card-body text-$color fw-bold fs-4'>₹" . number_format($value, 2) . "</div>
                </div>
            </div>";
        }
        ?>
    </div>

    <!-- Sales Overview Chart -->
    <div class="card card-custom mt-5 p-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>📊 Sales Overview</span>
        <div>
          <button class="btn btn-outline-primary btn-sm me-2" onclick="loadGraph('daily')">Daily</button>
          <button class="btn btn-outline-primary btn-sm me-2" onclick="loadGraph('monthly')">Monthly</button>
          <button class="btn btn-outline-primary btn-sm" onclick="loadGraph('yearly')">Yearly</button>
        </div>
      </div>
      <div class="card-body">
        <canvas id="reportChart" height="120"></canvas>
      </div>
    </div>
  </div>

  <script>
  let reportChart;

  function loadGraph(view = 'monthly') {
    fetch('./report_data.php?view=' + view)
      .then(res => res.json())
      .then(data => {
        const ctx = document.getElementById('reportChart').getContext('2d');

        if (reportChart) reportChart.destroy();

        reportChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: data.labels,
            datasets: [{
              label: 'Total Sales',
              data: data.totals,
              fill: true,
              backgroundColor: 'rgba(0, 123, 255, 0.1)',
              borderColor: 'rgba(0, 123, 255, 1)',
              tension: 0.4,
              pointRadius: 4
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                display: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: value => `₹${value}`
                }
              }
            }
          }
        });
      });
  }

  // Load Monthly by default
  loadGraph('monthly');
  </script>

</body>

</html>