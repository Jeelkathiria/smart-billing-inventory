<?php
require_once '../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';

// Session check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
  header('Location: ../auth/index.php?error=Please%20login');
  exit();
}

$store_id = $_SESSION['store_id'];

/* -------------------- ADD CATEGORY -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['category_name']))) {
  $category_name = trim($_POST['category_name']);

  // Check if category already exists for this store
  $check = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ? AND store_id = ?");
  $check->bind_param("si", $category_name, $store_id);
  $check->execute();
  $exists = $check->get_result()->num_rows > 0;
  $check->close();

  if ($exists) {
    header("Location: categories.php?exists=" . urlencode($category_name));
    exit();
  }

  // Insert new category
  $sql = "INSERT INTO categories (category_name, store_id) VALUES (?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("si", $category_name, $store_id);
  $stmt->execute();
  $stmt->close();

  header("Location: categories.php?success=1");
  exit();
}

/* -------------------- DELETE CATEGORY -------------------- */
if (isset($_GET['delete'])) {
  $delete_id = (int) $_GET['delete'];
  $sql = "DELETE FROM categories WHERE category_id = ? AND store_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $delete_id, $store_id);
  try {
    $stmt->execute();
  } catch (mysqli_sql_exception $e) {
    header('Location: categories.php?error=Cannot%20delete%20category%20with%20products%20assigned');
    exit();
  }
  $stmt->close();
  header("Location: categories.php?deleted=1");
  exit();
}

/* -------------------- FETCH CATEGORIES -------------------- */
$sql = "SELECT * FROM categories WHERE store_id = ? ORDER BY category_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Categories</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/common.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      background-color: #f4f6f9;
      font-family: 'Poppins', sans-serif;
    }

    h3 {
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 2rem;
    }

    .add-category-card {
      background: #fff;
      border-radius: 0.8rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      padding: 1.5rem;
      margin-bottom: 2rem;
      transition: all 0.2s ease;
    }

    .add-category-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
    }

    .form-control {
      font-size: 0.95rem;
      border-radius: 0.5rem;
      padding: 0.6rem 1rem;
    }

    .btn-primary {
      font-size: 0.9rem;
      border-radius: 0.5rem;
      transition: all 0.2s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }

    .card-table {
      border: none;
      border-radius: 0.8rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      overflow: hidden;
    }

    .table thead {
      background-color: #e9ecef;
    }

    .table th {
      font-weight: 600;
      font-size: 0.9rem;
      color: #495057;
    }

    .table td {
      vertical-align: middle;
      font-size: 0.9rem;
    }

    .table-hover tbody tr:hover {
      background-color: #f1f3f5;
      transition: all 0.15s ease;
    }

    .btn-danger {
      border-radius: 0.5rem;
      font-size: 0.85rem;
    }

    @media (max-width: 768px) {
      .content {
        margin-left: 0;
        padding: 5vh 2vw;
      }

      .add-category-card {
        padding: 1rem;
      }

      h3 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>

<body>
  <?php include '../components/navbar.php'; ?>
  <?php include '../components/sidebar.php'; ?>

  <div class="container content mt-4">
    <h3 class="text-center mb-4">📂 Manage Categories</h3>

    <!-- Add Category -->
    <div class="add-category-card">
      <form method="POST" class="row g-3 align-items-center">
        <div class="col-md-9 col-sm-12">
          <input type="text" name="category_name" class="form-control" placeholder="Enter Category Name" required>
        </div>
        <div class="col-md-3 col-sm-12 d-grid">
          <button class="btn btn-primary d-flex align-items-center justify-content-center">
            <i class="bi bi-plus-circle me-2"></i> Add Category
          </button>
        </div>
      </form>
    </div>

    <!-- Category Table -->
    <div class="card card-table">
      <div class="card-body p-0">
        <?php if ($result->num_rows > 0): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th class="text-center">ID</th>
                <th>Category Name</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="text-center"><?= $row['category_id'] ?></td>
                <td><?= htmlspecialchars($row['category_name']) ?></td>
                <td class="text-center">
                  <button onclick="confirmDelete(<?= $row['category_id'] ?>)" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash"></i> Delete
                  </button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="text-center text-muted py-3">No categories added yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Alerts -->
  <?php if (isset($_GET['exists'])): ?>
  <script>
  Swal.fire({
    icon: 'warning',
    title: 'Duplicate Category',
    text: "Category '<?= htmlspecialchars($_GET['exists']) ?>' already exists!",
    confirmButtonColor: '#0d6efd'
  });
  </script>
  <?php elseif (isset($_GET['success'])): ?>
  <script>
  Swal.fire({
    icon: 'success',
    title: 'Added!',
    text: 'Category added successfully.',
    timer: 1500,
    showConfirmButton: false
  });
  </script>
  <?php elseif (isset($_GET['deleted'])): ?>
  <script>
  Swal.fire({
    icon: 'success',
    title: 'Deleted!',
    text: 'Category deleted successfully.',
    timer: 1500,
    showConfirmButton: false
  });
  </script>
  <?php elseif (isset($_GET['error'])): ?>
  <script>
  Swal.fire({
    icon: 'error',
    title: 'Delete Failed',
    text: decodeURIComponent("<?= $_GET['error'] ?>"),
    confirmButtonColor: '#0d6efd'
  });
  </script>
  <?php endif; ?>

  <script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Are you sure?',
      text: "This category will be deleted permanently.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location = '?delete=' + id;
      }
    });
  }
  </script>
</body>
</html>
