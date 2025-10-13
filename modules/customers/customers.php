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
        header("Location: customer.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['customer_id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id=? AND store_id=?");
            $stmt->bind_param("ii", $id, $store_id);
            $stmt->execute();
        }
        header("Location: customer.php");
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/common.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
  .card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
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
    background-color: #f0f3fa;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
  }

  .table td {
    vertical-align: middle;
  }

  .table-hover tbody tr:hover {
    background-color: #f8f9ff;
  }

  .search-wrapper {
    position: relative;
    margin: 15px auto;
    max-width: 700px;
  }

  #searchInput {
    width: 100%;
    padding: 12px 16px 12px 40px;
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

  /* Modal */
  .modal-content {
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  }

  .modal-header {
    background: linear-gradient(135deg, #007bff, #6610f2);
    color: white;
    border: none;
  }

  .btn-primary {
    border-radius: 50px;
    padding: 8px 18px;
  }

  .btn-danger {
    border-radius: 50px;
  }

  h2.page-title {
    font-weight: 700;
    text-align: center;
    margin-bottom: 1.5rem;
    color: #333;
    letter-spacing: 0.5px;
  }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="content">

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people-fill me-2"></i> Customer Records</span>
      </div>

      <div class="search-wrapper">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="searchInput" placeholder="Search by Name, Mobile, or Email" class="form-control">
      </div>

      <div class="card-body table-responsive">
        <table class="table table-hover align-middle text-center">
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
                <button class="btn btn-sm btn-outline-primary me-1"
                  onclick="editCustomer(<?= $c['customer_id'] ?>,'<?= addslashes($c['customer_name']) ?>','<?= addslashes($c['customer_mobile']) ?>','<?= addslashes($c['customer_email']) ?>','<?= addslashes($c['customer_address']) ?>')">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this customer?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="6" class="text-muted py-4">No customers found.</td>
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
            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Customer</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="customer_id" id="edit_id">
            <div class="mb-3">
              <label class="form-label">Name *</label>
              <input type="text" name="customer_name" id="edit_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Mobile</label>
              <input type="text" name="customer_mobile" id="edit_mobile" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="customer_email" id="edit_email" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Address</label>
              <textarea name="customer_address" id="edit_address" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
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