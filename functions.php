<?php
/**
 * Utility functions for the invoice management system
 */

/**
 * Sanitize input data
 * 
 * @param string $data The input data to sanitize
 * @return string The sanitized data
 */
// In functions.php
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}


/**
 * Format currency amount
 * 
 * @param float $amount The amount to format
 * @param string $currency The currency symbol (default: $)
 * @return string Formatted currency string
 */
function format_currency($amount, $currency = '$') {
    return $currency . number_format($amount, 2);
}

/**
 * Generate a PDF invoice
 * 
 * @param int $invoice_id The ID of the invoice to generate PDF for
 * @return string Path to the generated PDF file
 */
function generate_invoice_pdf($invoice_id, $conn) {
    // This is a placeholder function.
    // In a real implementation, you would use a library like FPDF or TCPDF
    // to generate a proper PDF invoice
    
    // Get invoice data
    $stmt = $conn->prepare("SELECT i.*, c.* FROM invoices i 
                           JOIN clients c ON i.client_id = c.client_id 
                           WHERE i.invoice_id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    
    // Get invoice items
    $items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $items_stmt->bind_param("i", $invoice_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get payment history
    $payments_stmt = $conn->prepare("SELECT * FROM receipts WHERE invoice_id = ? ORDER BY payment_date");
    $payments_stmt->bind_param("i", $invoice_id);
    $payments_stmt->execute();
    $payments = $payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Placeholder return - in reality you would generate and save the PDF
    return "invoices/invoice_{$invoice_id}.pdf";
}

/**
 * Calculate the due date based on terms
 * 
 * @param string $date Starting date in Y-m-d format
 * @param int $days Number of days for payment terms
 * @return string Due date in Y-m-d format
 */
function calculate_due_date($date, $days = 30) {
    return date('Y-m-d', strtotime($date . " + {$days} days"));
}

/**
 * Generate a unique invoice number
 * 
 * @return string Formatted invoice number
 */
// functions.php
if (!function_exists('generate_invoice_number')) {
    function generate_invoice_number() {
        return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
    }
}


/**
 * Generate a unique receipt number
 * 
 * @return string Formatted receipt number
 */
if (!function_exists('generate_receipt_number')) {
    function generate_receipt_number() {
        return 'RCT-' . date('Ymd') . '-' . rand(1000, 9999);
    }
}


/**
 * Get a list of the most recent invoices
 * 
 * @param object $conn Database connection
 * @param int $limit Number of invoices to retrieve
 * @return array Array of invoice data
 */
function get_recent_invoices($conn, $limit = 5) {
    $result = $conn->query("SELECT i.*, c.full_name FROM invoices i 
                           JOIN clients c ON i.client_id = c.client_id 
                           ORDER BY i.created_at DESC LIMIT {$limit}");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get total unpaid invoices amount
 * 
 * @param object $conn Database connection
 * @return float Total unpaid amount
 */
function get_total_unpaid($conn) {
    $result = $conn->query("SELECT SUM(total_amount) as total FROM invoices 
                           WHERE payment_status IN ('Unpaid', 'Partial')");
    $data = $result->fetch_assoc();
    return $data['total'] ?? 0;
}

/**
 * Get count of overdue invoices
 * 
 * @param object $conn Database connection
 * @return int Number of overdue invoices
 */
function get_overdue_count($conn) {
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as count FROM invoices 
                           WHERE due_date < '{$today}' AND payment_status != 'Paid'");
    $data = $result->fetch_assoc();
    return $data['count'] ?? 0;
}

/**
 * Check if an invoice is overdue
 * 
 * @param string $due_date Due date in Y-m-d format
 * @param string $status Payment status
 * @return bool True if invoice is overdue
 */
function is_invoice_overdue($due_date, $status) {
    return ($status !== 'Paid' && strtotime($due_date) < strtotime('today'));
}
?>