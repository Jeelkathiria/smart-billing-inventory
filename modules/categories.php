<?php
require_once '../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
    // Redirect to login page in auth folder
    header('Location: ../auth/index.php?error=Please%20login');
    exit();
}

// Store session variables for later use
$store_id = $_SESSION['store_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Handle insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['category_name']))) {
    $category_name = trim($_POST['category_name']);
    $sql = "INSERT INTO categories (category_name, store_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $category_name, $store_id);
    $stmt->execute();
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];
    $sql = "DELETE FROM categories WHERE category_id = ? AND store_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delete_id, $store_id);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Redirect with error message
        header('Location: categories.php?error=Cannot%20delete%20category%20with%20products%20assigned');
        exit();
    }
}

// Fetch categories only for current store
$sql = "SELECT * FROM categories WHERE store_id = ? ORDER BY category_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Category Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .table-hover tbody tr:hover {
      background-color: #f1f1f1;
    }
    .card {
      border-radius: 15px;
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
      margin-left: 220px;
      padding: 20px;
      padding-top: 80px; 
    }

  </style>
</head>
<body class="py-4">
 <!-- Navbar -->
  <?php include '../components/navbar.php'; ?>


  <!-- Sidebar -->
 <?php include '../components/sidebar.php'; ?>

<div class="container content">

  <h3 class="mb-4 text-center">📂 Manage Categories</h3>

  <!-- Add Category Form -->
  <form method="POST" class="row g-3 mb-4 shadow-sm p-4 bg-white rounded">
    <div class="col-md-8">
      <input type="text" name="category_name" class="form-control" placeholder="Enter Category Name" required>
    </div>
    <div class="col-md-4 d-grid">
      <button class="btn btn-primary rounded-pill px-4 py-2 d-flex align-items-center justify-content-center">
        <i class="bi bi-plus-circle me-2"></i> Add Category
      </button>
    </div>
  </form>

  <!-- Category List -->
  <div class="card shadow-sm">
    <div class="card-body">
      <?php if ($result->num_rows > 0) { ?>
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Category Name</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
              <td><?= $row['category_id'] ?></td>
              <td><?= htmlspecialchars($row['category_name']) ?></td>
              <td>
                <button onclick="confirmDelete(<?= $row['category_id'] ?>)" class="btn btn-sm btn-danger rounded-pill" title="Delete Category">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      <?php } else { ?>
        <p class="text-center text-muted">No categories added yet.</p>
      <?php } ?>
    </div>
  </div>

  <?php if (isset($_GET['error'])): ?>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'Delete Failed',
        text: decodeURIComponent("<?= $_GET['error'] ?>")
      });
    </script>
  <?php endif; ?>
</div>

<script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Are you sure?',
      text: "Category will be deleted permanently!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location = '?delete=' + id;
      }
    })
  }
</script>

</body>
</html>