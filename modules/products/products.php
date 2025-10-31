<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$store_id = $_SESSION['store_id'] ?? 0;

/* ======================================
   FETCH CATEGORIES
====================================== */
$cat_stmt = $conn->prepare("SELECT category_id, category_name FROM categories WHERE store_id = ?");
$cat_stmt->bind_param("i", $store_id);
$cat_stmt->execute();
$categories = $cat_stmt->get_result();

/* ======================================
   ADD PRODUCT
====================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id'])) {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $sell_price = (float)($_POST['sell_price'] ?? 0);
    $gst_percent = (float)($_POST['gst'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($name && $category_id && $sell_price >= 0 && $purchase_price >= 0 && $gst_percent >= 0 && $stock >= 0) {
        $gst_amount = round($sell_price * $gst_percent / 100, 2);
        $total_price = round($sell_price + $gst_amount, 2);
        $profit = round($sell_price - $purchase_price, 2);

        $check = $conn->prepare("SELECT 1 FROM products WHERE product_name = ? AND store_id = ?");
        $check->bind_param("si", $name, $store_id);
        $check->execute();

        if ($check->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO products 
                (product_name, category_id, purchase_price, sell_price, gst_percent, total_price, profit, stock, store_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("siddiddii", $name, $category_id, $purchase_price, $sell_price, $gst_percent, $total_price, $profit, $stock, $store_id);
            $stmt->execute();
        }
    }
}

/* ======================================
   EDIT PRODUCT
====================================== */
if (isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $edit_name = trim($_POST['edit_name']);
    $edit_category_id = (int)$_POST['edit_category_id'];
    $edit_purchase_price = (float)$_POST['edit_purchase_price'];
    $edit_sell_price = (float)$_POST['edit_sell_price'];
    $edit_gst = (float)$_POST['edit_gst'];
    $edit_stock = (int)$_POST['edit_stock'];

    $edit_total_price = round($edit_sell_price + ($edit_sell_price * $edit_gst / 100), 2);
    $edit_profit = round($edit_sell_price - $edit_purchase_price, 2);

    $stmt = $conn->prepare("
        UPDATE products 
        SET product_name = ?, category_id = ?, purchase_price = ?, sell_price = ?, gst_percent = ?, total_price = ?, stock = ?, profit = ?
        WHERE product_id = ? AND store_id = ?
    ");
    $stmt->bind_param("sididdiiii", $edit_name, $edit_category_id, $edit_purchase_price, $edit_sell_price, $edit_gst, $edit_total_price, $edit_stock, $edit_profit, $edit_id, $store_id);
    $stmt->execute();
}

/* ======================================
   DELETE PRODUCT
====================================== */
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $delete_id, $store_id);
    $stmt->execute();
}

