<?php
require('fpdf186/fpdf.php');
require('config/db.php');

if (!isset($_GET['invoice_id'])) {
    die("Invoice ID is required.");
}

$invoice_id = $_GET['invoice_id'];

// Fetch invoice, client, and receipt info
$invoice_query = $conn->prepare("
    SELECT i.*, c.full_name, c.address, c.email, c.phone_number
    FROM invoices i
    JOIN clients c ON i.client_id = c.client_id
    WHERE i.invoice_id = ?
");
$invoice_query->bind_param("i", $invoice_id);
$invoice_query->execute();
$invoice_result = $invoice_query->get_result();

if ($invoice_result->num_rows === 0) {
    die("Invoice not found.");
}

$invoice = $invoice_result->fetch_assoc();

// Fetch latest receipt for invoice
$receipt_query = $conn->prepare("
    SELECT * FROM receipts
    WHERE invoice_id = ?
    ORDER BY created_at DESC LIMIT 1
");
$receipt_query->bind_param("i", $invoice_id);
$receipt_query->execute();
$receipt_result = $receipt_query->get_result();

if ($receipt_result->num_rows === 0) {
    die("No receipt found for this invoice.");
}

$receipt = $receipt_result->fetch_assoc();

// Extend FPDF to create custom elements
class PDF extends FPDF {
    protected $extgstates = array();
    
    function RoundedRect($x, $y, $w, $h, $r, $style = '', $angle = 0) {
        $k = $this->k;
        $hp = $this->h;
        
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' || $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        
        // If angle is not 0, we need to rotate
        if ($angle != 0) {
            $this->_out('q');
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F cm', 
                cos($angle * M_PI / 180), sin($angle * M_PI / 180),
                -sin($angle * M_PI / 180), cos($angle * M_PI / 180),
                $x * $k, ($hp - $y) * $k));
            $x = 0;
            $y = 0;
        }
        
        $MyArc = 4/3 * (sqrt(2) - 1);
        
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        
        $this->_Arc($xc + $r * $MyArc, $y, $xc + $r, $y + $r * $MyArc, $xc + $r, $yc);
        
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        
        $this->_out($op);
        
        if ($angle != 0) {
            $this->_out('Q');
        }
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k));
    }
    
    function Circle($x, $y, $r, $style = 'F') {
        $this->Ellipse($x, $y, $r, $r, $style);
    }
    
    function Ellipse($x, $y, $rx, $ry, $style = 'D') {
        if ($style == 'F')
            $op = 'f';
        elseif ($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';
            
        $lx = 4/3 * (M_SQRT2 - 1) * $rx;
        $ly = 4/3 * (M_SQRT2 - 1) * $ry;
        
        $k = $this->k;
        $h = $this->h;
        
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $rx) * $k, ($h - $y) * $k,
            ($x + $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x + $lx) * $k, ($h - ($y - $ry)) * $k,
            $x * $k, ($h - ($y - $ry)) * $k));
            
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $lx) * $k, ($h - ($y - $ry)) * $k,
            ($x - $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x - $rx) * $k, ($h - $y) * $k));
            
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x - $lx) * $k, ($h - ($y + $ry)) * $k,
            $x * $k, ($h - ($y + $ry)) * $k));
            
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x + $lx) * $k, ($h - ($y + $ry)) * $k,
            ($x + $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x + $rx) * $k, ($h - $y) * $k,
            $op));
    }
    
    // Add transparency/alpha channel support
    function SetAlpha($alpha, $bm = 'Normal') {
        // set alpha for stroking (CA) and non-stroking (ca) operations
        $gs = $this->AddExtGState(array('ca' => $alpha, 'CA' => $alpha, 'BM' => '/' . $bm));
        $this->SetExtGState($gs);
    }
    
    function AddExtGState($parms) {
        $n = count($this->extgstates) + 1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }
    
    function SetExtGState($gs) {
        $this->_out(sprintf('/GS%d gs', $gs));
    }
    
    function _enddoc() {
        if (!empty($this->extgstates) && $this->PDFVersion < '1.4') {
            $this->PDFVersion = '1.4';
        }
        parent::_enddoc();
    }
    
    function _putextgstates() {
        for ($i = 1; $i <= count($this->extgstates); $i++) {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            foreach ($this->extgstates[$i]['parms'] as $k => $v) {
                $this->_put('/' . $k . ' ' . $v);
            }
            $this->_put('>>');
            $this->_put('endobj');
        }
    }
    
    function _putresourcedict() {
        parent::_putresourcedict();
        $this->_put('/ExtGState <<');
        foreach ($this->extgstates as $k => $extgstate) {
            $this->_put('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
        }
        $this->_put('>>');
    }
    
    function _putresources() {
        $this->_putextgstates();
        parent::_putresources();
    }
    
    // Method to add a "PAID" stamp
    function AddPaidStamp($x, $y, $width = 150) {
        // Save current state
        $this->_out('q');
        
        // Rotate
        $this->_out('1 0 0 1 '.($x).' '.($y).' cm');
        $this->_out('0.7071 0.7071 -0.7071 0.7071 0 0 cm');
        
        // Draw red rectangle with "PAID" text
        $this->SetFillColor(255, 0, 0);
        $this->SetAlpha(0.5);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 50);
        
        $rectWidth = $width;
        $rectHeight = 50;
        $this->Rect(-$rectWidth/2, -$rectHeight/2, $rectWidth, $rectHeight, 'F');
        
        // Text
        $this->SetXY(-$rectWidth/2 + 20, -$rectHeight/2 + 8);
        $this->Cell($rectWidth - 40, $rectHeight - 16, 'PAID', 0, 0, 'C');
        
        // Restore state
        $this->SetAlpha(1);
        $this->_out('Q');
    }
    
    function Footer() {
        // Position footer exactly at bottom of page
        $this->SetY(-20);
        
        // Brighter green background for footer
        $this->SetFillColor(1, 120, 65);
        $this->RoundedRect(10, $this->GetY(), $this->GetPageWidth() - 20, 10, 5, 'F');
        
        // Set text color to white for footer text
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', '', 12);
        
        // Footer text with contact info and icons
        $footerY = $this->GetY() + 3.5;
        
        // Reduce the gap between icons and text (adjust these values as needed)
        $iconTextGap = 4; 
        
        // Website with icon
        $webIconX = 15;
        $this->Image('icons/web.png', $webIconX, $footerY - 1, 4, 4);
        $this->SetXY($webIconX + $iconTextGap, $footerY);
        $this->Cell(50, 4, 'jubertfarms.com', 0, 0, 'L');
        
        // Phone with icon
        $phoneIconX = 75;
        $this->Image('icons/phone.png', $phoneIconX, $footerY - 1, 4, 4);
        $this->SetXY($phoneIconX + $iconTextGap, $footerY);
        $this->Cell(60, 4, '+233 2570 44814', 0, 0, 'C');
        
        // Email with icon
        $emailIconX = $this->GetPageWidth() - 65;
        $this->Image('icons/email.png', $emailIconX, $footerY - 1, 4, 4);
        $this->SetXY($emailIconX + $iconTextGap, $footerY);
        $this->Cell(50, 4, 'info@jubertfarms.com', 0, 0, 'R');
    }
}

