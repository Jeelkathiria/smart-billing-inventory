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
  body {
    margin: 0;
    font-family: sans-serif;
  }

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
    background: #f0f0f0;
    border-left: 4px solid #007bff;
  }

  .sidebar-footer {
    padding: 12px 20px;
    margin-top: auto;
  }

  .content {
    margin-left: 220px;
    padding: 20px;
    padding-top: 90px;
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

    <?php
    $labels = [
      'customer_name' => 'Customer Name',
      'customer_email' => 'Customer Email',
      'customer_mobile' => 'Mobile',
      'customer_address' => 'Delivery Address',
      'gstin' => 'GSTIN'
    ];
    foreach ($fields as $key => $enabled):
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
  let cart = [], productsList = {};
  const enabledFields = <?php echo json_encode(array_keys(array_filter($fields))); ?>;

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
    $('barcodeInput').value = '';
    $('addProductBtn').disabled = true;
    enabledFields.forEach(k => { if ($(k)) $(k).value = ''; });
  };

  const showToast = (id, d=2000) => {
    const t = $(id);
    t && (t.style.display='block', setTimeout(()=>t.style.display='none',d));
  };

  // Load products on category change
  $('categorySelect').addEventListener('change', async function(){
    const catId = this.value;
    productsList = {};
    $('productSelect').disabled = true;
    $('productSelect').innerHTML = '<option>Loading...</option>';
    $('addProductBtn').disabled = true;

    if (!catId) {
      $('productSelect').innerHTML = '<option value="">Select Product</option>';
      return;
    }

    try {
      const res = await fetch('../sales/fetch_products.php?category_id=' + catId);
      const data = await res.json();
      $('productSelect').innerHTML = '<option value="">Select Product</option>';
      if (Array.isArray(data) && data.length) {
        data.forEach(p => {
          productsList[p.product_id] = p;
          const stock = parseInt(p.stock);
          $('productSelect').innerHTML +=
            `<option value="${p.product_id}" ${stock===0?'disabled':''}>${p.product_name}${stock===0?' (Out of Stock)':''}</option>`;
        });
      } else $('productSelect').innerHTML += '<option value="" disabled>No products available</option>';
    } catch(e) {
      console.error(e);
      $('productSelect').innerHTML += '<option value="" disabled>Error loading</option>';
    }
    $('productSelect').disabled = false;
  });

  // Enable add button on product select
  $('productSelect').addEventListener('change', () => {
    $('addProductBtn').disabled = !$('productSelect').value;
  });

  // Barcode input
  $('barcodeInput').addEventListener('input', function() {
    const code = this.value.trim();
    const found = Object.entries(productsList).find(([_, p]) => p.barcode === code);
    $('productSelect').value = found ? found[0] : '';
    $('addProductBtn').disabled = !$('productSelect').value;
  });

  // Add product to cart
  $('addProductBtn').addEventListener('click', () => {
    const id = $('productSelect').value;
    const qty = Math.max(1, parseInt($('qtyInput').value) || 1);
    if (!id) return alert('Select product');
    const p = productsList[id];
    if (!p) return;
    const stock = parseInt(p.stock) || 0;
    if (stock === 0) return alert(`${p.product_name} out of stock`);
    if (qty > stock) return alert(`Only ${stock} in stock`);

    const price = parseFloat(p.price) || 0;
    const gst = parseFloat(p.gst_percent) || 0;
    const gstAmt = (price * gst / 100);

    cart.push({
      product_id: p.product_id,
      product_name: p.product_name,
      quantity: qty,
      price: price,
      amount: price * qty,
      gst: gstAmt * qty,
      total: (price + gstAmt) * qty
    });

    renderTable();
    $('barcodeInput').value = '';
    $('productSelect').value = '';
    $('addProductBtn').disabled = true;
  });

  function renderTable() {
    const tbody = document.querySelector("#billingTable tbody");
    if (!tbody) return;
    tbody.innerHTML = '';
    let sub=0, gst=0;
    cart.forEach((item,i)=>{
      sub += item.amount;
      gst += item.gst;
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${i+1}</td><td>${item.product_name}</td><td>${item.quantity}</td><td>₹${item.total.toFixed(2)}</td><td><button class="btn btn-danger btn-sm" data-index="${i}">Remove</button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button[data-index]').forEach(b=>{
      b.addEventListener('click', ()=> {
        cart.splice(parseInt(b.dataset.index),1);
        renderTable();
      });
    });
    $('subTotal').innerText = sub.toFixed(2);
    $('taxAmount').innerText = gst.toFixed(2);
    $('totalAmount').innerText = (sub+gst).toFixed(2);
  }

  // Checkout
  $('generateInvoiceBtn').addEventListener('click', async ()=>{
    if (!cart.length) return alert('Cart empty');

    for (let k of enabledFields) {
      const el = $(k);
      if (el && !el.value.trim()) {
        el.classList.add('input-error');
        setTimeout(()=>el.classList.remove('input-error'),500);
        el.focus();
        return alert(`${k} is required`);
      }
    }

    const data = {
      date: $('invoice_date').value,
      items: cart.map(i=>({
        product_id: i.product_id,
        quantity: i.quantity,
        price: i.price,
        gst_percent: parseFloat(i.gst_percent) || 0

      }))
    };
    enabledFields.forEach(k=>{
      const el = $(k);
      if(el) data[k] = el.value.trim();
    });

    try {
      const res = await fetch('./checkout.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
      });
      const r = await res.json();
      if (r.status==='success') {
        showToast('successToast');
        resetForm();
        window.open(`./generate_invoice.php?sale_id=${r.sale_id}&download=1`, '_blank');
      } else alert(r.message || 'Checkout failed');
    } catch(e){
      console.error(e);
      alert('Checkout failed');
    }
  });

});
</script>
</body>
</html>
