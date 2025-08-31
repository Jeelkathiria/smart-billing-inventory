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

// Fetch cashiers for this store
$stmt = $conn->prepare("
    SELECT id, username, email, role, last_login
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
  </style>
  </head>
  <body>
    <div class="container mt-4">
      <?php include_once __DIR__ . '/../../components/navbar.php'; ?>
      <?php include_once __DIR__ . '/../../components/sidebar.php'; ?>

    <h2>Cashiers in This Store</h2>
    <div class="card mt-3">
        <div class="card-body">
            <?php if (empty($cashiers)): ?>
                <p>No cashiers found for this store.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cashiers as $index => $cashier): ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td><?= htmlspecialchars($cashier['username']); ?></td>
                                    <td><?= htmlspecialchars($cashier['email']); ?></td>
                                    <td><?= $cashier['last_login'] ? htmlspecialchars($cashier['last_login']) : 'Never'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
