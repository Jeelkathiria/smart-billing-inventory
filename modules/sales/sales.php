<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../auth/auth_check.php";

/* ------------------------------------------
   Helper for Safe HTML Output
------------------------------------------- */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------
   SESSION & BASIC SETUP
------------------------------------------- */
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

/* ------------------------------------------
   TODAY’S SUMMARY
------------------------------------------- */
$stmt = $conn->prepare("SELECT COUNT(*) AS sales_count, SUM(total_amount) AS revenue 
                        FROM sales 
                        WHERE DATE(sale_date) = ? AND store_id = ?");
$stmt->bind_param("si", $today, $store_id);
$stmt->execute();
$todayRes = $stmt->get_result()->fetch_assoc();
$todaySalesCount = $todayRes['sales_count'] ?? 0;
$todayRevenue    = $todayRes['revenue'] ?? 0;
$stmt->close();

/* ------------------------------------------
   FILTERS & PAGINATION
------------------------------------------- */
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

/* ------------------------------------------
   COUNT TOTAL RECORDS
------------------------------------------- */
$count_sql = "SELECT COUNT(*) AS total FROM sales s $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = ($count_stmt->get_result()->fetch_assoc())['total'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $records_per_page);

/* ------------------------------------------
   FETCH PAGINATED SALES DATA
------------------------------------------- */
$sql = "
    SELECT s.sale_id, s.invoice_id, s.total_amount, s.subtotal, s.tax_amount, s.sale_date,
           COALESCE(c.customer_name, '--') AS customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.customer_id
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
  }

  .card {
    border-radius: 1.5ch;
    box-shadow: 0 0.8ch 2ch rgba(0, 0, 0, 0.05);
  }

  .card-header {
    background: linear-gradient(90deg, #007bff, #0056d2);
    color: #fff;
    border-radius: 1.5ch 1.5ch 0 0;
  }

  .summary-card {
    border-radius: 1.5ch;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
    height: 18vh;
    box-shadow: 0 1ch 2.5ch rgba(0, 0, 0, 0.1);
    padding: 3ch 4ch;
    transition: transform 0.3s ease;
  }

  .summary-card:hover {
    transform: translateY(-0.5ch);
  }

  .bg-green {
    background: linear-gradient(135deg, #28a745, #218838);
  }

  .bg-blue {
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

  .table thead {
    background-color: #0d6efd;
    color: #fff;
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
            <h4>₹<?= e(number_format($todayRevenue, 2)); ?></h4>
          </div>
          <i class="bi bi-bar-chart-line fs-1 opacity-75"></i>
        </div>
      </div>
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

    <div class="card mb-5">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-2"></i>Sales History</span>
        <a href="export_sales.php" class="btn btn-light text-success fw-semibold shadow-sm px-4 py-2">
          <i class="bi bi-file-earmark-excel-fill me-2"></i>Export
        </a>
      </div>

      <div class="card-body" style="padding: 3ch 4ch;">
        <form method="GET" class="d-flex align-items-center gap-3 mb-4">
          <input type="text" name="invoice_id" class="form-control" placeholder="Search Invoice ID..."
            value="<?= e($filter_invoice); ?>" style="max-width:30ch;">
          <input type="date" name="filter_date" class="form-control" value="<?= e($filter_date); ?>"
            style="max-width:22ch;">
          <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
          <a href="sales.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Reset</a>
        </form>

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
                <td colspan="7" class="text-muted">No sales history available.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <nav>
          <ul class="pagination justify-content-center mt-4">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link"
                href="?page=<?= $page - 1 ?>&invoice_id=<?= e($filter_invoice); ?>&filter_date=<?= e($filter_date); ?>">Previous</a>
            </li>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?= ($p == $page) ? 'active' : ''; ?>">
              <a class="page-link"
                href="?page=<?= $p ?>&invoice_id=<?= e($filter_invoice); ?>&filter_date=<?= e($filter_date); ?>"><?= $p; ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <li class="page-item"><a class="page-link"
                href="?page=<?= $page + 1 ?>&invoice_id=<?= e($filter_invoice); ?>&filter_date=<?= e($filter_date); ?>">Next</a>
            </li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
    </div>
  </main>
</body>

</html>