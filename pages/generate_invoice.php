<?php
require_once '../includes/fpdf.php';
require_once '../includes/db.php'; 

// Decode JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items'])) {
    die("Invalid invoice data");
}

// Extract values
$items = $data['items'];
$subTotal = $data['subTotal'];
$tax = $data['tax'];
$total = $data['total'];
$customer_name = $conn->real_escape_string($data['customer_name'] ?? 'Guest');
$date = date("Y-m-d H:i:s"); // current timestamp

// Generate next invoice number
$invoice_prefix = "INV";
$last_invoice = $conn->query("SELECT invoice_id FROM sales ORDER BY sale_id DESC LIMIT 1");

if ($last_invoice && $last_invoice->num_rows > 0) {
    $row = $last_invoice->fetch_assoc();
    $last_number = intval(substr($row['invoice_id'], 3)); // remove 'INV'
    $new_invoice_number = $last_number + 1;
} else {
    $new_invoice_number = 1;
}
$invoice_id = $invoice_prefix . str_pad($new_invoice_number, 3, "0", STR_PAD_LEFT);

// Insert into sales table
$insert_sale = $conn->query("
    INSERT INTO sales (invoice_id, customer_name, total_amount, sale_date)
    VALUES ('$invoice_id', '$customer_name', '$total', '$date')
");

$sale_id = $conn->insert_id;

// Insert each item into sale_items and reduce inventory
foreach ($items as $item) {
    $product_id = (int) $item['id'];
    $product_name = $conn->real_escape_string($item['name']);
    $quantity = (int) $item['qty'];
    $price = (float) $item['rate'];
    $gst_percent = (int) $item['gst'];

    // Check current stock
    $check = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
    $check->bind_param("i", $product_id);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        $current = $result->fetch_assoc();
        $current_stock = (int) $current['stock'];

        if ($current_stock >= $quantity) {
            // Update stock
            $update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
            $update->bind_param("ii", $quantity, $product_id);
            if (!$update->execute()) {
                error_log("Inventory update failed for product_id $product_id: " . $conn->error);
            }

            // Insert into sale_items
            $insert_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, gst_percent)
                                           VALUES (?, ?, ?, ?, ?, ?)");
            $insert_item->bind_param("iisidi", $sale_id, $product_id, $product_name, $quantity, $price, $gst_percent);
            if (!$insert_item->execute()) {
                error_log("Failed to insert sale item for product_id $product_id: " . $conn->error);
            }

        } else {
            // Not enough stock
            error_log("Not enough stock for product ID: $product_id. Requested: $quantity, Available: $current_stock");
            continue;
        }
    } else {
        // Product not found
        error_log("Product ID $product_id not found in database.");
        continue;
    }
}



// === PDF Generation ===
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Invoice ID: $invoice_id", 0, 1);
$pdf->Cell(0, 10, "Customer: $customer_name", 0, 1);
$pdf->Cell(0, 10, 'Date: ' . date("d-m-Y H:i", strtotime($date)), 0, 1);
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(10, 10, '#', 1);
$pdf->Cell(60, 10, 'Product', 1);
$pdf->Cell(20, 10, 'Qty', 1);
$pdf->Cell(30, 10, 'Rate', 1);
$pdf->Cell(20, 10, 'GST%', 1);
$pdf->Cell(40, 10, 'Amount', 1);
$pdf->Ln();

// Table Data
$pdf->SetFont('Arial', '', 12);
foreach ($items as $i => $item) {
    $pdf->Cell(10, 10, $i + 1, 1);
    $pdf->Cell(60, 10, $item['name'], 1);
    $pdf->Cell(20, 10, $item['qty'], 1);
    $pdf->Cell(30, 10, number_format($item['rate'], 2), 1);
    $pdf->Cell(20, 10, $item['gst'], 1);
    $pdf->Cell(40, 10, number_format($item['total'], 2), 1);
    $pdf->Ln();
}

$pdf->Ln(5);

// Summary
$pdf->Cell(140, 10, 'Subtotal', 0, 0, 'R');
$pdf->Cell(40, 10, 'Rs. ' . number_format($subTotal, 2), 0, 1, 'R');

$pdf->Cell(140, 10, 'Tax (5%)', 0, 0, 'R');
$pdf->Cell(40, 10, 'Rs. ' . number_format($tax, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(140, 10, 'Total', 0, 0, 'R');
$pdf->Cell(40, 10, 'Rs. ' . number_format($total, 2), 0, 1, 'R');

// Output PDF to browser
$pdf->Output('I', 'Invoice_' . date("Ymd_His") . '.pdf');
?>