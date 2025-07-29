<?php
ob_start(); // Start output buffering to prevent premature output

require_once '../includes/fpdf.php';
require_once '../includes/db.php';

// Optional: Disable displaying errors, but log them instead
error_reporting(E_ALL);
ini_set("display_errors", 0);
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/pdf_error.log");

// Decode JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items'])) {
    http_response_code(400);
    die("Invalid invoice data");
}

// Extract values
$items = $data['items'];
$subTotal = $data['subTotal'];
$tax = $data['tax'];
$total = $data['total'];
$customer_name = $conn->real_escape_string($data['customer_name'] ?? 'Guest');
$date = date("Y-m-d H:i:s");

// Generate next invoice number
$invoice_prefix = "INV";
$last_invoice = $conn->query("SELECT invoice_id FROM sales ORDER BY sale_id DESC LIMIT 1");

if ($last_invoice && $last_invoice->num_rows > 0) {
    $row = $last_invoice->fetch_assoc();
    $last_number = intval(substr($row['invoice_id'], 3));
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

    $check = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
    $check->bind_param("i", $product_id);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        $current_stock = (int) $result->fetch_assoc()['stock'];

        if ($current_stock >= $quantity) {
            $update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
            $update->bind_param("ii", $quantity, $product_id);
            $update->execute();

            $insert_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, gst_percent)
                                           VALUES (?, ?, ?, ?, ?, ?)");
            $insert_item->bind_param("iisidi", $sale_id, $product_id, $product_name, $quantity, $price, $gst_percent);
            $insert_item->execute();
        } else {
            error_log("Not enough stock for product ID: $product_id. Requested: $quantity, Available: $current_stock");
        }
    } else {
        error_log("Product ID $product_id not found.");
    }
}

// === PDF Generation ===
ob_end_clean(); // Clean output buffer before sending PDF

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Invoice ID: $invoice_id", 0, 1);
$pdf->Cell(0, 10, "Customer: $customer_name", 0, 1);
$pdf->Cell(0, 10, 'Date: ' . date("d-m-Y H:i", strtotime($date)), 0, 1);
$pdf->Ln(5);

// Table Headers
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(10, 10, '#', 1);
$pdf->Cell(60, 10, 'Product', 1);
$pdf->Cell(20, 10, 'Qty', 1);
$pdf->Cell(30, 10, 'Rate', 1);
$pdf->Cell(20, 10, 'GST%', 1);
$pdf->Cell(40, 10, 'Amount', 1);
$pdf->Ln();

// Table Rows
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

// Totals
$pdf->Cell(140, 10, 'Subtotal', 0, 0, 'R');
$pdf->Cell(40, 10, 'Rs. ' . number_format($subTotal, 2), 0, 1, 'R');

$pdf->Cell(140, 10, 'Tax (5%)', 0, 0, 'R');
$pdf->Cell(40, 10, 'Rs. ' . number_format($tax, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(140, 10, 'Total', 0, 0, 'R');
$pdf->Cell(40, 10, 'Rs. ' . number_format($total, 2), 0, 1, 'R');

// Output to browser
// Create directory if not exists
$pdfDir = '../invoices/';
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0777, true); // Create folder recursively
}

$pdfFilename = 'Invoice_' . $invoice_id . '.pdf';
$pdfPath = $pdfDir . $pdfFilename;

$pdf->Output('F', $pdfPath); // Save PDF to file

// Store relative path in database
$relativePath = 'invoices/' . $pdfFilename;
$escapedPath = $conn->real_escape_string($relativePath);
$conn->query("UPDATE sales SET pdf_path = '$escapedPath' WHERE sale_id = $sale_id");

// (Optional) Stream PDF to browser after saving
$pdf->Output('I', $pdfFilename);
exit;

