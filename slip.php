<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}

require_once 'config/db.php'; // your DB connection 
require_once 'functions.php'; // added utility functions file

function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}


// Initialize variables
$response = '';
$response_type = '';
$invoice_items = [];
$clients = [];
$selected_invoice = null;

// Fetch all clients for dropdown selection
$clients_query = $conn->query("SELECT client_id, full_name, email FROM clients ORDER BY full_name");
while ($client = $clients_query->fetch_assoc()) {
    $clients[] = $client;
}

// Handle form actions
$action = $_GET['action'] ?? '';

// View invoice details
if ($action === 'view_invoice' && isset($_GET['id'])) {
    $invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($invoice_id) {
        // Get invoice details
        $stmt = $conn->prepare("SELECT i.*, c.full_name, c.email, c.phone_number, c.address 
                               FROM invoices i 
                               JOIN clients c ON i.client_id = c.client_id 
                               WHERE i.invoice_id = ?");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $selected_invoice = $stmt->get_result()->fetch_assoc();
        
        // Get invoice items
        $items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $items_stmt->bind_param("i", $invoice_id);
        $items_stmt->execute();
        $invoice_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Create a new invoice
if ($action === 'create_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
    $invoice_no = sanitize_input($_POST['invoice_no'] ?? '');
    $invoice_date = sanitize_input($_POST['invoice_date'] ?? '');
    $due_date = sanitize_input($_POST['due_date'] ?? '');
    $subtotal = filter_input(INPUT_POST, 'subtotal', FILTER_VALIDATE_FLOAT);
    $tax_rate = filter_input(INPUT_POST, 'tax_rate', FILTER_VALIDATE_FLOAT);
    $tax_amount = filter_input(INPUT_POST, 'tax_amount', FILTER_VALIDATE_FLOAT);
    $discount_amount = filter_input(INPUT_POST, 'discount_amount', FILTER_VALIDATE_FLOAT);
    $total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
    $payment_status = sanitize_input($_POST['payment_status'] ?? 'Unpaid');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate required fields
    if (!$client_id || empty($invoice_no) || empty($invoice_date) || empty($due_date) || $total_amount === false) {
        $response = "All required fields must be filled out correctly.";
        $response_type = "error";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        try {
            // Insert invoice
            $stmt = $conn->prepare("INSERT INTO invoices 
                (invoice_no, client_id, invoice_date, due_date, subtotal, tax_rate, tax_amount, discount_amount, total_amount, payment_status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissdddddss", $invoice_no, $client_id, $invoice_date, $due_date, $subtotal, $tax_rate, $tax_amount, $discount_amount, $total_amount, $payment_status, $notes);
            $stmt->execute();
            $invoice_id = $conn->insert_id;
            
            // Insert invoice items
            if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
                $item_stmt = $conn->prepare("INSERT INTO invoice_items 
                    (invoice_id, product_name, description, quantity, unit_price, amount)
                    VALUES (?, ?, ?, ?, ?, ?)");
                
                for ($i = 0; $i < count($_POST['item_name']); $i++) {
                    $item_name = sanitize_input($_POST['item_name'][$i]);
                    $description = sanitize_input($_POST['item_description'][$i] ?? '');
                    $quantity = filter_var($_POST['item_quantity'][$i], FILTER_VALIDATE_INT);
                    $unit_price = filter_var($_POST['item_price'][$i], FILTER_VALIDATE_FLOAT);
                    $amount = filter_var($_POST['item_total'][$i], FILTER_VALIDATE_FLOAT);
                    
                    if (!empty($item_name) && $quantity && $unit_price && $amount) {
                        $item_stmt->bind_param("issidd", $invoice_id, $item_name, $description, $quantity, $unit_price, $amount);
                        $item_stmt->execute();
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            log_action($conn, $_SESSION['user_id'], "Created invoice #$invoice_no for client ID $client_id.");
            $response = "Invoice #$invoice_no created successfully.";
            $response_type = "success";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $response = "Error creating invoice: " . $e->getMessage();
            $response_type = "error";
        }
    }
}

// Generate receipt for an invoice
if ($action === 'generate_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
    $receipt_no = sanitize_input($_POST['receipt_no']);
    $payment_date = sanitize_input($_POST['payment_date']);
    $payment_amount = filter_input(INPUT_POST, 'payment_amount', FILTER_VALIDATE_FLOAT);
    $payment_method = sanitize_input($_POST['payment_method']);
    $payment_reference = sanitize_input($_POST['payment_reference'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    if (!$invoice_id || empty($receipt_no) || empty($payment_date) || $payment_amount === false) {
        $response = "All required fields must be filled out correctly.";
        $response_type = "error";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        try {
            // Insert receipt
            $stmt = $conn->prepare("INSERT INTO receipts 
                (receipt_no, invoice_id, payment_date, payment_amount, payment_method, payment_reference, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisdsss", $receipt_no, $invoice_id, $payment_date, $payment_amount, $payment_method, $payment_reference, $notes);
            $stmt->execute();
            
            // Update invoice payment status
            // Get current invoice data
            $invoice_query = $conn->prepare("SELECT total_amount FROM invoices WHERE invoice_id = ?");
            $invoice_query->bind_param("i", $invoice_id);
            $invoice_query->execute();
            $invoice_data = $invoice_query->get_result()->fetch_assoc();
            
            // Get total paid amount
            $paid_query = $conn->prepare("SELECT SUM(payment_amount) as total_paid FROM receipts WHERE invoice_id = ?");
            $paid_query->bind_param("i", $invoice_id);
            $paid_query->execute();
            $paid_data = $paid_query->get_result()->fetch_assoc();
            $total_paid = $paid_data['total_paid'];
            
            // Determine new payment status
            $payment_status = 'Unpaid';
            if ($total_paid >= $invoice_data['total_amount']) {
                $payment_status = 'Paid';
            } else if ($total_paid > 0) {
                $payment_status = 'Partial';
            }
            
            // Update invoice status
            $update_stmt = $conn->prepare("UPDATE invoices SET payment_status = ? WHERE invoice_id = ?");
            $update_stmt->bind_param("si", $payment_status, $invoice_id);
            $update_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            log_action($conn, $_SESSION['user_id'], "Generated receipt #$receipt_no for invoice ID $invoice_id.");
            $response = "Receipt generated successfully and invoice status updated.";
            $response_type = "success";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $response = "Error generating receipt: " . $e->getMessage();
            $response_type = "error";
        }
    }
}

// Delete invoice
if ($action === 'delete_invoice' && isset($_GET['id'])) {
    $invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($invoice_id) {
        $stmt = $conn->prepare("DELETE FROM invoices WHERE invoice_id = ?");
        $stmt->bind_param("i", $invoice_id);
        if ($stmt->execute()) {
            log_action($conn, $_SESSION['user_id'], "Deleted invoice ID $invoice_id.");
            $response = "Invoice deleted successfully.";
            $response_type = "success";
        }
         else {
            $response = "Error deleting invoice: " . $stmt->error;
            $response_type = "error";
        }
    }
}

// Fetch invoices for display with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$count_query = $conn->query("SELECT COUNT(*) as total FROM invoices");
$total_rows = $count_query->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$invoices_query = "SELECT i.*, c.full_name FROM invoices i 
                  JOIN clients c ON i.client_id = c.client_id 
                  ORDER BY i.created_at DESC 
                  LIMIT $offset, $limit";
$invoices = $conn->query($invoices_query);

// Utility function for sanitizing input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Generate a unique invoice number
function generate_invoice_number() {
    return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
}

// Generate a unique receipt number
function generate_receipt_number() {
    return 'RCT-' . date('Ymd') . '-' . rand(1000, 9999);
}

//hearder
// Include header
include 'views/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice & Receipt Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .invoice-form {
            display: none;
        }
        #invoiceItems .form-row {
            margin-bottom: 10px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .alert {
            margin-top: 15px;
        }
    </style>
</head>
<body>


    <div class="container mt-4">
        <h1 class="mb-4">Invoice & Receipt Management</h1>
        
        <?php if (!empty($response)): ?>
            <div class="alert alert-<?= $response_type === 'success' ? 'success' : 'danger' ?>">
                <?= $response ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Invoices</h5>
                <button class="btn btn-primary" onclick="toggleForm('createInvoiceForm')">
                    <i class="bi bi-plus-circle"></i> New Invoice
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Invoice No</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices->num_rows > 0): ?>
                                <?php while ($row = $invoices->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['invoice_no']) ?></td>
        <td><?= htmlspecialchars($row['full_name']) ?></td>
        <td><?= htmlspecialchars($row['invoice_date']) ?></td>
        <td><?= htmlspecialchars($row['due_date']) ?></td>
        <td><?= number_format($row['total_amount'], 2) ?></td>
        <td>
            <span class="badge <?= getStatusBadgeClass($row['payment_status']) ?>">
                <?= htmlspecialchars($row['payment_status']) ?>
            </span>
        </td>
        <td class="action-buttons">
            <a href="?action=view_invoice&id=<?= $row['invoice_id'] ?>" class="btn btn-sm btn-info">
                <i class="bi bi-eye"></i>
            </a>
            <?php
$invoice_id = $row['invoice_id'];
$invoice_no = $row['invoice_no'];
$payment_status = $row['payment_status'];

// Fetch latest receipt_no for this invoice
$receipt_stmt = $conn->prepare("SELECT receipt_no FROM receipts WHERE invoice_id = ? ORDER BY payment_date DESC LIMIT 1");
$receipt_stmt->bind_param("i", $invoice_id);
$receipt_stmt->execute();
$receipt_stmt->bind_result($receipt_no);
$receipt_stmt->fetch();
$receipt_stmt->close();

// Now use the receipt_no in the logic
if (($payment_status === 'Paid' || $payment_status === 'Partial') && !empty($receipt_no)) {
    $pdf_link = "receipt_pdf.php?invoice_id=" . urlencode($invoice_id) . "&receipt_no=" . urlencode($receipt_no);
} else {
    $pdf_link = "invoice_pdf.php?invoice_no=" . urlencode($invoice_no);
}
?>


            <a href="<?= $pdf_link ?>" class="btn btn-sm btn-secondary" target="_blank">
                <i class="bi bi-file-pdf"></i>
            </a>

            <?php if ($payment_status !== 'Paid'): ?>
                <button class="btn btn-sm btn-success" onclick="showReceiptModal(<?= $row['invoice_id'] ?>, '<?= $row['invoice_no'] ?>')">
                    <i class="bi bi-receipt"></i>
                </button>
            <?php endif; ?>

            <a href="?action=delete_invoice&id=<?= $row['invoice_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this invoice?')">
                <i class="bi bi-trash"></i>
            </a>
        </td>
    </tr>
<?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No invoices found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create Invoice Form -->
        <div id="createInvoiceForm" class="card mb-4 invoice-form">
            <div class="card-header">
                <h5 class="mb-0">Create New Invoice</h5>
            </div>
            <div class="card-body">
                <form method="post" action="?action=create_invoice" id="invoiceForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="client_id" class="form-label">Client</label>
                                <select class="form-select" name="client_id" id="client_id" required>
                                    <option value="">Select Client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['client_id'] ?>">
                                            <?= htmlspecialchars($client['full_name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="invoice_no" class="form-label">Invoice Number</label>
                                <input type="text" class="form-control" name="invoice_no" id="invoice_no" 
                                       value="<?= generate_invoice_number() ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="invoice_date" class="form-label">Invoice Date</label>
                                <input type="date" class="form-control" name="invoice_date" id="invoice_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" name="due_date" id="due_date" 
                                       value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Invoice Items</h5>
                    <div id="invoiceItems">
                        <div class="row mb-2 bg-light p-2">
                            <div class="col-md-4"><strong>Item</strong></div>
                            <div class="col-md-3"><strong>Quantity</strong></div>
                            <div class="col-md-2"><strong>Price</strong></div>
                            <div class="col-md-2"><strong>Total</strong></div>
                            <div class="col-md-1"></div>
                        </div>
                        <div class="invoice-item form-row row">
                            <div class="col-md-4 mb-2">
                                <input type="text" class="form-control" name="item_name[]" placeholder="Item name" required>
                                <input type="text" class="form-control mt-1" name="item_description[]" placeholder="Description">
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="number" class="form-control item-quantity" name="item_quantity[]" value="1" min="1" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="number" step="0.01" class="form-control item-price" name="item_price[]" placeholder="0.00" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="number" step="0.01" class="form-control item-total" name="item_total[]" placeholder="0.00" readonly>
                            </div>
                            <div class="col-md-1 mb-2">
                                <button type="button" class="btn btn-danger btn-sm remove-item">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 mt-2">
                        <button type="button" id="addItemBtn" class="btn btn-secondary">
                            <i class="bi bi-plus"></i> Add Item
                        </button>
                    </div>
                    
                    <div class="row justify-content-end mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="mb-2 row">
                                        <label class="col-sm-6 col-form-label">Subtotal:</label>
                                        <div class="col-sm-6">
                                            <input type="number" step="0.01" class="form-control-plaintext text-end" id="subtotal" name="subtotal" value="0.00" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2 row">
                                        <label class="col-sm-6 col-form-label">Tax Rate (%):</label>
                                        <div class="col-sm-6">
                                            <input type="number" step="0.01" class="form-control text-end" id="tax_rate" name="tax_rate" value="0">
                                        </div>
                                    </div>
                                    <div class="mb-2 row">
                                        <label class="col-sm-6 col-form-label">Tax Amount:</label>
                                        <div class="col-sm-6">
                                            <input type="number" step="0.01" class="form-control-plaintext text-end" id="tax_amount" name="tax_amount" value="0.00" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2 row">
                                        <label class="col-sm-6 col-form-label">Discount:</label>
                                        <div class="col-sm-6">
                                            <input type="number" step="0.01" class="form-control text-end" id="discount_amount" name="discount_amount" value="0">
                                        </div>
                                    </div>
                                    <div class="mb-2 row">
                                        <label class="col-sm-6 col-form-label"><strong>Total:</strong></label>
                                        <div class="col-sm-6">
                                            <input type="number" step="0.01" class="form-control-plaintext text-end fw-bold" id="total_amount" name="total_amount" value="0.00" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" name="payment_status" id="payment_status">
                                    <option value="Unpaid" selected>Unpaid</option>
                                    <option value="Partial">Partial</option>
                                    <option value="Paid">Paid</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-secondary me-2" onclick="toggleForm('createInvoiceForm')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Invoice View -->
        <?php if ($selected_invoice): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Invoice Details: <?= htmlspecialchars($selected_invoice['invoice_no']) ?></h5>
                    <div>
                    <a href="invoice_pdf.php?invoice_no=<?= urlencode($selected_invoice['invoice_no']) ?>" class="btn btn-secondary" target="_blank">
    <i class="bi bi-file-pdf"></i> Download Invoice
</a>
<a href="receipt_pdf.php?invoice_id=<?= $selected_invoice['invoice_id'] ?>" class="btn btn-secondary" target="_blank">
    <i class="bi bi-file-pdf"></i> Download Receipt
</a><a href="receipt_pdf.php?invoice_id=<?= $invoice['id'] ?>&receipt_no=<?= $receipt['receipt_no'] ?>" target="_blank">
    Download Receipt
</a>



                        <button class="btn btn-success" onclick="showReceiptModal(<?= $selected_invoice['invoice_id'] ?>, '<?= $selected_invoice['invoice_no'] ?>')">
                            <i class="bi bi-receipt"></i> Add Payment
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Client Information</h6>
                            <p>
                                <strong><?= htmlspecialchars($selected_invoice['full_name']) ?></strong><br>
                                Email: <?= htmlspecialchars($selected_invoice['email']) ?><br>
                                Phone: <?= htmlspecialchars($selected_invoice['phone_number']) ?><br>
                                Address: <?= nl2br(htmlspecialchars($selected_invoice['address'])) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Invoice Information</h6>
                            <p>
                                <strong>Invoice Number:</strong> <?= htmlspecialchars($selected_invoice['invoice_no']) ?><br>
                                <strong>Date:</strong> <?= htmlspecialchars($selected_invoice['invoice_date']) ?><br>
                                <strong>Due Date:</strong> <?= htmlspecialchars($selected_invoice['due_date']) ?><br>
                                <strong>Status:</strong> 
                                <span class="badge <?= getStatusBadgeClass($selected_invoice['payment_status']) ?>">
                                    <?= htmlspecialchars($selected_invoice['payment_status']) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <h6>Invoice Items</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoice_items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                        <td class="text-end"><?= $item['quantity'] ?></td>
                                        <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                                        <td class="text-end"><?= number_format($item['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Subtotal</strong></td>
                                    <td class="text-end"><?= number_format($selected_invoice['subtotal'], 2) ?></td>
                                </tr>
                                <?php if ($selected_invoice['tax_amount'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end">Tax (<?= $selected_invoice['tax_rate'] ?>%)</td>
                                        <td class="text-end"><?= number_format($selected_invoice['tax_amount'], 2) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($selected_invoice['discount_amount'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end">Discount</td>
                                        <td class="text-end">-<?= number_format($selected_invoice['discount_amount'], 2) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total</strong></td>
                                    <td class="text-end"><strong><?= number_format($selected_invoice['total_amount'], 2) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Payment History -->
                    <?php
                    $payments_query = $conn->prepare("SELECT * FROM receipts WHERE invoice_id = ? ORDER BY payment_date DESC");
                    $payments_query->bind_param("i", $selected_invoice['invoice_id']);
                    $payments_query->execute();
                    $payments = $payments_query->get_result();
                    ?>
                    
                    <h6>Payment History</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments->num_rows > 0): ?>
                                    <?php 
                                    $total_paid = 0;
                                    while ($payment = $payments->fetch_assoc()): 
                                        $total_paid += $payment['payment_amount'];
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($payment['receipt_no']) ?></td>
                                            <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                                            <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                                            <td><?= htmlspecialchars($payment['payment_reference']) ?></td>
                                            <td class="text-end"><?= number_format($payment['payment_amount'], 2) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Total Paid</strong></td>
                                        <td class="text-end"><strong><?= number_format($total_paid, 2) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Balance Due</strong></td>
                                        <td class="text-end"><strong><?= number_format($selected_invoice['total_amount'] - $total_paid, 2) ?></strong></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No payments recorded</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!empty($selected_invoice['notes'])): ?>
                        <div class="mt-3">
                            <h6>Notes</h6>
                            <p><?= nl2br(htmlspecialchars($selected_invoice['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="#" class="btn btn-secondary" onclick="history.back(); return false;">
                            <i class="bi bi-arrow-left"></i> Back to Invoices
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="?action=generate_receipt" id="receiptForm">
                        <input type="hidden" name="invoice_id" id="receipt_invoice_id">
                        
                        <div class="mb-3">
                            <label for="invoice_reference" class="form-label">Invoice</label>
                            <input type="text" class="form-control" id="invoice_reference" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="receipt_no" class="form-label">Receipt Number</label>
                            <input type="text" class="form-control" name="receipt_no" id="receipt_no" 
                                   value="<?= generate_receipt_number() ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_date" class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="payment_date" id="payment_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_amount" class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" name="payment_amount" id="payment_amount" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" id="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_reference" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="payment_reference" id="payment_reference" 
                                   placeholder="Transaction ID, Check Number, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label for="receipt_notes" class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="receipt_notes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('receiptForm').submit()">Record Payment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Helper function to return badge class based on payment status
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'Paid':
                    return 'bg-success';
                case 'Partial':
                    return 'bg-warning';
                default:
                    return 'bg-danger';
            }
        }
        
        // Toggle form visibility
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            if (form.style.display === 'block') {
                form.style.display = 'none';
            } else {
                form.style.display = 'block';
            }
        }
        
        // Show receipt modal
        function showReceiptModal(invoiceId, invoiceNo) {
            document.getElementById('receipt_invoice_id').value = invoiceId;
            document.getElementById('invoice_reference').value = invoiceNo;
            
            // Create and show the modal
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();
        }
        
        // Item row template for adding new items
        const itemRowTemplate = `
            <div class="invoice-item form-row row">
                <div class="col-md-4 mb-2">
                    <input type="text" class="form-control" name="item_name[]" placeholder="Item name" required>
                    <input type="text" class="form-control mt-1" name="item_description[]" placeholder="Description">
                </div>
                <div class="col-md-3 mb-2">
                    <input type="number" class="form-control item-quantity" name="item_quantity[]" value="1" min="1" required>
                </div>
                <div class="col-md-2 mb-2">
                    <input type="number" step="0.01" class="form-control item-price" name="item_price[]" placeholder="0.00" required>
                </div>
                <div class="col-md-2 mb-2">
                    <input type="number" step="0.01" class="form-control item-total" name="item_total[]" placeholder="0.00" readonly>
                </div>
                <div class="col-md-1 mb-2">
                    <button type="button" class="btn btn-danger btn-sm remove-item">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        // Add new item row
        document.getElementById('addItemBtn').addEventListener('click', function() {
            const itemsContainer = document.getElementById('invoiceItems');
            const newRow = document.createElement('div');
            newRow.innerHTML = itemRowTemplate;
            itemsContainer.appendChild(newRow);
            
            // Add event listeners to new row
            addItemRowEventListeners(newRow);
        });
        
        // Remove item row
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item') || e.target.parentElement.classList.contains('remove-item')) {
                const button = e.target.classList.contains('remove-item') ? e.target : e.target.parentElement;
                const row = button.closest('.invoice-item');
                
                // Don't remove if it's the only row
                const allRows = document.querySelectorAll('.invoice-item');
                if (allRows.length > 1) {
                    row.remove();
                    calculateTotals();
                }
            }
        });
        
        // Calculate item total when quantity or price changes
        function addItemRowEventListeners(row) {
            const quantityInput = row.querySelector('.item-quantity');
            const priceInput = row.querySelector('.item-price');
            const totalInput = row.querySelector('.item-total');
            
            function calculateItemTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const total = quantity * price;
                totalInput.value = total.toFixed(2);
                calculateTotals();
            }
            
            quantityInput.addEventListener('input', calculateItemTotal);
            priceInput.addEventListener('input', calculateItemTotal);
        }
        
        // Calculate invoice totals
        function calculateTotals() {
            const itemTotals = document.querySelectorAll('.item-total');
            let subtotal = 0;
            
            itemTotals.forEach(function(input) {
                subtotal += parseFloat(input.value) || 0;
            });
            
            const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
            const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
            
            const taxAmount = subtotal * (taxRate / 100);
            const total = subtotal + taxAmount - discountAmount;
            
            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('tax_amount').value = taxAmount.toFixed(2);
            document.getElementById('total_amount').value = total.toFixed(2);
        }
        
        // Add event listeners to existing item rows
        document.querySelectorAll('.invoice-item').forEach(function(row) {
            addItemRowEventListeners(row);
        });
        
        // Add event listeners for tax and discount
        document.getElementById('tax_rate').addEventListener('input', calculateTotals);
        document.getElementById('discount_amount').addEventListener('input', calculateTotals);
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($action === 'view_invoice'): ?>
                // No need to show invoice form when viewing an invoice
            <?php else: ?>
                // toggleForm('createInvoiceForm');
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// Helper function to get badge class based on payment status
function getStatusBadgeClass($status) {
    switch($status) {
        case 'Paid':
            return 'bg-success';
        case 'Partial':
            return 'bg-warning';
        default:
            return 'bg-danger';
    }
}
?>
<?php
include 'views/footer.php';?>