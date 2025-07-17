<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

// Add to Cart
if (isset($_POST['add_to_cart'])) {
  $product_id = (int) $_POST['product_id'];
  $quantity = (int) $_POST['quantity'];

  $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
  $stmt->bind_param("i", $product_id);
  $stmt->execute();
  $product = $stmt->get_result()->fetch_assoc();

  if ($product && $quantity > 0 && $quantity <= $product['stock']) {
    $item = [
      'id' => $product['product_id'],
      'name' => $product['name'],
      'price' => $product['price'],
      'gst' => $product['gst_percent'],
      'total_price' => $product['total_price'],
      'quantity' => $quantity
    ];
    $_SESSION['cart'][] = $item;
  }
}

// Remove from Cart
if (isset($_GET['remove'])) {
  $remove_index = (int) $_GET['remove'];
  if (isset($_SESSION['cart'][$remove_index])) {
    unset($_SESSION['cart'][$remove_index]);
  }
}

// Fetch Products
$products = $conn->query("SELECT * FROM products");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Billing</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <h3 class="mb-4">🧾 Billing Page</h3>

  <form method="POST" class="row g-3 mb-4">
    <div class="col-md-5">
      <select name="product_id" class="form-select" required>
        <option value="">Select Product</option>
        <?php while ($prod = $products->fetch_assoc()) { ?>
          <option value="<?= $prod['product_id'] ?>"> <?= htmlspecialchars($prod['name']) ?> (₹<?= number_format($prod['total_price'], 2) ?>)</option>
        <?php } ?>
      </select>
    </div>
    <div class="col-md-3">
      <input type="number" name="quantity" class="form-control" placeholder="Quantity" min="1" required>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100" name="add_to_cart">Add to Cart</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Cart Items</h5>
      <?php if (!empty($_SESSION['cart'])) { ?>
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Product</th>
              <th>Price</th>
              <th>GST (%)</th>
              <th>Quantity</th>
              <th>Total</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $grand_total = 0; foreach ($_SESSION['cart'] as $index => $item) {
              $item_total = $item['total_price'] * $item['quantity'];
              $grand_total += $item_total;
            ?>
              <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td>₹<?= number_format($item['price'], 2) ?></td>
                <td><?= $item['gst'] ?>%</td>
                <td><?= $item['quantity'] ?></td>
                <td>₹<?= number_format($item_total, 2) ?></td>
                <td><a href="?remove=<?= $index ?>" class="btn btn-sm btn-danger">Remove</a></td>
              </tr>
            <?php } ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="4" class="text-end">Grand Total:</th>
              <th colspan="2">₹<?= number_format($grand_total, 2) ?></th>
            </tr>
          </tfoot>
        </table>
        <a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>
      <?php } else { ?>
        <p class="text-muted">No products in cart.</p>
      <?php } ?>
    </div>
  </div>
</div>
</body>
</html>
