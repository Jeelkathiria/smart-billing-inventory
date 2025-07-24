<?php
require_once '../includes/fpdf.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items'])) {
    die("Invalid invoice data");
}

$items = $data['items'];
$subTotal = $data['subTotal'];
$tax = $data['tax'];
$total = $data['total'];

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Invoice Heading
$pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Date: ' . date("d-m-Y H:i"), 0, 1, 'R');
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
$pdf->Cell(40, 10, '₹' . number_format($subTotal, 2), 0, 1, 'R');

$pdf->Cell(140, 10, 'Tax (5%)', 0, 0, 'R');
$pdf->Cell(40, 10, '₹' . number_format($tax, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(140, 10, 'Total', 0, 0, 'R');
$pdf->Cell(40, 10, '₹' . number_format($total, 2), 0, 1, 'R');

// Output PDF
$pdf->Output('I', 'Invoice_' . date("Ymd_His") . '.pdf');  // 'I' = inline display, 'D' = download
?>
