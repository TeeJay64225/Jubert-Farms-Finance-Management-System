<?php
require('fpdf186/fpdf.php');
require('config/db.php');

if (!isset($_GET['invoice_no'])) {
    die('Invoice number is required.');
}

$invoice_no = $_GET['invoice_no'];

// Fetch invoice details
$invoice_query = $conn->prepare("SELECT i.*, c.full_name, c.email, c.phone_number, c.address FROM invoices i JOIN clients c ON i.client_id = c.client_id WHERE i.invoice_no = ?");
$invoice_query->bind_param("s", $invoice_no);
$invoice_query->execute();
$invoice = $invoice_query->get_result()->fetch_assoc();

if (!$invoice) {
    die('Invoice not found.');
}

// Fetch invoice items
$items_query = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items_query->bind_param("i", $invoice['invoice_id']);
$items_query->execute();
$items_result = $items_query->get_result();

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
        $iconTextGap = 4; // Reduced from 6 (21-15) to 2
        
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

// Add "INVOICE" heading with smaller font - using bright green now
$pdf->SetFont('Helvetica', 'B', 45);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(90, 20);
$pdf->Cell(90, 20, 'INVOICE', 0, 1, 'R');

// Create invoice details box - with brighter background
$pdf->SetFillColor(1, 120, 65);
$pdf->RoundedRect(10, 50, $pdf->GetPageWidth() - 20, 20, 5, 'F');

// Invoice details text - COMPACT
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor(255, 255, 255); // White text for contrast
$pdf->SetXY(15, 55);
$pdf->Cell(80, 6, 'Invoice No: '.$invoice['invoice_no'], 0, 0);

// Move the date further to the right by increasing the X value
$pdf->SetXY($pdf->GetPageWidth() - 65, 55); // Changed from -95 to -85
// or try an even larger value like:
// $pdf->SetXY($pdf->GetPageWidth() - 75, 55);

$pdf->Cell(80, 6, 'Date: '.$invoice['invoice_date'], 0, 0);
// Create client and company info section - COMPACT VERSION
$pdf->SetY(75);
$pdf->SetFillColor(5, 46, 27); // Lighter green

// Client info box (left side) - REDUCED HEIGHT
$pdf->RoundedRect(10, $pdf->GetY(), ($pdf->GetPageWidth() - 30)/2, 35, 5, 'F');
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor(255, 255, 255); // White text for contrast
$pdf->SetXY(20, $pdf->GetY() + 4);
$pdf->Cell(40, 6, 'BILL TO', 0, 1);

// Double space after BILL TO
$pdf->Ln(2); // Add extra space (default is 3, so 6 doubles it)

// Output client name
$pdf->SetFont('Arial', '', 12);
$pdf->SetX(20);
$pdf->Cell(($pdf->GetPageWidth() - 30)/2 - 10, 5, $invoice['full_name'], 0, 1);

// Double space between name and address
$pdf->Ln(2); // Add extra space

// Output address
$pdf->SetX(20);
$pdf->MultiCell(($pdf->GetPageWidth() - 30)/2 - 10, 5, $invoice['address'], 0);

// Company info box (right side) - REDUCED HEIGHT and MOVED FURTHER RIGHT
$pdf->SetY(75); // Match Y of client box
$pdf->SetFillColor(5, 46, 27);

// Adjust position more to the right by increasing the X position
// Original: $pdf->GetPageWidth()/2 + 5
// New: $pdf->GetPageWidth()/2 + 20 (increased by 15 units)
$pdf->RoundedRect($pdf->GetPageWidth()/2 + 20, $pdf->GetY(), ($pdf->GetPageWidth() - 60)/2, 35, 5, 'F');

$pdf->SetFont('Arial', 'B', 15);
// Adjust the X position for the "PAYABLE TO" text as well
$pdf->SetXY($pdf->GetPageWidth()/2 + 30, $pdf->GetY() + 4);
$pdf->Cell(40, 6, 'PAYABLE TO', 0, 1);

// Double space after BILL TO
$pdf->Ln(2); // Add extra space

$pdf->SetFont('Arial', '', 12);
// Adjust the X position for the company details text
$pdf->SetX($pdf->GetPageWidth()/2 + 30);
$pdf->MultiCell(($pdf->GetPageWidth() - 60)/2 - 10, 6, "Jubert Farms\n0532621185", 0);
// Double space between name and address


// Draw invoice items table - REDUCED TOP MARGIN
$pdf->SetY(115);

// Table header
$pdf->SetFillColor(1, 120, 65);
$pdf->RoundedRect(10, $pdf->GetY(), $pdf->GetPageWidth() - 20, 12, 5, 'F');
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor(255, 255, 255); // White text for header

// Table header columns - COMPACT
$pdf->SetXY(15, $pdf->GetY() + 3);
$pdf->Cell(80, 6, 'ITEM DESCRIPTION', 0, 0);
$pdf->SetX(95);
$pdf->Cell(25, 6, 'QTY', 0, 0, 'C');
$pdf->SetX(120);
$pdf->Cell(35, 6, 'PRICE', 0, 0, 'R');
$pdf->SetX($pdf->GetPageWidth() - 50);
$pdf->Cell(35, 6, 'TOTAL', 0, 0, 'R');

