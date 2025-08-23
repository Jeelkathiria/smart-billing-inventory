<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/fpdf/fpdf.php';

if (!isset($_GET['sale_id'])) {
    http_response_code(400);
    exit('Missing sale_id');
}
$sale_id = (int)$_GET['sale_id'];

// Fetch sale and items
$sale = $conn->query("SELECT * FROM sales WHERE id = $sale_id")->fetch_assoc();
$items = $conn->query("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.product_id WHERE si.sale_id = $sale_id");

// Generate PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont("Arial", "B", 16);
$pdf->Cell(0, 10, "Invoice #$sale_id", 0, 1, "C");
$pdf->SetFont("Arial", "", 12);
$pdf->Cell(0, 10, "Customer: ".$sale['customer_name'], 0, 1);
$pdf->Cell(0, 10, "Date: ".$sale['created_at'], 0, 1);

// table header
$pdf->Ln(10);
$pdf->SetFont("Arial","B",12);
$pdf->Cell(80,10,"Product",1);
$pdf->Cell(30,10,"Qty",1);
$pdf->Cell(40,10,"Price",1);
$pdf->Cell(40,10,"Total",1);
$pdf->Ln();

$pdf->SetFont("Arial","",12);
while($row = $items->fetch_assoc()) {
    $pdf->Cell(80,10,$row['name'],1);
    $pdf->Cell(30,10,$row['quantity'],1);
    $pdf->Cell(40,10,number_format($row['unit_price'],2),1);
    $pdf->Cell(40,10,number_format($row['total_price'],2),1);
    $pdf->Ln();
}

$pdf->Ln(5);
$pdf->Cell(150,10,"Subtotal",1);
$pdf->Cell(40,10,number_format($sale['subtotal'],2),1);
$pdf->Ln();
$pdf->Cell(150,10,"Tax",1);
$pdf->Cell(40,10,number_format($sale['tax'],2),1);
$pdf->Ln();
$pdf->Cell(150,10,"Total",1);
$pdf->Cell(40,10,number_format($sale['total'],2),1);

$pdf->Output('D', "Invoice_{$sale_id}.pdf"); // outputs as download
