<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';


?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Billing</title>
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

  .sidebar-footer {
    padding: 12px 20px;
    margin-top: auto;
  }

  .content {
    margin-left: 220px;
    padding: 20px;
    padding-top: 60px;
  }

  @keyframes fadeInOut {
    0% {
      opacity: 0;
      transform: translateY(-10px);
    }

    10% {
      opacity: 1;
      transform: translateY(0);
    }

    90% {
      opacity: 1;
      transform: translateY(0);
    }

    100% {
      opacity: 0;
      transform: translateY(-10px);
    }

  }

  #successToast i {
    margin-right: 8px;
  }

  #successToast.show {
    animation: fadeInOut 3s ease-in-out;
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
      transform: translateX(0);
    }

    25% {
      transform: translateX(-5px);
    }

    50% {
      transform: translateX(5px);
    }

    75% {
      transform: translateX(-5px);
    }

    100% {
      transform: translateX(0);
    }
  }

  .input-error {
    border-color: red !important;
    animation: shake 0.3s;
  }
  </style>
</head>

<body>

  <!-- Navbar -->
  <?php include '../../components/navbar.php'; ?>


  <!-- Sidebar -->
  <?php include '../../components/sidebar.php'; ?>

  <div class=" content mt-4">
    <audio id="successSound"
      src="https://cdn.pixabay.com/download/audio/2022/03/15/audio_7d30f4f34d.mp3?filename=success-1-6297.mp3"
      preload="auto"></audio>


    <h3 class="mb-4">🧾 Live Billing</h3>

    <!-- Optional Barcode Scanner -->
    <div class="scanner-box">
      <p>📷 Scan Barcode (optional)</p>
      <input type="text" id="barcodeInput" class="form-control" placeholder="Scan or enter barcode...">
    </div>

    <!-- Invoice Date Input -->
    <div class="mb-3">
      <label for="invoice_date" class="form-label">Date & Time</label>
      <input type="text" id="invoice_date" name="invoice_date" class="form-control" readonly>
    </div>


    <!-- Customer Name Input -->
    <div class="mb-3">
      <label for="customer_name" class="form-label">Customer Name</label>
      <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Enter Customer Name"
        required>
    </div>

    <!-- Select Category and Product -->
    <div class="row mb-3">
      <div class="col-md-4">
        <label>Category</label>
        <select id="categorySelect" class="form-select">
          <option value="">Select Category</option>
          <?php
        $categories = $conn->query("SELECT * FROM categories WHERE store_id = $store_id");

        while ($cat = $categories->fetch_assoc()) {
          echo "<option value='{$cat['category_id']}'>{$cat['category_name']}</option>";
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
        <button id="addProductBtn" class="btn btn-success w-100">Add</button>
      </div>
    </div>

    <!-- Billing Table -->
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

  <!-- Success Toast -->
  <div id="successToast"
    style="position: fixed; top: 20px; right: 20px; background-color: #28a745; color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); display: none; z-index: 9999; font-weight: 500;">
    ✅ Billing Successful!
  </div>

  <!-- Screen overlay -->
  <div id="overlayFade"
    style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(255,255,255,0.7); z-index: 9998; display: none;">
  </div>

<script>
let cart = [];
let productsList = {};

// Utility functions
const $ = (id) => document.getElementById(id);
const pad = (n) => n.toString().padStart(2, "0");

function getFormattedDateTime() {
  const now = new Date();
  return `${pad(now.getDate())}-${pad(now.getMonth() + 1)}-${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

function showToast(id, duration = 2000) {
  const toast = $(id);
  toast.style.display = 'block';
  setTimeout(() => toast.style.display = 'none', duration);
}

function resetBillingForm() {
  cart = [];
  renderTable();
  $("customer_name").value = '';
  $("invoice_date").value = getFormattedDateTime();
  $("categorySelect").value = '';
  $("productSelect").innerHTML = '<option value="">Select Product</option>';
  $("productSelect").disabled = true;
  $("qtyInput").value = 1;
  $("barcodeInput").value = '';
}

// Initial setup
document.addEventListener("DOMContentLoaded", () => {
  $("invoice_date").value = getFormattedDateTime();
});

// Category change → Load products
$("categorySelect").addEventListener("change", async function () {
  const categoryId = this.value;
  const productSelect = $("productSelect");
  productsList = {};
  productSelect.disabled = true;
  productSelect.innerHTML = '<option value="">Loading...</option>';

  if (!categoryId) {
    productSelect.innerHTML = '<option value="">Select Product</option>';
    return;
  }

  try {
    const res = await fetch(`/modules/sales/fetch_products.php?category_id=${categoryId}`);
    const data = await res.json();
    productSelect.innerHTML = '<option value="">Select Product</option>';

    if (data.length === 0) {
      productSelect.innerHTML += '<option value="" disabled>No products available</option>';
    } else {
      data.forEach(p => {
        productsList[p.product_id] = p;
        const disabled = p.stock == 0 ? 'disabled' : '';
        const stockText = p.stock == 0 ? ' (Out of Stock)' : '';
        productSelect.innerHTML += `<option value="${p.product_id}" ${disabled}>${p.product_name}${stockText}</option>`;
      });
    }
  } catch {
    productSelect.innerHTML = '<option value="" disabled>Error loading products</option>';
  } finally {
    productSelect.disabled = false;
  }
});

// Barcode input → Auto-select product
$("barcodeInput").addEventListener("input", function () {
  const code = this.value.trim();
  const found = Object.entries(productsList).find(([_, p]) => p.barcode === code);
  $("productSelect").value = found ? found[0] : "";
});

// Add product to cart
$("addProductBtn").addEventListener("click", () => {
  const productId = $("productSelect").value;
  const qty = Math.max(1, parseInt($("qtyInput").value) || 1);
  if (!productId) return;

  const product = productsList[productId];
  const stock = parseInt(product.stock);
  if (stock === 0) return alert(`❌ ${product.product_name} is out of stock`);
  if (qty > stock) return alert(`❌ Only ${stock} in stock for ${product.product_name}`);

const price = parseFloat(product.price) || 0;
const gstPercent = parseFloat(product.gst_percent) || 0;
const gstAmountPerUnit = (price * gstPercent) / 100;

cart.push({
  product_id: product.product_id,
  product_name: product.product_name,
  quantity: qty,
  price: price,
  gst_percent: gstPercent,  
  amount: price * qty,                     // without GST
  gst: gstAmountPerUnit * qty,             // total GST
  total: (price + gstAmountPerUnit) * qty  // with GST
});



  renderTable();
  $("barcodeInput").value = '';
});

function renderTable() {
  const tbody = document.querySelector("#billingTable tbody");
  tbody.innerHTML = '';

  let subTotal = 0, totalGst = 0;

cart.forEach((item, i) => {
  subTotal += item.amount;
  totalGst += item.gst;

  tbody.innerHTML += `
    <tr>
      <td>${i + 1}</td>
      <td>${item.product_name}</td>
      <td>${item.quantity}</td>
      <td>₹${item.total.toFixed(2)}</td>
      <td><button class="btn btn-danger btn-sm" onclick="removeItem(${i})">Remove</button></td>
    </tr>`;
});


  const finalTotal = subTotal + totalGst; // define before using

  // make sure these spans contain only numbers, not ₹
  document.getElementById('subTotal').innerText = subTotal.toFixed(2);
  document.getElementById('taxAmount').innerText = totalGst.toFixed(2);
  document.getElementById('totalAmount').innerText = finalTotal.toFixed(2);
}


function removeItem(index) {
  cart.splice(index, 1);
  renderTable();
}

// Generate invoice
$("generateInvoiceBtn").addEventListener("click", async () => {
  if (cart.length === 0) return; // Add proper validation UI if needed
  const customerName = $("customer_name").value.trim();
  if (!customerName) return;

 const invoiceData = {
  customer_name: customerName,
  date: document.getElementById("invoice_date").value,
  items: cart.map(item => ({
    product_id: item.product_id,
    product_name: item.product_name,
    quantity: item.quantity,
    price: item.price,   // 👈 MUST be present
    amount: item.amount,
    gst_percent: item.gst_percent  
  })),
  subTotal: parseFloat(document.getElementById("subTotal").innerText),
  tax: parseFloat(document.getElementById("taxAmount").innerText),
  total: parseFloat(document.getElementById("totalAmount").innerText)
};


  try {
    const res = await fetch('checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(invoiceData)
    });

    const text = await res.text();
    const data = JSON.parse(text);

    if (data.status === 'success') {
      showToast('successToast');
      // Download PDF
      const pdfRes = await fetch(`generate_invoice.php?sale_id=${data.sale_id}`);
      const blob = await pdfRes.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `Invoice_${data.invoice_id}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      resetBillingForm();
    } else {
      alert(data.message);
    }
  } catch (err) {
    console.error('Checkout failed:', err.message);
  }
});
</script>




</body>

</html>