// Table rows - COMPACT - with brighter alternating colors
$pdf->SetFont('Arial', '', 12);
$y = 130;
$itemCount = 0;

// Get items from database with BRIGHTER alternating row colors
while ($item = $items_result->fetch_assoc()) {
    $itemCount++;
    
    // Alternating row colors for better readability - BRIGHTER COLORS
    if ($itemCount % 2 == 0) {
        $pdf->SetFillColor(220, 237, 200); // Very light green
        $pdf->SetTextColor(50, 50, 50);    // Dark text for contrast
    } else {
        $pdf->SetFillColor(240, 244, 195); // Light yellow-green
        $pdf->SetTextColor(50, 50, 50);    // Dark text for contrast
    }
    
    $pdf->RoundedRect(10, $y, $pdf->GetPageWidth() - 20, 9, 2, 'F');
    
    // Item details
    $pdf->SetXY(15, $y + 1);
    $pdf->Cell(80, 7, $item['product_name'], 0, 0);
    $pdf->SetX(95);
    $pdf->Cell(25, 7, $item['quantity'], 0, 0, 'C');
    $pdf->SetX(120);
    $pdf->Cell(35, 7, 'GHS ' . number_format($item['unit_price'], 2), 0, 0, 'R');
    $pdf->SetX($pdf->GetPageWidth() - 50);
    $pdf->Cell(35, 7, 'GHS ' . number_format($item['amount'], 2), 0, 0, 'R');
    
    $y += 10;
}

// FIXED LAYOUT APPROACH LIKE IN SAMPLE
// Calculate positions based on page height
$pageHeight = $pdf->GetPageHeight();
$remainingSpace = $pageHeight - $y - 25;
$notesHeight = min(40, $remainingSpace / 2);

// Calculate y position that will place notes and summary at bottom of page with proper spacing
$finalSectionY = $pageHeight - $notesHeight - 25;
$buffer = max(8, ($finalSectionY - $y) / 2);
$notesY = $y + $buffer;

// Notes section with lighter background
$pdf->SetFillColor(5, 46, 27);
$pdf->RoundedRect(10, $notesY, ($pdf->GetPageWidth() - 30)/2, $notesHeight, 5, 'F');

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(255, 255, 255); // White text for contrast
$pdf->SetXY(15, $notesY + 3);
$pdf->Cell(40, 5, 'NOTES:', 0, 1);
// Double space after BILL TO
$pdf->Ln(2); // Add extra space

$pdf->SetFont('Arial', '', 13);
$pdf->SetX(15);
$pdf->MultiCell(($pdf->GetPageWidth() - 30)/2 - 10, 4, "Thank You For Doing Business With Us.\nWe Look Forward To Working With You Again.", 0);

// Summary section with brighter background
$pdf->SetY($notesY);
$pdf->SetFillColor(1, 120, 65);
$pdf->RoundedRect($pdf->GetPageWidth()/2 + 5, $notesY, ($pdf->GetPageWidth() - 30)/2, $notesHeight, 5, 'F');

// Summary details with dark text for contrast
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(255, 255, 255); // White text for contrast
$pdf->SetXY($pdf->GetPageWidth()/2 + 10, $notesY + 3);
$pdf->Cell(40, 5, 'SUB TOTAL', 0, 0);
// Double space after BILL TO
$pdf->Ln(2); // Add extra space

$pdf->SetX($pdf->GetPageWidth() - 50);
$pdf->Cell(35, 5, 'GHS ' . number_format($invoice['subtotal'], 2), 0, 1, 'R');

$pdf->SetX($pdf->GetPageWidth()/2 + 10);
$pdf->Cell(40, 5, 'TAX (10%)', 0, 0);
$pdf->SetX($pdf->GetPageWidth() - 50);
$pdf->Cell(35, 5, 'GHS ' . number_format($invoice['tax_amount'], 2), 0, 1, 'R');

// Skip discount line if zero or not set (to match sample)
if (isset($invoice['discount_amount']) && $invoice['discount_amount'] > 0) {
    $pdf->SetX($pdf->GetPageWidth()/2 + 10);
    $pdf->Cell(40, 5, 'DISCOUNT', 0, 0);
    $pdf->SetX($pdf->GetPageWidth() - 50);
    $pdf->Cell(35, 5, 'GHS ' . number_format($invoice['discount_amount'], 2), 0, 1, 'R');
}

// Draw a separator line - darker for visibility
$pdf->SetDrawColor(100, 100, 100);
$pdf->SetLineWidth(0.5);
$pdf->Line($pdf->GetPageWidth()/2 + 10, $pdf->GetY() + 2, $pdf->GetPageWidth() - 15, $pdf->GetY() + 2);

// Grand total with bold font
$pdf->SetY($pdf->GetY() + 3);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX($pdf->GetPageWidth()/2 + 10);
$pdf->Cell(40, 5, 'GRAND TOTAL', 0, 0);
$pdf->SetX($pdf->GetPageWidth() - 50);
$pdf->Cell(35, 5, 'GHS ' . number_format($invoice['total_amount'], 2), 0, 0, 'R');

$pdf->Output();