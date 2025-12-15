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
        }
        header("Location: customers.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['customer_id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id=? AND store_id=?");
            $stmt->bind_param("ii", $id, $store_id);
            $stmt->execute();
        }
        header("Location: customers.php");
        exit;
    }

      if ($action === 'add') {
        if ($name) {
          $stmt = $conn->prepare("INSERT INTO customers (store_id, customer_name, customer_mobile, customer_email, customer_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
          $stmt->bind_param("issss", $store_id, $name, $mobile, $email, $address);
          $stmt->execute();
          $stmt->close();
        }
        header("Location: customers.php");
        exit;
      }
}

/* ======================================================
   2️⃣ PAGINATION SETUP
====================================================== */
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM customers WHERE store_id=?");
$countStmt->bind_param("i", $store_id);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($total / $limit);

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
  <title>Customer Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/common.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
  body {
    background: #f8f9fa;
    overflow-x: hidden;
  }

  .navbar {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  }

  .content {
    padding: 30px;
    margin-left: 230px;
    transition: margin-left 0.3s ease;
  }

  .sidebar.collapsed~.content {
    margin-left: 70px;
  }

  @media (max-width: 991px) {
    .content {
      margin-left: 0 !important;
      padding: 20px;
    }
    .search-wrapper {
      padding: 0 8px;
      max-width: calc(100% - 16px);
    }
  }

  .card {
    border: none;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    overflow: hidden;
  }

  .card-header {
    background: linear-gradient(135deg, #007bff, #6610f2);
    color: white;
    font-weight: 600;
    letter-spacing: 0.4px;
    padding: 1rem 1.5rem;
  }

  .table th {
    background-color: #f1f3f9;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
  }

  .table td {
    vertical-align: middle;
  }

  .table-hover tbody tr:hover {
    background-color: #f8f9ff;
    transition: 0.2s ease;
  }

  .search-wrapper {
    position: relative;
    margin: 20px auto;
    width: 100%;
    max-width: 1200px; /* allow the search bar to be wide on large screens */
    padding: 0 10px; /* small horizontal padding so it doesn't touch edges */
  }

  .card .card-header .btn-primary {
    border-radius: 8px;
    padding: 6px 10px;
    font-weight: 600;
  }

  .table tbody tr td {
    font-size: 0.95rem;
  }

  .page-link {
    border-radius: 12px !important;
    padding: 6px 10px;
  }

  #searchInput {
    width: 100%;
    padding: 12px 20px 12px 44px;
    border-radius: 50px;
    border: 1px solid #ced4da;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
  }

  #searchInput:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.15);
  }

  .search-icon {
    position: absolute;
    top: 50%;
    left: 15px;
    transform: translateY(-50%);
    color: #6c757d;
  }

  .pagination {
    justify-content: center;
    margin-top: 25px;
  }

  .page-link {
    border-radius: 50px !important;
    color: #007bff;
    font-weight: 500;
  }

  .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
  }

  .modal-content {
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  }

  .modal-header {
    background: linear-gradient(135deg, #007bff, #6610f2);
    color: white;
    border: none;
  }

  .btn {
    border-radius: 50px;
    padding: 6px 14px;
  }

  .btn-outline-primary:hover {
    background-color: #007bff;
    color: #fff;
  }

  .btn-outline-danger:hover {
    background-color: #dc3545;
    color: #fff;
  }

  h2.page-title {
    font-weight: 700;
    text-align: center;
    margin-bottom: 1.5rem;
    color: #333;
    letter-spacing: 0.5px;
  }

  .bi-search::before {
    content: "\f52a";
    margin-left: 1vw;
}
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="content mt-5">
    <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <span class="h5 mb-0"><i class="bi bi-people-fill me-2"></i> Customer Records</span>
              <span class="ms-3 badge bg-light text-dark" style="font-weight:600;"><i class="bi bi-database-check me-1"></i>Total: <?= $total ?></span>
            </div>
            <div>
              <button class="btn btn-sm btn-light me-2" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i></button>
              <button class="btn btn-sm btn-primary" onclick="addCustomer()"><i class="bi bi-plus-lg me-1"></i> Add Customer</button>
            </div>
          </div>

      <div class="search-wrapper">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="searchInput" placeholder="Search by Name, Mobile, or Email" class="form-control">
      </div>

      <div class="card-body table-responsive">
        <table class="table table-hover align-middle text-center">
          <thead>
            <tr>
              <th>#</th>
              <th style="min-width:220px"><i class="bi bi-person-badge me-1"></i> Name</th>
              <th><i class="bi bi-telephone me-1"></i> Mobile</th>
              <th><i class="bi bi-envelope me-1"></i> Email</th>
              <th><i class="bi bi-geo-alt me-1"></i> Address</th>
              <th><i class="bi bi-gear me-1"></i> Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($customers): ?>
            <?php
              $sr_no = $offset + 1;
              foreach ($customers as $c):
              ?>
            <tr>
              <td><?= $sr_no++ ?></td>
              <td><?= htmlspecialchars($c['customer_name'] ?: '--') ?></td>
              <td><?= htmlspecialchars($c['customer_mobile'] ?: '--') ?></td>
              <td><?= htmlspecialchars($c['customer_email'] ?: '--') ?></td>
              <td><?= htmlspecialchars($c['customer_address'] ?: '--') ?></td>
              <td>
                <div class="d-flex justify-content-center align-items-center gap-1">
                  <button class="btn btn-sm btn-outline-primary me-1" title="Edit"
                    onclick="editCustomer(<?= $c['customer_id'] ?>,'<?= addslashes($c['customer_name']) ?>','<?= addslashes($c['customer_mobile']) ?>','<?= addslashes($c['customer_email']) ?>','<?= addslashes($c['customer_address']) ?>')">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <form method="POST" style="display:inline-block;" class="delete-form" data-name="<?= htmlspecialchars($c['customer_name']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                      <i class="bi bi-trash3-fill"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="6" class="text-muted py-4"><i class="bi bi-exclamation-circle me-2"></i>No customers found.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>"><i class="bi bi-arrow-left"></i>
                Prev</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Next <i
                  class="bi bi-arrow-right"></i></a></li>
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
            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Customer</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" id="edit_action" value="edit">
            <input type="hidden" name="customer_id" id="edit_id">
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-person-fill me-1"></i>Name *</label>
              <input type="text" name="customer_name" id="edit_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-telephone-fill me-1"></i>Mobile</label>
              <input type="text" name="customer_mobile" id="edit_mobile" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-envelope-fill me-1"></i>Email</label>
              <input type="email" name="customer_email" id="edit_email" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-geo-alt-fill me-1"></i>Address</label>
              <textarea name="customer_address" id="edit_address" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i>
              Cancel</button>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save2 me-1"></i> Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Modal -->
  <div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i> Add Customer</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-person-fill me-1"></i>Name *</label>
              <input type="text" name="customer_name" id="add_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-telephone-fill me-1"></i>Mobile</label>
              <input type="text" name="customer_mobile" id="add_mobile" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-envelope-fill me-1"></i>Email</label>
              <input type="email" name="customer_email" id="add_email" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="bi bi-geo-alt-fill me-1"></i>Address</label>
              <textarea name="customer_address" id="add_address" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i>
              Cancel</button>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-plus-lg me-1"></i> Add Customer</button>
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
    document.getElementById('edit_action').value = 'edit';
    new bootstrap.Modal(document.getElementById('editModal')).show();
  }

  function addCustomer() {
    document.getElementById('add_name').value = '';
    document.getElementById('add_mobile').value = '';
    document.getElementById('add_email').value = '';
    document.getElementById('add_address').value = '';
    new bootstrap.Modal(document.getElementById('addModal')).show();
  }

  // SweetAlert2 delete confirmation for better UX
  document.querySelectorAll('.delete-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const name = form.dataset.name || 'this customer';
      Swal.fire({
        title: 'Delete Customer',
        text: `Are you sure you want to delete ${name}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

  document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      const text = row.innerText.toLowerCase();
      row.style.display = text.includes(filter) ? '' : 'none';
    });
  });
  </script>
</body>

</html>