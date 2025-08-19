<?php
require_once __DIR__ . '/../../config/db.php';

session_start();

$store_id = $_SESSION['store_id'];
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
          echo "<option value='{$cat['category_id']}'>{$cat['name']}</option>";
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
      <p><strong>Tax (5%):</strong> ₹<span id="taxAmount">0.00</span></p>
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

  // Set invoice date on page load
  document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("invoice_date").value = getFormattedDateTime();
  });

  // Populate products when category changes
  document.getElementById('categorySelect').addEventListener('change', function() {
    const categoryId = this.value;
    if (!categoryId) return;

    fetch(`fetch_products.php?category_id=${categoryId}`)
      .then(res => res.json())
      .then(data => {
        const productSelect = document.getElementById('productSelect');
        productSelect.innerHTML = '<option value="">Select Product</option>';
        productsList = {}; // reset

        data.forEach(product => {
          productsList[product.product_id] = product;
          productSelect.innerHTML += `<option value="${product.product_id}">${product.name}</option>`;
        });

        productSelect.disabled = false;
      });
  });

  // Barcode input detection
  document.getElementById('barcodeInput').addEventListener('input', function() {
    const enteredBarcode = this.value.trim();
    if (enteredBarcode === "") return;

    const found = Object.entries(productsList).find(([id, product]) => product.barcode === enteredBarcode);

    if (found) {
      const productId = found[0];
      document.getElementById('productSelect').value = productId;
    } else {
      document.getElementById('productSelect').value = "";
    }
  });

  // Add product to cart
  document.getElementById('addProductBtn').addEventListener('click', function() {
    const productId = document.getElementById('productSelect').value;
    const qty = parseInt(document.getElementById('qtyInput').value);

    if (!productId || qty < 1) return;

    const product = productsList[productId];
    const stock = parseInt(product.stock);

    if (stock === 0) {
      alert(`❌ ${product.name} is out of stock`);
      return;
    }

    if (qty > stock) {
      alert(`❌ Only ${stock} in stock for ${product.name}`);
      return;
    }

    const rate = parseFloat(product.price);
    const gstPercent = parseFloat(product.gst_percent);
    const baseAmount = qty * rate;
    const gstAmount = baseAmount * (gstPercent / 100);
    const totalAmount = baseAmount + gstAmount;

    cart.push({
      id: product.product_id,
      name: product.name,
      qty,
      rate,
      gst: gstPercent,
      total: totalAmount
    });

    renderTable();
    document.getElementById('barcodeInput').value = '';
  });


  function renderTable() {
    const tbody = document.querySelector("#billingTable tbody");
    tbody.innerHTML = '';
    let subTotal = 0;

    cart.forEach((item, i) => {
      subTotal += item.total;
      tbody.innerHTML += `
      <tr>
        <td>${i + 1}</td>
        <td>${item.name}</td>
        <td>${item.qty}</td>
        <td>₹${item.total.toFixed(2)}</td>
        <td><button class="btn btn-danger btn-sm" onclick="removeItem(${i})">Remove</button></td>
      </tr>`;
    });

    const tax = subTotal * 0.05;
    const finalTotal = subTotal + tax;

    document.getElementById("subTotal").innerText = subTotal.toFixed(2);
    document.getElementById("taxAmount").innerText = tax.toFixed(2);
    document.getElementById("totalAmount").innerText = finalTotal.toFixed(2);
  }

  function removeItem(index) {
    cart.splice(index, 1);
    renderTable();
  }

  function getFormattedDateTime() {
    const now = new Date();
    const pad = (n) => n < 10 ? '0' + n : n;
    const date = `${pad(now.getDate())}-${pad(now.getMonth() + 1)}-${now.getFullYear()}`;
    const time = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
    return `${date} ${time}`;
  }

  // Generate invoice
  document.getElementById("generateInvoiceBtn").addEventListener("click", function() {
    if (cart.length === 0) return;

    const customerName = document.getElementById("customer_name").value.trim();
    if (!customerName) {
      alert("Please enter a customer name.");
      return;
    }

    const invoiceData = {
      customer_name: customerName,
      date: document.getElementById("invoice_date").value,
      items: cart,
      subTotal: parseFloat(document.getElementById("subTotal").innerText),
      tax: parseFloat(document.getElementById("taxAmount").innerText),
      total: parseFloat(document.getElementById("totalAmount").innerText)
    };

    fetch('generate_invoice.php', {
        method: 'POST',
        body: JSON.stringify(invoiceData),
        headers: {
          'Content-Type': 'application/json'
        }
      })
      .then(response => response.blob())
      .then(blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = "Invoice_" + new Date().toISOString().slice(0, 19).replace(/[-T:]/g, "") + ".pdf";
        a.click();
        URL.revokeObjectURL(url);

        // Play sound
        document.getElementById("successSound").play().then(() => {
          document.getElementById("successSound").pause();
        }).catch(() => {});


        // Show toast and overlay
        const overlay = document.getElementById("overlayFade");
        const toast = document.getElementById("successToast");
        overlay.style.display = 'block';
        toast.classList.add("show");
        toast.style.display = 'block';

        setTimeout(() => {
          overlay.style.display = 'none';
          toast.classList.remove("show");
          toast.style.display = 'none';
        }, 3000);

        // Reset form
        cart = [];
        renderTable();
        document.getElementById('barcodeInput').value = '';
        document.getElementById('productSelect').innerHTML = '<option value="">Select Product</option>';
        document.getElementById('productSelect').disabled = true;
        document.getElementById('categorySelect').value = '';
        document.getElementById('qtyInput').value = 1;
        document.getElementById('customer_name').value = '';
      });
  });
  </script>



</body>

</html>