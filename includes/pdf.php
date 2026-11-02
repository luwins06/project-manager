<?php
// includes/pdf.php - Pure PHP PDF Receipt Generator
// Generates standard, valid PDF 1.4 documents without any external libraries.

class ReceiptPDF
{
    private $order_id;
    private $order_date;
    private $customer_name;
    private $customer_email;
    private $order_status;
    private $items = [];
    private $grand_total = 0.0;

    public function __construct($order_id, $order_date, $customer_name, $customer_email, $order_status)
    {
        $this->order_id       = $order_id;
        $this->order_date     = $order_date;
        $this->customer_name  = $customer_name;
        $this->customer_email = $customer_email;
        $this->order_status   = $order_status;
    }

    public function addItem($name, $category, $qty, $price, $subtotal)
    {
        $this->items[] = [
            'name'     => $name,
            'category' => $category,
            'qty'      => (int)$qty,
            'price'    => (float)$price,
            'subtotal' => (float)$subtotal
        ];
        $this->grand_total += (float)$subtotal;
    }

    public function setGrandTotal($total)
    {
        $this->grand_total = (float)$total;
    }

    private function escape($str)
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$str);
    }

    public function generate()
    {
        // 612 x 792 pt (US Letter)
        $stream = "";

        // Header Background Box
        $stream .= "0.12 0.25 0.45 rg\n"; // Deep Navy
        $stream .= "40 700 532 60 re f\n";

        // Brand Title
        $stream .= "BT\n";
        $stream .= "/F2 20 Tf\n";
        $stream .= "1 1 1 rg\n"; // White text
        $stream .= "55 725 Td\n";
        $stream .= "(" . $this->escape("ProjectHub - Transaction Receipt") . ") Tj\n";
        $stream .= "ET\n";

        // Subtitle
        $stream .= "BT\n";
        $stream .= "/F1 10 Tf\n";
        $stream .= "0.85 0.9 1 rg\n";
        $stream .= "55 710 Td\n";
        $stream .= "(" . $this->escape("Digital Agency & Project Management Platform | Order #" . $this->order_id) . ") Tj\n";
        $stream .= "ET\n";

        // Order Meta Box
        $stream .= "0.96 0.97 0.98 rg\n";
        $stream .= "40 610 532 75 re f\n";
        $stream .= "0.8 0.85 0.9 RG\n";
        $stream .= "1 w\n";
        $stream .= "40 610 532 75 re s\n";

        // Meta Text
        $stream .= "BT\n/F2 11 Tf\n0.15 0.2 0.25 rg\n55 665 Td\n(" . $this->escape("Customer Details:") . ") Tj\nET\n";
        $stream .= "BT\n/F1 10 Tf\n0.2 0.2 0.2 rg\n55 648 Td\n(" . $this->escape("Name: " . $this->customer_name) . ") Tj\nET\n";
        $stream .= "BT\n/F1 10 Tf\n0.2 0.2 0.2 rg\n55 632 Td\n(" . $this->escape("Email: " . $this->customer_email) . ") Tj\nET\n";

        $stream .= "BT\n/F2 11 Tf\n0.15 0.2 0.25 rg\n330 665 Td\n(" . $this->escape("Order Information:") . ") Tj\nET\n";
        $stream .= "BT\n/F1 10 Tf\n0.2 0.2 0.2 rg\n330 648 Td\n(" . $this->escape("Date: " . $this->order_date) . ") Tj\nET\n";
        $stream .= "BT\n/F1 10 Tf\n0.2 0.2 0.2 rg\n330 632 Td\n(" . $this->escape("Status: " . strtoupper($this->order_status)) . ") Tj\nET\n";

        // Table Header
        $stream .= "0.2 0.35 0.6 rg\n";
        $stream .= "40 575 532 25 re f\n";
        $stream .= "BT\n/F2 10 Tf\n1 1 1 rg\n";
        $stream .= "55 583 Td\n(" . $this->escape("Service / Package Description") . ") Tj\n";
        $stream .= "225 0 Td\n(" . $this->escape("Category") . ") Tj\n";
        $stream .= "95 0 Td\n(" . $this->escape("Price") . ") Tj\n";
        $stream .= "55 0 Td\n(" . $this->escape("Qty") . ") Tj\n";
        $stream .= "50 0 Td\n(" . $this->escape("Subtotal") . ") Tj\n";
        $stream .= "ET\n";

        // Table Rows
        $y = 550;
        $row_alt = false;
        foreach ($this->items as $item) {
            if ($row_alt) {
                $stream .= "0.97 0.98 0.99 rg\n";
                $stream .= "40 " . ($y - 5) . " 532 22 re f\n";
            }
            $row_alt = !$row_alt;

            // Row bottom line
            $stream .= "0.9 0.9 0.9 RG\n0.5 w\n";
            $stream .= "40 " . ($y - 5) . " m 572 " . ($y - 5) . " l s\n";

            $stream .= "BT\n/F1 10 Tf\n0.15 0.15 0.15 rg\n";
            $stream .= "55 {$y} Td\n(" . $this->escape(substr($item['name'], 0, 32)) . ") Tj\n";
            $stream .= "225 0 Td\n(" . $this->escape(substr($item['category'], 0, 15)) . ") Tj\n";
            $stream .= "95 0 Td\n(" . $this->escape("PHP " . number_format($item['price'], 2)) . ") Tj\n";
            $stream .= "55 0 Td\n(" . $this->escape((string)$item['qty']) . ") Tj\n";
            $stream .= "50 0 Td\n(" . $this->escape("PHP " . number_format($item['subtotal'], 2)) . ") Tj\n";
            $stream .= "ET\n";

            $y -= 24;
            if ($y < 120) {
                break; // single page receipt guard
            }
        }

        // Totals Box
        $y -= 15;
        $stream .= "0.12 0.25 0.45 rg\n";
        $stream .= "340 " . ($y - 8) . " 232 30 re f\n";
        $stream .= "BT\n/F2 13 Tf\n1 1 1 rg\n";
        $stream .= "355 {$y} Td\n(" . $this->escape("Grand Total:") . ") Tj\n";
        $stream .= "100 0 Td\n(" . $this->escape("PHP " . number_format($this->grand_total, 2)) . ") Tj\n";
        $stream .= "ET\n";

        // Footer Note
        $stream .= "BT\n/F1 9 Tf\n0.5 0.5 0.5 rg\n";
        $stream .= "55 60 Td\n(" . $this->escape("Thank you for choosing ProjectHub. Once confirmed, your project workspace is created automatically.") . ") Tj\n";
        $stream .= "0 -14 Td\n(" . $this->escape("ITIM211 Information Management 2026 | Generated on " . date('Y-m-d H:i:s')) . ") Tj\n";
        $stream .= "ET\n";

        // Assemble PDF Objects
        $len = strlen($stream);

        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /MediaBox [0 0 612 792] /Contents 6 0 R >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Length {$len} >>\nstream\n{$stream}\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";

        $o1 = strlen($header);
        $o2 = $o1 + strlen($obj1);
        $o3 = $o2 + strlen($obj2);
        $o4 = $o3 + strlen($obj3);
        $o5 = $o4 + strlen($obj4);
        $o6 = $o5 + strlen($obj5);
        $xref_offset = $o6 + strlen($obj6);

        $xref = "xref\n0 7\n";
        $xref .= "0000000000 65535 f \n";
        $xref .= sprintf("%010d 00000 n \n", $o1);
        $xref .= sprintf("%010d 00000 n \n", $o2);
        $xref .= sprintf("%010d 00000 n \n", $o3);
        $xref .= sprintf("%010d 00000 n \n", $o4);
        $xref .= sprintf("%010d 00000 n \n", $o5);
        $xref .= sprintf("%010d 00000 n \n", $o6);

        $trailer = "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF\n";

        return $header . $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6 . $xref . $trailer;
    }

    public function output($mode = 'S', $filename = 'receipt.pdf')
    {
        $pdf = $this->generate();
        if ($mode === 'S') {
            return $pdf;
        }

        if ($mode === 'D' || $mode === 'I') {
            header('Content-Type: application/pdf');
            $disp = ($mode === 'D') ? 'attachment' : 'inline';
            header("Content-Disposition: {$disp}; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdf;
            exit;
        }

        return $pdf;
    }
}
