<?php
require_once '../includes/db.php';

if (!isset($_GET['sale_id'])) {
    die("Invalid access.");
}

$sale_id = intval($_GET['sale_id']);
$sql = "SELECT * FROM sales WHERE sale_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invoice not found.");
}

$invoice = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice #<?php echo htmlspecialchars($invoice['invoice_id']); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <a href="sales.php" class="btn btn-outline-secondary mb-4">
      <i class="bi bi-arrow-left"></i> Back to Sales History
    </a>

    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">
        <h4 class="mb-0">🧾 Invoice #<?php echo htmlspecialchars($invoice['invoice_id']); ?></h4>
      </div>
      <div class="card-body">
        <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($invoice['customer_name']); ?></p>
        <p><strong>Date & Time:</strong> <?php echo date('d-m-Y H:i:s', strtotime($invoice['sale_date'])); ?></p>
        <p><strong>Total Amount:</strong> ₹<?php echo number_format($invoice['total_amount'], 2); ?></p>

        <!-- Toggle Sale Details -->
        <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#saleDetails" aria-expanded="false" aria-controls="saleDetails">
          <i class="bi bi-chevron-down"></i> Show Sale Details
        </button>

        <div class="collapse mt-3" id="saleDetails">
          <div class="card card-body border">
            <!-- Placeholder for future sale_items table -->
            <p class="text-muted">Sale item details will appear here when available.</p>
            <!-- Example future structure:
            <ul>
              <li>Product A - 2 pcs - ₹500</li>
              <li>Product B - 1 pc - ₹250</li>
            </ul>
            -->
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle (with Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
