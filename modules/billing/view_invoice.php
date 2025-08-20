<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_GET['invoice_id'])) {
    die("No invoice ID provided.");
}

$invoice_id = $_GET['invoice_id'];

// Fetch invoice details
$invoice_stmt = $conn->prepare("SELECT * FROM sales WHERE invoice_id = ?");
$invoice_stmt->bind_param("s", $invoice_id);
$invoice_stmt->execute();
$invoice_result = $invoice_stmt->get_result();
$invoice = $invoice_result->fetch_assoc();

if (!$invoice) {
    die("Invoice not found.");
}

$sale_id = $invoice['sale_id']; // For fetching items
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice #<?php echo htmlspecialchars($invoice['invoice_id']); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
    }
    .invoice-box {
      background: white;
      padding: 30px;
      border: 1px solid #dee2e6;
      max-width: 800px;
      margin: auto;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }
    .invoice-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .invoice-header h2 {
      margin-bottom: 5px;
    }
    .table td, .table th {
      vertical-align: middle;
    }
    .total-row {
      font-weight: bold;
    }
    .text-end strong {
      font-size: 1.1rem;
    }
  </style>
</head>

<body>
  <div class="container mt-5">
    <div class="invoice-box">
      <div class="invoice-header">
        <h2>INVOICE</h2>
        <p><strong>Invoice ID:</strong> <?php echo htmlspecialchars($invoice['invoice_id']); ?></p>
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['customer_name']); ?></p>
        <p><strong>Date:</strong> <?php echo date('d-m-Y H:i', strtotime($invoice['sale_date'])); ?></p>
      </div>

      <table class="table table-bordered text-center">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Product</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>GST%</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $item_sql = "SELECT * FROM sale_items WHERE sale_id = ?";
          $item_stmt = $conn->prepare($item_sql);
          $item_stmt->bind_param("i", $sale_id);
          $item_stmt->execute();
          $items = $item_stmt->get_result();

          $i = 1;
          $subtotal = 0;
          $tax = 0;

          while ($row = $items->fetch_assoc()) {
              $amount = $row['quantity'] * $row['price'];
              $item_tax = $amount * ($row['gst_percent'] / 100);

              $subtotal += $amount;
              $tax += $item_tax;

              echo "<tr>
                      <td>{$i}</td>
                      <td>" . htmlspecialchars($row['product_name']) . "</td>
                      <td>{$row['quantity']}</td>
                      <td>₹" . number_format($row['price'], 2) . "</td>
                      <td>{$row['gst_percent']}%</td>
                      <td>₹" . number_format($amount, 2) . "</td>
                    </tr>";
              $i++;
          }

          $total = $subtotal + $tax;
          ?>
        </tbody>
      </table>

      <div class="text-end mt-3">
        <p><strong>Subtotal:</strong> ₹<?php echo number_format($subtotal, 2); ?></p>
        <p><strong>Tax:</strong> ₹<?php echo number_format($tax, 2); ?></p>
        <p class="total-row"><strong>Total:</strong> ₹<?php echo number_format($total, 2); ?></p>
      </div>
    </div>
  </div>
</body>
</html>
