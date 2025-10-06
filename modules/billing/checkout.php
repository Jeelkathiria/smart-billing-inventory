<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Always return JSON
header('Content-Type: application/json');

// Enable errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ==================================================
   1. AUTH CHECK
================================================== */
if (!isset($_SESSION['user_id'], $_SESSION['store_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id  = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

/* ==================================================
   2. READ STORE SETTINGS
================================================== */
$store_stmt = $conn->prepare("SELECT billing_fields FROM stores WHERE store_id=?");
$store_stmt->bind_param("i", $store_id);
$store_stmt->execute();
$store_res = $store_stmt->get_result();
$store = $store_res->fetch_assoc();
$storeFields = json_decode($store['billing_fields'], true) ?: [];
$store_stmt->close();

/* ==================================================
   3. READ & VALIDATE INPUT
================================================== */
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
    exit();
}

// Collect only enabled customer fields
$customerData = [];
foreach($storeFields as $field => $enabled){
    if($enabled && isset($data[$field])){
        $customerData[$field] = trim($data[$field]);
    }
}

// Collect items
$items = [];
foreach ($data['items'] as $item) {
    $items[] = [
        'product_id'  => (int)$item['product_id'],
        'quantity'    => (int)$item['quantity'],
        'price'       => (float)$item['price'],
        'gst_percent' => isset($item['gst_percent']) ? (float)$item['gst_percent'] : 0
    ];
}

/* ==================================================
   4. CALCULATE TOTALS FROM PRODUCTS (NOT FRONTEND)
================================================== */
$subtotal = 0;
$total_tax = 0;

// Get all product IDs from cart
$product_ids = array_column($items, 'product_id');
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$types = str_repeat('i', count($product_ids));

// Fetch product info from DB
$stmt = $conn->prepare("SELECT product_id, price, gst_percent, stock FROM products WHERE product_id IN ($placeholders) AND store_id=?");
$stmt->bind_param($types.'i', ...array_merge($product_ids, [$store_id]));
$stmt->execute();
$res = $stmt->get_result();

// Map product_id => product info
$dbProducts = [];
while($p = $res->fetch_assoc()) {
    $dbProducts[$p['product_id']] = $p;
}

// Calculate totals
foreach ($items as &$item) {
    if (!isset($dbProducts[$item['product_id']])) {
        throw new Exception("Product ID {$item['product_id']} not found");
    }

    $p = $dbProducts[$item['product_id']];

    // Stock check
    if ($item['quantity'] > $p['stock']) {
        throw new Exception("Insufficient stock for product ID {$item['product_id']}");
    }

    // Use DB values
    $item['price']       = (float)$p['price'];
    $item['gst_percent'] = (float)$p['gst_percent'];

    $line_total  = $item['price'] * $item['quantity'];
    $line_tax    = $line_total * ($item['gst_percent']/100);

    $subtotal   += $line_total;
    $total_tax  += $line_tax;
}

$total_amount = $subtotal + $total_tax;


$invoice_id   = uniqid('INV');

/* ==================================================
   5. START TRANSACTION
================================================== */
$conn->begin_transaction();
try {
    /* ----------------------------------------------
       5a. CUSTOMER HANDLING
    ---------------------------------------------- */
    $customer_id = null;

    if(!empty($customerData)){

        // Prefer mobile first, then email for lookup
        if(!empty($customerData['customer_mobile'])){
            $lookup_col = 'customer_mobile';
            $lookup_val = $customerData['customer_mobile'];
        } elseif(!empty($customerData['customer_email'])){
            $lookup_col = 'customer_email';
            $lookup_val = $customerData['customer_email'];
        } else {
            $lookup_col = null;
        }

        if($lookup_col){
            $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE $lookup_col=? AND store_id=?");
            $stmt->bind_param("si", $lookup_val, $store_id);
            $stmt->execute();
            $res = $stmt->get_result();

            if($row = $res->fetch_assoc()){
                $customer_id = $row['customer_id'];

                // Prepare dynamic update
                $updateCols = [];
                $updateVals = [];
                foreach($customerData as $col => $val){
                    $updateCols[] = "$col=?";
                    $updateVals[] = ($val === '' ? null : $val); // convert empty string to NULL
                }

                if(count($updateCols) > 0){
                    $updateVals[] = $customer_id;
                    $updateVals[] = $store_id;
                    $sql = "UPDATE customers SET ".implode(", ", $updateCols)." WHERE customer_id=? AND store_id=?";
                    $stmt_up = $conn->prepare($sql);

                    // Determine types
                    $types = '';
                    foreach($updateVals as $v){
                        $types .= is_int($v) ? 'i' : 's';
                    }

                    $stmt_up->bind_param($types, ...$updateVals);
                    $stmt_up->execute();
                    $stmt_up->close();
                }

            } else {
                // Insert new customer
                $cols = array_keys($customerData);
                $placeholders = array_fill(0, count($cols), "?");
                $sql = "INSERT INTO customers (store_id,".implode(",", $cols).") VALUES (?, ".implode(",", $placeholders).")";
                $stmt_ins = $conn->prepare($sql);

                $types = "i".str_repeat('s', count($cols));
                $values = array_merge([$store_id], array_map(fn($v) => $v === '' ? null : $v, array_values($customerData)));

                $stmt_ins->bind_param($types, ...$values);
                $stmt_ins->execute();
                $customer_id = $stmt_ins->insert_id;
                $stmt_ins->close();
            }

            $stmt->close();
        } else {
            // No mobile/email provided, insert as new customer with only store_id
            $sql = "INSERT INTO customers (store_id) VALUES (?)";
            $stmt_ins = $conn->prepare($sql);
            $stmt_ins->bind_param("i", $store_id);
            $stmt_ins->execute();
            $customer_id = $stmt_ins->insert_id;
            $stmt_ins->close();
        }
    }

    /* ----------------------------------------------
       5b. INSERT SALE
    ---------------------------------------------- */
    $sale_date = $data['date'] ?? date('Y-m-d H:i:s');
    if (!empty($data['date'])) {
        $dt = DateTime::createFromFormat('d-m-Y H:i', $data['date']);
        if ($dt) {
            $sale_date = $dt->format('Y-m-d H:i:s');
        }
    }
    $columns = ['invoice_id','store_id','created_by','sale_date','subtotal','tax_amount','total_amount'];
    $values  = [$invoice_id, $store_id, $user_id, $sale_date, $subtotal, $total_tax, $total_amount];

    $placeholders = ['?','?','?','?','?','?','?'];

    if($customer_id){
        $columns[] = 'customer_id';
        $placeholders[] = '?';
        $values[] = $customer_id;
    }

    foreach($storeFields as $field => $enabled){
    // Include only fields you want in sales table
    if($enabled && !in_array($field, ['customer_mobile', 'customer_address', 'customer_email'])) { 
        $columns[] = $field;
        $placeholders[] = '?';
        $values[] = $customerData[$field] ?? null;
    }
}



    $sql = "INSERT INTO sales (".implode(",", $columns).") VALUES (".implode(",", $placeholders).")";
    $stmt_sale = $conn->prepare($sql);

    // Bind all as string or float dynamically
    $types = '';
    foreach($values as $v){
        $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
    }
    $stmt_sale->bind_param($types, ...$values);
    $stmt_sale->execute();
    $sale_id = $stmt_sale->insert_id;
    $stmt_sale->close();

    /* ----------------------------------------------
       5c. INSERT SALE ITEMS + UPDATE STOCK
    ---------------------------------------------- */
    $stmt_item = $conn->prepare("INSERT INTO sale_items (store_id,sale_id,product_id,quantity,price,gst_percent) VALUES (?,?,?,?,?,?)");
    $stmt_update = $conn->prepare("UPDATE products SET stock=stock-? WHERE product_id=? AND stock>=? AND store_id=?");

    foreach($items as $item){
        $stmt_item->bind_param("iiiidd", $store_id, $sale_id, $item['product_id'], $item['quantity'], $item['price'], $item['gst_percent']);
        $stmt_item->execute();

        $stmt_update->bind_param("iiii", $item['quantity'], $item['product_id'], $item['quantity'], $store_id);
        $stmt_update->execute();
        if($stmt_update->affected_rows === 0) throw new Exception("Insufficient stock for product ID ".$item['product_id']);
    }
    $stmt_item->close();
    $stmt_update->close();

    $conn->commit();

    echo json_encode([
        'status'     => 'success',
        'sale_id'    => $sale_id,
        'invoice_id' => $invoice_id,
        'subtotal'   => $subtotal,
        'tax'        => $total_tax,
        'total'      => $total_amount
    ]);

} catch(Exception $e){
    $conn->rollback();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}

$conn->close();