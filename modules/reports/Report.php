<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
    // Redirect to login page in auth folder
    header('Location: ../../auth/index.php?error=Please%20login');
    exit();
}

// Store session variables for later use
$store_id = $_SESSION['store_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Initialize variables to avoid undefined errors
$total_revenue = 0;
$total_tax     = 0;
$today_total   = 0;
$month_total   = 0;
$year_total    = 0;

// Summary (total revenue and tax)
$sql_summary = "
    SELECT 
        SUM(si.quantity * si.price) AS total_revenue,
        SUM(si.quantity * si.price * (si.gst_percent / 100)) AS total_tax
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE s.store_id = ?
";
$stmt = $conn->prepare($sql_summary);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($total_revenue, $total_tax);
$stmt->fetch();
$stmt->close();

// Today's revenue
$sql_today = "SELECT SUM(total_amount) FROM sales WHERE store_id = ? AND DATE(sale_date) = CURDATE()";
$stmt = $conn->prepare($sql_today);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($today_total);
$stmt->fetch();
$stmt->close();

// This month's revenue
$sql_month = "SELECT SUM(total_amount) FROM sales WHERE store_id = ? AND MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())";
$stmt = $conn->prepare($sql_month);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($month_total);
$stmt->fetch();
$stmt->close();

// This year's revenue
$sql_year = "SELECT SUM(total_amount) FROM sales WHERE store_id = ? AND YEAR(sale_date) = YEAR(CURDATE())";
$stmt = $conn->prepare($sql_year);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($year_total);
$stmt->fetch();
$stmt->close();

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
            ['Total Tax Collected', $total_tax, 'danger'],
            ["Today's Revenue", $today_total, 'secondary'],
            ['This Month', $month_total, 'secondary'],
            ['This Year', $year_total, 'secondary']
        ];
        foreach ($cards as [$label, $value, $color]) {
            echo "
            <div class='col-md-4'>
                <div class='card card-custom bg-white p-3'>
                    <div class='card-header'>$label</div>
                    <div class='card-body text-$color fw-bold fs-4'>₹" . number_format((float)($value ?? 0), 2) . "</div>
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