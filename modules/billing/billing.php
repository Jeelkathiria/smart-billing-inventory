<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

/**
 * Billing module (Live UI)
 * - Handles product selection, cart management and checkout
 * - Uses toasts for transient user messages and enforces non-negative numeric inputs
 */

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
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/common.css">

  <style>
  body {
    background: #f8f9fa;
    overflow-x: hidden;
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
  }

  h3 {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
  }

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

  .btn {
    border-radius: 8px;
    font-weight: 500;
    letter-spacing: 0.3px;
  }

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
              // Only render known billing fields (avoid showing internal flags like print_store_email)
              if (!$enabled) continue;
              if (!isset($labels[$key])) continue;
              $label = $labels[$key];
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

    <!-- Barcode -->
    <div class="card mt-4 shadow-sm border-0 position-relative">
      <!-- Info Icon at Top-Right -->
      <i class="bi bi-info-circle text-primary position-absolute top-0 end-0 m-3" data-bs-toggle="tooltip"
        data-bs-placement="left" title="Scan the barcode using a scanner to auto-add the product to the cart. 
If entering manually, type the barcode and press Enter to add it."></i>

      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="barcodeInput"><i class="bi bi-upc-scan"></i> Barcode</label>
            <input type="text" id="barcodeInput" class="form-control" placeholder="Scan or enter barcode manually">
          </div>
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
          <h5 class="modal-title" id="alertModalLabel"><i class="bi bi-info-circle"></i> Notice</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body fs-5" id="alertModalBody">...</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);
    let cart = [];
    let productsList = {};
    const enabledFields = <?= json_encode(array_keys(array_filter($fields))) ?>;
    const labels = <?= json_encode($labels) ?>;

    const pad = n => n.toString().padStart(2, '0');
    const getDateTime = () => {
      const d = new Date();
      return `${pad(d.getDate())}-${pad(d.getMonth()+1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };
    $('invoice_date').value = getDateTime();

    // Non-blocking notice converted to toast (2s) per global UX rule
    const showPopup = (message, title = "Notice") => {
      const payload = (title ? (title + ': ') : '') + message;
      if (window.showGlobalToast) {
        showGlobalToast(payload, 'info', 2000);
        return;
      }
      // Fallback to modal if toast helper not available
      const modalEl = $('#alertModal');
      if (!modalEl) {
        console.error('Alert modal not found');
        alert(message);
        return;
      }
      const titleEl = modalEl.querySelector('#alertModalLabel');
      const bodyEl = modalEl.querySelector('#alertModalBody');
      if (titleEl) titleEl.textContent = title;
      if (bodyEl) bodyEl.textContent = message;
      try {
        new bootstrap.Modal(modalEl).show();
      } catch (e) {
        console.error('Modal error:', e);
        alert(message);
      }
    }

    const showToast = (id, duration = 2000) => {
      const t = $(id);
      t.style.display = 'block';
      setTimeout(() => t.style.display = 'none', duration);
    }

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

    // ---------------- Fetch Products by Category ----------------
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

    // ---------------- Product selection from dropdown ----------------
    $('productSelect').addEventListener('change', async () => {
      const pid = $('productSelect').value;
      $('addProductBtn').disabled = !pid;
      if (!pid) return;
      try {
        const res = await fetch(`../sales/fetch_price.php?product_id=${pid}`);
        const data = await res.json();
        if (data.status === 'success' && data.product) {
          productsList[pid] = {
            ...productsList[pid],
            ...data.product
          };
        }
      } catch (err) {
        console.error(err);
      }
    });

    // ---------------- Barcode Handling ----------------
    $('barcodeInput').addEventListener('keydown', async (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const code = e.target.value.trim();
        if (!code) return;

        try {
          // 1️⃣ Fetch product by barcode
          const res = await fetch(`fetch_product_by_barcode.php?barcode=${encodeURIComponent(code)}`);
          const data = await res.json();
          if (data.status !== 'success' || !data.product)
            return showPopup('Product not found for this barcode', 'Error');

          const p = data.product;

          // 2️⃣ Update category dropdown
          $('categorySelect').value = p.category_id;

          // 3️⃣ Fetch full products for category
          const productSelect = $('productSelect');
          productSelect.disabled = true;
          productSelect.innerHTML = '<option>Loading...</option>';

          const res2 = await fetch(`../sales/fetch_products.php?category_id=${p.category_id}`);
          const products = await res2.json();
          productSelect.innerHTML = '<option value="">Select Product</option>';
          productsList = {};
          if (Array.isArray(products) && products.length) {
            products.forEach(pr => {
              productsList[pr.product_id] = pr;
              const stock = parseInt(pr.stock) || 0;
              productSelect.innerHTML +=
                `<option value="${pr.product_id}" ${stock===0?'disabled':''}>${pr.product_name}${stock===0?' (Out of Stock)':''}</option>`;
            });
          }
          productSelect.disabled = false;

          // 4️⃣ Select product
          productSelect.value = p.product_id;

          // 5️⃣ Fetch full price & gst for this product (important!)
          const res3 = await fetch(`../sales/fetch_price.php?product_id=${p.product_id}`);
          const priceData = await res3.json();
          if (priceData.status === 'success' && priceData.product) {
            productsList[p.product_id] = {
              ...productsList[p.product_id],
              ...priceData.product
            };
          }

          // 6️⃣ Add to cart directly
          addProductToCart(productsList[p.product_id], 1);

          $('barcodeInput').value = ''; // clear barcode input
        } catch (err) {
          console.error(err);
          showPopup('Error fetching product', 'Error');
        }
      }
    });

    // ---------------- Global Barcode Scan Handling ----------------
    let barcodeBuffer = '';
    let barcodeTimer;

    document.addEventListener('keydown', async (e) => {
      // Only allow alphanumeric and Enter
      if (e.key.length === 1) {
        barcodeBuffer += e.key;
        clearTimeout(barcodeTimer);
        // Reset buffer if no key pressed within 50ms
        barcodeTimer = setTimeout(() => barcodeBuffer = '', 50);
      } else if (e.key === 'Enter') {
        if (!barcodeBuffer) return;
        const code = barcodeBuffer;
        barcodeBuffer = '';
        e.preventDefault();

        try {
          // Fetch product by barcode
          const res = await fetch(`fetch_product_by_barcode.php?barcode=${encodeURIComponent(code)}`);
          const data = await res.json();
          if (data.status !== 'success' || !data.product)
            return showPopup('Product not found for this barcode', 'Error');

          const p = data.product;

          // Update category dropdown
          $('categorySelect').value = p.category_id;

          // Fetch full products for category
          const productSelect = $('productSelect');
          productSelect.disabled = true;
          productSelect.innerHTML = '<option>Loading...</option>';

          const res2 = await fetch(`../sales/fetch_products.php?category_id=${p.category_id}`);
          const products = await res2.json();
          productSelect.innerHTML = '<option value="">Select Product</option>';
          productsList = {};
          if (Array.isArray(products) && products.length) {
            products.forEach(pr => {
              productsList[pr.product_id] = pr;
              const stock = parseInt(pr.stock) || 0;
              productSelect.innerHTML +=
                `<option value="${pr.product_id}" ${stock===0?'disabled':''}>${pr.product_name}${stock===0?' (Out of Stock)':''}</option>`;
            });
          }
          productSelect.disabled = false;

          // Select product
          productSelect.value = p.product_id;

          // Fetch full price & GST
          const res3 = await fetch(`../sales/fetch_price.php?product_id=${p.product_id}`);
          const priceData = await res3.json();
          if (priceData.status === 'success' && priceData.product) {
            productsList[p.product_id] = {
              ...productsList[p.product_id],
              ...priceData.product
            };
          }

          // Add to cart automatically
          addProductToCart(productsList[p.product_id], 1);

        } catch (err) {
          console.error(err);
          showPopup('Error fetching product', 'Error');
        }
      }
    });



    async function handleBarcode(code) {
      try {
        const res = await fetch(`fetch_product_by_barcode.php?barcode=${encodeURIComponent(code)}`);
        const data = await res.json();
        if (data.status !== 'success' || !data.product)
          return showPopup('Product not found for this barcode', 'Error');

        const p = data.product;

        // Update category dropdown
        $('categorySelect').value = p.category_id;

        // Fetch products for that category
        const productSelect = $('productSelect');
        productSelect.disabled = true;
        productSelect.innerHTML = '<option>Loading...</option>';

        const res2 = await fetch(`../sales/fetch_products.php?category_id=${p.category_id}`);
        const products = await res2.json();
        productSelect.innerHTML = '<option value="">Select Product</option>';
        productsList = {};
        if (Array.isArray(products) && products.length) {
          products.forEach(pr => {
            productsList[pr.product_id] = pr;
            const stock = parseInt(pr.stock) || 0;
            productSelect.innerHTML +=
              `<option value="${pr.product_id}" ${stock===0?'disabled':''}>${pr.product_name}${stock===0?' (Out of Stock)':''}</option>`;
          });
        }
        productSelect.disabled = false;

        // Select product
        productSelect.value = p.product_id;

        // Auto-add for scanner
        if (e && e.isTrusted) {
          addProductToCart(productsList[p.product_id], 1);
          $('barcodeInput').value = '';
        } else {
          $('addProductBtn').disabled = false; // Manual barcode, enable Add button
        }

      } catch (err) {
        console.error(err);
        showPopup('Error fetching product', 'Error');
      }
    }

    // ---------------- Add Product to Cart ----------------
    const addProductToCart = (p, qty) => {
      const existing = cart.find(item => item.product_id == p.product_id);
      const price = parseFloat(p.sell_price) || 0;
      const gstPercent = parseFloat(p.gst_percent) || 0;
      const amount = price * qty;
      const gst = (amount * gstPercent) / 100;

      if (existing) {
        existing.quantity += qty;
        existing.amount = existing.price * existing.quantity;
        existing.gst = (existing.amount * gstPercent) / 100;
        existing.total = existing.amount + existing.gst;
      } else {
        cart.push({
          product_id: p.product_id,
          product_name: p.product_name,
          quantity: qty,
          price,
          gst_percent: gstPercent,
          amount,
          gst,
          total: amount + gst
        });
      }
      renderTable();
    }

    $('addProductBtn').addEventListener('click', () => {
      const pid = $('productSelect').value;
      const qty = parseInt($('qtyInput').value) || 1;
      if (!pid) return showPopup('Please select a product.');
      const p = productsList[pid];
      if (!p) return showPopup('Product data not found.');
      addProductToCart(p, qty);
      $('qtyInput').value = 1;
      $('productSelect').value = '';
      $('addProductBtn').disabled = true;
    });

    // ---------------- Render Cart Table ----------------
    function renderTable() {
      const tbody = document.querySelector("#billingTable tbody");
      tbody.innerHTML = '';
      let sub = 0,
        gst = 0;

      cart.forEach((item, i) => {
        sub += item.amount;
        gst += item.gst;

        const tr = document.createElement('tr');
        tr.innerHTML = `
        <td>${i+1}</td>
        <td>${item.product_name}</td>
        <td>
          <div class="input-group input-group-sm">
            <button class="btn btn-outline-secondary btn-decrease" data-index="${i}">-</button>
            <input type="number" class="form-control form-control-sm text-center" min="1" value="${item.quantity}" data-index="${i}">
            <button class="btn btn-outline-secondary btn-increase" data-index="${i}">+</button>
          </div>
        </td>
        <td>₹${item.amount.toFixed(2)}</td>
        <td><button class="btn btn-danger btn-sm" data-index="${i}">Remove</button></td>
      `;
        tbody.appendChild(tr);
      });

      // Buttons & input events
      tbody.querySelectorAll('button.btn-danger').forEach(b => {
        b.addEventListener('click', () => {
          cart.splice(parseInt(b.dataset.index), 1);
          renderTable();
        });
      });
      tbody.querySelectorAll('button.btn-increase').forEach(b => {
        b.addEventListener('click', () => {
          const idx = parseInt(b.dataset.index);
          cart[idx].quantity++;
          cart[idx].amount = cart[idx].price * cart[idx].quantity;
          cart[idx].gst = (cart[idx].amount * cart[idx].gst_percent) / 100;
          cart[idx].total = cart[idx].amount + cart[idx].gst;
          renderTable();
        });
      });
      tbody.querySelectorAll('button.btn-decrease').forEach(b => {
        b.addEventListener('click', () => {
          const idx = parseInt(b.dataset.index);
          if (cart[idx].quantity > 1) {
            cart[idx].quantity--;
            cart[idx].amount = cart[idx].price * cart[idx].quantity;
            cart[idx].gst = (cart[idx].amount * cart[idx].gst_percent) / 100;
            cart[idx].total = cart[idx].amount + cart[idx].gst;
            renderTable();
          }
        });
      });
      tbody.querySelectorAll('input[type=number]').forEach(input => {
        input.addEventListener('change', () => {
          const idx = parseInt(input.dataset.index);
          let val = parseInt(input.value);
          if (val < 1) val = 1;
          cart[idx].quantity = val;
          cart[idx].amount = cart[idx].price * val;
          cart[idx].gst = (cart[idx].amount * cart[idx].gst_percent) / 100;
          cart[idx].total = cart[idx].amount + cart[idx].gst;
          renderTable();
        });
      });

      $('subTotal').innerText = sub.toFixed(2);
      $('taxAmount').innerText = gst.toFixed(2);
      $('totalAmount').innerText = (sub + gst).toFixed(2);
    }

    // ---------------- Checkout ----------------
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
        
        if (!res.ok) {
          return showPopup(`Server error: ${res.status} ${res.statusText}`, 'Error');
        }

        const responseText = await res.text();
        console.log('Server response:', responseText);

        let r;
        try {
          r = JSON.parse(responseText);
        } catch (parseErr) {
          console.error('JSON Parse Error:', parseErr);
          console.error('Response was:', responseText);
          return showPopup('Server returned invalid JSON. Check browser console.', 'Parse Error');
        }

        if (r && r.status === 'success') {
          showToast('successToast');
          resetForm();
          if (print && r.sale_id) {
            window.open(`./generate_invoice.php?sale_id=${r.sale_id}&download=1`, '_blank');
          }
        } else {
          showPopup(r?.message || 'Checkout failed.', 'Error');
        }
      } catch (e) {
        console.error('Checkout error:', e);
        showPopup(`Checkout failed: ${e.message}`, 'Error');
      }
    }

    // Attach checkout buttons
    $('generateInvoiceBtn').addEventListener('click', () => processCheckout(true));
    $('saveOnlyBtn').addEventListener('click', () => processCheckout(false));

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
  });
  </script>


</body>

</html>