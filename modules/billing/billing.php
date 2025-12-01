<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$store_id = $_SESSION['store_id'] ?? 0;

// Fetch store info
$stmt = $conn->prepare("SELECT store_type, billing_fields FROM stores WHERE store_id = ?");
$stmt->bind_param('i', $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc() ?: ['store_type' => '', 'billing_fields' => '{}'];

$fields = [];
if (!empty($store['billing_fields'])) {
    $decoded = json_decode($store['billing_fields'], true);
    if (is_array($decoded)) $fields = $decoded;
}

// Fetch categories
$catStmt = $conn->prepare("SELECT category_id, category_name FROM categories WHERE store_id = ?");
$catStmt->bind_param('i', $store_id);
$catStmt->execute();
$categories = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Billing field labels
$labels = [
    'customer_name' => 'Customer Name',
    'customer_email' => 'Email',
    'customer_mobile' => 'Mobile',
    'customer_address' => 'Delivery Address',
    'gstin' => 'GSTIN'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>BillMitra - Live Billing</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/common.css">

  <style>
  /* ---------------- Global Layout ---------------- */
  body {
    background: #f8f9fa;
    overflow-x: hidden;
  }

  .content {
    padding: 30px;
    margin-left: 230px;
    transition: margin-left 0.3s ease;
  }

  /* When sidebar collapsed */
  .sidebar.collapsed~.content {
    margin-left: 70px;
  }

  @media (max-width: 991px) {
    .content {
      margin-left: 0 !important;
      padding: 20px;
    }
  }

  h3 {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  /* ---------------- Form Styling ---------------- */
  label {
    font-weight: 500;
    margin-bottom: 4px;
    color: #333;
  }

  .form-control,
  .form-select,
  textarea {
    border-radius: 8px;
    border: 1px solid #ced4da;
    font-size: 0.95rem;
  }

  /* ---------------- Table ---------------- */
  table.table {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  }

  table thead th {
    background: #f1f3f6;
    font-weight: 600;
    color: #333;
  }

  table tbody td {
    vertical-align: middle;
  }

  /* ---------------- Totals Section ---------------- */
  .totals-section {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    margin-top: 20px;
  }

  .totals-section p,
  .totals-section h5 {
    margin-bottom: 6px;
  }

  .totals-section h5 {
    color: #0d6efd;
  }

  /* ---------------- Buttons ---------------- */
  .btn {
    border-radius: 8px;
    font-weight: 500;
    letter-spacing: 0.3px;
  }

  /* ---------------- Toast ---------------- */
  #successToast {
    animation: fadeInOut 2s ease-in-out;
  }

  @keyframes fadeInOut {
    0% {
      opacity: 0;
      transform: translateY(-10px);
    }

    10%,
    90% {
      opacity: 1;
      transform: translateY(0);
    }

    100% {
      opacity: 0;
      transform: translateY(-10px);
    }
  }

  /* ---------------- Input Error ---------------- */
  .input-error {
    border-color: #dc3545 !important;
    animation: shake 0.3s;
  }

  @keyframes shake {
    25% {
      transform: translateX(-3px);
    }

    50% {
      transform: translateX(3px);
    }

    75% {
      transform: translateX(-3px);
    }
  }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="content mt-5">
    <h3><i class="bi bi-receipt"></i> Billing</h3>

    <!-- Customer Details -->
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label><i class="bi bi-calendar3"></i> Date & Time</label>
            <input type="text" id="invoice_date" class="form-control" readonly>
          </div>

          <?php foreach ($fields as $key => $enabled):
              if (!$enabled) continue;
              $label = $labels[$key] ?? ucfirst($key);
          ?>
          <div class="col-md-<?php echo $key === 'customer_address' ? 8 : 4; ?>">
            <label><?= $label ?></label>
            <?php if ($key === 'customer_address'): ?>
            <textarea id="<?= $key ?>" class="form-control" rows="2" placeholder="<?= $label ?>"></textarea>
            <?php else: ?>
            <input type="text" id="<?= $key ?>" class="form-control" placeholder="<?= $label ?>">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Category / Product -->
    <div class="card mt-4 shadow-sm border-0">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label><i class="bi bi-tag"></i> Category</label>
            <select id="categorySelect" class="form-select">
              <option value="">Select Category</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label><i class="bi bi-box-seam"></i> Product</label>
            <select id="productSelect" class="form-select" disabled>
              <option value="">Select Product</option>
            </select>
          </div>

          <div class="col-md-2">
            <label>Qty</label>
            <input type="number" id="qtyInput" class="form-control" value="1" min="1">
          </div>

          <div class="col-md-2">
            <button id="addProductBtn" class="btn btn-success w-100" disabled>
              <i class="bi bi-cart-plus"></i> Add
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cart Table -->
    <div class="table-responsive mt-4">
      <table class="table table-bordered align-middle" id="billingTable">
        <thead>
          <tr>
            <th>Sr. No</th>
            <th>Product</th>
            <th>Qty</th>
            <th>Amount</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <!-- Totals -->
    <div class="totals-section text-end">
      <p><strong>Subtotal:</strong> ₹<span id="subTotal">0.00</span></p>
      <p><strong>Tax:</strong> ₹<span id="taxAmount">0.00</span></p>
      <h5><strong>Total:</strong> ₹<span id="totalAmount">0.00</span></h5>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <button class="btn btn-secondary" id="saveOnlyBtn" data-bs-toggle="tooltip" data-bs-trigger="hover"
          data-bs-placement="top" title="Invoice will NOT be printed. It will only be saved in the database.">
          <i class="bi bi-save2"></i> Save Only
        </button>

        <button class="btn btn-primary" id="generateInvoiceBtn" data-bs-toggle="tooltip" data-bs-trigger="hover"
          data-bs-placement="top" title="Invoice will be saved AND a printable PDF/receipt will be generated.">
          <i class="bi bi-file-earmark-text"></i> Generate Invoice
        </button>

      </div>

    </div>
  </div>
  </div>

  <!-- Success Toast -->
  <div id="successToast" class="position-fixed top-0 end-0 bg-success text-white p-3 rounded shadow"
    style="margin:20px;display:none;z-index:9999;">
    <i class="bi bi-check-circle"></i> Billing Successful!
  </div>

  <!-- Alert Modal -->
  <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-info-circle"></i> Notice</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body fs-5" id="alertModalBody">...</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>


  <!-- ✅ Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- ================= JS ================= -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);
    let cart = [];
    let productsList = {};
    const enabledFields = <?= json_encode(array_keys(array_filter($fields))) ?>;
    const labels = <?= json_encode($labels) ?>;

    function showPopup(message, title = "Notice") {
      const modalEl = document.getElementById('alertModal');
      if (!modalEl) return alert(message);
      modalEl.querySelector('#alertModalLabel').textContent = title;
      modalEl.querySelector('#alertModalBody').textContent = message;
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }

    function showToast(id, duration = 2000) {
      const t = $(id);
      if (!t) return;
      t.style.display = 'block';
      setTimeout(() => t.style.display = 'none', duration);
    }

    const pad = n => n.toString().padStart(2, '0');
    const getDateTime = () => {
      const d = new Date();
      return `${pad(d.getDate())}-${pad(d.getMonth()+1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };
    $('invoice_date').value = getDateTime();

    const resetForm = () => {
      cart = [];
      renderTable();
      $('invoice_date').value = getDateTime();
      $('categorySelect').value = '';
      $('productSelect').innerHTML = '<option value="">Select Product</option>';
      $('productSelect').disabled = true;
      $('qtyInput').value = 1;
      if ($('barcodeInput')) $('barcodeInput').value = '';
      $('addProductBtn').disabled = true;
      enabledFields.forEach(k => {
        if ($(k)) $(k).value = '';
      });
    };

    // Fetch Products
    $('categorySelect').addEventListener('change', async () => {
      const catId = $('categorySelect').value;
      const productSelect = $('productSelect');
      productSelect.disabled = true;
      productSelect.innerHTML = '<option>Loading...</option>';
      $('addProductBtn').disabled = true;
      productsList = {};

      if (!catId) {
        productSelect.innerHTML = '<option value="">Select Product</option>';
        return;
      }

      try {
        const res = await fetch(`../sales/fetch_products.php?category_id=${catId}`);
        const products = await res.json();
        productSelect.innerHTML = '<option value="">Select Product</option>';
        if (Array.isArray(products) && products.length) {
          products.forEach(p => {
            productsList[p.product_id] = p;
            const stock = parseInt(p.stock) || 0;
            productSelect.innerHTML +=
              `<option value="${p.product_id}" ${stock===0?'disabled':''}>${p.product_name}${stock===0?' (Out of Stock)':''}</option>`;
          });
        } else productSelect.innerHTML += '<option disabled>No products available</option>';
        productSelect.disabled = false;
      } catch (e) {
        console.error(e);
        productSelect.innerHTML = '<option disabled>Error loading</option>';
      }
    });

    // Product selection
    $('productSelect').addEventListener('change', async () => {
      const productId = $('productSelect').value;
      const addBtn = $('addProductBtn');
      addBtn.disabled = true;

      if (!productId) return;

      try {
        const res = await fetch(`../sales/fetch_price.php?product_id=${productId}`);
        const data = await res.json();

        if (data.status === 'success' && data.product) {
          const p = data.product;
          productsList[productId] = {
            ...productsList[productId],
            sell_price: p.sell_price,
            gst_percent: p.gst_percent,
            profit: p.profit,
            stock: p.stock
          };
          addBtn.disabled = false;
        }
      } catch (err) {
        console.error(err);
      }
    });

    // Add Product
    $('addProductBtn').addEventListener('click', () => {
      const addBtn = $('addProductBtn');
      const productId = $('productSelect').value;
      const qty = parseInt($('qtyInput').value) || 1;
      if (!productId) return showPopup('Please select a product.');

      const p = productsList[productId];
      if (!p) return showPopup('Product data not found.');

      const price = parseFloat(p.sell_price) || 0;
      const gstPercent = parseFloat(p.gst_percent) || 0;
      const amount = price * qty;
      const gst = (amount * gstPercent) / 100;

      cart.push({
        product_id: productId,
        product_name: p.product_name,
        quantity: qty,
        price,
        gst_percent: gstPercent,
        amount,
        gst,
        total: amount + gst
      });

      renderTable();
      $('productSelect').value = '';
      $('qtyInput').value = 1;
      addBtn.disabled = true;
    });

    // Render Cart
    function renderTable() {
      const tbody = document.querySelector("#billingTable tbody");
      tbody.innerHTML = '';
      let sub = 0,
        gst = 0;
      cart.forEach((item, i) => {
        sub += item.amount;
        gst += item.gst;
        const tr = document.createElement('tr');
        tr.innerHTML =
          `<td>${i+1}</td><td>${item.product_name}</td><td>${item.quantity}</td><td>₹${item.amount.toFixed(2)}</td><td><button class="btn btn-danger btn-sm" data-index="${i}">Remove</button></td>`;
        tbody.appendChild(tr);
      });
      tbody.querySelectorAll('button[data-index]').forEach(b => {
        b.addEventListener('click', () => {
          cart.splice(parseInt(b.dataset.index), 1);
          renderTable();
        });
      });
      $('subTotal').innerText = sub.toFixed(2);
      $('taxAmount').innerText = gst.toFixed(2);
      $('totalAmount').innerText = (sub + gst).toFixed(2);
    }

    // ✅ Common Checkout Function
    async function processCheckout(print = false) {
      if (!cart.length) return showPopup('Your cart is empty. Please add at least one product.', 'Empty Cart');

      for (let k of enabledFields) {
        const el = $(k);
        if (el && !el.value.trim()) {
          el.classList.add('input-error');
          setTimeout(() => el.classList.remove('input-error'), 500);
          el.focus();
          return showPopup(`${labels[k]||k} is required.`, 'Missing Information');
        }
      }

      const data = {
        date: $('invoice_date').value,
        items: cart.map(i => ({
          product_id: i.product_id,
          quantity: i.quantity,
          price: i.price,
          gst_percent: i.gst_percent
        })),
        print: print ? 1 : 0
      };
      Object.keys(labels).forEach(k => {
        const el = $(k);
        data[k] = el && el.value.trim() ? el.value.trim() : null;
      });

      try {
        const res = await fetch('./checkout.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });
        const r = await res.json();
        if (r.status === 'success') {
          showToast('successToast');
          resetForm();
          if (print && r.sale_id) {
            window.open(`./generate_invoice.php?sale_id=${r.sale_id}&download=1`, '_blank');
          }
        } else {
          showPopup(r.message || 'Checkout failed.', 'Error');
        }
      } catch (e) {
        console.error(e);
        showPopup('Checkout failed. Please try again.', 'Error');
      }
    }

    // ✅ Attach Events
    $('generateInvoiceBtn').addEventListener('click', () => processCheckout(true));
    $('saveOnlyBtn').addEventListener('click', () => processCheckout(false));
  });

  document.addEventListener("DOMContentLoaded", function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
  });
  </script>
</body>

</html>