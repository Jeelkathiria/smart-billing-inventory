<?php
require_once '../includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Billing</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
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
  <div class="container mt-4">
    <audio id="successSound"
      src="https://cdn.pixabay.com/download/audio/2022/03/15/audio_7d30f4f34d.mp3?filename=success-1-6297.mp3"
      preload="auto"></audio>




    <?php include '../components/backToDashboard.php'; ?>

    <h3 class="mb-4">🧾 Live Billing</h3>

    <!-- Optional Barcode Scanner -->
    <div class="scanner-box">
      <p>📷 Scan Barcode (optional)</p>
      <input type="text" id="barcodeInput" class="form-control" placeholder="Scan or enter barcode...">
    </div>

    <!-- Select Category and Product -->
    <div class="row mb-3">
      <div class="col-md-4">
        <label>Category</label>
        <select id="categorySelect" class="form-select">
          <option value="">Select Category</option>
          <?php
        $categories = $conn->query("SELECT * FROM categories");
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

        document.getElementById('productSelect').disabled = false;
      });
  });

  // Barcode input logic
  document.getElementById('barcodeInput').addEventListener('input', function() {
    const enteredBarcode = this.value.trim();

    if (enteredBarcode === "") return;

    const productSelect = document.getElementById('productSelect');
    const found = Object.entries(productsList).find(([id, product]) => product.barcode === enteredBarcode);

    if (found) {
      const productId = found[0];
      productSelect.value = productId;
    } else {
      productSelect.value = "";
    }
  });

  document.getElementById('addProductBtn').addEventListener('click', function() {
    const productId = document.getElementById('productSelect').value;
    const qty = parseInt(document.getElementById('qtyInput').value);

    if (!productId || qty < 1) return;

    const product = productsList[productId];
    const rate = parseFloat(product.price);
    const gstPercent = parseFloat(product.gst_percent);
    const baseAmount = qty * rate;
    const gstAmount = baseAmount * (gstPercent / 100);
    const totalAmount = baseAmount + gstAmount;

    cart.push({
      name: product.name,
      qty,
      rate,
      gst: gstPercent,
      total: totalAmount
    });

    renderTable();
    document.getElementById('barcodeInput').value = ''; // clear after adding
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

  // Generate Invoice Button (Auto-download PDF via fetch + blob)
  document.getElementById("generateInvoiceBtn").addEventListener("click", function() {
    if (cart.length === 0) return;

    const invoiceData = {
      items: cart.map(item => ({
        name: item.name,
        qty: item.qty,
        rate: item.rate,
        gst: item.gst,
        total: item.total
      })),
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
        // Download the invoice
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = "Invoice_" + new Date().toISOString().slice(0, 19).replace(/[-T:]/g, "") + ".pdf";
        a.click();
        URL.revokeObjectURL(url);

        // ✅ Play sound
        // Play short success notification sound
        const successSound = document.getElementById("successSound");
        successSound.currentTime = 0;
        successSound.play().catch(() => {
          console.warn("Sound play blocked by browser");
        });

        // Show toast and overlay with animation
        const overlay = document.getElementById("overlayFade");
        const toast = document.getElementById("successToast");
        overlay.style.display = 'block';
        toast.classList.add("show");
        toast.style.display = 'block';

        // Hide after animation completes
        setTimeout(() => {
          overlay.style.display = 'none';
          toast.classList.remove("show");
          toast.style.display = 'none';
        }, 3000);

        // ✅ Reset billing
        cart = [];
        renderTable();
        document.getElementById('barcodeInput').value = '';
        document.getElementById('productSelect').innerHTML = '<option value="">Select Product</option>';
        document.getElementById('productSelect').disabled = true;
        document.getElementById('categorySelect').value = '';
        document.getElementById('qtyInput').value = 1;
      });
  });
  </script>


</body>

</html>