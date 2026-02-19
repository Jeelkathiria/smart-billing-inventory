<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/fpdf/tfpdf.php';
session_start();

// ---------------------------
// AUTH CHECK
// ---------------------------
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    die("Unauthorized");
}

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if (!$sale_id) die("Sale ID missing");

$store_id = $_SESSION['store_id'];

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

// ---------------------------
// FETCH SALE INFO
// ---------------------------
$stmt = $conn->prepare("SELECT * FROM sales WHERE sale_id=? AND store_id=?");
$stmt->bind_param("ii", $sale_id, $store_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) die("Sale not found");

// ---------------------------
// FETCH STORE INFO
// ---------------------------
$cols = ['store_name','store_email','contact_number','gstin','billing_fields'];
$resA = $conn->query("SHOW COLUMNS FROM stores LIKE 'store_address'");
if ($resA && $resA->num_rows) $cols[] = 'store_address';
$resB = $conn->query("SHOW COLUMNS FROM stores LIKE 'note'");
if ($resB && $resB->num_rows) $cols[] = 'note';
// Fallback to old 'notice' column if it exists
$resB = $conn->query("SHOW COLUMNS FROM stores LIKE 'notice'");
if ($resB && $resB->num_rows && !in_array('notice', $cols)) $cols[] = 'notice';
$colsSql = implode(', ', $cols);
$storeQuery = $conn->prepare("SELECT $colsSql FROM stores WHERE store_id=?");
$storeQuery->bind_param("i", $store_id);
$storeQuery->execute();
$store = $storeQuery->get_result()->fetch_assoc();
$storeQuery->close();

