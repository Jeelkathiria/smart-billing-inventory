<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$user_id  = $_SESSION['user_id'] ?? null;
$store_id = $_SESSION['store_id'] ?? null;
$role     = $_SESSION['role'] ?? '';

if (!$user_id || !$store_id) {
    header("Location: /auth/index.php");
    exit;
}

// Restrict access — only admin or manager
if (!in_array($role, ['admin', 'manager'])) {
    echo '
    <div class="container mt-5">
      <div class="alert alert-danger">
        <h4 class="alert-heading">Access Denied</h4>
        <p>Only administrators and managers can access this page.</p>
      </div>
    </div>';
    exit;
}

// ✅ Fetch all cashiers (including email)
$stmt = $conn->prepare("
    SELECT 
        user_id, 
        username, 
        email,
        role,
        CASE 
        WHEN last_activity >= (NOW() - INTERVAL 5 SECOND) THEN 'online'
        ELSE 'offline'
        END AS status


    FROM users
    WHERE store_id = ? AND role = 'cashier'
    ORDER BY username
");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$cashiers = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Cashiers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/assets/css/common.css">

  <style>
  body {
    background: #f8f9fb;
    overflow-x: hidden;
  }

  .content {
    margin-left: 260px;
    padding: 4vh 2vw;
    min-height: 100vh;
    transition: all 0.3s ease;
  }

  /* --- Header --- */
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 3vh;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .page-header h2 {
    font-weight: 600;
    color: #222;
    font-size: 1.7rem;
  }

  /* --- Card --- */
  .card {
    border: none;
    border-radius: 1.2rem;
    box-shadow: 0 0.6vh 1.4vh rgba(0, 0, 0, 0.08);
    background-color: #fff;
  }

  /* --- Table --- */
  .table thead th {
    background-color: #f1f5ff;
    color: #333;
    font-weight: 600;
    font-size: 0.95rem;
    border-bottom: 2px solid #dee2e6;
  }

  .table td {
    vertical-align: middle;
    font-size: 0.93rem;
  }

  .table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
    transition: all 0.2s ease-in-out;
  }

  /* --- Status Dots --- */
  .dot {
    display: inline-block;
    width: 0.8rem;
    height: 0.8rem;
    border-radius: 50%;
    margin-right: 0.5rem;
  }

  .bg-success {
    background-color: #28a745 !important;
  }

  .bg-danger {
    background-color: #dc3545 !important;
  }

  /* --- Responsive Adjustments --- */
  @media (max-width: 1200px) {
    .content {
      margin-left: 0;
      padding-top: 8vh;
    }

    .table th,
    .table td {
      font-size: 0.9rem;
    }

    .page-header h2 {
      font-size: 1.4rem;
    }
  }

  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }

    .content {
      padding: 2vh 4vw;
    }

    .table th,
    .table td {
      font-size: 0.85rem;
    }
  }
  </style>
</head>

<body>

  <!-- Navbar -->
  <?php include '../../components/navbar.php'; ?>

  <!-- Sidebar -->
  <?php include '../../components/sidebar.php'; ?>

  <main class="content mt-5">
    <div class="container-fluid">

      <div class="page-header">
        <h2><i class="bi bi-person-lines-fill text-primary me-2"></i>Cashier Overview</h2>
        <?php date_default_timezone_set('Asia/Kolkata'); ?>
        <span class="text-muted small">Last Updated: <?= date("M d, Y • H:i") ?></span>


      </div>

      <div class="card">
        <div class="card-body p-0">
          <?php if (empty($cashiers)): ?>
          <div class="alert alert-info m-4">
            <i class="bi bi-info-circle"></i> No cashiers found for this store.
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th class="ps-4">#</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php $count = 1;
                  foreach ($cashiers as $cashier): ?>
                <tr>
                  <td class="ps-4"><?= $count++; ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($cashier['username'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($cashier['email'] ?? 'N/A'); ?></td>
                  <td>
                    <span class="d-inline-flex align-items-center">
                      <span class="dot <?= $cashier['status'] === 'online' ? 'bg-success' : 'bg-danger'; ?>"></span>
                      <?= ucfirst($cashier['status']); ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

</body>

</html>

<script>
function updateLastUpdated() {
    const now = new Date();
    
    // Format: M d, Y • H:i:s
    const formatted = now.toLocaleString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    }).replace(',', ' •');

    document.getElementById("lastUpdated").textContent = "Last Updated: " + formatted;
}

// Update every 5 seconds
setInterval(updateLastUpdated, 5000);
</script>
