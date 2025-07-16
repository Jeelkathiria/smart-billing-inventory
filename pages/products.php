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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name']);
  $category_id = (int) $_POST['category_id'];
  $price = (float) $_POST['price'];
  $stock = (int) $_POST['stock'];
  $gst_percent = (float) $_POST['gst'];
  $gst_amount = round($price * $gst_percent / 100, 2);
  $total_price = round($price + $gst_amount, 2);

  if (!empty($name) && $category_id && $price >= 0 && $stock >= 0 && $gst_percent >= 0) {
    // Check for duplicate product
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

// Handle Delete
if (isset($_GET['delete'])) {
  $delete_id = (int) $_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
  $stmt->bind_param("i", $delete_id);
  $stmt->execute();
}

// Fetch Products
$products = $conn->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.category_id ORDER BY p.product_id DESC");
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
  <h3 class="mb-4">🛒 Manage Products</h3>

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
</script>

</body>
</html>
