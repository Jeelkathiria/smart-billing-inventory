<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php'; // starts session

$user_id  = $_SESSION['user_id'] ?? null;
$store_id = $_SESSION['store_id'] ?? null;
$role     = $_SESSION['role'] ?? '';

if (!$user_id || !$store_id) {
    header("Location: /auth/index.php");
    exit;
}

// Only admins and managers can view
if (!in_array($role, ['admin', 'manager'])) {
    ?>
<div class="container mt-5">
  <div class="alert alert-danger">
    <h4 class="alert-heading">Access Denied</h4>
    <p>Only administrators and managers can access this page.</p>
  </div>
</div>
<?php
    exit;
}

// Fetch all cashiers for this store
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
  <title>users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
     body {
      background-color: #f8f9fa;
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

     .content {
      margin-left: 240px;
      padding: 20px;
      padding-top: 80px; 
    }
     .dot {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      box-shadow: 0 0 4px rgba(0,0,0,0.2);
    }
    .table-hover tbody tr:hover {
      background-color: rgba(0, 123, 255, 0.05) !important;
      cursor: pointer;
    }
    .card {
      background-color: #ffffff;
    }
  </style>
</head>

<body class="bg-light">
  <!-- Navbar -->
  <?php include '../../components/navbar.php'; ?>

  <!-- Sidebar -->
  <?php include '../../components/sidebar.php'; ?>

  <main class="content" style="margin-left:240px; padding: 80px 20px 20px;">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark mb-0">
          <i class="bi bi-people-fill text-primary me-2"></i> Cashiers in This Store
        </h2>
        <span class="text-muted small">Updated <?= date("M d, Y H:i"); ?></span>
      </div>

      <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
          <?php if (empty($cashiers)): ?>
            <div class="alert alert-info m-3">
              <i class="bi bi-info-circle"></i> No cashiers found for this store.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle table-striped table-hover mb-0">
                <thead class="table-primary">
                  <tr>
                    <th class="ps-4">#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $count = 1; ?>
                  <?php foreach ($cashiers as $cashier): ?>
                    <tr>
                      <td class="ps-4"><?= $count++; ?></td>
                      <td class="fw-semibold"><?= htmlspecialchars($cashier['username']); ?></td>
                      <td><?= htmlspecialchars($cashier['email']); ?></td>
                      <td>
                        <span class="d-inline-flex align-items-center">
                          <span class="dot <?= $cashier['status']==='online' ? 'bg-success' : 'bg-danger'; ?> me-2"></span>
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
</html>