// ---------------------------
// FETCH SALE ITEMS
// ---------------------------
$stmt = $conn->prepare(
    "SELECT si.*, " . $product_name_expr . " AS product_name \n    FROM sale_items si \n    LEFT JOIN products p ON p.product_id = si.product_id\n    WHERE si.sale_id=? AND si.store_id=?"
);
$stmt->bind_param("ii", $sale_id, $store_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------------------------
// DECODE BILLING META
// ---------------------------
$billing_meta = !empty($sale['billing_meta']) ? json_decode($sale['billing_meta'], true) : [];

// ---------------------------
// CREATE PDF
// ---------------------------
$download = isset($_GET['download']) && $_GET['download'] == 1;
$pdf = new tFPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

// Add UTF-8 font
$pdf->AddFont('DejaVu','','DejaVuSans.ttf',true);
$pdf->AddFont('DejaVu','B','DejaVuSans-Bold.ttf',true);

// ---------------------------
// HEADER
// ---------------------------
$pdf->SetFont('DejaVu','B',16);
$pdf->Cell(0,10, $store['store_name'],0,1,'C');

$pdf->SetFont('DejaVu','',10);
// Decode billing_fields and decide whether to print store email
$billing_fields = [];
if (!empty($store['billing_fields'])) {
    $decoded = json_decode($store['billing_fields'], true);
    if (is_array($decoded)) $billing_fields = $decoded;
}
$contactText = 'Contact: '.($store['contact_number'] ?? '');
$emailText = (!empty($billing_fields['print_store_email']) && !empty($store['store_email'])) ? ('Email: '.($store['store_email'])) : '';
$pdf->Cell(0,5,trim($emailText.($emailText && $contactText ? ' | ' : '') . $contactText),0,1,'C');
// Print address if available
if (!empty($store['store_address'])) {
    $pdf->SetFont('DejaVu','',9);
    $pdf->MultiCell(0,5, $store['store_address'],0,'C');
    $pdf->SetFont('DejaVu','',10);
}
if(!empty($store['gstin'])) $pdf->Cell(0,5,'GSTIN: '.$store['gstin'],0,1,'C');

$pdf->Ln(5);
$pdf->SetLineWidth(0.5);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(3);

// ---------------------------
// INVOICE INFO
// ---------------------------
$pdf->SetFont('DejaVu','B',12);
$pdf->Cell(0,6,'Invoice No: '.$sale['invoice_id'],0,1,'L');
$pdf->SetFont('DejaVu','',11);
$pdf->Cell(0,6,'Date: '.date('d-m-Y H:i', strtotime($sale['sale_date'])),0,1,'L');

if(!empty($billing_meta['customer_name'])) $pdf->Cell(0,5,'Customer: '.$billing_meta['customer_name'],0,1,'L');
if(!empty($billing_meta['customer_mobile'])) $pdf->Cell(0,5,'Mobile: '.$billing_meta['customer_mobile'],0,1,'L');

// Biller: fetch from sales.created_by (username)
$biller_name = '';
if (!empty($sale['created_by'])) {
    $uq = $conn->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
    $uq->bind_param("i", $sale['created_by']);
    $uq->execute();
    $ur = $uq->get_result()->fetch_assoc();
    if ($ur) $biller_name = $ur['username'];
    $uq->close();
}
if (!empty($biller_name)) {
    $pdf->Cell(0,5,'Biller: '.$biller_name,0,1,'L');
}

$pdf->Ln(3);
$pdf->SetLineWidth(0.3);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(2);

// ---------------------------
// ITEMS TABLE
// ---------------------------
$pdf->SetFont('DejaVu','B',11);
$pdf->SetFillColor(230,230,230);
$pdf->Cell(10,8,'#',1,0,'C',true);
$pdf->Cell(90,8,'Product',1,0,'C',true);
$pdf->Cell(20,8,'Qty',1,0,'C',true);
$pdf->Cell(25,8,'Price (excl GST)',1,0,'C',true);
$pdf->Cell(25,8,'GST%',1,0,'C',true);
$pdf->Cell(30,8,'Total',1,1,'C',true);

$pdf->SetFont('DejaVu','',11);
foreach($items as $i=>$item){
    $amount_inc = $item['total_price']; // inclusive
    $tax_amount = $amount_inc * ($item['gst_percent'] / (100 + $item['gst_percent']));
    $amount_excl = $amount_inc - $tax_amount;
    $unit_excl = ($item['quantity'] > 0) ? ($amount_excl / $item['quantity']) : 0;
    $pdf->Cell(10,8,$i+1,1,0,'C');
    $pdf->Cell(90,8,$item['product_name'],1,0);
    $pdf->Cell(20,8,$item['quantity'],1,0,'C');
    $pdf->Cell(25,8,'₹'.number_format($unit_excl,2),1,0,'R');
    $pdf->Cell(25,8,$item['gst_percent'].'%',1,0,'C');
    $pdf->Cell(30,8,'₹'.number_format($amount_inc,2),1,1,'R');
}
$pdf->Ln(3);

// ---------------------------
// TOTALS (from sales table)
// ---------------------------
$pdf->SetFont('DejaVu','B',12);
$subtotal = floatval($sale['subtotal'] ?? 0);
$tax = floatval($sale['tax_amount'] ?? 0);
$total = floatval($sale['total_amount'] ?? 0);

$pdf->Cell(150,8,'Subtotal:',0,0,'R');
$pdf->Cell(40,8,'₹'.number_format($subtotal,2),1,1,'R');

$pdf->Cell(150,8,'Tax:',0,0,'R');
$pdf->Cell(40,8,'₹'.number_format($tax,2),1,1,'R');

$pdf->Cell(150,10,'Total (incl. GST):',0,0,'R');
$pdf->Cell(40,10,'₹'.number_format($total,2),1,1,'R');

// ---------------------------
// FOOTER
// ---------------------------
$pdf->Ln(5);
$pdf->SetFont('DejaVu','',10);
$pdf->MultiCell(0,5,"Thank you for your purchase!\nThis is a computer-generated invoice.",0,'C');

// Print invoice note if present
$invoiceNote = $store['note'] ?? $store['notice'] ?? '';
if (!empty($invoiceNote)) {
    $pdf->Ln(3);
    $pdf->SetFont('DejaVu','',9);
    $pdf->MultiCell(0,5, 'Note: ' . $invoiceNote, 0, 'L');
}

// ---------------------------
// OUTPUT PDF
// ---------------------------
$pdf->Output($download ? 'D' : 'I','Invoice_'.$sale['invoice_id'].'.pdf');
