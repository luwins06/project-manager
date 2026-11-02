<?php
// orders/receipt.php - Generate and Stream PDF Receipt (Term Test Lab: 5pts)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_once('../includes/pdf.php');
require_login();

$order_id = (int)($_GET['order_id'] ?? 0);
$user_id  = (int)$_SESSION['user_id'];

// Fetch order
$rows = db_select($conn, "SELECT * FROM v_order_totals WHERE order_id = ?", 'i', [$order_id]);
if (empty($rows)) {
    die("Receipt not found.");
}
$order = $rows[0];

// Only order owner or admin can download receipt
if ((int)$order['user_id'] !== $user_id && !has_role('admin')) {
    die("Permission denied.");
}

// Fetch items
$items = db_select($conn, "SELECT * FROM v_order_details WHERE order_id = ?", 'i', [$order_id]);

// Generate PDF
$pdf = new ReceiptPDF(
    $order['order_id'],
    $order['created_at'],
    $order['customer_name'],
    $order['customer_email'],
    $order['status']
);

foreach ($items as $item) {
    $pdf->addItem(
        $item['service_name'],
        $item['category'],
        $item['quantity'],
        $item['unit_price'],
        $item['subtotal']
    );
}
$pdf->setGrandTotal($order['grand_total']);

$filename = "ProjectHub_Receipt_Order_" . $order['order_id'] . ".pdf";
$pdf->output('I', $filename);
