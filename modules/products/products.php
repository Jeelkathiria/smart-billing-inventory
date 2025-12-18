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
$barcode = trim($_POST['barcode'] ?? '');

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
            (product_name, category_id, purchase_price, sell_price, gst_percent, total_price, profit, stock, store_id, barcode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("siddiddiss", $name, $category_id, $purchase_price, $sell_price, $gst_percent, $total_price, $profit, $stock, $store_id, $barcode);

            $stmt->execute();
        }
    }
}

/* ======================================
   Barode Update Prodcuct
====================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id'])) {
    $barcode = trim($_POST['barcode'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $sell_price = (float)($_POST['sell_price'] ?? 0);
    $gst_percent = (float)($_POST['gst'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($barcode && $name && $category_id && $sell_price >= 0 && $purchase_price >= 0 && $gst_percent >= 0 && $stock >= 0) {
        $gst_amount = round($sell_price * $gst_percent / 100, 2);
        $total_price = round($sell_price + $gst_amount, 2);
        $profit = round($sell_price - $purchase_price, 2);

        // Check if product with barcode already exists
        $check = $conn->prepare("SELECT * FROM products WHERE barcode = ? AND store_id = ?");
        $check->bind_param("si", $barcode, $store_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            // Update existing product
            $stmt = $conn->prepare("
                UPDATE products 
                SET product_name=?, category_id=?, purchase_price=?, sell_price=?, gst_percent=?, total_price=?, profit=?, stock=?
                WHERE barcode=? AND store_id=?
            ");
            $stmt->bind_param(
                "sididdiisi",
                $name, $category_id, $purchase_price, $sell_price, $gst_percent, $total_price, $profit, $stock, $barcode, $store_id
            );
            $stmt->execute();
        } else {
            // Insert new product
            $stmt = $conn->prepare("
                INSERT INTO products 
                (barcode, product_name, category_id, purchase_price, sell_price, gst_percent, total_price, profit, stock, store_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssiddiddii",
                $barcode, $name, $category_id, $purchase_price, $sell_price, $gst_percent, $total_price, $profit, $stock, $store_id
            );
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
   DELETE PRODUCT (preserve historical sale_items)
   - copy product_name into sale_items.product_name (if empty)
   - set sale_items.product_id = NULL
   - then delete product row
====================================== */
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    // Start transaction for safety
    $conn->begin_transaction();
    try {
        // Fetch product name (fallback to empty string)
        $pstmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ? AND store_id = ? LIMIT 1");
        $pstmt->bind_param("ii", $delete_id, $store_id);
        $pstmt->execute();
        $prow = $pstmt->get_result()->fetch_assoc();
        $pname = $prow['product_name'] ?? '';
        $pstmt->close();

        // If sale_items has a product_name column, populate it for referenced rows where empty
        $col_stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'product_name'");
        if ($col_stmt) {
            $col_stmt->execute();
            $col_stmt->bind_result($col_count);
            $col_stmt->fetch();
            $col_stmt->close();
            if ($col_count > 0 && $pname !== '') {
                $update_name = $conn->prepare("UPDATE sale_items SET product_name = ? WHERE product_id = ? AND store_id = ? AND (product_name IS NULL OR product_name = '')");
                $update_name->bind_param("sii", $pname, $delete_id, $store_id);
                $update_name->execute();
                $update_name->close();
            }
        }

        // Set product_id to NULL on sale_items to preserve historical rows
        $update_null = $conn->prepare("UPDATE sale_items SET product_id = NULL WHERE product_id = ? AND store_id = ?");
        $update_null->bind_param("ii", $delete_id, $store_id);
        $update_null->execute();
        $update_null->close();

        // Finally delete the product
        $del = $conn->prepare("DELETE FROM products WHERE product_id = ? AND store_id = ?");
        $del->bind_param("ii", $delete_id, $store_id);
        $del->execute();
        $del->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        // Optionally surface error to UI via GET param or log; keep behavior silent here
    }
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/common.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
  body {
    background-color: #f8f9fb;
    overflow-x: hidden;
  }

  /* Consistent layout */
  .content {
    margin-left: 230px;
    padding: 80px 25px;
    transition: margin-left 0.3s ease;
  }

  @media (max-width: 992px) {
    .content {
      margin-left: 0;
      padding: 70px 15px;
    }
  }

  .card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  }

  .table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    white-space: nowrap;
  }

  .table td {
    vertical-align: middle;
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

  /* Fix for sidebar collapse */
  .sidebar.collapsed+.content {
    margin-left: 70px !important;
  }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../../components/navbar.php'; ?>
  <?php include __DIR__ . '/../../components/sidebar.php'; ?>

  <div class="content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <h3 class="fw-bold text-primary"><i class="bi bi-box-seam-fill me-2"></i>Product Management</h3>
      <div class="d-flex gap-2 flex-wrap">
        <input type="text" id="searchInput" class="form-control search-box" placeholder="Search by Name..">
      </div>
    </div>

    <?php if (count($low_stock_products) > 0): ?>
    <div class="alert alert-warning shadow-sm">
      <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Low Stock Alert</strong>
      <ul class="mt-2 mb-0">
        <?php foreach ($low_stock_products as $prod): ?>
        <li><i class="bi bi-caret-right-fill text-danger"></i>
          <?= htmlspecialchars($prod['product_name']) ?> — Only <?= $prod['stock'] ?> left!
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#addProductForm">
      <i class="bi bi-plus-circle me-1"></i> Add Product
    </button>

    <div id="addProductForm" class="collapse mb-4">
      <form id="productForm" class="row g-3 shadow-sm p-4 bg-white rounded"
      method="POST" action="products.php" novalidate>

        <!-- Barcode Input -->
        <div class="col-md-3">
          <input type="text" name="barcode" id="barcode_input" class="form-control" placeholder="Scan / Enter Barcode"
            autofocus>
          <small id="barcodeMsg" class="text-danger"></small>
        </div>

        <!-- Product Name -->
        <div class="col-md-3">
          <input type="text" name="name" id="product_name" class="form-control" placeholder="Product Name">
          <small id="nameMsg" class="text-danger"></small>
        </div>

        <!-- Category -->
        <div class="col-md-2">
          <select name="category_id" id="category_id" class="form-select">
            <option value="">Select Category</option>
            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endwhile; ?>
          </select>
          <small id="categoryMsg" class="text-danger"></small>
        </div>

        <!-- Purchase Price -->
        <div class="col-md-2">
          <input type="number" name="purchase_price" id="purchase_price" step="0.01" class="form-control"
            placeholder="Purchase Price">
          <small id="purchaseMsg" class="text-danger"></small>
        </div>

        <!-- Sell Price -->
        <div class="col-md-2">
          <input type="number" name="sell_price" id="sell_price" step="0.01" class="form-control"
            placeholder="Sell Price">
          <small id="sellMsg" class="text-danger"></small>
        </div>

        <!-- GST -->
        <div class="col-md-1">
          <input type="number" name="gst" id="gst_percent" step="0.01" class="form-control" placeholder="GST (%)">
          <small id="gstMsg" class="text-danger"></small>
        </div>

        <!-- Stock -->
        <div class="col-md-1">
          <input type="number" name="stock" id="stock" class="form-control" placeholder="Stock">
          <small id="stockMsg" class="text-danger"></small>
        </div>

        <!-- Add Button -->
        <div class="col-md-1 d-grid">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Add</button>
        </div>
      </form>
    </div>


    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <?php if ($products->num_rows > 0): ?>
        <table class="table align-middle table-hover" id="productTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Category</th>
              <th>Purchase</th>
              <th>Sell</th>
              <th>GST</th>
              <th>Total</th>
              <th>Profit</th>
              <th>Stock</th>
              <th>Actions</th>
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
                <button onclick='editProduct(<?= json_encode($prod) ?>)' class="btn btn-sm btn-warning me-1"><i
                    class="bi bi-pencil-square"></i></button>
                <button onclick="confirmDelete(<?= $prod['product_id'] ?>)" class="btn btn-sm btn-danger"><i
                    class="bi bi-trash3-fill"></i></button>
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

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" id="editForm" class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Edit Product</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">
          <div class="mb-3"><label class="form-label">Product Name</label><input type="text" name="edit_name"
              id="edit_name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Category</label>
            <select name="edit_category_id" id="edit_category_id" class="form-select" required>
              <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
              <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Purchase Price</label><input type="number" step="0.01"
              name="edit_purchase_price" id="edit_purchase_price" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Sell Price</label><input type="number" step="0.01"
              name="edit_sell_price" id="edit_sell_price" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">GST (%)</label><input type="number" step="0.01" name="edit_gst"
              id="edit_gst" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Stock</label><input type="number" name="edit_stock"
              id="edit_stock" class="form-control" min="0" required></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Update</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script>
  const confirmDelete = (id) => {
    Swal.fire({
      title: 'Delete this product?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then(res => {
      if (res.isConfirmed) window.location = '?delete=' + id;
    });
  };

  // Form validation
  const form = document.getElementById('productForm');

  form.addEventListener('submit', function(e) {
    e.preventDefault(); // prevent default submit

    let isValid = true;

    // Helper function to check numeric > 0
    function checkNumeric(id, msgId) {
      const value = parseFloat(document.getElementById(id).value);
      if (isNaN(value) || value <= 0) {
        document.getElementById(msgId).textContent = "Value must be greater than 0";
        return false;
      } else {
        document.getElementById(msgId).textContent = "";
        return true;
      }
    }

    // Validate each field
    const barcode = document.getElementById('barcode_input').value.trim();
    if (!barcode) {
      document.getElementById('barcodeMsg').textContent = "Barcode is required";
      isValid = false;
    } else document.getElementById('barcodeMsg').textContent = "";

    const name = document.getElementById('product_name').value.trim();
    if (!name) {
      document.getElementById('nameMsg').textContent = "Product Name is required";
      isValid = false;
    } else document.getElementById('nameMsg').textContent = "";

    const category = document.getElementById('category_id').value;
    if (!category) {
      document.getElementById('categoryMsg').textContent = "Select a category";
      isValid = false;
    } else document.getElementById('categoryMsg').textContent = "";

    const purchaseOk = checkNumeric('purchase_price', 'purchaseMsg');
    const sellOk = checkNumeric('sell_price', 'sellMsg');
    const gstOk = checkNumeric('gst_percent', 'gstMsg');
    const stockOk = checkNumeric('stock', 'stockMsg');

    isValid = isValid && purchaseOk && sellOk && gstOk && stockOk;

    if (isValid) {
      console.log("Form valid, ready to submit.");
      form.submit(); // Uncomment to submit normally
    } else {
      console.log("Form validation failed.");
    }
  });

  let editModal;

  document.addEventListener('DOMContentLoaded', () => {
    // Initialize edit modal
    editModal = new bootstrap.Modal(document.getElementById('editModal'));

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#productTable tbody tr');
    searchInput.addEventListener('keyup', function() {
      const query = this.value.toLowerCase();
      tableRows.forEach(row => {
        const name = row.children[1].textContent.toLowerCase();
        row.style.display = name.includes(query) ? '' : 'none';
      });
    });

    // Barcode prefill
    const barcodeInput = document.getElementById('barcode_input');

    barcodeInput.addEventListener('input', function() {
      let barcode = this.value.trim();
      if (!barcode) return;

      fetch('check_barcode.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'barcode=' + encodeURIComponent(barcode)
        })
        .then(res => res.json())
        .then(data => {
          if (data.exists) {
            document.getElementById('product_name').value = data.product_name;
            document.getElementById('category_id').value = data.category_id;
            document.getElementById('purchase_price').value = data.purchase_price;
            document.getElementById('sell_price').value = data.sell_price;
            document.getElementById('gst_percent').value = data.gst_percent;
            document.getElementById('stock').value = data.stock;
            document.getElementById('barcodeMsg').textContent = "Product exists, editing!";
          } else {
            document.getElementById('product_name').value = '';
            document.getElementById('category_id').value = '';
            document.getElementById('purchase_price').value = '';
            document.getElementById('sell_price').value = '';
            document.getElementById('gst_percent').value = '';
            document.getElementById('stock').value = '';
            document.getElementById('barcodeMsg').textContent = '';
          }
        });
    });
  });

  // Edit product modal
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