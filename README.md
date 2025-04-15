# Jubert-Farms-Finance-Management-System
A comprehensive web-based finance management system designed for Jubert Farms to track income, expenses, generate financial reports, and manage transactions efficiently. Built to streamline financial operations and support better decision-making through real-time insights.


For payroll:
Remember to add deductions and additions column where they serve as extra income or deductions

2. Key Features (Updated)
Modern Dashboard & User Interface
- Real-time Charts & Graphs: Display payroll summary, salary distributions, and expenses visually.
- Employee Profile Cards: Show employee details with a profile photo, making the UI more attractive.
Employee Management
- Add, edit, and remove employees.
- Upload and store employee photos for easy identification.
- Position Selection: C.E.O, Manager, Marketing Director, Supervisor, Laborer. - Employment Type: Fulltime, By-Day.
- Employee Status: Active, Terminated, Suspended.
Payroll Processing & Automation
- Compute salaries based on employee type and attendance.
- Handle deductions and bonuses.
- Automated Payroll Generation for full-time employees on payday.
Payroll & Report Printing
- Print payroll for selected employees.
- Print overall payroll reports.
- Payroll Slips Downloadable as PDF with a professional template.
- Payroll printouts include company details, logo, contact, and address.
Letter Generation & Bulk Email Notifications
- Generates and emails official letters automatically (Appointment, Dismissal, Suspension). - New Feature: Bulk Email Notifications for payroll updates, letters, and notices.
Security & Performance Enhancements
- Only Admin Users No other user roles exist in the system. - Login with Numeric Usercode & Passcode.
- Two-Factor Authentication (2FA) for extra security.
- Audit Logs: Track who logs in and makes changes.
- Database Optimization: Indexing and caching for faster performance.





















<?php
// payslip_generator.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: views/login.php");
    exit();
}

require_once 'config/db.php';
require_once 'fpdf186/fpdf.php'; // Update path to your FPDF library

// Check if payroll_id is provided
if (!isset($_GET['payroll_id'])) {
    die("Error: No payroll ID provided");
}

$payroll_id = $_GET['payroll_id'];

// Get payroll and employee details
$sql = "SELECT p.*, e.first_name, e.last_name, e.position, e.employment_type, e.phone, e.email 
        FROM payroll p 
        JOIN employees e ON p.employee_id = e.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();

if (!$payroll) {
    die("Error: Payroll record not found");
}

// Get deductions for this payroll
$sql = "SELECT * FROM payroll_deductions WHERE payroll_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$deductions_result = $stmt->get_result();
$deductions = $deductions_result->fetch_all(MYSQLI_ASSOC);

// Get additions for this payroll
$sql = "SELECT * FROM payroll_additions WHERE payroll_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$additions_result = $stmt->get_result();
$additions = $additions_result->fetch_all(MYSQLI_ASSOC);

// Company information
$company_name = "Jubert Farms Inc.";
$company_address = "123 Farm Road, Agricultural District";
$company_phone = "(123) 456-7890";
$company_email = "info@jubertfarms.com";

// Create PDF
class PayslipPDF extends FPDF {
    function Header() {
        global $company_name, $company_address, $company_phone, $company_email;
        
        // Logo - if you have a logo file
        // $this->Image('logo.png', 10, 10, 30);
        
        // Company Information
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, $company_name, 0, 1, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $company_address, 0, 1, 'R');
        $this->Cell(0, 6, "Phone: $company_phone | Email: $company_email", 0, 1, 'R');
        $this->Line(10, 38, 200, 38);
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PayslipPDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Payslip Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'PAYSLIP', 0, 1, 'C');
$pdf->Ln(5);

// Employee Details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Employee Information', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 8, 'Name:', 0);
$pdf->Cell(0, 8, $payroll['first_name'] . ' ' . $payroll['last_name'], 0, 1);
$pdf->Cell(40, 8, 'Position:', 0);
$pdf->Cell(0, 8, $payroll['position'], 0, 1);
$pdf->Cell(40, 8, 'Employee Type:', 0);
$pdf->Cell(0, 8, $payroll['employment_type'], 0, 1);
$pdf->Cell(40, 8, 'Phone:', 0);
$pdf->Cell(0, 8, $payroll['phone'], 0, 1);
$pdf->Cell(40, 8, 'Email:', 0);
$pdf->Cell(0, 8, $payroll['email'], 0, 1);
$pdf->Ln(5);

// Payroll Details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Payroll Details', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 8, 'Payroll ID:', 0);
$pdf->Cell(0, 8, $payroll_id, 0, 1);
$pdf->Cell(40, 8, 'Payment Date:', 0);
$pdf->Cell(0, 8, date('F d, Y', strtotime($payroll['payment_date'])), 0, 1);
$pdf->Ln(5);

// Salary Breakdown
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Salary Breakdown', 0, 1);
$pdf->SetFont('Arial', '', 10);

// Base Salary
$pdf->Cell(100, 8, 'Base Salary', 0);
$pdf->Cell(0, 8, '$' . number_format($payroll['base_salary'], 2), 0, 1, 'R');

// Additions
if (count($additions) > 0) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, 'Additions:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    foreach ($additions as $addition) {
        $pdf->Cell(100, 6, $addition['description'], 0);
        $pdf->Cell(0, 6, '$' . number_format($addition['amount'], 2), 0, 1, 'R');
    }
    
    $pdf->Cell(100, 8, 'Total Additions', 0);
    $pdf->Cell(0, 8, '$' . number_format($payroll['total_additions'], 2), 0, 1, 'R');
}

// Deductions
if (count($deductions) > 0) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, 'Deductions:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    foreach ($deductions as $deduction) {
        $pdf->Cell(100, 6, $deduction['description'], 0);
        $pdf->Cell(0, 6, '$' . number_format($deduction['amount'], 2), 0, 1, 'R');
    }
    
    $pdf->Cell(100, 8, 'Total Deductions', 0);
    $pdf->Cell(0, 8, '$' . number_format($payroll['total_deductions'], 2), 0, 1, 'R');
}

// Final Salary
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 10, 'NET SALARY', 'T');
$pdf->Cell(0, 10, '$' . number_format($payroll['amount'], 2), 'T', 1, 'R');

// Notes
if (!empty($payroll['notes'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, 'Notes:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, $payroll['notes'], 0);
}

// Footer
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 6, 'This is a computer-generated document. No signature required.', 0, 1, 'C');
$pdf->Cell(0, 6, 'Generated on: ' . date('F d, Y'), 0, 1, 'C');

// Create directory for payslips if it doesn't exist
$upload_dir = 'uploads/payslips';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate a unique filename
$filename = 'payslip_' . $payroll_id . '_' . str_replace(' ', '_', $payroll['first_name'] . '_' . $payroll['last_name']) . '.pdf';
$filepath = $upload_dir . '/' . $filename;

// Determine if we should display or save
$display_mode = isset($_GET['display']) && $_GET['display'] == 'true';

if ($display_mode) {
    // Display in browser
    $pdf->Output('I', $filename);
} else {
    // Save to file
    $pdf->Output('F', $filepath);
    
    // Redirect back with success message
    $_SESSION['success_message'] = "Payslip generated successfully. <a href='view_payslip.php?payroll_id=$payroll_id' target='_blank'>View Payslip</a>";
    header("Location: admin/payroll.php");
    exit;
}
?>