<?php
require_once __DIR__ . "/../../config/db.php";

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

$store_id = $_SESSION['store_id'];
$today = date('Y-m-d');

// Today's revenue and sales count for this store
$query = "SELECT COUNT(*) AS sales_count, SUM(total_amount) AS revenue 
          FROM sales WHERE DATE(sale_date) = ? AND store_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $today, $store_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

$todaySalesCount = $result['sales_count'] ?? 0;
$todayRevenue = $result['revenue'] ?? 0;

// Filtering
$filterDate = $_GET['filter_date'] ?? '';
$whereClause = "WHERE store_id = ?";
$params = [$store_id];
$types = "i";

if (!empty($filterDate)) {
  $whereClause .= " AND DATE(sale_date) = ?";
  $params[] = $filterDate;
  $types .= "s";
}

$sql = "SELECT * FROM sales $whereClause ORDER BY sale_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$salesResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
  body {
    background-color: #f5f7fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
  }

  .card {
    border-radius: 1rem;
    overflow: hidden;
    background-color: #fff;
  }

  .card-header {
    background: linear-gradient(90deg, #0d6efd, #0b5ed7);
    color: white;
    padding: 1rem 2rem;
    font-weight: 600;
  }

  .summary-card {
    border: none;
    border-radius: 1rem;
    padding: 1.5rem;
    color: #fff;
    background: linear-gradient(135deg, #28a745, #218838);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
  }

  .summary-card.bg-primary {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
  }

  .summary-card h4 {
    font-weight: 700;
    font-size: 1.8rem;
    margin-top: 4px;
  }

  .summary-card h6 {
    font-size: 0.95rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
  }

  .search-wrapper {
    position: relative;
    width: 100%;
  }

  .search-wrapper input {
    padding-left: 2.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  }

  .search-wrapper .bi-search {
    position: absolute;
    top: 50%;
    left: 12px;
    transform: translateY(-50%);
    color: #6c757d;
  }

  .form-control,
  .btn {
    border-radius: 0.75rem;
  }

  #toggleFilter {
    border-radius: 50px;
    transition: all 0.3s ease;
  }

  #toggleFilter:hover {
    background-color: #0d6efd;
    color: white;
  }

  .table th,
  .table td {
    vertical-align: middle;
    text-align: center;
  }

  .table thead {
    background-color: #0d6efd;
    color: white;
  }

  tbody tr:hover {
    background-color: #f1f5ff;
  }

  .badge {
    font-size: 0.9rem;
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

  .content-wrapper {
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

  @media (max-width: 768px) {
    .summary-card h4 {
      font-size: 1.4rem;
    }

    .card-header h5 {
      font-size: 1rem;
    }

    .btn {
      padding: 6px 10px;
    }

    .search-wrapper input {
      padding-left: 2rem;
    }

    #filterDate {
      max-width: 100% !important;
      width: 100%;
    }

    .d-flex.justify-content-between.flex-wrap {
      flex-direction: column;
    }
  }


  @media (max-width: 576px) {
    .summary-card h4 {
      font-size: 1.4rem;
    }
  }
  </style>
</head> 

<body>

  <?php include '../includes/navbar.php'; ?>
  <?php include '../includes/sidebar.php'; ?>



  <div class="content-wrapper">
  <h3 class="mb-4"><i class="bi bi-graph-up-arrow me-2"></i>Sales Page</h3>
    <div class="card shadow-sm mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Sales History</h5>
        <a href="export_sales.php" class="btn btn-light text-success fw-semibold shadow-sm rounded-pill px-4 py-2">
          <i class="bi bi-file-earmark-excel-fill me-2"></i>Export
        </a>
      </div>

      <div class="card-body">
        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
          <div class="col-md-6">
            <div class="summary-card d-flex justify-content-between align-items-center">
              <div>
                <h6 class="mb-1"><i class="bi bi-cash-coin me-2"></i>Revenue Today</h6>
                <h4>₹<?php echo number_format($todayRevenue, 2); ?></h4>
              </div>
              <i class="bi bi-bar-chart-line fs-1 opacity-75"></i>
            </div>
          </div>
          <div class="col-md-6">
            <div class="summary-card bg-primary d-flex justify-content-between align-items-center">
              <div>
                <h6 class="mb-1"><i class="bi bi-receipt-cutoff me-2"></i>Invoices Today</h6>
                <h4><?php echo htmlspecialchars($todaySalesCount); ?></h4>
              </div>
              <i class="bi bi-journal-text fs-1 opacity-75"></i>
            </div>
          </div>
        </div>

        <!-- Search & Filter -->
        <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
          <div class="flex-grow-1 me-2 search-wrapper">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control shadow-sm" id="searchInput"
              placeholder="Search by Invoice ID or Customer Name..." onkeyup="filterTable()">
          </div>

          <form method="GET" class="d-flex align-items-center gap-2" id="filterForm">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center" id="toggleFilter">
              <i class="bi bi-funnel-fill" id="filterIcon"></i>
            </button>
            <input type="date" class="form-control" name="filter_date" id="filterDate"
              value="<?php echo htmlspecialchars($filterDate); ?>"
              style="max-width: 200px; display: <?php echo $filterDate ? 'block' : 'none'; ?>;" />
            <input type="submit" style="display: none;" />
          </form>
        </div>

        <!-- Sales Table -->
        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle text-center" id="salesTable">
            <thead>
              <tr>
                <th>Invoice ID</th>
                <th>Customer Name</th>
                <th>Date & Time</th>
                <th>Total Amount</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($salesResult && $salesResult->num_rows > 0): ?>
              <?php while ($row = $salesResult->fetch_assoc()): ?>
              <tr>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['invoice_id']); ?></span></td>
                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                <td><?php echo date('d-m-Y H:i:s', strtotime($row['sale_date'])); ?></td>
                <td><strong>₹<?php echo number_format($row['total_amount'], 2); ?></strong></td>
                <td>
                  <a href="view_invoice.php?invoice_id=<?php echo urlencode($row['invoice_id']); ?>"
                    class="btn btn-outline-primary btn-sm rounded-pill">
                    <i class="bi bi-eye"></i> View
                  </a>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php else: ?>
              <tr>
                <td colspan="5" class="text-muted">No sales history available for the selected date.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- JS: Search & Filter -->
  <script>
  function filterTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#salesTable tbody tr');
    rows.forEach(row => {
      const invoice = row.cells[0].innerText.toLowerCase();
      const customer = row.cells[1].innerText.toLowerCase();
      row.style.display = invoice.includes(input) || customer.includes(input) ? '' : 'none';
    });
  }

  const toggleBtn = document.getElementById("toggleFilter");
  const filterDateInput = document.getElementById("filterDate");
  const filterIcon = document.getElementById("filterIcon");
  const form = document.getElementById("filterForm");

  toggleBtn.addEventListener("click", () => {
    const isVisible = filterDateInput.style.display === "block";
    if (isVisible) {
      filterDateInput.style.display = "none";
      filterDateInput.value = '';
      window.location.href = window.location.pathname;
      filterIcon.classList.remove("text-primary");
    } else {
      filterDateInput.style.display = "block";
      filterIcon.classList.add("text-primary");
    }
  });

  filterDateInput.addEventListener("change", () => {
    if (filterDateInput.value) {
      form.submit();
    }
  });
  </script>
</body>

</html>