/* ======================================
   FETCH PRODUCTS
====================================== */
$products_stmt = $conn->prepare("
    SELECT p.*, c.category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.store_id = ?
    ORDER BY p.product_id DESC
");
$products_stmt->bind_param("i", $store_id);
$products_stmt->execute();
$products = $products_stmt->get_result();

/* ======================================
   LOW STOCK ALERT
====================================== */
$low_stmt = $conn->prepare("SELECT product_name, stock FROM products WHERE stock < 5 AND store_id = ?");
$low_stmt->bind_param("i", $store_id);
$low_stmt->execute();
$low_stock_products = $low_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Product Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/common.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
  body {
    background-color: #f8f9fb;
  }

  .content {
    margin-left: 230px;
    padding: 80px 30px;
  }

  .card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  }

  .table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
  }

  .btn {
    border-radius: 6px;
  }

  .low-stock-row {
    background-color: #fff5f5 !important;
  }

  .low-stock-row td {
    color: #c62828 !important;
    font-weight: 600;
  }

  .search-box {
    max-width: 300px;
  }

  .form-control,
  .form-select {
    border-radius: 8px;
  }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../../components/navbar.php'; ?>
  <?php include __DIR__ . '/../../components/sidebar.php'; ?>

  <div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold text-primary"><i class="bi bi-box-seam-fill me-2"></i>Product Management</h3>
      <div class="d-flex gap-2">
        <input type="text" id="searchInput" class="form-control search-box" placeholder="🔍 Search product...">
        <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#addProductForm"
          title="Add New Product">
          <i class="bi bi-plus-circle me-1"></i> Add Product
        </button>
      </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if (count($low_stock_products) > 0): ?>
    <div class="alert alert-warning shadow-sm">
      <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Low Stock Alert</strong>
      <ul class="mt-2 mb-0">
        <?php foreach ($low_stock_products as $prod): ?>
        <li><i class="bi bi-caret-right-fill text-danger"></i>
          <?= htmlspecialchars($prod['product_name']) ?> — Only <?= $prod['stock'] ?> left!</li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Add Product Form -->
    <div id="addProductForm" class="collapse mb-4">
      <form method="POST" class="row g-3 shadow-sm p-4 bg-white rounded">
        <div class="col-md-3">
          <input type="text" name="name" class="form-control" placeholder="Product Name" required>
        </div>
        <div class="col-md-2">
          <select name="category_id" class="form-select" required>
            <option value="">Select Category</option>
            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="number" name="purchase_price" step="0.01" class="form-control" placeholder="Purchase Price"
            required>
        </div>
        <div class="col-md-2">
          <input type="number" name="sell_price" step="0.01" class="form-control" placeholder="Sell Price" required>
        </div>
        <div class="col-md-1">
          <input type="number" name="gst" step="0.01" class="form-control" placeholder="GST (%)" required>
        </div>
        <div class="col-md-1">
          <input type="number" name="stock" min="0" class="form-control" placeholder="Stock" required>
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Add</button>
        </div>
      </form>
    </div>

    <!-- Product List -->
    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <?php if ($products->num_rows > 0): ?>
        <table class="table align-middle table-hover" id="productTable">
          <thead>
            <tr>
              <th><i class="bi bi-hash"></i></th>
              <th><i class="bi bi-tag"></i> Name</th>
              <th><i class="bi bi-list-ul"></i> Category</th>
              <th><i class="bi bi-cart"></i> Purchase</th>
              <th><i class="bi bi-currency-rupee"></i> Sell</th>
              <th><i class="bi bi-percent"></i> GST</th>
              <th><i class="bi bi-cash-stack"></i> Total</th>
              <th><i class="bi bi-graph-up-arrow"></i> Profit</th>
              <th><i class="bi bi-box2"></i> Stock</th>
              <th><i class="bi bi-gear"></i> Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($prod = $products->fetch_assoc()): ?>
            <tr class="<?= $prod['stock'] < 5 ? 'low-stock-row' : '' ?>">
              <td><?= $prod['product_id'] ?></td>
              <td><?= htmlspecialchars($prod['product_name']) ?></td>
              <td><?= htmlspecialchars($prod['category_name']) ?></td>
              <td>₹<?= number_format($prod['purchase_price'], 2) ?></td>
              <td>₹<?= number_format($prod['sell_price'], 2) ?></td>
              <td><?= $prod['gst_percent'] ?>%</td>
              <td>₹<?= number_format($prod['total_price'], 2) ?></td>
              <td>₹<?= number_format($prod['profit'], 2) ?></td>
              <td><?= $prod['stock'] ?></td>
              <td>
                <button onclick='editProduct(<?= json_encode($prod) ?>)' class="btn btn-sm btn-warning me-1"
                  title="Edit Product">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <button onclick="confirmDelete(<?= $prod['product_id'] ?>)" class="btn btn-sm btn-danger"
                  title="Delete Product">
                  <i class="bi bi-trash3-fill"></i>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p class="text-center text-muted"><i class="bi bi-inbox me-1"></i>No products added yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Edit Product Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" id="editForm" class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Edit Product</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">
          <div class="mb-3">
            <label class="form-label">Product Name</label>
            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="edit_category_id" id="edit_category_id" class="form-select" required>
              <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
              <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Purchase Price</label>
            <input type="number" step="0.01" name="edit_purchase_price" id="edit_purchase_price" class="form-control"
              required>
          </div>
          <div class="mb-3">
            <label class="form-label">Sell Price</label>
            <input type="number" step="0.01" name="edit_sell_price" id="edit_sell_price" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">GST (%)</label>
            <input type="number" step="0.01" name="edit_gst" id="edit_gst" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Stock</label>
            <input type="number" name="edit_stock" id="edit_stock" class="form-control" min="0" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Update</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Delete this product?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((res) => {
      if (res.isConfirmed) window.location = '?delete=' + id;
    });
  }

  let editModal;
  document.addEventListener('DOMContentLoaded', () => {
    editModal = new bootstrap.Modal(document.getElementById('editModal'));

    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#productTable tbody tr');
    searchInput.addEventListener('keyup', function() {
      const query = this.value.toLowerCase();
      tableRows.forEach(row => {
        const name = row.children[1].textContent.toLowerCase();
        row.style.display = name.includes(query) ? '' : 'none';
      });
    });
  });

  function editProduct(product) {
    document.getElementById('edit_id').value = product.product_id;
    document.getElementById('edit_name').value = product.product_name;
    document.getElementById('edit_category_id').value = product.category_id;
    document.getElementById('edit_purchase_price').value = product.purchase_price;
    document.getElementById('edit_sell_price').value = product.sell_price;
    document.getElementById('edit_gst').value = product.gst_percent;
    document.getElementById('edit_stock').value = product.stock;
    editModal.show();
  }
  </script>
</body>

</html>