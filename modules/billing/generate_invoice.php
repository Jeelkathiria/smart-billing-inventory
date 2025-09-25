<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/fpdf/fpdf.php';
session_start();

// Ensure authentication
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    die("Unauthorized");
}

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if (!$sale_id) die("Sale ID missing");

$store_id = $_SESSION['store_id'];

/* -----------------------------
   1. Fetch Sale Info
------------------------------ */
$stmt = $conn->prepare("SELECT * FROM sales WHERE sale_id=? AND store_id=?");
$stmt->bind_param("ii", $sale_id, $store_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) die("Sale not found");

/* -----------------------------
   2. Fetch Store Info (with GSTIN)
------------------------------ */
$storeQuery = $conn->prepare("SELECT store_name, store_email, contact_number, gstin 
                              FROM stores WHERE store_id=?");
$storeQuery->bind_param("i", $store_id);
$storeQuery->execute();
$store = $storeQuery->get_result()->fetch_assoc();
$storeQuery->close();

/* -----------------------------
   3. Fetch Sale Items
------------------------------ */
$stmt = $conn->prepare("SELECT si.*, p.product_name 
                        FROM sale_items si 
                        JOIN products p ON p.product_id = si.product_id
                        WHERE si.sale_id=? AND si.store_id=?");
$stmt->bind_param("ii", $sale_id, $store_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* -----------------------------
   4. Decode Billing Meta
------------------------------ */
$billing_meta = !empty($sale['billing_meta']) 
    ? json_decode($sale['billing_meta'], true) 
    : [];


/* -----------------------------
   5. Create PDF Invoice
------------------------------ */
$download = isset($_GET['download']) && $_GET['download'] == 1;
$pdf = new FPDF();
$pdf->AddPage();

// Store Header
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, $store['store_name'], 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'Email: ' . $store['store_email'], 0, 1, 'C');
$pdf->Cell(0, 8, 'Contact: ' . $store['contact_number'], 0, 1, 'C');

if (!empty($store['gstin'])) {
    $pdf->Cell(0, 8, 'GSTIN: ' . $store['gstin'], 0, 1, 'C');
}
$pdf->Ln(10);

// Invoice Info
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Invoice: ' . $sale['invoice_id'], 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Ln(5);
$pdf->Cell(0, 8, 'Customer Name: ' . ($billing_meta['customer_name'] ?? '--'), 0, 1);
$pdf->Cell(0, 8, 'Mobile: ' . ($billing_meta['customer_mobile'] ?? '--'), 0, 1);
$pdf->Cell(0, 8, 'Address: ' . ($billing_meta['customer_address'] ?? '--'), 0, 1);
$pdf->Cell(0, 8, 'Table/Order No: ' . ($billing_meta['table_no'] ?? '--'), 0, 1);
$pdf->Ln(5);

// Items Table - Header
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(10, 8, 'S.No', 1);
$pdf->Cell(80, 8, 'Product', 1);
$pdf->Cell(20, 8, 'Qty', 1);
$pdf->Cell(30, 8, 'Price', 1);
$pdf->Cell(30, 8, 'Total', 1);
$pdf->Ln();

// GSTIN - Only if exists
if (!empty($store['gstin'])) {
    $pdf->Ln(5);
    $pdf->Cell(0, 8, 'GSTIN: ' . utf8_decode($store['gstin']), 0, 1, 'R');
}
// Items Table - Content
$pdf->SetFont('Arial', '', 12);
foreach ($items as $i => $item) {
    $pdf->Cell(10, 8, $i + 1, 1);
    $pdf->Cell(80, 8, $item['product_name'], 1);
    $pdf->Cell(20, 8, $item['quantity'], 1);
    $pdf->Cell(30, 8, '₹' . number_format($item['price'], 2), 1);
    $pdf->Cell(30, 8, '₹' . number_format($item['price'] * $item['quantity'], 2), 1);
    $pdf->Ln();
}

// Totals
$pdf->Ln(5);
$tax = floatval($sale['tax'] ?? 0);
$subtotal = floatval($sale['total_amount'] - $tax);

$pdf->Cell(0, 8, 'Subtotal: ₹' . number_format($subtotal, 2), 0, 1, 'R');
$pdf->Cell(0, 8, 'Tax: ₹' . number_format($tax, 2), 0, 1, 'R');
$pdf->Cell(0, 8, 'Total: ₹' . number_format($sale['total_amount'], 2), 0, 1, 'R');


/* -----------------------------
   6. Output PDF
------------------------------ */
$pdf->Output($download ? 'D' : 'I', 'Invoice_' . $sale['invoice_id'] . '.pdf');
