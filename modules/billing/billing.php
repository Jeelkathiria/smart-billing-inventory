<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$store_id = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 0;

// Fetch store info safely
$sql = "SELECT store_type, billing_fields FROM stores WHERE store_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $store_id);
$stmt->execute();
$result = $stmt->get_result();
$store = $result->fetch_assoc() ?: ['store_type' => '', 'billing_fields' => '{}'];

$fields = json_decode($store['billing_fields'], true);
if (!is_array($fields)) $fields = [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Billing</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
  .navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    z-index: 1030;
    background: #fff;
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

  .content {
    margin-left: 220px;
    padding: 20px;
    padding-top: 60px;
  }

  .scanner-box {
    border: 2px dashed #ccc;
    padding: 15px;
    text-align: center;
    margin-bottom: 20px;
  }

  .table td,
  .table th {
    vertical-align: middle;
  }

  @keyframes shake {
    0% {
      transform: translateX(0)
    }

    25% {
      transform: translateX(-5px)
    }

    50% {
      transform: translateX(5px)
    }

    75% {
      transform: translateX(-5px)
    }

    100% {
      transform: translateX(0)
    }
  }

  .input-error {
    border-color: red !important;
    animation: shake 0.3s;
  }

  #successToast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    display: none;
    z-index: 9999;
    font-weight: 500;
  }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="content mt-4">
    <audio id="successSound"
      src="https://cdn.pixabay.com/download/audio/2022/03/15/audio_7d30f4f34d.mp3?filename=success-1-6297.mp3"
      preload="auto"></audio>
    <h3 class="mb-4">🧾 Live Billing</h3>

    <div class="scanner-box">
      <p>📷 Scan Barcode (optional)</p>
      <input type="text" id="barcodeInput" class="form-control" placeholder="Scan or enter barcode...">
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label for="invoice_date" class="form-label">Date & Time</label>
        <input type="text" id="invoice_date" name="invoice_date" class="form-control" readonly>
      </div>

      <?php if(!empty($fields['table_no'])): ?>
      <div class="col-md-4">
        <label for="table_no" class="form-label">Table / Order No</label>
        <input type="text" id="table_no" name="table_no" class="form-control" placeholder="Table or Order No">
      </div>
      <?php endif; ?>

      <?php if(!empty($fields['customer_name'])): ?>
      <div class="col-md-4">
        <label for="customer_name" class="form-label">Customer Name</label>
        <input type="text" id="customer_name" name="customer_name" class="form-control"
          placeholder="Enter Customer Name" <?php echo empty($fields['customer_name_optional'])?'required':''; ?>>
      </div>
      <?php endif; ?>

      <?php if(!empty($fields['customer_mobile'])): ?>
      <div class="col-md-4">
        <label for="customer_mobile" class="form-label">Mobile</label>
        <input type="text" id="customer_mobile" name="customer_mobile" class="form-control" placeholder="Mobile">
      </div>
      <?php endif; ?>

      <?php if(!empty($fields['address'])): ?>
      <div class="col-md-8">
        <label for="customer_address" class="form-label">Delivery Address</label>
        <textarea id="customer_address" name="customer_address" class="form-control" rows="2"
          placeholder="Delivery Address"></textarea>
      </div>
      <?php endif; ?>

      <?php if(!empty($fields['gstin'])): ?>
      <div class="col-md-4">
        <label for="gstin" class="form-label">GSTIN</label>
        <input type="text" id="gstin" name="gstin" class="form-control" placeholder="GSTIN">
      </div>
      <?php endif; ?>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label>Category</label>
        <select id="categorySelect" class="form-select">
          <option value="">Select Category</option>
          <?php
          $catStmt = $conn->prepare("SELECT * FROM categories WHERE store_id = ?");
          $catStmt->bind_param('i', $store_id);
          $catStmt->execute();
          $catRes = $catStmt->get_result();
          while($cat = $catRes->fetch_assoc()) {
              $catId = $cat['category_id'] ?? $cat['id'] ?? '';
              $catName = $cat['category_name'] ?? $cat['name'] ?? 'Category';
              echo "<option value=\"".htmlspecialchars($catId)."\">".htmlspecialchars($catName)."</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-4">
        <label>Product</label>
        <select id="productSelect" class="form-select" disabled>
          <option value="">Select Product</option>
        </select>
      </div>
      <div class="col-md-2">
        <label>Qty</label>
        <input type="number" id="qtyInput" class="form-control" value="1" min="1">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button id="addProductBtn" class="btn btn-success w-100" disabled>Add</button>
      </div>
    </div>

    <table class="table table-bordered" id="billingTable">
      <thead class="table-dark">
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

    <div class="text-end">
      <p><strong>Subtotal:</strong> ₹<span id="subTotal">0.00</span></p>
      <p><strong>Tax:</strong> ₹<span id="taxAmount">0.00</span></p>
      <h5><strong>Total:</strong> ₹<span id="totalAmount">0.00</span></h5>
      <button class="btn btn-primary mt-2" id="generateInvoiceBtn">Generate Invoice</button>
    </div>
  </div>

  <div id="successToast">✅ Billing Successful!</div>

  <script>
  let cart = [];
  let productsList = {};
  const $ = id => document.getElementById(id);
  const pad = n => n.toString().padStart(2, '0');
  const getFormattedDateTime = () => {
    const now = new Date();
    return `${pad(now.getDate())}-${pad(now.getMonth()+1)}-${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
  }
  const showToast = (id, d = 2000) => {
    const t = $(id);
    if (!t) return;
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', d);
  }
  const resetBillingForm = () => {
    cart = [];
    renderTable();
    ['customer_name', 'customer_mobile', 'customer_address', 'gstin', 'table_no'].forEach(id => {
      if ($(id)) $(id).value = '';
    });
    if ($('invoice_date')) $('invoice_date').value = getFormattedDateTime();
    if ($('categorySelect')) $('categorySelect').value = '';
    if ($('productSelect')) {
      $('productSelect').innerHTML = '<option value="">Select Product</option>';
      $('productSelect').disabled = true;
    }
    if ($('qtyInput')) $('qtyInput').value = 1;
    if ($('barcodeInput')) $('barcodeInput').value = '';
    if ($('addProductBtn')) $('addProductBtn').disabled = true;
  }

  document.addEventListener("DOMContentLoaded", () => {
    if ($('invoice_date')) $('invoice_date').value = getFormattedDateTime();
  });

  $('categorySelect')?.addEventListener('change', async function() {
    const catId = this.value;
    productsList = {};
    $('productSelect').disabled = true;
    $('productSelect').innerHTML = '<option value="">Loading...</option>';
    $('addProductBtn').disabled = true;
    if (!catId) {
      $('productSelect').innerHTML = '<option value="">Select Product</option>';
      $('productSelect').disabled = true;
      return;
    }
    try {
      const res = await fetch(`/modules/sales/fetch_products.php?category_id=${encodeURIComponent(catId)}`);
      const data = await res.json();
      $('productSelect').innerHTML = '<option value="">Select Product</option>';
      if (Array.isArray(data) && data.length > 0) {
        data.forEach(p => {
          productsList[p.product_id] = p;
          const disabled = parseInt(p.stock) === 0 ? 'disabled' : '';
          const stockText = parseInt(p.stock) === 0 ? ' (Out of Stock)' : '';
          $('productSelect').innerHTML +=
            `<option value="${p.product_id}" ${disabled}>${p.product_name}${stockText}</option>`;
        });
      } else $('productSelect').innerHTML += '<option value="" disabled>No products available</option>';
    } catch (err) {
      console.error(err);
      $('productSelect').innerHTML = '<option value="" disabled>Error loading products</option>';
    }
    $('productSelect').disabled = false;
  });

  $('productSelect')?.addEventListener('change', () => {
    $('addProductBtn').disabled = !$('productSelect').value;
  });

  $('barcodeInput')?.addEventListener('input', function() {
    const code = this.value.trim();
    const found = Object.entries(productsList).find(([_, p]) => (p.barcode || '') === code);
    $('productSelect').value = found ? found[0] : '';
    $('addProductBtn').disabled = !$('productSelect').value;
  });

  $('addProductBtn')?.addEventListener('click', () => {
    const productId = $('productSelect')?.value;
    const qty = Math.max(1, parseInt($('qtyInput')?.value) || 1);
    if (!productId) return alert('Please select a product');
    const product = productsList[productId];
    if (!product) return alert('Product details missing');
    const stock = parseInt(product.stock) || 0;
    if (stock === 0) return alert(`${product.product_name} is out of stock`);
    if (qty > stock) return alert(`Only ${stock} in stock`);
    const price = parseFloat(product.price) || 0;
    const gst = parseFloat(product.gst_percent) || 0;
    const gstAmt = (price * gst) / 100;
    cart.push({
      product_id: product.product_id,
      product_name: product.product_name,
      quantity: qty,
      price: price,
      gst_percent: gst,
      amount: price * qty,
      gst: gstAmt * qty,
      total: (price + gstAmt) * qty
    });
    renderTable();
    if ($('barcodeInput')) $('barcodeInput').value = '';
  });

  function renderTable() {
    const tbody = document.querySelector("#billingTable tbody");
    if (!tbody) return;
    tbody.innerHTML = '';
    let subTotal = 0,
      totalGst = 0;
    cart.forEach((item, i) => {
      subTotal += item.amount;
      totalGst += item.gst;
      const tr = document.createElement('tr');
      tr.innerHTML =
        `<td>${i+1}</td><td>${item.product_name}</td><td>${item.quantity}</td><td>₹${item.total.toFixed(2)}</td><td><button class="btn btn-danger btn-sm" data-index="${i}">Remove</button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button[data-index]').forEach(btn => btn.addEventListener('click', () => {
      cart.splice(parseInt(btn.getAttribute('data-index')), 1);
      renderTable();
    }));
    const finalTotal = subTotal + totalGst;
    if ($('subTotal')) $('subTotal').innerText = subTotal.toFixed(2);
    if ($('taxAmount')) $('taxAmount').innerText = totalGst.toFixed(2);
    if ($('totalAmount')) $('totalAmount').innerText = finalTotal.toFixed(2);
  }

  $('generateInvoiceBtn')?.addEventListener('click', async () => {
    if (cart.length === 0) return alert('Cart is empty');
    const nameEl = $('customer_name');
    if (nameEl && nameEl.hasAttribute('required') && !nameEl.value.trim()) {
      nameEl.classList.add('input-error');
      setTimeout(() => nameEl.classList.remove('input-error'), 500);
      nameEl.focus();
      return;
    }

    const invoiceData = {
      customer_name: nameEl ? nameEl.value.trim() : '',
      customer_mobile: $('customer_mobile') ? $('customer_mobile').value.trim() : '',
      customer_address: $('customer_address') ? $('customer_address').value.trim() : '',
      gstin: $('gstin') ? $('gstin').value.trim() : '',
      table_no: $('table_no') ? $('table_no').value.trim() : '',
      date: $('invoice_date') ? $('invoice_date').value : '',
      items: cart.map(item => ({
        product_id: item.product_id,
        product_name: item.product_name,
        quantity: item.quantity,
        price: item.price,
        amount: item.amount,
        gst_percent: item.gst_percent
      })),
      subTotal: parseFloat($('subTotal').innerText) || 0,
      tax: parseFloat($('taxAmount').innerText) || 0,
      total: parseFloat($('totalAmount').innerText) || 0
    };

    try {
      const res = await fetch('./checkout.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(invoiceData)
      });
      if (!res.ok) throw new Error('Network response not ok');
      const data = await res.json();
      if (data.status === 'success') {
        showToast('successToast');
        resetBillingForm();
        window.open(`./generate_invoice.php?sale_id=${data.sale_id}&download=1`, '_blank');
      } else {
        alert(data.message || 'Checkout failed');
      }
    } catch (err) {
      console.error(err);
      alert('Checkout failed');
    }
  });
  </script>
</body>

</html>