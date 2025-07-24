<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

// Fetch categories for dropdown
$categories = $conn->query("SELECT * FROM categories");

// Handle Add Product with GST entered by user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id'])) {
  $name = trim($_POST['name']);
  $category_id = (int) $_POST['category_id'];
  $price = (float) $_POST['price'];
  $stock = (int) $_POST['stock'];
  $gst_percent = (float) $_POST['gst'];
  $gst_amount = round($price * $gst_percent / 100, 2);
  $total_price = round($price + $gst_amount, 2);

  if (!empty($name) && $category_id && $price >= 0 && $stock >= 0 && $gst_percent >= 0) {
    $check = $conn->prepare("SELECT * FROM products WHERE name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows === 0) {
      $stmt = $conn->prepare("INSERT INTO products (name, category_id, price, stock, gst_percent, total_price) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sididd", $name, $category_id, $price, $stock, $gst_percent, $total_price);
      $stmt->execute();
    }
  }
}

// Handle Edit Product
if (isset($_POST['edit_id'])) {
  $edit_id = (int) $_POST['edit_id'];
  $edit_name = trim($_POST['edit_name']);
  $edit_category_id = (int) $_POST['edit_category_id'];
  $edit_price = (float) $_POST['edit_price'];
  $edit_gst = (float) $_POST['edit_gst'];
  $edit_stock = (int) $_POST['edit_stock'];
  $edit_gst_amount = round($edit_price * $edit_gst / 100, 2);
  $edit_total_price = round($edit_price + $edit_gst_amount, 2);

  $stmt = $conn->prepare("UPDATE products SET name = ?, category_id = ?, price = ?, gst_percent = ?, total_price = ?, stock = ? WHERE product_id = ?");
  $stmt->bind_param("sididdi", $edit_name, $edit_category_id, $edit_price, $edit_gst, $edit_total_price, $edit_stock, $edit_id);
  $stmt->execute();
}

// Handle Delete
if (isset($_GET['delete'])) {
  $delete_id = (int) $_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
  $stmt->bind_param("i", $delete_id);
  $stmt->execute();
}

// Fetch Products
$products = $conn->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.category_id ORDER BY p.product_id DESC");

// Fetch Low Stock Products
$low_stock_result = $conn->query("SELECT * FROM products WHERE stock < 5");
$low_stock_products = $low_stock_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Product Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">

<div class="container mt-5">
  <?php include '../components/backToDashboard.php'; ?>
  <h3 class="mb-4">🛒 Manage Products</h3>


  <div class="alert alert-warning shadow-sm">
    <h5>⚠️ Low Stock Alerts</h5>
    <ul>
      <?php if (count($low_stock_products) > 0) {
        foreach ($low_stock_products as $prod) { ?>
          <li><?= htmlspecialchars($prod['name']) ?> — Only <?= $prod['stock'] ?> left!</li>
        <?php } 
      } else { ?>
        <li>All products have sufficient stock.</li>
      <?php } ?>
    </ul>
  </div>

  <!-- Add Product Form -->
  <form method="POST" class="row g-3 mb-4 shadow-sm p-3 bg-white rounded">
    <div class="col-md-2">
      <input type="text" name="name" class="form-control" placeholder="Product Name" required>
    </div>
    <div class="col-md-2">
      <select name="category_id" class="form-select" required>
        <option value="">Select Category</option>
        <?php while ($cat = $categories->fetch_assoc()) { ?>
          <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
        <?php } ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="number" name="price" step="0.01" class="form-control" placeholder="Base Price" required>
    </div>
    <div class="col-md-2">
      <input type="number" name="gst" step="0.01" class="form-control" placeholder="GST (%)" required>
    </div>
    <div class="col-md-2">
      <input type="number" name="stock" class="form-control" placeholder="Stock" required>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Add Product</button>
    </div>
  </form>

  <!-- Product List -->
  <div class="card shadow-sm">
    <div class="card-body">
      <?php if ($products->num_rows > 0) { ?>
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Product Name</th>
              <th>Category</th>
              <th>Base Price</th>
              <th>GST (%)</th>
              <th>Total Price</th>
              <th>Stock</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($prod = $products->fetch_assoc()) { ?>
            <tr>
              <td><?= $prod['product_id'] ?></td>
              <td><?= htmlspecialchars($prod['name']) ?></td>
              <td><?= htmlspecialchars($prod['category_name']) ?></td>
              <td>₹<?= number_format($prod['price'], 2) ?></td>
              <td><?= number_format($prod['gst_percent'], 2) ?>%</td>
              <td>₹<?= number_format($prod['total_price'], 2) ?></td>
              <td><?= $prod['stock'] ?></td>
              <td>
                <button onclick='editProduct(<?= json_encode($prod) ?>)' class="btn btn-sm btn-warning">Edit</button>
                <button onclick="confirmDelete(<?= $prod['product_id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      <?php } else { ?>
        <p class="text-center text-muted">No products added yet.</p>
      <?php } ?>
    </div>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="editForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="mb-2">
          <input type="text" name="edit_name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <select name="edit_category_id" id="edit_category_id" class="form-select" required>
            <?php
              $categories->data_seek(0);
              while ($cat = $categories->fetch_assoc()) { ?>
              <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="mb-2">
          <input type="number" step="0.01" name="edit_price" id="edit_price" class="form-control" required>
        </div>
        <div class="mb-2">
          <input type="number" step="0.01" name="edit_gst" id="edit_gst" class="form-control" required>
        </div>
        <div class="mb-2">
          <input type="number" name="edit_stock" id="edit_stock" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Update Product</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Are you sure?',
      text: "Product will be deleted permanently!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location = '?delete=' + id;
      }
    })
  }

  let editModal;
  document.addEventListener('DOMContentLoaded', function () {
    editModal = new bootstrap.Modal(document.getElementById('editModal'));
  });

  function editProduct(product) {
    document.getElementById('edit_id').value = product.product_id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_category_id').value = product.category_id;
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_gst').value = product.gst_percent;
    document.getElementById('edit_stock').value = product.stock;
    if (typeof editModal === 'undefined') {
      editModal = new bootstrap.Modal(document.getElementById('editModal'));
    }
    editModal.show();
  }
</script>
</body>
</html>
