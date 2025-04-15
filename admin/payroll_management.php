<?php
// This would be part of your payroll_management.php file

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for action parameter
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'get_payslip':
                getPayslip();
                break;
            case 'email_payslip':
                emailPayslip();
                break;
            // Other actions would go here
        }
    }
}

/**
 * Get payslip data for the specified payroll ID
 */
function getPayslip() {
    global $conn;
    
    // Validate payroll ID
    if (!isset($_POST['payroll_id']) || !is_numeric($_POST['payroll_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid payroll ID'
        ]);
        exit;
    }
    
    $payrollId = (int)$_POST['payroll_id'];
    
    // Get payroll data with prepared statement
    $query = "SELECT p.*, e.first_name, e.last_name, e.position, e.department, e.employment_type, 
              e.bank_name, e.bank_account, e.salary as base_salary
              FROM payroll p
              JOIN employees e ON p.employee_id = e.id
              WHERE p.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $payrollId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Payslip not found'
        ]);
        exit;
    }
    
    $payroll = mysqli_fetch_assoc($result);
    
    // Format data for response
    $payroll['employee_name'] = $payroll['first_name'] . ' ' . $payroll['last_name'];
    $payroll['pay_period'] = date('F Y', strtotime($payroll['payment_date']));
    
    // Get allowances
    $allowances = [];
    $query = "SELECT name, amount FROM payroll_allowances WHERE payroll_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $payrollId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $allowances[] = $row;
    }
    
    // Get deductions
    $deductions = [];
    $query = "SELECT name, amount FROM payroll_deductions WHERE payroll_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $payrollId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $deductions[] = $row;
    }
    
    // Calculate total deductions (including tax)
    $totalDeductions = $payroll['tax_amount'];
    foreach ($deductions as $deduction) {
        $totalDeductions += $deduction['amount'];
    }
    
    $payroll['allowances'] = $allowances;
    $payroll['deductions'] = $deductions;
    $payroll['total_deductions'] = $totalDeductions;
    
    echo json_encode([
        'status' => 'success',
        'data' => $payroll
    ]);
    exit;
}

/**
 * Email the payslip to the employee
 */
function emailPayslip() {
    global $conn;
    
    // Validate payroll ID
    if (!isset($_POST['payroll_id']) || !is_numeric($_POST['payroll_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid payroll ID'
        ]);
        exit;
    }
    
    $payrollId = (int)$_POST['payroll_id'];
    
    // Get employee email
    $query = "SELECT e.email, e.first_name, e.last_name, p.payment_date
              FROM payroll p
              JOIN employees e ON p.employee_id = e.id
              WHERE p.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $payrollId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Employee not found'
        ]);
        exit;
    }
    
    $data = mysqli_fetch_assoc($result);
    
    if (empty($data['email'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Employee email address not found'
        ]);
        exit;
    }
    
    // Generate PDF payslip (you would need a PDF library like mPDF or TCPDF for this)
    // For this example, we'll assume you have a function to generate the PDF
    $pdfPath = generatePayslipPDF($payrollId);
    
    if (!$pdfPath) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to generate payslip PDF'
        ]);
        exit;
    }
    
    // Send email with attachment
    $to = $data['email'];
    $subject = 'Your Payslip for ' . date('F Y', strtotime($data['payment_date']));
    $payPeriod = date('F Y', strtotime($data['payment_date']));
    
    $message = "
    <html>
    <head>
        <title>Payslip</title>
    </head>
    <body>
        <p>Dear {$data['first_name']} {$data['last_name']},</p>
        <p>Please find attached your payslip for the period of {$payPeriod}.</p>
        <p>If you have any questions regarding your payslip, please contact the HR department.</p>
        <p>Thank you.</p>
        <p>Best regards,<br>HR Department<br>Jubert Farms Finance</p>
    </body>
    </html>
    ";
    
    // To send HTML mail, the Content-type header must be set
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Jubert Farms HR <hr@jubertfarms.com>' . "\r\n";
    
    // Send email and check if successful
    $emailSent = sendEmailWithAttachment($to, $subject, $message, $pdfPath);
    
    if ($emailSent) {
        // Log the email action
        $userId = $_SESSION['user_id'];
        $action = "Sent payslip email to {$data['first_name']} {$data['last_name']} ({$data['email']})";
        $stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "is", $userId, $action);
        mysqli_stmt_execute($stmt);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Payslip emailed successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send email'
        ]);
    }
    exit;
}

/**
 * Generate PDF payslip (this is a placeholder function)
 * In a real application, you'd use a library like mPDF or TCPDF
 */
function generatePayslipPDF($payrollId) {
    // Placeholder - in a real application you would generate a PDF file
    // For example, using mPDF:
    /*
    require_once 'vendor/autoload.php';
    
    // Get payslip data
    $payslipData = getPayslipData($payrollId);
    
    // Create PDF
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML(generatePayslipHTML($payslipData));
    
    // Save to file
    $filename = 'payslips/payslip_' . $payrollId . '.pdf';
    $mpdf->Output($filename, 'F');
    
    return $filename;
    */
    
    // For this example, we'll just return a placeholder path
    return 'payslips/payslip_' . $payrollId . '.pdf';
}

/**
 * Send email with attachment (this is a placeholder function)
 * In a real application, you'd use a library like PHPMailer
 */
function sendEmailWithAttachment($to, $subject, $message, $attachment) {
    // Placeholder - in a real application you would use PHPMailer or similar
    // For example:
    /*
    require 'vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->setFrom('hr@jubertfarms.com', 'Jubert Farms HR');
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $message;
    $mail->addAttachment($attachment);
    
    return $mail->send();
    */
    
    // For this example, we'll just return true to simulate success
    return true;
}
?>