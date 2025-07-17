<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

// Initialize cart if empty
if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}

// Handle Remove Item
if (isset($_GET['remove'])) {
  $remove_index = (int) $_GET['remove'];
  if (isset($_SESSION['cart'][$remove_index])) {
    unset($_SESSION['cart'][$remove_index]);
    $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
  }
}

// Calculate Total Bill
$total_amount = 0;
foreach ($_SESSION['cart'] as $item) {
  $total_amount += $item['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Cart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h3 class="mb-4">🛒 Your Cart</h3>

  <?php if (count($_SESSION['cart']) > 0) { ?>
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Product</th>
          <th>Price</th>
          <th>GST (%)</th>
          <th>Quantity</th>
          <th>Total</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($_SESSION['cart'] as $index => $item) { ?>
          <tr>
            <td><?= $index + 1 ?></td>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td>₹<?= number_format($item['price'], 2) ?></td>
            <td><?= $item['gst'] ?>%</td>
            <td><?= $item['quantity'] ?></td>
            <td>₹<?= number_format($item['total'], 2) ?></td>
            <td>
              <a href="?remove=<?= $index ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remove this item?')">Remove</a>
            </td>
          </tr>
        <?php } ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="5" class="text-end">Grand Total:</th>
          <th>₹<?= number_format($total_amount, 2) ?></th>
          <th></th>
        </tr>
      </tfoot>
    </table>

    <a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>

  <?php } else { ?>
    <p class="text-center text-muted">🛒 Your cart is empty.</p>
  <?php } ?>

</div>
</body>
</html>
