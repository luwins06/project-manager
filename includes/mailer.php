<?php
// includes/mailer.php - Mailtrap Notification & PDF Receipt Sender
// Term Test Lab: Sends customer notification with subtotal, grand total, and PDF attachment.

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/pdf.php');

function send_order_status_email($conn, $order_id)
{
    // 1. Fetch order summary from view
    $totals_sql = "SELECT * FROM v_order_totals WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $totals_sql);
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $totals_res = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($totals_res);
    mysqli_stmt_close($stmt);

    if (!$order) {
        return ['success' => false, 'error' => 'Order not found.'];
    }

    // 2. Fetch order items from view
    $items_sql = "SELECT * FROM v_order_details WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $items_sql);
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $items_res = mysqli_stmt_get_result($stmt);
    $items = mysqli_fetch_all($items_res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // 3. Generate PDF Receipt
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
    $pdf_data = $pdf->generate();

    // 4. Build HTML email body
    $rows_html = '';
    foreach ($items as $it) {
        $rows_html .= "<tr>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>" . htmlspecialchars($it['service_name']) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>" . htmlspecialchars($it['category']) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: center;'>" . (int)$it['quantity'] . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format($it['unit_price'], 2) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format($it['subtotal'], 2) . "</td>
        </tr>";
    }

    $status_color = ($order['status'] === 'Completed') ? '#10b981' : (($order['status'] === 'Confirmed') ? '#3b82f6' : (($order['status'] === 'In Progress') ? '#6366f1' : '#f59e0b'));

    $html_body = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><title>Order Status Update #{$order['order_id']}</title></head>
    <body style='font-family: Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px;'>
      <div style='max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>
        <div style='background: #1e293b; padding: 24px; color: #ffffff;'>
          <h2 style='margin: 0; font-size: 22px;'>ProjectHub - Order Status Update</h2>
          <p style='margin: 4px 0 0 0; color: #94a3b8; font-size: 14px;'>Project Management & Service Availing Platform</p>
        </div>
        <div style='padding: 24px;'>
          <p>Dear <strong>" . htmlspecialchars($order['customer_name']) . "</strong>,</p>
          <p>The status of your service order <strong>#" . (int)$order['order_id'] . "</strong> has been updated to:</p>
          <div style='display: inline-block; padding: 6px 14px; background: {$status_color}; color: #ffffff; border-radius: 20px; font-weight: bold; font-size: 14px; margin-bottom: 16px;'>
            " . htmlspecialchars(strtoupper($order['status'])) . "
          </div>
          <p style='color: #475569;'>A formal PDF receipt of this transaction is attached to this email.</p>
          
          <h3 style='border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 24px;'>Itemized Services Breakdown</h3>
          <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
            <thead>
              <tr style='background: #f1f5f9; text-align: left;'>
                <th style='padding: 10px;'>Service</th>
                <th style='padding: 10px;'>Category</th>
                <th style='padding: 10px; text-align: center;'>Qty</th>
                <th style='padding: 10px; text-align: right;'>Price</th>
                <th style='padding: 10px; text-align: right;'>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {$rows_html}
            </tbody>
            <tfoot>
              <tr>
                <td colspan='4' style='padding: 14px 10px; text-align: right; font-weight: bold; font-size: 16px;'>Grand Total:</td>
                <td style='padding: 14px 10px; text-align: right; font-weight: bold; font-size: 16px; color: #1e293b;'>PHP " . number_format($order['grand_total'], 2) . "</td>
              </tr>
            </tfoot>
          </table>

          <div style='margin-top: 24px; padding: 14px; background: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 4px; font-size: 13px; color: #475569;'>
            <strong>Next Steps:</strong> You can log in to your ProjectHub account anytime to monitor the project milestones and task completion for this order. Once completed, you will be invited to leave a review!
          </div>
        </div>
        <div style='background: #f1f5f9; padding: 16px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0;'>
          ProjectHub System &copy; " . date('Y') . " | ITIM211 Term Test Lab Transaction
        </div>
      </div>
    </body>
    </html>";

    // 5. Save local backup copy in mailoutput/ for offline grading verification
    $mail_dir = __DIR__ . '/../mailoutput';
    if (!is_dir($mail_dir)) {
        @mkdir($mail_dir, 0777, true);
    }
    $timestamp = date('Ymd_His');
    @file_put_contents("{$mail_dir}/order_{$order['order_id']}_{$timestamp}.html", $html_body);
    @file_put_contents("{$mail_dir}/receipt_order_{$order['order_id']}_{$timestamp}.pdf", $pdf_data);

    // 6. Send via Mailtrap SMTP socket
    $mailtrap_configured = (MAILTRAP_USER !== 'mailtrap_user_placeholder' && MAILTRAP_PASS !== 'mailtrap_pass_placeholder');
    $smtp_sent = false;
    $smtp_error = '';

    if ($mailtrap_configured) {
        $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";

        // Headers
        $headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "To: <" . $order['customer_email'] . ">\r\n";
        $headers .= "Subject: Order #" . $order['order_id'] . " Status Update: " . $order['status'] . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        // Body with HTML + PDF attachment
        $message  = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n\r\n";

        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: application/pdf; name=\"receipt-order-" . $order['order_id'] . ".pdf\"\r\n";
        $message .= "Content-Description: receipt-order-" . $order['order_id'] . ".pdf\r\n";
        $message .= "Content-Disposition: attachment; filename=\"receipt-order-" . $order['order_id'] . ".pdf\"; size=" . strlen($pdf_data) . ";\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($pdf_data)) . "\r\n\r\n";
        $message .= "--{$boundary}--";

        // Connect via socket
        $socket = @fsockopen(MAILTRAP_HOST, MAILTRAP_PORT, $errno, $errstr, 5);
        if ($socket) {
            $read = @fgets($socket, 512);
            @fputs($socket, "EHLO projecthub.local\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, "AUTH LOGIN\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, base64_encode(MAILTRAP_USER) . "\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, base64_encode(MAILTRAP_PASS) . "\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, "MAIL FROM: <" . MAIL_FROM . ">\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, "RCPT TO: <" . $order['customer_email'] . ">\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, "DATA\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
            $read = @fgets($socket, 512);

            @fputs($socket, "QUIT\r\n");
            fclose($socket);
            $smtp_sent = true;
        } else {
            $smtp_error = "Could not connect to Mailtrap ({$errstr}). Saved to local mailoutput.";
        }
    }

    return [
        'success'        => true,
        'smtp_sent'      => $smtp_sent,
        'mailtrap_used'  => $mailtrap_configured,
        'backup_saved'   => true,
        'pdf_size'       => strlen($pdf_data),
        'customer_email' => $order['customer_email'],
        'grand_total'    => $order['grand_total'],
        'order_status'   => $order['status']
    ];
}
