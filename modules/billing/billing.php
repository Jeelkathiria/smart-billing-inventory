<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$store_id = $_SESSION['store_id'] ?? 0;

// Fetch store info
$stmt = $conn->prepare("SELECT store_type, billing_fields FROM stores WHERE store_id = ?");
$stmt->bind_param('i', $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc() ?: ['store_type' => '', 'billing_fields' => '{}'];
$fields = json_decode($store['billing_fields'], true) ?: [];

// Fetch categories
$catStmt = $conn->prepare("SELECT category_id, category_name FROM categories WHERE store_id = ?");
$catStmt->bind_param('i', $store_id);
$catStmt->execute();
$categories = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Labels for billing fields
$labels = [
    'customer_name' => 'Customer Name',
    'customer_email' => 'Customer Email',
    'customer_mobile' => 'Mobile',
    'customer_address' => 'Delivery Address',
    'gstin' => 'GSTIN'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Billing</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
   <link rel="stylesheet" href="/assets/css/common.css">
  <style>

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

  .input-error {
    border-color: red;
    animation: shake 0.3s;
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

  #successToast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    display: none;
    z-index: 9999;
    font-weight: 500;
  }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="content">
    <h3 class="mb-4">🧾 Live Billing</h3>

    <div class="scanner-box">
      <input type="text" id="barcodeInput" class="form-control" placeholder="Scan or enter barcode...">
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label>Date & Time</label>
        <input type="text" id="invoice_date" class="form-control" readonly>
      </div>

      <?php foreach ($fields as $key => $enabled):
                if (!$enabled) continue;
                $label = $labels[$key] ?? ucfirst($key);
            ?>
      <div class="col-md-<?php echo $key === 'customer_address' ? 8 : 4; ?>">
        <label><?php echo $label; ?></label>
        <?php if ($key === 'customer_address'): ?>
        <textarea id="<?php echo $key ?>" class="form-control" rows="2" placeholder="<?php echo $label ?>"></textarea>
        <?php else: ?>
        <input type="text" id="<?php echo $key ?>" class="form-control" placeholder="<?php echo $label ?>">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label>Category</label>
        <select id="categorySelect" class="form-select">
          <option value="">Select Category</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
          <?php endforeach; ?>
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
document.addEventListener('DOMContentLoaded', () => {
  const $ = id => document.getElementById(id);
  let cart = [];
  let productsList = {};

  const enabledFields = <?php echo json_encode(array_keys(array_filter($fields))); ?>;
  const labels = <?php echo json_encode($labels); ?>;

  // --- Date & Time ---
  const pad = n => n.toString().padStart(2, '0');
  const getDateTime = () => {
    const d = new Date();
    return `${pad(d.getDate())}-${pad(d.getMonth()+1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };
  $('invoice_date').value = getDateTime();

  // --- Reset Form ---
  const resetForm = () => {
    cart = [];
    renderTable();
    $('invoice_date').value = getDateTime();
    $('categorySelect').value = '';
    $('productSelect').innerHTML = '<option value="">Select Product</option>';
    $('productSelect').disabled = true;
    $('qtyInput').value = 1;
    $('barcodeInput').value = '';
    $('addProductBtn').disabled = true;

    enabledFields.forEach(k => { if ($(k)) $(k).value = ''; });
  };

  // --- Show Toast ---
  const showToast = (id, duration = 2000) => {
    const t = $(id);
    if (!t) return;
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', duration);
  };

  // --- Fetch Products by Category ---
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
          productSelect.innerHTML += `
            <option value="${p.product_id}" ${stock===0?'disabled':''}>
              ${p.product_name}${stock===0?' (Out of Stock)':''}
            </option>`;
        });
      } else {
        productSelect.innerHTML += '<option value="" disabled>No products available</option>';
      }

      productSelect.disabled = false;
    } catch (e) {
      console.error(e);
      productSelect.innerHTML = '<option value="" disabled>Error loading</option>';
    }
  });

  // --- Product Select Change ---
  $('productSelect').addEventListener('change', async () => {
    const productId = $('productSelect').value;
    $('addProductBtn').disabled = true;
    if (!productId) return;

    try {
      const res = await fetch(`../sales/fetch_price.php?product_id=${productId}`);
      const data = await res.json();

      if (data.status === 'success') {
        const p = data.product;
        productsList[productId] = {
          product_id: p.product_id,
          product_name: $('productSelect').selectedOptions[0].text,
          sell_price: p.sell_price,
          gst_percent: p.gst_percent,
          profit: p.profit,
          stock: p.stock
        };

        $('addProductBtn').disabled = p.stock <= 0;
        if (p.stock <= 0) alert('Product out of stock');
      } else {
        alert(data.message || 'Failed to fetch product info');
      }
    } catch (e) {
      console.error(e);
      alert('Error fetching product info');
    }
  });

  // --- Barcode Input ---
  $('barcodeInput').addEventListener('input', function() {
    const code = this.value.trim();
    const found = Object.entries(productsList).find(([_, p]) => p.barcode === code);
    $('productSelect').value = found ? found[0] : '';
    $('addProductBtn').disabled = !$('productSelect').value;
  });

  // --- Add Product to Cart ---
  $('addProductBtn').addEventListener('click', async () => {
    const productId = $('productSelect').value;
    const qty = Math.max(1, parseInt($('qtyInput').value) || 1);
    if (!productId) return alert('Select product');

    const product = productsList[productId];
    if (!product) return alert('Product not found');

    try {
        // Fetch latest price, gst, stock from backend
        const res = await fetch(`../sales/fetch_price.php?product_id=${productId}`);
        const data = await res.json();
        if (data.status !== 'success') return alert(data.message || 'Price fetch failed');

        const p = data.product;
        const stock = parseInt(p.stock) || 0;
        if (stock === 0) return alert(`${product.product_name} out of stock`);
        if (qty > stock) return alert(`Only ${stock} in stock`);

        const price = parseFloat(p.sell_price) || 0;
        const gst = parseFloat(p.gst_percent) || 0;
        const gstAmt = price * gst / 100;

        cart.push({
            product_id: productId,
            product_name: product.product_name, // <-- Use name from productsList
            quantity: qty,
            price: price,
            gst_percent: gst,
            amount: price * qty,
            gst: gstAmt * qty,
            total: (price + gstAmt) * qty
        });

        renderTable();

        $('barcodeInput').value = '';
        $('productSelect').value = '';
        $('addProductBtn').disabled = true;

    } catch (e) {
        console.error(e);
        alert('Failed to fetch product price');
    }
});


  // --- Render Cart Table ---
  function renderTable() {
    const tbody = document.querySelector("#billingTable tbody");
    tbody.innerHTML = '';
    let sub = 0, gst = 0;

    cart.forEach((item, i) => {
      sub += item.amount;
      gst += item.gst;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${i+1}</td>
        <td>${item.product_name}</td>
        <td>${item.quantity}</td>
        <td>₹${item.total.toFixed(2)}</td>
        <td><button class="btn btn-danger btn-sm" data-index="${i}">Remove</button></td>
      `;
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

  // --- Checkout / Generate Invoice ---
  $('generateInvoiceBtn').addEventListener('click', async () => {
    if (!cart.length) return alert('Cart empty');

    // Validate required fields
    for (let k of enabledFields) {
      const el = $(k);
      if (el && !el.value.trim()) {
        el.classList.add('input-error');
        setTimeout(() => el.classList.remove('input-error'), 500);
        el.focus();
        return alert(`${labels[k] || k} is required`);
      }
    }

    const data = {
      date: $('invoice_date').value,
      items: cart.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
        price: i.price,
        gst_percent: i.gst_percent
      }))
    };

    // Include enabled customer fields
    enabledFields.forEach(k => { const el = $(k); if (el) data[k] = el.value.trim(); });

    try {
      const res = await fetch('./checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const r = await res.json();
      if (r.status === 'success') {
        showToast('successToast');
        resetForm();
        window.open(`./generate_invoice.php?sale_id=${r.sale_id}&download=1`, '_blank');
      } else {
        alert(r.message || 'Checkout failed');
      }
    } catch (e) {
      console.error(e);
      alert('Checkout failed');
    }
  });
});
</script>

</body>

</html>