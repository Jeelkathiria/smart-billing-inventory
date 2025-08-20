<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/fpdf/fpdf.php';

// Force JSON response
header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['customer_name'], $data['items'], $data['subTotal'], $data['tax'], $data['total'])) {
        echo json_encode(["status" => "error", "message" => "Invalid input"]);
        exit;
    }

    $customer_name = $data['customer_name'];
    $items = $data['items'];
    $subtotal = $data['subTotal'];
    $tax = $data['tax'];
    $total = $data['total'];

    // Insert into sales table
    $stmt = $conn->prepare("INSERT INTO sales (customer_name, subtotal, tax, total, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sddd", $customer_name, $subtotal, $tax, $total);
    $stmt->execute();
    $sale_id = $stmt->insert_id;
    $stmt->close();

    // Insert products into sales_products + reduce stock
    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, gst_percent, total_price) 
                        VALUES (?, ?, ?, ?, ?, ?)");

    $stockUpdate = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");

    foreach ($items as $item) {
    $product_id = (int)$item['id'];
    $quantity   = (int)$item['qty'];
    $price      = (float)$item['rate'];
    $gst        = isset($item['gst']) ? (float)$item['gst'] : 0;
    $line_total = round(($price + ($price * $gst / 100)) * $quantity, 2);

    $stmt->bind_param("iiiddd", $sale_id, $product_id, $quantity, $price, $gst, $line_total);
    if (!$stmt->execute()) {
        die("Sale item insert failed: " . $stmt->error);
    }

    $stockUpdate->bind_param("ii", $quantity, $product_id);
    if (!$stockUpdate->execute()) {
        die("Stock update failed: " . $stockUpdate->error);
    }
}

    $stmt->close();
    $stockUpdate->close();

    // Generate PDF invoice
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont("Arial", "B", 16);
    $pdf->Cell(0, 10, "Invoice #$sale_id", 0, 1, "C");
    $pdf->Ln(5);
    $pdf->SetFont("Arial", "", 12);
    $pdf->Cell(0, 10, "Customer: " . $customer_name, 0, 1);
    $pdf->Cell(0, 10, "Date: " . date("d-m-Y H:i:s"), 0, 1);

    // Table header
    $pdf->Ln(10);
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(80, 10, "Product", 1);
    $pdf->Cell(30, 10, "Qty", 1);
    $pdf->Cell(40, 10, "Price", 1);
    $pdf->Cell(40, 10, "Total", 1);
    $pdf->Ln();

    // Table rows
    $pdf->SetFont("Arial", "", 12);
    foreach ($items as $item) {
        $line_total = $item['qty'] * $item['rate'];
        $pdf->Cell(80, 10, $item['name'], 1);
        $pdf->Cell(30, 10, $item['qty'], 1);
        $pdf->Cell(40, 10, number_format($item['rate'], 2), 1);
        $pdf->Cell(40, 10, number_format($line_total, 2), 1);
        $pdf->Ln();
    }

    // Totals
    $pdf->Ln(5);
    $pdf->Cell(150, 10, "Subtotal", 1);
    $pdf->Cell(40, 10, number_format($subtotal, 2), 1);
    $pdf->Ln();
    $pdf->Cell(150, 10, "Tax", 1);
    $pdf->Cell(40, 10, number_format($tax, 2), 1);
    $pdf->Ln();
    $pdf->Cell(150, 10, "Total", 1);
    $pdf->Cell(40, 10, number_format($total, 2), 1);

    // Save PDF to includes/invoices/
    $pdf_dir = __DIR__ . '/../../includes/invoices/';
    if (!is_dir($pdf_dir)) {
        mkdir($pdf_dir, 0777, true);
    }
    $pdf_filename = "invoice_" . $sale_id . ".pdf";
    $pdf_path = $pdf_dir . $pdf_filename;
    $pdf->Output("F", $pdf_path);

    // Save relative path into DB
    $relative_path = "includes/invoices/" . $pdf_filename;
    $stmt = $conn->prepare("UPDATE sales SET pdf_path = ? WHERE id = ?");
    $stmt->bind_param("si", $relative_path, $sale_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success", "invoice_id" => $sale_id, "pdf" => $relative_path]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
