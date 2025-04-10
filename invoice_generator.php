<?php
// Include FPDF library
require('fpdf186/fpdf.php');
include 'config/db.php';

// Create PDF class extension with rounded rectangle support
class PDF_Rounded extends FPDF
{
    // Method to draw a rounded rectangle
    function RoundedRec($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F')
            $op = 'f';
        elseif ($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));

        // Top right corner
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);

        // Right side
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);

        // Bottom side
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);

        // Left side
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    // Helper function to draw an arc
    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k,
            ($h - $y1) * $this->k,
            $x2 * $this->k,
            ($h - $y2) * $this->k,
            $x3 * $this->k,
            ($h - $y3) * $this->k
        ));
    }
}

// Function to generate PDF
function generatePDF($sale_id, $action = 'view') {
    global $conn;
    
    // Get sale details
    $sql = "SELECT s.*, c.full_name, c.email, c.phone_number, c.address 
            FROM sales s 
            LEFT JOIN clients c ON s.client_id = c.client_id 
            WHERE s.sale_id = $sale_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        die("Sale record not found.");
    }
    
    $sale = $result->fetch_assoc();
    
    // Create PDF with rounded corners
    $pdf = new PDF_Rounded('P', 'mm', 'A4');
    $pdf->AddPage();
    
    // Set default font
    $pdf->SetFont('Arial', '', 10);
    
    // Define colors
    $headerColor = [46, 125, 50]; // Dark green
    $textColor = [0, 0, 0];
    $lightGray = [240, 240, 240];
    $accentColor = [46, 125, 50]; // Green for accents
    
    // Add a green header background
    $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->RoundedRec(10, 10, 190, 20, 5, 'F');
    
    // Document Title (Invoice or Receipt based on payment status)
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->SetTextColor(255, 255, 255); // White text for header
    $pdf->SetXY(15, 15);
    $pdf->Cell(100, 10, ($sale['payment_status'] == 'Paid' ? 'INVOICE' : 'INVOICE'), 0, 0);
    
    // Invoice details (right aligned)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(120, 15);
    $pdf->Cell(75, 5, 'Invoice No: ' . $sale['invoice_no'], 0, 1, 'R');
    $pdf->SetXY(120, 21);
    $pdf->Cell(75, 5, 'Date: ' . date('d/m/Y', strtotime($sale['sale_date'])), 0, 1, 'R');
    
    // Reset text color
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    
    // Company and Client Information (side by side)
    $pdf->SetXY(10, 40);
    
    // Client info box
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->RoundedRec(10, 40, 90, 40, 5, 'FD');
    
    // Company info box
    $pdf->RoundedRec(110, 40, 90, 40, 5, 'FD');
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetXY(15, 45);
    $pdf->Cell(80, 7, 'BILL TO:', 0, 0);
    $pdf->SetXY(115, 45);
    $pdf->Cell(80, 7, 'PAYABLE TO:', 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    
    // Client details
    $pdf->SetXY(15, 52);
    $pdf->Cell(80, 6, $sale['full_name'], 0, 1);
    $pdf->SetX(15);
    $pdf->Cell(80, 6, $sale['address'], 0, 1);
    $pdf->SetX(15);
    $pdf->Cell(80, 6, $sale['phone_number'], 0, 1);
    $pdf->SetX(15);
    $pdf->Cell(80, 6, $sale['email'], 0, 1);
    
    // Company details
    $pdf->SetXY(115, 52);
    $pdf->Cell(80, 6, 'Jubert Farms', 0, 1);
    $pdf->SetX(115);
    $pdf->Cell(80, 6, '+233 2570 44814', 0, 1);
    $pdf->SetX(115);
    $pdf->Cell(80, 6, 'jubertfarms.com', 0, 1);
    $pdf->SetX(115);
    $pdf->Cell(80, 6, 'info@jubertfarms.com', 0, 1);
    
    // Items section
    $pdf->SetXY(10, 90);
    
    // Table header - with rounded top corners
    $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->RoundedRec(10, 90, 190, 10, 3, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(15, 91);
    $pdf->Cell(80, 8, 'ITEM DESCRIPTION', 0, 0);
    $pdf->SetXY(95, 91);
    $pdf->Cell(25, 8, 'QTY', 0, 0, 'C');
    $pdf->SetXY(120, 91);
    $pdf->Cell(35, 8, 'PRICE', 0, 0, 'R');
    $pdf->SetXY(155, 91);
    $pdf->Cell(40, 8, 'TOTAL', 0, 0, 'R');
    
    // Reset text color
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    
    // Table content with alternating row colors
    $pdf->SetXY(10, 100);
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->RoundedRec(10, 100, 190, 10, 0, 'F');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetXY(15, 101);
    $pdf->Cell(80, 8, $sale['product_name'], 0, 0);
    $pdf->SetXY(95, 101);
    $pdf->Cell(25, 8, $sale['quantity'], 0, 0, 'C');
    $pdf->SetXY(120, 101);
    $pdf->Cell(35, 8, 'GH₵ ' . number_format($sale['unit_price'], 2), 0, 0, 'R');
    $pdf->SetXY(155, 101);
    $pdf->Cell(40, 8, 'GH₵ ' . number_format($sale['amount'], 2), 0, 0, 'R');
    
    // Totals section
    $pdf->SetXY(110, 120);
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->RoundedRec(110, 120, 90, 30, 3, 'F');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(115, 123);
    $pdf->Cell(45, 8, 'SUB TOTAL:', 0, 0);
    $pdf->SetXY(155, 123);
    $pdf->Cell(40, 8, 'GH₵ ' . number_format($sale['amount'], 2), 0, 1, 'R');
    
    $pdf->SetXY(115, 131);
    $pdf->Cell(45, 8, 'TAX (10%):', 0, 0);
    $pdf->SetXY(155, 131);
    $pdf->Cell(40, 8, 'GH₵ 0.00', 0, 1, 'R');
    
    // Grand total with green background
    $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->RoundedRec(110, 140, 90, 10, 3, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(115, 141);
    $pdf->Cell(45, 8, 'GRAND TOTAL:', 0, 0);
    $pdf->SetXY(155, 141);
    $pdf->Cell(40, 8, 'GH₵ ' . number_format($sale['amount'], 2), 0, 1, 'R');
    
    // Reset text color
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    
    // Notes section
    $pdf->SetXY(10, 160);
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->RoundedRec(10, 160, 190, 40, 5, 'FD');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(15, 165);
    $pdf->Cell(50, 6, 'NOTES:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetXY(15, 172);
    $pdf->MultiCell(180, 6, $sale['notes']);
    
    // Footer with green background
    $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->RoundedRec(10, 210, 190, 20, 5, 'F');
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(10, 215);
    $pdf->Cell(190, 6, 'Thank You For Doing Business With Us.', 0, 1, 'C');
    $pdf->SetXY(10, 221);
    $pdf->Cell(190, 6, 'We Look Forward To Working With You Again.', 0, 1, 'C');
    
    // Output PDF based on requested action
    if ($action == 'download') {
        $pdf->Output('D', 'Invoice_' . $sale['invoice_no'] . '.pdf');
    } elseif ($action == 'save') {
        // Create directory if it doesn't exist
        if (!is_dir('invoices')) {
            mkdir('invoices', 0755, true);
        }
        $path = 'invoices/Invoice_' . $sale['invoice_no'] . '.pdf';
        $pdf->Output('F', $path);
        return $path;
    } else {
        $pdf->Output();
    }
}

// Handle request
if (isset($_GET['sale_id'])) {
    $sale_id = (int)$_GET['sale_id'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'view';
    
    if ($action == 'view' || $action == 'download' || $action == 'save') {
        generatePDF($sale_id, $action);
    }
} else {
    echo "Sale ID is required.";
}
?>