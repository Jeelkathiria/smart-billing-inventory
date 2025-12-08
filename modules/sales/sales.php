<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../auth/auth_check.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$user_id  = $_SESSION['user_id'];
$role     = $_SESSION['role'];
$store_id = $_SESSION['store_id'];

if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: ../../auth/index.php');
    exit();
}

$today = date('Y-m-d');

/* ---------- TODAY’S SUMMARY ---------- */
$today = date('Y-m-d');

if ($_SESSION['role'] === 'cashier') {
    // Cashier: only their own sales
    $cashier_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) AS sales_count, SUM(total_amount) AS revenue 
                            FROM sales 
                            WHERE DATE(sale_date) = ? AND store_id = ? AND created_by = ?");
    $stmt->bind_param("sii", $today, $store_id, $cashier_id);
} else {
    // Admin/Manager: all store sales
    $stmt = $conn->prepare("SELECT COUNT(*) AS sales_count, SUM(total_amount) AS revenue 
                            FROM sales 
                            WHERE DATE(sale_date) = ? AND store_id = ?");
    $stmt->bind_param("si", $today, $store_id);
}

$stmt->execute();
$todayRes = $stmt->get_result()->fetch_assoc();
$todaySalesCount = $todayRes['sales_count'] ?? 0;
$todayRevenue    = $todayRes['revenue'] ?? 0;
$stmt->close();


/* ---------- FILTERS & PAGINATION ---------- */
$records_per_page = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $records_per_page;

$filter_invoice = $_GET['invoice_id'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

$where = "WHERE s.store_id = ?";
$params = [$store_id];
$types = "i";

if ($role === 'cashier') {
    $where .= " AND s.created_by = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($filter_invoice)) {
    $where .= " AND s.invoice_id LIKE ?";
    $params[] = "%{$filter_invoice}%";
    $types .= "s";
}

if (!empty($filter_date)) {
    $where .= " AND DATE(s.sale_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

$count_sql = "SELECT COUNT(*) AS total FROM sales s $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = ($count_stmt->get_result()->fetch_assoc())['total'] ?? 0;
$count_stmt->close();
$total_pages = ceil($total_rows / $records_per_page);

$sql = "
    SELECT s.sale_id, s.invoice_id, s.total_amount, s.subtotal, s.tax_amount, s.sale_date,
           COALESCE(c.customer_name, '--') AS customer_name,
           COALESCE(u.username, '--') AS billed_by
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN users u ON s.created_by = u.user_id
    $where
    ORDER BY s.sale_date DESC
    LIMIT ?, ?
";
$params[] = $start;
$params[] = $records_per_page;
$types .= "ii";

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
  body {
    background-color: #f8f9fa;
    overflow-x: hidden;
  }

  main.content {
    padding: 2rem;
    margin-left: 230px;
    transition: margin-left 0.3s ease;
  }

  @media (max-width: 992px) {
    main.content {
      margin-left: 0;
      padding: 1.5rem;
    }
  }

  .card {
    border-radius: 1.2rem;
    box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, 0.05);
  }

  .card-header {
    background: linear-gradient(90deg, #007bff, #0056d2);
    color: #fff;
    border-radius: 1.2rem 1.2rem 0 0;
    font-weight: 600;
  }

  .summary-card {
    border-radius: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
    height: 180px;
    padding: 2rem;
    box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease;
  }

  .summary-card:hover {
    transform: translateY(-5px);
  }

  .bg-green {
    background: linear-gradient(135deg, #28a745, #218838);
  }

  .bg-blue {
    background: linear-gradient(135deg, #0d6efd, #0a58ca);
  }

  .summary-card h4 {
    font-weight: 700;
    font-size: 2rem;
    margin: 0;
  }

  .summary-card h6 {
    font-size: 1rem;
    opacity: 0.9;
  }

  table {
    font-size: 0.95rem;
  }

  .table thead {
    background-color: #0d6efd;
    color: #fff;
  }

  .table tbody tr:hover {
    background-color: #f5f8ff;
  }

  .btn-outline-primary.btn-sm {
    font-weight: 500;
  }

  .pagination .page-link {
    border-radius: 0.5rem;
    color: #007bff;
    font-weight: 500;
  }

  .pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
    color: #fff;
  }

  @media (max-width: 768px) {
    .summary-card {
      height: auto;
      padding: 1.5rem;
      flex-direction: column;
      text-align: center;
    }

    .summary-card i {
      font-size: 2rem !important;
      margin-top: 0.5rem;
    }

    table {
      font-size: 0.85rem;
    }
  }

  @media (max-width: 576px) {
    .card-body {
      padding: 1rem !important;
    }

    form.d-flex {
      flex-direction: column;
      gap: 1rem;
    }

    .summary-card {
      padding: 1rem;
    }

    .summary-card h4 {
      font-size: 1.5rem;
    }
  }
  </style>
</head>

<body>
  <?php include(__DIR__ . "/../../components/navbar.php"); ?>
  <?php include(__DIR__ . "/../../components/sidebar.php"); ?>

  <main class="content mt-5">
    <!-- SUMMARY CARDS -->
    <!-- SUMMARY CARDS -->
    <div class="row g-4 mb-5">

      <!-- Revenue Today -->
      <div class="col-md-6">
        <div class="summary-card bg-green">
          <div>
            <h6>
              <i class="bi bi-cash-coin me-2"></i>
              <?php
        echo ($_SESSION['role'] === 'cashier') 
            ? "Total Billing Amount Today" 
            : "Revenue Today";
    ?>
            </h6>

            <h4>₹<?= e(number_format($todayRevenue, 2)); ?></h4>
          </div>
          <i class="bi bi-bar-chart-line fs-1 opacity-75"></i>
        </div>
      </div>

      <!-- Invoices Today -->
      <div class="col-md-6">
        <div class="summary-card bg-blue">
          <div>
            <h6><i class="bi bi-receipt-cutoff me-2"></i>Invoices Today</h6>
            <h4><?= e($todaySalesCount); ?></h4>
          </div>
          <i class="bi bi-journal-text fs-1 opacity-75"></i>
        </div>
      </div>

    </div>


    <!-- SALES HISTORY -->
    <div class="card mb-5">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-2"></i>Sales History</span>
        <a href="export_sales.php" class="btn btn-light text-success fw-semibold shadow-sm px-4 py-2">
          <i class="bi bi-file-earmark-excel-fill me-2"></i>Export
        </a>
      </div>

      <div class="card-body p-4">
        <!-- Filters -->
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap mb-4" id="filterForm">
          <input type="text" name="invoice_id" class="form-control" placeholder="Search Invoice ID..."
            value="<?= e($filter_invoice); ?>" style="max-width:280px;">
          <input type="date" name="filter_date" class="form-control" value="<?= e($filter_date); ?>"
            style="max-width:200px;">
          <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
          <a href="sales.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Reset</a>
        </form>

        <!-- Table + Pagination -->
        <div id="sales-container">
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
              <thead>
                <tr>
                  <th>Invoice ID</th>
                  <th>Customer Name</th>
                  <th>Date & Time</th>
                  <th>Subtotal</th>
                  <th>Tax</th>
                  <th>Total</th>
                  <th>Billed By</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if($salesResult && $salesResult->num_rows > 0): ?>
                <?php while($row = $salesResult->fetch_assoc()): ?>
                <tr>
                  <td><span class="badge bg-secondary"><?= e($row['invoice_id']); ?></span></td>
                  <td><?= e($row['customer_name']); ?></td>
                  <td><?= e(date('d-m-Y H:i:s', strtotime($row['sale_date']))); ?></td>
                  <td>₹<?= e(number_format($row['subtotal'], 2)); ?></td>
                  <td>₹<?= e(number_format($row['tax_amount'], 2)); ?></td>
                  <td><strong>₹<?= e(number_format($row['total_amount'], 2)); ?></strong></td>
                  <td><?= e($row['billed_by']); ?></td>
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
                  <td colspan="8" class="text-muted">No sales history available.</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
          <nav>
            <ul class="pagination justify-content-center mt-4 flex-wrap">
              <?php for ($p = 1; $p <= $total_pages; $p++): ?>
              <li class="page-item <?= ($p == $page) ? 'active' : ''; ?>">
                <a class="page-link"
                  href="?page=<?= $p ?>&invoice_id=<?= e($filter_invoice); ?>&filter_date=<?= e($filter_date); ?>">
                  <?= $p; ?>
                </a>
              </li>
              <?php endfor; ?>
            </ul>
          </nav>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <script>
  document.addEventListener("DOMContentLoaded", function() {
    const salesContainer = document.getElementById("sales-container");

    // existing pagination handler (keeps working with returned fragments)
    salesContainer.addEventListener("click", function(e) {
      const link = e.target.closest(".page-link");
      if (link) {
        e.preventDefault();
        fetch(link.href)
          .then(res => res.text())
          .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, "text/html");
            const newContent = newDoc.querySelector("#sales-container").innerHTML;
            salesContainer.innerHTML = newContent;
            salesContainer.scrollIntoView({ behavior: "smooth", block: "start" });
          })
          .catch(err => console.error("Pagination load error:", err));
      }
    });

    // Debounce helper
    function debounce(fn, wait) {
      let t;
      return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
      };
    }

    // Perform AJAX search and replace sales container
    async function performSearch(params = {}) {
      try {
        // build query: always page=1 for live search
        const url = new URL(window.location.href);
        url.searchParams.set('page', '1');
        if (params.invoice !== undefined) url.searchParams.set('invoice_id', params.invoice);
        if (params.date !== undefined) url.searchParams.set('filter_date', params.date);
        // fetch the full page and extract sales-container
        const res = await fetch(url.toString(), { credentials: 'same-origin' });
        const html = await res.text();
        const parser = new DOMParser();
        const newDoc = parser.parseFromString(html, "text/html");
        const newContentEl = newDoc.querySelector("#sales-container");
        if (newContentEl) {
          salesContainer.innerHTML = newContentEl.innerHTML;
          salesContainer.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      } catch (err) {
        console.error('Live search error:', err);
      }
    }

    // Inputs
    const invoiceInput = document.querySelector('input[name="invoice_id"]');
    const dateInput = document.querySelector('input[name="filter_date"]');

    // Debounced handler for invoice typing
    const debouncedInvoice = debounce(() => {
      performSearch({ invoice: invoiceInput.value.trim(), date: dateInput.value });
    }, 350);

    invoiceInput.addEventListener('input', debouncedInvoice);

    // Trigger search when date changes
    dateInput.addEventListener('change', () => {
      performSearch({ invoice: invoiceInput.value.trim(), date: dateInput.value });
    });

    // Prevent form submit from reloading when user presses Enter; rely on live search
    const filterForm = document.getElementById('filterForm');
    filterForm.addEventListener('submit', function(e) {
      e.preventDefault();
      // ensure immediate search on explicit submit
      performSearch({ invoice: invoiceInput.value.trim(), date: dateInput.value });
    });
  });
  </script>
</body>

</html>