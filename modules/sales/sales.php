<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . '/../../auth/auth_check.php';

$user_id  = $_SESSION['user_id'];
$role     = $_SESSION['role'];
$store_id = $_SESSION['store_id'];

// Session timeout (30 min)
if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: ../../auth/index.php');
    exit();
}

$today = date('Y-m-d');

// Today's summary
$stmt = $conn->prepare("SELECT COUNT(*) AS sales_count, SUM(total_amount) AS revenue 
                        FROM sales 
                        WHERE DATE(sale_date) = ? AND store_id = ?");
$stmt->bind_param("si", $today, $store_id);
$stmt->execute();
$todayRes = $stmt->get_result()->fetch_assoc();
$todaySalesCount = $todayRes['sales_count'] ?? 0;
$todayRevenue    = $todayRes['revenue'] ?? 0;
$stmt->close();

$filterDate = $_GET['filter_date'] ?? '';

$sql = "SELECT s.sale_id, s.invoice_id, s.total_amount, s.sale_date, c.customer_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        WHERE s.store_id = ?";
$params = [$store_id];
$types = "i";

if ($role === 'cashier') {
    $sql .= " AND s.created_by = ?";
    $params[] = $user_id;
    $types .= "i";
}
if (!empty($filterDate)) {
    $sql .= " AND DATE(s.sale_date) = ?";
    $params[] = $filterDate;
    $types .= "s";
}

$sql .= " ORDER BY s.sale_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$salesResult = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/common.css">
  <style>
  .card {
    border: none;
    border-radius: 1.5ch;
    background-color: #fff;
    box-shadow: 0 0.8ch 2ch rgba(0, 0, 0, 0.05);
  }

  .card-header {
    background: linear-gradient(90deg, #007bff, #0056d2);
    color: #fff;
    padding: 2ch 4ch;
    font-size: 2ch;
    border-radius: 1.5ch 1.5ch 0 0;
  }

  .summary-card {
    padding: 3ch 4ch;
    border-radius: 1.5ch;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
    height: 18vh;
    box-shadow: 0 1ch 2.5ch rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
  }

  .summary-card:hover {
    transform: translateY(-0.5ch);
  }

  .summary-card.bg-green {
    background: linear-gradient(135deg, #28a745, #218838);
  }

  .summary-card.bg-blue {
    background: linear-gradient(135deg, #0d6efd, #0a58ca);
  }

  .summary-card h4 {
    font-weight: 700;
    font-size: 3.5ch;
  }

  .summary-card h6 {
    font-size: 2ch;
    color: rgba(255, 255, 255, 0.85);
  }

  .search-wrapper {
    position: relative;
    width: 100%;
  }

  .search-wrapper input {
    padding-left: 4ch;
    border-radius: 1.5ch;
    font-size: 1.8ch;
    height: 6vh;
    box-shadow: 0 0.4ch 1.2ch rgba(0, 0, 0, 0.05);
  }

  .search-wrapper .bi-search {
    position: absolute;
    top: 50%;
    left: 1.5ch;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 1.8ch;
  }

  .btn {
    border-radius: 2ch;
    font-size: 1.6ch;
  }

  #toggleFilter {
    border-radius: 50%;
    width: 6ch;
    height: 6ch;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .table {
    font-size: 2ch;
  }

  .table thead {
    background-color: #0d6efd;
    color: white;
    font-size: 1.6ch;
  }

  tbody tr:hover {
    background-color: #f0f6ff;
  }

  .pagination .page-item .page-link {
    border-radius: 50%;
    width: 4ch;
    height: 4ch;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8ch;
  }
  </style>
</head>

<body>
  <?php include(__DIR__ . "/../../components/navbar.php"); ?>
  <?php include(__DIR__ . "/../../components/sidebar.php"); ?>

  <main class="content">
    <div class="row g-4 mb-5">
      <div class="col-md-6">
        <div class="summary-card bg-green">
          <div>
            <h6><i class="bi bi-cash-coin me-2"></i>Revenue Today</h6>
            <h4>₹<?= number_format($todayRevenue, 2); ?></h4>
          </div>
          <i class="bi bi-bar-chart-line fs-1 opacity-75"></i>
        </div>
      </div>
      <div class="col-md-6">
        <div class="summary-card bg-blue">
          <div>
            <h6><i class="bi bi-receipt-cutoff me-2"></i>Invoices Today</h6>
            <h4><?= htmlspecialchars($todaySalesCount); ?></h4>
          </div>
          <i class="bi bi-journal-text fs-1 opacity-75"></i>
        </div>
      </div>
    </div>

    <div class="card mb-5">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-2"></i>Sales History</span>
        <a href="export_sales.php" class="btn btn-light text-success fw-semibold shadow-sm px-4 py-2">
          <i class="bi bi-file-earmark-excel-fill me-2"></i>Export
        </a>
      </div>

      <div class="card-body" style="padding: 3ch 4ch;">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
          <div class="flex-grow-1 search-wrapper">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search Invoice ID or Customer..."
              onkeyup="filterTable()">
          </div>

          <form method="GET" id="filterForm" class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary" id="toggleFilter">
              <i class="bi bi-funnel-fill" id="filterIcon"></i>
            </button>
            <input type="date" class="form-control" name="filter_date" id="filterDate"
              value="<?= htmlspecialchars($filterDate); ?>"
              style="max-width:30ch; display: <?= $filterDate ? 'block' : 'none'; ?>;" />
            <input type="submit" style="display:none;">
          </form>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle text-center" id="salesTable">
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
              <?php if($salesResult && $salesResult->num_rows > 0): ?>
              <?php while($row = $salesResult->fetch_assoc()): ?>
              <tr>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['invoice_id']); ?></span></td>
                <td><?= htmlspecialchars($row['customer_name'] ?? '--'); ?></td>
                <td><?= date('d-m-Y H:i:s', strtotime($row['sale_date'])); ?></td>
                <td><strong>₹<?= number_format($row['total_amount'], 2); ?></strong></td>
                <td>
                  <a href="view_invoice.php?sale_id=<?= urlencode($row['sale_id']); ?>"
                    class="btn btn-outline-primary btn-sm rounded-pill">
                    <i class="bi bi-eye"></i> View
                  </a>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php else: ?>
              <tr>
                <td colspan="5" class="text-muted">No sales history available.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <ul class="pagination justify-content-center mt-4" id="paginationControls"></ul>
      </div>
    </div>
  </main>

  <script>
  // Search
  function filterTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#salesTable tbody tr').forEach(row => {
      const invoice = row.cells[0].innerText.toLowerCase();
      const customer = row.cells[1].innerText.toLowerCase();
      row.style.display = invoice.includes(input) || customer.includes(input) ? '' : 'none';
    });
  }

  // Filter toggle
  const toggleBtn = document.getElementById("toggleFilter");
  const filterDateInput = document.getElementById("filterDate");
  const filterIcon = document.getElementById("filterIcon");
  const form = document.getElementById("filterForm");

  toggleBtn.addEventListener("click", () => {
    const isVisible = filterDateInput.style.display === "block";
    filterDateInput.style.display = isVisible ? "none" : "block";
    if (isVisible) window.location.href = window.location.pathname;
    filterIcon.classList.toggle("text-primary", !isVisible);
  });
  filterDateInput.addEventListener("change", () => form.submit());

  // Pagination
  const rowsPerPage = 8;
  const rows = Array.from(document.querySelectorAll("#salesTable tbody tr"));
  const pagination = document.getElementById("paginationControls");

  function showPage(page) {
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    rows.forEach((r, i) => r.style.display = (i >= start && i < end) ? '' : 'none');
    pagination.querySelectorAll("li").forEach((btn, i) =>
      btn.classList.toggle("active", i === page));
  }

  function setupPagination() {
  const pages = Math.ceil(rows.length / rowsPerPage);
  pagination.innerHTML = "";
  for (let i = 1; i <= pages; i++) {
    const li = document.createElement("li");
    li.className = `page-item${i === 1 ? " active" : ""}`;
    li.innerHTML = `<a class='page-link' href='#'>${i}</a>`;

    li.addEventListener("click", e => {
      e.preventDefault();
      showPage(i);
      updateActivePage(i);
    });

    pagination.appendChild(li);
  }
}

function updateActivePage(page) {
  const allPages = pagination.querySelectorAll(".page-item");
  allPages.forEach((li, index) => {
    if (index + 1 === page) {
      li.classList.add("active");
    } else {
      li.classList.remove("active");
    }
  });
}

function showPage(page) {
  const start = (page - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  rows.forEach((row, index) => {
    row.style.display = index >= start && index < end ? "" : "none";
  });
}

// Initial setup
setupPagination();
showPage(1);

  </script>
</body>

</html>