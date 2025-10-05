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
$storeQuery = $conn->prepare("SELECT store_name, store_email, contact_number, gstin FROM stores WHERE store_id=?");
$storeQuery->bind_param("i", $store_id);
$storeQuery->execute();
$store = $storeQuery->get_result()->fetch_assoc();
$storeQuery->close();

// ---------------------------
// FETCH SALE ITEMS
// ---------------------------
$stmt = $conn->prepare("
    SELECT si.*, p.product_name 
    FROM sale_items si 
    JOIN products p ON p.product_id = si.product_id
    WHERE si.sale_id=? AND si.store_id=?
");
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
$pdf->Cell(0,5,'Email: '.$store['store_email'].' | Contact: '.$store['contact_number'],0,1,'C');
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
$pdf->Cell(25,8,'Price',1,0,'C',true);
$pdf->Cell(25,8,'GST%',1,0,'C',true);
$pdf->Cell(30,8,'Total',1,1,'C',true);

$pdf->SetFont('DejaVu','',11);
foreach($items as $i=>$item){
    $amount = $item['price'] * $item['quantity'];
    $tax_amount = $amount * ($item['gst_percent']/100);
    $pdf->Cell(10,8,$i+1,1,0,'C');
    $pdf->Cell(90,8,$item['product_name'],1,0);
    $pdf->Cell(20,8,$item['quantity'],1,0,'C');
    $pdf->Cell(25,8,'₹'.number_format($item['price'],2),1,0,'R');
    $pdf->Cell(25,8,$item['gst_percent'].'%',1,0,'C');
    $pdf->Cell(30,8,'₹'.number_format($amount+$tax_amount,2),1,1,'R');
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

$pdf->Cell(150,10,'Total:',0,0,'R');
$pdf->Cell(40,10,'₹'.number_format($total,2),1,1,'R');

// ---------------------------
// FOOTER
// ---------------------------
$pdf->Ln(5);
$pdf->SetFont('DejaVu','',10);
$pdf->MultiCell(0,5,"Thank you for your purchase!\nThis is a computer-generated invoice.",0,'C');

// ---------------------------
// OUTPUT PDF
// ---------------------------
$pdf->Output($download ? 'D' : 'I','Invoice_'.$sale['invoice_id'].'.pdf');
