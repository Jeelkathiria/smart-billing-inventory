<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/auth_check.php';

// ================== SESSION VALIDATION ==================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
  header('Location: ../../auth/index.php?error=Please%20login');
  exit();
}

$store_id = $_SESSION['store_id'];

// ================== ADD CATEGORY ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['category_name']))) {
  $category_name = trim($_POST['category_name']);

  // Check if category already exists
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
  $insert = $conn->prepare("INSERT INTO categories (category_name, store_id) VALUES (?, ?)");
  $insert->bind_param("si", $category_name, $store_id);
  $insert->execute();
  $insert->close();

  header("Location: categories.php?success=1");
  exit();
}

// ================== DELETE CATEGORY ==================
if (isset($_GET['delete'])) {
  $delete_id = (int) $_GET['delete'];
  $delete = $conn->prepare("DELETE FROM categories WHERE category_id = ? AND store_id = ?");
  $delete->bind_param("ii", $delete_id, $store_id);

  try {
    $delete->execute();
  } catch (mysqli_sql_exception $e) {
    header('Location: categories.php?error=Cannot%20delete%20category%20with%20products%20assigned');
    exit();
  }

  $delete->close();
  header("Location: categories.php?deleted=1");
  exit();
}

// ================== FETCH CATEGORIES ==================
$query = "
  SELECT c.category_id, c.category_name, COUNT(p.product_id) AS product_count
  FROM categories c
  LEFT JOIN products p ON p.category_id = c.category_id AND p.store_id = c.store_id
  WHERE c.store_id = ?
  GROUP BY c.category_id, c.category_name
  ORDER BY c.category_id DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$categories = $stmt->get_result();
$stmt->close();

// ================== FETCH PRODUCTS (Group by Category) ==================
$productMap = [];
$prod_stmt = $conn->prepare("SELECT product_id, product_name, category_id FROM products WHERE store_id = ? ORDER BY product_name");
$prod_stmt->bind_param("i", $store_id);
$prod_stmt->execute();
$prod_result = $prod_stmt->get_result();
while ($p = $prod_result->fetch_assoc()) {
  $productMap[$p['category_id']][] = $p;
}
$prod_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Categories</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/common.css">

  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- ======== STYLES ======== -->
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

    /* Add Category Card */
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

    /* Table Card */
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
      cursor: pointer;
    }

    .table-hover tbody tr:hover {
      background-color: #f1f3f5;
      transition: all 0.15s ease;
    }

    .btn-danger {
      border-radius: 0.5rem;
      font-size: 0.85rem;
    }

    /* Product Dropdown */
    .product-dropdown {
      background-color: #fafafa;
      border-top: 1px solid #dee2e6;
      padding: 0.5rem 1rem 1rem 1rem;
    }

    .product-dropdown ul {
      list-style-type: none;
      margin: 0;
      padding: 0;
    }

    .product-dropdown li {
      padding: 4px 0;
      color: #334155;
      font-size: 0.9rem;
    }

    .toggle-icon {
      color: #0d6efd;
      transition: transform 0.3s ease;
    }

    .rotate {
      transform: rotate(180deg);
      transition: 0.3s ease;
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <?php include '../components/navbar.php'; ?>

  <!-- Sidebar -->
  <?php include '../components/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="content mt-4">
    <h3 class="text-center mb-4"><i class="bi bi-folder2-open me-2"></i>Manage Categories</h3>

    <!-- ============ ADD CATEGORY ============ -->
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

    <!-- ============ CATEGORY TABLE ============ -->
    <div class="card card-table">
      <div class="card-body p-0">
        <?php if ($categories->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="text-center">#</th>
                  <th>Category Name</th>
                  <th class="text-center">Products</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php $i = 1; while ($row = $categories->fetch_assoc()): ?>
                  <tr data-bs-toggle="collapse" data-bs-target="#products-<?= $row['category_id'] ?>" class="category-row">
                    <td class="text-center"><?= $i++ ?></td>
                    <td>
                      <i class="bi bi-caret-down-fill toggle-icon me-2"></i>
                      <?= htmlspecialchars($row['category_name']) ?>
                    </td>
                    <td class="text-center text-secondary"><?= $row['product_count'] ?></td>
                    <td class="text-center">
                      <button onclick="event.stopPropagation(); confirmDelete(<?= $row['category_id'] ?>)"
                        class="btn btn-sm btn-danger">
                        <i class="bi bi-trash"></i> Delete
                      </button>
                    </td>
                  </tr>

                  <!-- Product List -->
                  <tr class="collapse" id="products-<?= $row['category_id'] ?>">
                    <td colspan="4" class="product-dropdown">
                      <?php if (!empty($productMap[$row['category_id']])): ?>
                        <ul>
                          <?php foreach ($productMap[$row['category_id']] as $prod): ?>
                            <li><i class="bi bi-box me-2"></i><?= htmlspecialchars($prod['product_name']) ?></li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <p class="text-muted mb-0"><i>No products in this category.</i></p>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-center text-muted py-3 mb-0">No categories added yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ============ SWEETALERT NOTIFICATIONS ============ -->
  <?php if (isset($_GET['exists'])): ?>
    <script>
      Swal.fire({ icon: 'warning', title: 'Duplicate Category', text: "Category '<?= htmlspecialchars($_GET['exists']) ?>' already exists!", confirmButtonColor: '#0d6efd' });
    </script>
  <?php elseif (isset($_GET['success'])): ?>
    <script>
      Swal.fire({ icon: 'success', title: 'Added!', text: 'Category added successfully.', timer: 1500, showConfirmButton: false });
    </script>
  <?php elseif (isset($_GET['deleted'])): ?>
    <script>
      Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Category deleted successfully.', timer: 1500, showConfirmButton: false });
    </script>
  <?php elseif (isset($_GET['error'])): ?>
    <script>
      Swal.fire({ icon: 'error', title: 'Delete Failed', text: decodeURIComponent("<?= $_GET['error'] ?>"), confirmButtonColor: '#0d6efd' });
    </script>
  <?php endif; ?>

  <!-- ============ SCRIPT ============ -->
  <script>
    // Confirm before delete
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
        if (result.isConfirmed) window.location = '?delete=' + id;
      });
    }

    // Rotate caret icon on expand/collapse
    document.addEventListener('click', function(e) {
      if (e.target.closest('.category-row')) {
        const row = e.target.closest('.category-row');
        const icon = row.querySelector('.toggle-icon');
        const collapse = document.querySelector(row.getAttribute('data-bs-target'));
        const isShown = collapse.classList.contains('show');
        document.querySelectorAll('.toggle-icon').forEach(i => i.classList.remove('rotate'));
        if (!isShown) icon.classList.add('rotate');
      }
    });
  </script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
