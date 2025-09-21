<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_GET['sale_id'])) {
    die("Sale ID missing.");
}
$sale_id = (int)$_GET['sale_id'];

$sale_stmt = $conn->prepare("SELECT * FROM sales WHERE sale_id = ?");
$sale_stmt->bind_param("i", $sale_id);
$sale_stmt->execute();
$sale = $sale_stmt->get_result()->fetch_assoc();

if (!$sale) die("Sale not found.");

// Join products to get product_name
$item_stmt = $conn->prepare("SELECT si.*, p.product_name FROM sale_items si JOIN products p ON si.product_id = p.product_id WHERE si.sale_id = ?");
$item_stmt->bind_param("i", $sale_id);
$item_stmt->execute();
$items = $item_stmt->get_result();

$subtotal = 0;
$tax = 0;
?>
<!DOCTYPE html>
<html>
<head>
  <title>Invoice #<?= htmlspecialchars($sale['invoice_id']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 bg-white p-4 shadow">
 <h2>Invoice #<?= htmlspecialchars($sale['invoice_id'] ?? '') ?></h2>
<?php if (!empty($sale['customer_name'])): ?>
  <p>Customer: <?= htmlspecialchars($sale['customer_name']) ?></p>
<?php endif; ?>
<?php if (!empty($sale['sale_date'])): ?>
  <p>Date: <?= date('d-m-Y H:i', strtotime($sale['sale_date'])) ?></p>
<?php endif; ?>

  
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
      <?php $i=1; while($row = $items->fetch_assoc()):
          $amount = $row['price'] * $row['quantity'];
          $item_tax = $amount * ($row['gst_percent'] / 100);
          $subtotal += $amount;
          $tax += $item_tax;
      ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['product_name']) ?></td>
        <td><?= $row['quantity'] ?></td>
        <td>₹<?= number_format($row['price'], 2) ?></td>
        <td><?= $row['gst_percent'] ?>%</td>
        <td>₹<?= number_format($amount + $item_tax, 2) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <div class="text-end">
    <p><strong>Subtotal:</strong> ₹<?= number_format($subtotal, 2) ?></p>
    <p><strong>Tax:</strong> ₹<?= number_format($tax, 2) ?></p>
    <p><strong>Total:</strong> ₹<?= number_format($subtotal + $tax, 2) ?></p>
    <a href="sales.php" class="btn btn-primary mt-3">Okay</a>
  </div>
</div>
</body>
</html>