// Create and initialize PDF with smaller margins to maximize content area
$pdf = new PDF();
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 15); // Reduced bottom margin

// Define BRIGHTER colors
$brightGreen = [76, 175, 80]; // Much brighter green for main elements
$lightGreen = [129, 199, 132]; // Even lighter green for sections
$backgroundColor = [255, 255, 255]; // White background

// Set main background color to WHITE instead of dark green
$pdf->SetFillColor(5, 46, 27);
$pdf->Rect(0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight(), 'F');

// Create modern header section with company logo - COMPACT VERSION
$pdf->SetAlpha(0.9); // Increased opacity for better visibility
$pdf->Circle(45, 30, 8, 'D'); // Circle for logo placement

// Add company logo image
$pdf->Image('assets/logo.png', 30, 15, 30, 30);

// Add "RECEIPT" heading with smaller font - using bright green now
$pdf->SetFont('Helvetica', 'B', 45);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(90, 20);
$pdf->Cell(90, 20, 'RECEIPT', 0, 1, 'R');

// Create receipt details box - with brighter background
$pdf->SetFillColor(1, 120, 65);
$pdf->RoundedRect(10, 50, $pdf->GetPageWidth() - 20, 20, 5, 'F');

// Receipt details text - COMPACT
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor(255, 255, 255); // White text for contrast
$pdf->SetXY(15, 55);
$pdf->Cell(80, 6, 'Receipt No: '.$receipt['receipt_no'], 0, 0);

// Move the date further to the right by increasing the X value
$pdf->SetXY($pdf->GetPageWidth() - 65, 55);
$pdf->Cell(80, 6, 'Date: '.$receipt['payment_date'], 0, 0);

// Create client and payment info section - COMPACT VERSION
$pdf->SetY(75);
$pdf->SetFillColor(5, 46, 27); // Lighter green

// Client info box (left side) - REDUCED HEIGHT
$pdf->RoundedRect(10, $pdf->GetY(), ($pdf->GetPageWidth() - 30)/2, 35, 5, 'F');
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor(255, 255, 255); // White text for contrast
$pdf->SetXY(20, $pdf->GetY() + 4);
$pdf->Cell(40, 6, 'CLIENT', 0, 1);

// Double space after CLIENT
$pdf->Ln(2); 

