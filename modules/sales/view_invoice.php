<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_GET['sale_id'])) {
    die("Sale ID missing.");
}
$sale_id = (int)$_GET['sale_id'];

$sale_stmt = $conn->prepare("SELECT * FROM sales WHERE sale_id = ?");
$sale_stmt->bind_param("i", $sale_id);
$sale_stmt->execute();
$sale = $sale_stmt->get_result()->fetch_assoc();

// detect availability of product_name in sale_items
$has_product_name = false;
$col_stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'product_name'");
if ($col_stmt) {
  $col_stmt->execute();
  $col_stmt->bind_result($col_count);
  $col_stmt->fetch();
  $col_stmt->close();
  $has_product_name = ($col_count > 0);
}

$product_name_expr = $has_product_name
  ? "COALESCE(si.product_name, p.product_name, CONCAT('Deleted product (ID:', si.product_id, ')'))"
  : "COALESCE(p.product_name, CONCAT('Deleted product (ID:', si.product_id, ')'))";

// Join products to get product_name
$item_stmt = $conn->prepare("SELECT si.*, " . $product_name_expr . " AS product_name FROM sale_items si LEFT JOIN products p ON si.product_id = p.product_id WHERE si.sale_id = ?");

if (!$sale) die("Sale not found.");

// Fetch store info so we can display store name and optionally the store email
$store = [];
if (isset($_SESSION['store_id'])) {
  $sstmt = $conn->prepare("SELECT store_name, store_email, contact_number, billing_fields FROM stores WHERE store_id = ? LIMIT 1");
  $sstmt->bind_param('i', $_SESSION['store_id']);
  $sstmt->execute();
  $store = $sstmt->get_result()->fetch_assoc() ?: [];
  $sstmt->close();
}

// Decode billing fields
$billing_fields = [];
if (!empty($store['billing_fields'])) {
  $dec = json_decode($store['billing_fields'], true);
  if (is_array($dec)) $billing_fields = $dec;
}

$contactText = !empty($store['contact_number']) ? 'Contact: '.htmlspecialchars($store['contact_number']) : '';
$emailText = (!empty($billing_fields['print_store_email']) && !empty($store['store_email'])) ? ('Email: '.htmlspecialchars($store['store_email'])) : '';

// Join products to get product_name, but prefer stored name in sale_items if product was deleted
$item_stmt = $conn->prepare("SELECT si.*, " . $product_name_expr . " AS product_name FROM sale_items si LEFT JOIN products p ON si.product_id = p.product_id WHERE si.sale_id = ?");
$item_stmt->bind_param("i", $sale_id);
$item_stmt->execute();
$items = $item_stmt->get_result();

$subtotal = 0;
$tax = 0;
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Invoice #<?= htmlspecialchars($sale['invoice_id']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 bg-white p-4 shadow">
 <?php if (!empty($store['store_name'])): ?>
   <div class="text-center">
     <h3><?= htmlspecialchars($store['store_name']) ?></h3>
     <p><?= trim($emailText.($emailText && $contactText ? ' | ' : '') . $contactText) ?></p>
   </div>
 <?php endif; ?>

 <h2>Invoice #<?= htmlspecialchars($sale['invoice_id'] ?? '') ?></h2>
<?php if (!empty($sale['customer_name'])): ?>
  <p>Customer: <?= htmlspecialchars($sale['customer_name']) ?></p>
<?php endif; ?>
<?php if (!empty($sale['sale_date'])): ?>
  <p>Date: <?= date('d-m-Y H:i', strtotime($sale['sale_date'])) ?></p>
<?php endif; ?>

<?php
// Biller name (who created the sale)
$biller_name = '';
if (!empty($sale['created_by'])) {
  $u_stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
  $u_stmt->bind_param("i", $sale['created_by']);
  $u_stmt->execute();
  $u = $u_stmt->get_result()->fetch_assoc();
  if ($u) $biller_name = $u['username'];
  $u_stmt->close();
}
if (!empty($biller_name)) {
  echo '<p>Biller: ' . htmlspecialchars($biller_name) . '</p>';
}
?>

  <table class="table table-bordered text-center">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Product</th>
        <th>Qty</th>
        <th>Rate (excl GST)</th>
        <th>GST%</th>
        <th>Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php $i=1; while($row = $items->fetch_assoc()):
          $amount_inc = $row['total_price']; // inclusive line total
          $item_tax = $amount_inc * ($row['gst_percent'] / (100 + $row['gst_percent']));
          $amount_excl = $amount_inc - $item_tax;
          $unit_excl = ($row['quantity'] > 0) ? ($amount_excl / $row['quantity']) : 0;
          $subtotal += $amount_excl;
          $tax += $item_tax;
      ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['product_name']) ?></td>
        <td><?= $row['quantity'] ?></td>
        <td>₹<?= number_format($unit_excl, 2) ?></td>
        <td><?= $row['gst_percent'] ?>%</td>
        <td>₹<?= number_format($amount_inc, 2) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <div class="text-end">
    <p><strong>Subtotal:</strong> ₹<?= number_format($subtotal, 2) ?></p>
    <p><strong>Tax:</strong> ₹<?= number_format($tax, 2) ?></p>
    <p><strong>Total (incl. GST):</strong> ₹<?= number_format($subtotal + $tax, 2) ?></p>
    <a href="sales.php" class="btn btn-primary mt-3">Okay</a>
  </div>
</div>
</body>
</html>
