<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Smart Billing</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Smart Billing</a>
      <div class="d-flex">
        <span class="navbar-text text-white me-3">
          Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
        </span>
        <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container mt-5">
    <h3 class="mb-4">📊 Dashboard</h3>
    <div class="row g-3">
      <div class="col-md-3">
        <a href="categories.php" class="text-decoration-none">
          <div class="card text-center p-3 shadow-sm">
            <h5>Manage Categories</h5>
          </div>
        </a>
      </div>
      <div class="col-md-3">
        <a href="products.php" class="text-decoration-none">
          <div class="card text-center p-3 shadow-sm">
            <h5>Manage Products</h5>
          </div>
        </a>
      </div>
      <div class="col-md-3">
        <a href="generate_bill.php" class="text-decoration-none">
          <div class="card text-center p-3 shadow-sm">
            <h5>Generate Bill</h5>
          </div>
        </a>
      </div>
      <div class="col-md-3">
        <a href="../admin_panel.php" class="text-decoration-none">
          <div class="card text-center p-3 shadow-sm">
            <h5>Admin Settings</h5>
          </div>
        </a>
      </div>
    </div>
  </div>

</body>
</html>
