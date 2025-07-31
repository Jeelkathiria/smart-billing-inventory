<?php
require_once '../includes/db.php';

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$query = $conn->prepare("SELECT * FROM sales ORDER BY sale_date DESC LIMIT 8 OFFSET ?");
$query->bind_param("i", $offset);
$query->execute();
$result = $query->get_result();

while ($sale = $result->fetch_assoc()):
?>
  <tr>
    <td><span class="badge bg-secondary"><?= $sale['invoice_id']; ?></span></td>
    <td><?= htmlspecialchars($sale['customer_name']); ?></td>
    <td><?= date('d-m-Y H:i:s', strtotime($sale['sale_date'])); ?></td>
    <td><strong>₹<?= number_format($sale['total_amount'], 2); ?></strong></td>
  </tr>
<?php endwhile; ?>
