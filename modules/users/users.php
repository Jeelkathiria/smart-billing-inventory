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

// Restrict access
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

// Fetch cashiers for current store
$stmt = $conn->prepare("
    SELECT user_id, username, email, role,
           CASE 
               WHEN last_activity >= (NOW() - INTERVAL 5 MINUTE) THEN 'online'
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/assets/css/common.css">
  <style>
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 3vh;
  }

  .page-header h2 {
    font-weight: 600;
    color: #222;
  }

  .card {
    border: none;
    border-radius: 1.2rem;
    box-shadow: 0 0.5vh 1vh rgba(0, 0, 0, 0.1);
    background-color: #ffffff;
  }

  .table th {
    background-color: #f1f5ff;
    color: #333;
  }

  .table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
    transform: scale(1.005);
    transition: 0.2s ease;
  }

  .dot {
    display: inline-block;
    width: 1.2vh;
    height: 1.2vh;
    border-radius: 50%;
    margin-right: 0.5vw;
  }

  .bg-success {
    background-color: #28a745 !important;
  }

  .bg-danger {
    background-color: #dc3545 !important;
  }

  @media (max-width: 992px) {
    .sidebar {
      width: 100%;
      height: auto;
      position: relative;
      border-right: none;
      flex-direction: row;
      justify-content: space-around;
      padding-top: 0;
    }

    .content {
      margin-left: 0;
      padding-top: 10vh;
    }
  }
  </style>
</head>

<body>

  <!-- Navbar -->
  <?php include '../../components/navbar.php'; ?>

  <!-- Sidebar -->
  <?php include '../../components/sidebar.php'; ?>

  <main class="content">
    <div class="container-fluid">

      <div class="page-header">
        <h2><i class="bi bi-person-lines-fill text-primary me-2"></i>Cashier Overview</h2>
        <span class="text-muted small">Last Updated: <?= date("M d, Y • H:i") ?></span>
      </div>

      <div class="card p-0">
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
                <?php $count = 1; foreach ($cashiers as $cashier): ?>
                <tr>
                  <td class="ps-4"><?= $count++; ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($cashier['username']); ?></td>
                  <td><?= htmlspecialchars($cashier['email']); ?></td>
                  <td>
                    <span class="d-inline-flex align-items-center">
                      <span class="dot <?= $cashier['status']==='online' ? 'bg-success' : 'bg-danger'; ?>"></span>
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