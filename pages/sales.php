<?php
require_once '../includes/db.php';
$today = date('Y-m-d');

$query = "SELECT COUNT(*) AS sales_count, SUM(total_amount) AS revenue 
          FROM sales WHERE DATE(sale_date) = '$today'";
$result = $conn->query($query)->fetch_assoc();

$todaySalesCount = $result['sales_count'] ?? 0;
$todayRevenue = $result['revenue'] ?? 0;
$filterDate = $_GET['filter_date'] ?? '';


$whereClause = '';
if (!empty($filterDate)) {
  $safeDate = $conn->real_escape_string($filterDate);
  $whereClause = "WHERE DATE(sale_date) = '$safeDate'";
}

$sql = "SELECT * FROM sales $whereClause ORDER BY sale_date DESC";
$salesResult = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales History</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

  <style>
  body {
    background-color: #f8f9fb;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }

  .card {
    border-radius: 1rem;
    overflow: hidden;
  }

  .card-header {
    background: linear-gradient(90deg, #0d6efd, #0b5ed7);
    color: white;
    padding: 1.5rem 2rem;
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
    font-size: 1.75rem;
  }

  .form-control,
  .btn {
    border-radius: 0.75rem;
  }

  .search-wrapper {
    position: relative;
  }

  .search-wrapper input {
    padding-left: 2.5rem;
  }

  .search-wrapper .bi-search {
    position: absolute;
    top: 50%;
    left: 12px;
    transform: translateY(-50%);
    color: #6c757d;
  }

  table thead {
    background-color: #343a40;
    color: #fff;
  }

  tbody tr:hover {
    background-color: #f1f5ff;
  }

  .badge {
    font-size: 0.9rem;
  }

  .filter-label {
    font-weight: 600;
  }

   #toggleFilter {
    border-radius: 50px;
    transition: all 0.3s ease;
  }

  #toggleFilter:hover {
    background-color: #0d6efd;
    color: white;
  }

  #filterDate {
    transition: 0.3s ease;
  }

  .table th,
  .table td {
    vertical-align: middle;
  }

  @media (max-width: 576px) {
    .summary-card h4 {
      font-size: 1.4rem;
    }
  }
  </style>
</head>

<body>
  <div class="container py-4">
    <?php include '../components/backToDashboard.php'; ?>

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
                <h4><?php echo $todaySalesCount; ?></h4>
              </div>
              <i class="bi bi-journal-text fs-1 opacity-75"></i>
            </div>
          </div>
        </div>

        <!-- Search + Filter Row -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <!-- Search -->
  <div class="flex-grow-1 me-2 search-wrapper">
    <i class="bi bi-search"></i>
    <input type="text" class="form-control shadow-sm" id="searchInput"
      placeholder="Search by Invoice ID or Customer Name..." onkeyup="filterTable()">
  </div>

  <!-- Filter Form -->
  <form method="GET" class="d-flex align-items-center gap-2" id="filterForm">
    <!-- Filter Icon Toggle -->
    <button type="button" class="btn btn-outline-secondary d-flex align-items-center" id="toggleFilter">
      <i class="bi bi-funnel-fill" id="filterIcon"></i>
    </button>

    <!-- Date Picker -->
    <input type="date" class="form-control" name="filter_date" id="filterDate"
      value="<?php echo htmlspecialchars($filterDate); ?>"
      style="max-width: 200px; display: <?php echo $filterDate ? 'block' : 'none'; ?>;" />

    <!-- Hidden Submit -->
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

  <!-- JS: Search -->
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

    // Filter Functionality
  const toggleBtn = document.getElementById("toggleFilter");
  const filterDate = document.getElementById("filterDate");
  const filterIcon = document.getElementById("filterIcon");
  const form = document.getElementById("filterForm");

  toggleBtn.addEventListener("click", () => {
    const isVisible = filterDate.style.display === "block";

    if (isVisible) {
      // Hide and reset the date filter
      filterDate.style.display = "none";
      filterDate.value = '';
      window.location.href = window.location.pathname; // Clear filter
      filterIcon.classList.remove("text-primary");
    } else {
      // Show and wait for user to pick a date
      filterDate.style.display = "block";
      filterIcon.classList.add("text-primary");
    }
  });

  filterDate.addEventListener("change", () => {
    if (filterDate.value) {
      form.submit();
    }
  });

  </script>
</body>

</html>