// Output client name
$pdf->SetFont('Arial', '', 12);
$pdf->SetX(20);
$pdf->Cell(($pdf->GetPageWidth() - 30)/2 - 10, 5, $invoice['full_name'], 0, 1);

// Double space between name and address
$pdf->Ln(2); 

// Output address
$pdf->SetX(20);
$pdf->MultiCell(($pdf->GetPageWidth() - 30)/2 - 10, 5, $invoice['address'], 0);

// Payment info box (right side) - REDUCED HEIGHT and MOVED FURTHER RIGHT
$pdf->SetY(75); // Match Y of client box
$pdf->SetFillColor(5, 46, 27);

// Adjust position more to the right
$pdf->RoundedRect($pdf->GetPageWidth()/2 + 20, $pdf->GetY(), ($pdf->GetPageWidth() - 60)/2, 35, 5, 'F');

$pdf->SetFont('Arial', 'B', 15);
// Adjust the X position for the "PAYMENT DETAILS" text
$pdf->SetXY($pdf->GetPageWidth()/2 + 30, $pdf->GetY() + 4);
$pdf->Cell(40, 6, 'PAYMENT DETAILS', 0, 1);

// Double space after PAYMENT DETAILS
$pdf->Ln(2); 

$pdf->SetFont('Arial', '', 12);
// Adjust the X position for the payment details text
$pdf->SetX($pdf->GetPageWidth()/2 + 30);
$pdf->Cell(($pdf->GetPageWidth() - 60)/2 - 10, 5, "Invoice No: ".$invoice['invoice_no'], 0, 1);
$pdf->SetX($pdf->GetPageWidth()/2 + 30);
$pdf->Cell(($pdf->GetPageWidth() - 60)/2 - 10, 5, "Method: ".$receipt['payment_method'], 0, 1);

// Payment summary section - styled similarly to invoice items
$pdf->SetY(115);

// Table header
$pdf->SetFillColor(1, 120, 65);
$pdf->RoundedRect(10, $pdf->GetY(), $pdf->GetPageWidth() - 20, 12, 5, 'F');
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor(255, 255, 255); // White text for header

// Payment summary header
$pdf->SetXY(15, $pdf->GetY() + 3);
$pdf->Cell(80, 6, 'PAYMENT SUMMARY', 0, 0);

// Payment row - with brighter color
$pdf->SetY(130);
$pdf->SetFillColor(220, 237, 200); // Very light green
$pdf->SetTextColor(50, 50, 50);    // Dark text for contrast
$pdf->RoundedRect(10, $pdf->GetY(), $pdf->GetPageWidth() - 20, 35, 5, 'F');

// Payment details
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(15, $pdf->GetY() + 5);
$pdf->Cell(100, 6, 'INVOICE AMOUNT:', 0, 0);
$pdf->SetX($pdf->GetPageWidth() - 50);
$pdf->Cell(35, 6, 'GHS ' . number_format($invoice['total_amount'], 2), 0, 1, 'R');

$pdf->SetX(15);
$pdf->Cell(100, 6, 'AMOUNT PAID:', 0, 0);
$pdf->SetX($pdf->GetPageWidth() - 50);
$pdf->Cell(35, 6, 'GHS ' . number_format($receipt['payment_amount'], 2), 0, 1, 'R');

$pdf->SetX(15);
$pdf->Cell(100, 6, 'BALANCE:', 0, 0);
$pdf->SetX($pdf->GetPageWidth() - 50);
$balance = $invoice['total_amount'] - $receipt['payment_amount'];
$pdf->Cell(35, 6, 'GHS ' . number_format($balance, 2), 0, 1, 'R');

$pdf->SetX(15);
$pdf->Cell(100, 6, 'PAYMENT STATUS:', 0, 0);
$pdf->SetX($pdf->GetPageWidth() - 50);
$status = ($balance <= 0) ? "PAID" : "PARTIAL";
$pdf->Cell(35, 6, $status, 0, 1, 'R');

// Calculate positions for notes section
$y = 175;

// Notes section with lighter background
$pdf->SetFillColor(5, 46, 27);
$pdf->RoundedRect(10, $y, $pdf->GetPageWidth() - 20, 40, 5, 'F');

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(255, 255, 255); // White text for contrast
$pdf->SetXY(15, $y + 3);
$pdf->Cell(40, 5, 'NOTES:', 0, 1);
// Double space after NOTES
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 13);
$pdf->SetX(15);
$pdf->MultiCell($pdf->GetPageWidth() - 40, 4, "Thank You For Your Payment. We Appreciate Your Business.\nPlease Keep This Receipt As Proof Of Payment.", 0);

// Add PAID stamp for fully paid invoices
if ($balance <= 0) {
    $pdf->AddPaidStamp(100, 140);
}

$pdf->Output();