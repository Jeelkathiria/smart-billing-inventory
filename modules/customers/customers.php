<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$store_id = $_SESSION['store_id'] ?? 0;

/* ======================================================
   1️⃣ HANDLE EDIT / DELETE
====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['customer_name'] ?? '');
    $mobile = trim($_POST['customer_mobile'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $address = trim($_POST['customer_address'] ?? '');

    if ($action === 'edit') {
        $id = (int)($_POST['customer_id'] ?? 0);
        if ($id && $name) {
            $stmt = $conn->prepare("UPDATE customers 
                                    SET customer_name=?, customer_mobile=?, customer_email=?, customer_address=? 
                                    WHERE customer_id=? AND store_id=?");
            $stmt->bind_param("ssssii", $name, $mobile, $email, $address, $id, $store_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: customer.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['customer_id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id=? AND store_id=?");
            $stmt->bind_param("ii", $id, $store_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: customer.php");
        exit;
    }
}

/* ======================================================
   2️⃣ PAGINATION SETUP
====================================================== */
$limit = 10; // customers per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get total customers
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM customers WHERE store_id=?");
$countStmt->bind_param("i", $store_id);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($total / $limit);

// Fetch paginated data
$stmt = $conn->prepare("SELECT * FROM customers WHERE store_id=? ORDER BY created_at DESC LIMIT ?, ?");
$stmt->bind_param("iii", $store_id, $offset, $limit);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>

    body {
    background-color: #f8f9fa;
    font-family: 'Segoe UI', sans-serif;
  }

  .navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    z-index: 1030;
    background-color: #fff;
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
    background: #fff;
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


    .container {
      margin-left: 220px;
      padding: 20px;
      padding-top: 80px;
    }

    .card {
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    }

    .card-header {
      background-color: #0d6efd;
      color: #fff;
      font-weight: 600;
      font-size: 1.2rem;
      border-bottom: none;
      border-radius: 12px 12px 0 0;
    }

    .table thead th {
      background-color: #0d6efd;
      color: #fff;
      text-align: center;
    }

    .table tbody tr:hover {
      background-color: #e3f2fd;
    }

    .search-wrapper {
      position: relative;
      width: 100%;
      max-width: 72vw;
      margin: 0 auto;
    }

    #searchInput {
      width: 100%;
      border-radius: 12px;
      padding: 10px 16px 10px 40px;
      border: 1px solid #ced4da;
      font-size: 15px;
      transition: all 0.25s ease;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    }

    .search-icon {
      position: absolute;
      top: 50%;
      left: 14px;
      transform: translateY(-50%);
      color: #6c757d;
    }

    .pagination {
      justify-content: center;
      margin-top: 20px;
    }

    .page-link {
      border-radius: 50px !important;
      color: #0d6efd;
    }

    .page-item.active .page-link {
      background-color: #0d6efd;
      border-color: #0d6efd;
    }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="container">
    <h2 class="mb-4 text-center">Customer Management</h2>

    <div class="card shadow-sm rounded-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">Customers</span>
      </div>

      <!-- Search -->
      <div class="search-wrapper mb-2 mt-2">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="searchInput" class="form-control search-input"
          placeholder="Search by Name, Mobile, or Email">
      </div>

      <div class="card-body table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Mobile</th>
              <th>Email</th>
              <th>Address</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($customers): ?>
              <?php foreach ($customers as $c): ?>
                <tr>
                  <td><?= $c['customer_id'] ?></td>
                  <td><?= htmlspecialchars($c['customer_name'] ?: '--') ?></td>
                  <td><?= htmlspecialchars($c['customer_mobile'] ?: '--') ?></td>
                  <td><?= htmlspecialchars($c['customer_email'] ?: '--') ?></td>
                  <td><?= htmlspecialchars($c['customer_address'] ?: '--') ?></td>
                  <td>
                    <button class="btn btn-sm btn-primary me-1 rounded-pill"
                      onclick="editCustomer(<?= $c['customer_id'] ?>,'<?= addslashes($c['customer_name']) ?>','<?= addslashes($c['customer_mobile']) ?>','<?= addslashes($c['customer_email']) ?>','<?= addslashes($c['customer_address']) ?>')">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" style="display:inline-block;"
                      onsubmit="return confirm('Delete this customer?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger rounded-pill"><i
                          class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-4">No customers found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <nav>
            <ul class="pagination">
              <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">Prev</a></li>
              <?php endif; ?>

              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>

              <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a></li>
              <?php endif; ?>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST">
          <div class="modal-header">
            <h5 class="modal-title">Edit Customer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="customer_id" id="edit_id">
            <div class="mb-3">
              <label>Name *</label>
              <input type="text" name="customer_name" id="edit_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Mobile</label>
              <input type="text" name="customer_mobile" id="edit_mobile" class="form-control">
            </div>
            <div class="mb-3">
              <label>Email</label>
              <input type="email" name="customer_email" id="edit_email" class="form-control">
            </div>
            <div class="mb-3">
              <label>Address</label>
              <textarea name="customer_address" id="edit_address" class="form-control"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function editCustomer(id, name, mobile, email, address) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_name').value = name;
      document.getElementById('edit_mobile').value = mobile;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_address').value = address;
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
      let filter = this.value.toLowerCase();
      let rows = document.querySelectorAll('table tbody tr');
      rows.forEach(row => {
        let name = row.cells[1].textContent.toLowerCase();
        let mobile = row.cells[2].textContent.toLowerCase();
        let email = row.cells[3].textContent.toLowerCase();
        if (name.includes(filter) || mobile.includes(filter) || email.includes(filter)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  </script>
</body>

</html>



















