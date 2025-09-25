<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Ensure authentication
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    die("Unauthorized");
}

$store_id = $_SESSION['store_id'];

/* -----------------------------
   Handle Edit / Delete
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $name = trim($_POST['customer_name'] ?? '');
    $mobile = trim($_POST['customer_mobile'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $address = trim($_POST['customer_address'] ?? '');

    if ($action === 'edit') {
        $id = (int)($_POST['customer_id'] ?? 0);
        if (!$id || !$name) die("Invalid data");
        $stmt = $conn->prepare("UPDATE customers SET customer_name=?, customer_mobile=?, customer_email=?, customer_address=? WHERE customer_id=? AND store_id=?");
        $stmt->bind_param("ssssii", $name, $mobile, $email, $address, $id, $store_id);
        $stmt->execute();
        $stmt->close();
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

/* -----------------------------
   Fetch Customer List
------------------------------ */
$result = $conn->prepare("SELECT * FROM customers WHERE store_id=? ORDER BY created_at DESC");
$result->bind_param("i", $store_id);
$result->execute();
$customers = $result->get_result()->fetch_all(MYSQLI_ASSOC);
$result->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
  body {
    background-color: #f8f9fa;
  }

  .card-header {
    background-color: #007bff;
    color: #fff;
    font-weight: 500;
  }

  .table thead th {
    background-color: #343a40;
    color: #fff;
  }

  .table tbody tr:hover {
    background-color: #f1f1f1;
  }

  .btn-space {
    margin-right: 5px;
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

  .container {
    margin-left: 220px;
    padding: 20px;
    padding-top: 80px;
  }


  </style>
</head>

<body class="py-4">
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="container py-5">
    <h2 class="mb-4 text-center">Customer List</h2>

    <!-- Customer List Table -->
    <div class="card shadow-sm">
      <div class="card-header">Customers</div>
      <div class="card-body table-responsive">
        <table class="table table-bordered table-striped align-middle table-hover">
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
              <td><?= htmlspecialchars($c['customer_name']) ?></td>
              <td><?= htmlspecialchars($c['customer_mobile']) ?></td>
              <td><?= htmlspecialchars($c['customer_email']) ?></td>
              <td><?= htmlspecialchars($c['customer_address']) ?></td>
              <td>
                <button class="btn btn-sm btn-primary btn-space"
                  onclick="editCustomer(<?= $c['customer_id'] ?>,'<?= addslashes($c['customer_name']) ?>','<?= addslashes($c['customer_mobile']) ?>','<?= addslashes($c['customer_email']) ?>','<?= addslashes($c['customer_address']) ?>')">Edit</button>
                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this customer?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
              <td colspan="6" class="text-center">No customers found</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Edit Customer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  function editCustomer(id, name, mobile, email, address) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_mobile').value = mobile;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_address').value = address;
    new bootstrap.Modal(document.getElementById('editModal')).show();
  }
  </script>
</body>

</html>