<?php
//this is payment_account_management.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';
function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

function createRequiredTables($conn) {
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'payment_accounts'");
    if (mysqli_num_rows($tableCheck) == 0) {
        $sql = "CREATE TABLE payment_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            account_type VARCHAR(50) NOT NULL,
            provider VARCHAR(100) NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            account_name VARCHAR(100) NOT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (employee_id),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Payment accounts table created successfully!";
        } else {
            $_SESSION['error'] = "Error creating payment accounts table: " . mysqli_error($conn);
        }
    }
}

// Create the required tables before processing anything else
createRequiredTables($conn);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new payment account
    if (isset($_POST['add_payment_account'])) {
        $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
        $account_type = mysqli_real_escape_string($conn, $_POST['account_type']);
        $provider = mysqli_real_escape_string($conn, $_POST['provider']);
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
        $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // Check if account number already exists
        $check_account = mysqli_query($conn, "SELECT account_number FROM payment_accounts WHERE account_number = '$account_number'");
        if (mysqli_num_rows($check_account) > 0) {
            $_SESSION['error'] = "Error: Account number already exists in the database!";
            header("Location: ../admin/payment_account_management.php");
            exit();
        }
        
        // If this is marked as primary, unset any other primary accounts for this employee
        if ($is_primary) {
            mysqli_query($conn, "UPDATE payment_accounts SET is_primary = 0 WHERE employee_id = '$employee_id'");
        }
        
        $sql = "INSERT INTO payment_accounts (employee_id, account_type, provider, account_number, account_name, is_primary) 
                VALUES ('$employee_id', '$account_type', '$provider', '$account_number', '$account_name', '$is_primary')";
        
        if (mysqli_query($conn, $sql)) {
            // Log this action
            $user_id = $_SESSION['user_id'];
            $action = "Added new payment account for employee ID: $employee_id";
            log_action($conn, $user_id, $action);
            
            $_SESSION['success'] = "Payment account added successfully!";
        } else {
            $_SESSION['error'] = "Error adding payment account: " . mysqli_error($conn);
        }
        
        header("Location: ../admin/payment_account_management.php");
        exit();
    }
    
    // Update payment account
    if (isset($_POST['update_payment_account'])) {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
        $account_type = mysqli_real_escape_string($conn, $_POST['account_type']);
        $provider = mysqli_real_escape_string($conn, $_POST['provider']);
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
        $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // Check if the account number already exists for another account
        $check_account = mysqli_query($conn, "SELECT id FROM payment_accounts WHERE account_number = '$account_number' AND id != '$id'");
        if (mysqli_num_rows($check_account) > 0) {
            $_SESSION['error'] = "Error: Account number already exists for another account!";
            header("Location: ../admin/payment_account_management.php");
            exit();
        }
        
        // If this is marked as primary, unset any other primary accounts for this employee
        if ($is_primary) {
            mysqli_query($conn, "UPDATE payment_accounts SET is_primary = 0 WHERE employee_id = '$employee_id' AND id != '$id'");
        }
        
        $sql = "UPDATE payment_accounts SET 
                employee_id = '$employee_id',
                account_type = '$account_type', 
                provider = '$provider', 
                account_number = '$account_number', 
                account_name = '$account_name', 
                is_primary = '$is_primary'
                WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql)) {
            // Log this action
            $user_id = $_SESSION['user_id'];
            $action = "Updated payment account ID: $id for employee ID: $employee_id";
            log_action($conn, $user_id, $action);
            
            $_SESSION['success'] = "Payment account updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating payment account: " . mysqli_error($conn);
        }
        
        header("Location: ../admin/payment_account_management.php");
        exit();
    }
    
    // Delete payment account
    if (isset($_POST['delete_payment_account'])) {
        $id = mysqli_real_escape_string($conn, $_POST['delete_id']);
        
        // Get account details for logging
        $result = mysqli_query($conn, "SELECT employee_id, account_type, provider FROM payment_accounts WHERE id = '$id'");
        $account = mysqli_fetch_assoc($result);
        
        $sql = "DELETE FROM payment_accounts WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql)) {
            // Log this action
            $user_id = $_SESSION['user_id'];
            $action = "Deleted payment account ID: $id (" . $account['account_type'] . " - " . $account['provider'] . ") for employee ID: " . $account['employee_id'];
            log_action($conn, $user_id, $action);
            
            $_SESSION['success'] = "Payment account deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting payment account: " . mysqli_error($conn);
        }
        
        header("Location: ../admin/payment_account_management.php");
        exit();
    }
    
    // Set account as primary
    if (isset($_POST['set_primary_account'])) {
        $id = mysqli_real_escape_string($conn, $_POST['account_id']);
        $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
        
        // First, unset all primary accounts for this employee
        mysqli_query($conn, "UPDATE payment_accounts SET is_primary = 0 WHERE employee_id = '$employee_id'");
        
        // Then set the selected account as primary
        $sql = "UPDATE payment_accounts SET is_primary = 1 WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql)) {
            // Log this action
            $user_id = $_SESSION['user_id'];
            $action = "Set payment account ID: $id as primary for employee ID: $employee_id";
            log_action($conn, $user_id, $action);
            
            $_SESSION['success'] = "Primary payment account updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating primary payment account: " . mysqli_error($conn);
        }
        
        header("Location: ../admin/payment_account_management.php");
        exit();
    }
}

// Get all employees
$sql = "SELECT id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY last_name, first_name";
$result = mysqli_query($conn, $sql);
$employees = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get all payment accounts with employee information
$sql = "SELECT pa.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name 
        FROM payment_accounts pa 
        JOIN employees e ON pa.employee_id = e.id 
        ORDER BY e.last_name, e.first_name, pa.is_primary DESC";
$result = mysqli_query($conn, $sql);
$payment_accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Now continue with the HTML part of your file...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Account Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/payroll_dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .provider-option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            cursor: pointer;
        }
        .provider-option:hover {
            background-color: #f8f9fa;
        }
        .provider-option.selected {
            background-color: #e9ecef;
            border-color: #007bff;
        }
        .provider-logo {
            width: 40px;
            height: 40px;
            margin-right: 10px;
            object-fit: contain;
        }
        .provider-name {
            font-weight: bold;
        }
        .provider-options-container {
            margin-top: 10px;
        }
        .payment-type-container {
            display: none;
        }
        .payment-type-container.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <!-- Logo and Brand on the left -->
            <span class="navbar-brand d-flex align-items-center">
                <div class="logo-container-nav me-2">
                    <img src="../assets/logo2.JPG" alt="Farm Logo" class="logo-nav">
                </div>
                Jubert Farms Finance 
            </span>
            
            <!-- Main Navigation Links - Centered -->
            <div class="nav-links-center">
                <a href="../admin/payroll_dashboard.php" class="nav-btn"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="../admin/payment_account_management.php" class="nav-btn active"><i class="fas fa-university"></i> Payment Accounts</a>
                <a href="../admin/payroll.php" class="nav-btn"><i class="fas fa-dollar-sign"></i> Payroll</a>
                <a href="../admin/employee_management.php" class="nav-btn"><i class="fas fa-users"></i> Employee Management</a>
            </div>
            
            <!-- User Info and Logout on the right -->
            <div>
                <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid px-4">
        <h1 class="mt-4">Payment Account Management</h1>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="../admin/payroll_dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Payment Accounts</li>
        </ol>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-university me-1"></i>
                Payment Accounts
                <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#addPaymentAccountModal">
                    <i class="fas fa-plus"></i> Add Payment Account
                </button>
            </div>
            <div class="card-body">
                <table id="paymentAccountsTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Account Type</th>
                            <th>Provider</th>
                            <th>Account Number</th>
                            <th>Account Name</th>
                            <th>Primary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_accounts as $account): ?>
                        <tr>
                            <td><?php echo $account['id']; ?></td>
                            <td><?php echo $account['employee_name']; ?></td>
                            <td><?php echo $account['account_type']; ?></td>
                            <td>
                                <?php 
                                $providerImage = '';
                                switch ($account['provider']) {
                                    case 'Ecobank':
                                        $providerImage = '../assets/images/payment/ecobank.png';
                                        break;
                                    case 'ABSA':
                                        $providerImage = '../assets/images/payment/absa.png';
                                        break;
                                    case 'CalBank':
                                        $providerImage = '../assets/images/payment/calbank.png';
                                        break;
                                    case 'Ghana Commercial Bank':
                                        $providerImage = '../assets/images/payment/gcb.png';
                                        break;
                                    case 'Telecel Cash':
                                        $providerImage = '../assets/images/payment/telecel.png';
                                        break;
                                    case 'AirtelTigo Cash':
                                        $providerImage = '../assets/images/payment/airteltigo.png';
                                        break;
                                    case 'MTN MoMo':
                                        $providerImage = '../assets/images/payment/mtn.png';
                                        break;
                                    default:
                                        $providerImage = '../assets/images/payment/default.png';
                                }
                                ?>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo $providerImage; ?>" alt="<?php echo $account['provider']; ?>" class="me-2" style="width: 24px; height: 24px; object-fit: contain;">
                                    <?php echo $account['provider']; ?>
                                </div>
                            </td>
                            <td><?php echo $account['account_number']; ?></td>
                            <td><?php echo $account['account_name']; ?></td>
                            <td>
                                <?php if ($account['is_primary']): ?>
                                    <span class="badge bg-success">Primary</span>
                                <?php else: ?>
                                    <form action="../admin/payment_account_management.php" method="post" class="d-inline">
                                        <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                        <input type="hidden" name="employee_id" value="<?php echo $account['employee_id']; ?>">
                                        <button type="submit" name="set_primary_account" class="btn btn-sm btn-outline-secondary">
                                            Set as Primary
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPaymentAccountModal<?php echo $account['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deletePaymentAccountModal<?php echo $account['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Payment Account Modal -->
    <div class="modal fade" id="addPaymentAccountModal" tabindex="-1" aria-labelledby="addPaymentAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPaymentAccountModalLabel">Add New Payment Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="../admin/payment_account_management.php" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee</label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>"><?php echo $employee['last_name'] . ', ' . $employee['first_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="account_type" class="form-label">Account Type</label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">Select Account Type</option>
                                <option value="Bank Account">Bank Account</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        
                        <!-- Provider options will be shown based on account type selection -->
                        <div class="mb-3">
                            <label for="provider" class="form-label">Provider</label>
                            <input type="hidden" id="provider" name="provider" required>
                            
                            <!-- Bank Account Options -->
                            <div id="bank_options" class="payment-type-container">
                                <div class="provider-options-container">
                                    <div class="provider-option" data-provider="Ecobank">
                                        <img src="../assets/images/payment/ecobank.png" alt="Ecobank" class="provider-logo">
                                        <span class="provider-name">Ecobank</span>
                                    </div>
                                    <div class="provider-option" data-provider="ABSA">
                                        <img src="../assets/images/payment/absa.png" alt="ABSA" class="provider-logo">
                                        <span class="provider-name">ABSA</span>
                                    </div>
                                    <div class="provider-option" data-provider="CalBank">
                                        <img src="../assets/images/payment/calbank.png" alt="CalBank" class="provider-logo">
                                        <span class="provider-name">CalBank</span>
                                    </div>
                                    <div class="provider-option" data-provider="Ghana Commercial Bank">
                                        <img src="../assets/images/payment/gcb.png" alt="Ghana Commercial Bank" class="provider-logo">
                                        <span class="provider-name">Ghana Commercial Bank</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mobile Money Options -->
                            <div id="mobile_options" class="payment-type-container">
                                <div class="provider-options-container">
                                    <div class="provider-option" data-provider="Telecel Cash">
                                        <img src="../assets/images/payment/telecel.png" alt="Telecel Cash" class="provider-logo">
                                        <span class="provider-name">Telecel Cash</span>
                                    </div>
                                    <div class="provider-option" data-provider="AirtelTigo Cash">
                                        <img src="../assets/images/payment/airteltigo.png" alt="AirtelTigo Cash" class="provider-logo">
                                        <span class="provider-name">AirtelTigo Cash</span>
                                    </div>
                                    <div class="provider-option" data-provider="MTN MoMo">
                                        <img src="../assets/images/payment/mtn.png" alt="MTN MoMo" class="provider-logo">
                                        <span class="provider-name">MTN MoMo</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cash Option (shows direct input) -->
                            <div id="cash_options" class="payment-type-container">
                                <input type="text" class="form-control" id="cash_provider" placeholder="Enter payment method details">
                                <small class="form-text text-muted">For cash payments, you can specify details like "Office Cash", "Field Cash", etc.</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="account_number" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="account_name" class="form-label">Account Name</label>
                            <input type="text" class="form-control" id="account_name" name="account_name" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_primary" name="is_primary">
                            <label class="form-check-label" for="is_primary">Set as Primary Account</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_payment_account" class="btn btn-primary">Add Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit and Delete Modals for each Payment Account -->
    <?php foreach ($payment_accounts as $account): ?>
    <!-- Edit Payment Account Modal -->
    <div class="modal fade" id="editPaymentAccountModal<?php echo $account['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payment Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="../admin/payment_account_management.php" method="post">
                    <input type="hidden" name="id" value="<?php echo $account['id']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_employee_id<?php echo $account['id']; ?>" class="form-label">Employee</label>
                            <select class="form-select" id="edit_employee_id<?php echo $account['id']; ?>" name="employee_id" required>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo $employee['id'] == $account['employee_id'] ? 'selected' : ''; ?>>
                                        <?php echo $employee['last_name'] . ', ' . $employee['first_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_account_type<?php echo $account['id']; ?>" class="form-label">Account Type</label>
                            <select class="form-select edit-account-type" id="edit_account_type<?php echo $account['id']; ?>" name="account_type" data-account-id="<?php echo $account['id']; ?>" required>
                                <option value="Bank Account" <?php echo $account['account_type'] === 'Bank Account' ? 'selected' : ''; ?>>Bank Account</option>
                                <option value="Mobile Money" <?php echo $account['account_type'] === 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                                <option value="Cash" <?php echo $account['account_type'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            </select>
                        </div>
                        
                        <!-- Provider options for edit modal -->
                        <div class="mb-3">
                            <label for="edit_provider<?php echo $account['id']; ?>" class="form-label">Provider</label>
                            <input type="hidden" id="edit_provider<?php echo $account['id']; ?>" name="provider" value="<?php echo $account['provider']; ?>" required>
                            
                            <!-- Bank Account Options -->
                            <div id="edit_bank_options<?php echo $account['id']; ?>" class="payment-type-container <?php echo $account['account_type'] === 'Bank Account' ? 'active' : ''; ?>">
                                <div class="provider-options-container">
                                    <div class="provider-option <?php echo $account['provider'] === 'Ecobank' ? 'selected' : ''; ?>" data-provider="Ecobank">
                                        <img src="../assets/images/payment/ecobank.png" alt="Ecobank" class="provider-logo">
                                        <span class="provider-name">Ecobank</span>
                                    </div>
                                    <div class="provider-option <?php echo $account['provider'] === 'ABSA' ? 'selected' : ''; ?>" data-provider="ABSA">
                                        <img src="../assets/images/payment/absa.png" alt="ABSA" class="provider-logo">
                                        <span class="provider-name">ABSA</span>
                                    </div>
                                    <div class="provider-option <?php echo $account['provider'] === 'CalBank' ? 'selected' : ''; ?>" data-provider="CalBank">
                                        <img src="../assets/images/payment/calbank.png" alt="CalBank" class="provider-logo">
                                        <span class="provider-name">CalBank</span>
                                    </div>
                                    <div class="provider-option <?php echo $account['provider'] === 'Ghana Commercial Bank' ? 'selected' : ''; ?>" data-provider="Ghana Commercial Bank">
                                        <img src="../assets/images/payment/gcb.png" alt="Ghana Commercial Bank" class="provider-logo">
                                        <span class="provider-name">Ghana Commercial Bank</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mobile Money Options -->
                            <div id="edit_mobile_options<?php echo $account['id']; ?>" class="payment-type-container <?php echo $account['account_type'] === 'Mobile Money' ? 'active' : ''; ?>">
                                <div class="provider-options-container">
                                    <div class="provider-option <?php echo $account['provider'] === 'Telecel Cash' ? 'selected' : ''; ?>" data-provider="Telecel Cash">
                                        <img src="../assets/images/payment/telecel.png" alt="Telecel Cash" class="provider-logo">
                                        <span class="provider-name">Telecel Cash</span>
                                    </div>
                                    <div class="provider-option <?php echo $account['provider'] === 'AirtelTigo Cash' ? 'selected' : ''; ?>" data-provider="AirtelTigo Cash">
                                        <img src="../assets/images/payment/airteltigo.png" alt="AirtelTigo Cash" class="provider-logo">
                                        <span class="provider-name">AirtelTigo Cash</span>
                                    </div>
                                    <div class="provider-option <?php echo $account['provider'] === 'MTN MoMo' ? 'selected' : ''; ?>" data-provider="MTN MoMo">
                                        <img src="../assets/images/payment/mtn.png" alt="MTN MoMo" class="provider-logo">
                                        <span class="provider-name">MTN MoMo</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cash Option (shows direct input) -->
                            <div id="edit_cash_options<?php echo $account['id']; ?>" class="payment-type-container <?php echo $account['account_type'] === 'Cash' ? 'active' : ''; ?>">
                                <input type="text" class="form-control" id="edit_cash_provider<?php echo $account['id']; ?>" 
                                       value="<?php echo $account['account_type'] === 'Cash' ? $account['provider'] : ''; ?>" 
                                       placeholder="Enter payment method details">
                                <small class="form-text text-muted">For cash payments, you can specify details like "Office Cash", "Field Cash", etc.</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_account_number<?php echo $account['id']; ?>" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="edit_account_number<?php echo $account['id']; ?>" name="account_number" value="<?php echo $account['account_number']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_account_name<?php echo $account['id']; ?>" class="form-label">Account Name</label>
                            <input type="text" class="form-control" id="edit_account_name<?php echo $account['id']; ?>" name="account_name" value="<?php echo $account['account_name']; ?>" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_primary<?php echo $account['id']; ?>" name="is_primary" <?php echo $account['is_primary'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="edit_is_primary<?php echo $account['id']; ?>">Set as Primary Account</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_payment_account" class="btn btn-primary">Update Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Payment Account Modal -->
    <div class="modal fade" id="deletePaymentAccountModal<?php echo $account['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this payment account?</p>
                    <ul>
                        <li><strong>Employee:</strong> <?php echo $account['employee_name']; ?></li>
                        <li><strong>Account Type:</strong> <?php echo $account['account_type']; ?></li>
                        <li><strong>Provider:</strong> 
                            <div class="d-flex align-items-center">
                                <?php 
                                $providerImage = '';
                                switch ($account['provider']) {
                                    case 'Ecobank':
                                        $providerImage = '../assets/images/payment/ecobank.png';
                                        break;
                                    case 'ABSA':
                                        $providerImage = '../assets/images/payment/absa.png';
                                        break;
                                    case 'CalBank':
                                        $providerImage = '../assets/images/payment/calbank.png';
                                        break;
                                    case 'Ghana Commercial Bank':
                                        $providerImage = '../assets/images/payment/gcb.png';
                                        break;
                                    case 'Telecel Cash':
                                        $providerImage = '../assets/images/payment/telecel.png';
                                        break;
                                    case 'AirtelTigo Cash':
                                        $providerImage = '../assets/images/payment/airteltigo.png';
                                        break;
                                    case 'MTN MoMo':
                                        $providerImage = '../assets/images/payment/mtn.png';
                                        break;
                                    default:
                                        $providerImage = '../assets/images/payment/default.png';
                                }
                                ?>
                                <img src="<?php echo $providerImage; ?>" alt="<?php echo $account['provider']; ?>" class="me-2" style="width: 24px; height: 24px; object-fit: contain;">
                                <?php echo $account['provider']; ?>
                            </div>
                        </li>
                        <li><strong>Account Number:</strong> <?php echo $account['account_number']; ?></li>
                    </ul>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form action="../admin/payment_account_management.php" method="post">
                        <input type="hidden" name="delete_id" value="<?php echo $account['id']; ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_payment_account" class="btn btn-danger">Delete Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTables
        $(document).ready(function() {
            $('#paymentAccountsTable').DataTable({
                responsive: true,
                order: [[1, 'asc']], // Sort by employee name
                lengthMenu: [10, 25, 50, 100],
                pageLength: 10
            });
        });

        // For the Add Payment Account Modal
        const accountTypeSelect = document.getElementById('account_type');
        const providerInput = document.getElementById('provider');
        const bankOptions = document.getElementById('bank_options');
        const mobileOptions = document.getElementById('mobile_options');
        const cashOptions = document.getElementById('cash_options');
        const cashProviderInput = document.getElementById('cash_provider');

        // Handle account type change in add modal
        if (accountTypeSelect) {
            accountTypeSelect.addEventListener('change', function() {
                // Hide all option containers
                bankOptions.classList.remove('active');
                mobileOptions.classList.remove('active');
                cashOptions.classList.remove('active');
                
                // Show the relevant container based on selection
                if (this.value === 'Bank Account') {
                    bankOptions.classList.add('active');
                } else if (this.value === 'Mobile Money') {
                    mobileOptions.classList.add('active');
                } else if (this.value === 'Cash') {
                    cashOptions.classList.add('active');
                }
                
                // Reset provider selection
                providerInput.value = '';
                document.querySelectorAll('#bank_options .provider-option, #mobile_options .provider-option').forEach(option => {
                    option.classList.remove('selected');
                });
            });
        }
        
        // Handle provider selection in add modal
        document.querySelectorAll('#bank_options .provider-option, #mobile_options .provider-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options in the same container
                const container = this.closest('.provider-options-container');
                container.querySelectorAll('.provider-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Set the provider value in the hidden input
                providerInput.value = this.getAttribute('data-provider');
            });
        });
        
        // Handle cash provider input
        if (cashProviderInput) {
            cashProviderInput.addEventListener('input', function() {
                providerInput.value = this.value;
            });
        }
        
        // Handle edit modals
        document.querySelectorAll('.edit-account-type').forEach(select => {
            select.addEventListener('change', function() {
                const accountId = this.getAttribute('data-account-id');
                const bankOptions = document.getElementById(`edit_bank_options${accountId}`);
                const mobileOptions = document.getElementById(`edit_mobile_options${accountId}`);
                const cashOptions = document.getElementById(`edit_cash_options${accountId}`);
                const providerInput = document.getElementById(`edit_provider${accountId}`);
                const cashProviderInput = document.getElementById(`edit_cash_provider${accountId}`);
                
                // Hide all option containers
                bankOptions.classList.remove('active');
                mobileOptions.classList.remove('active');
                cashOptions.classList.remove('active');
                
                // Show the relevant container based on selection
                if (this.value === 'Bank Account') {
                    bankOptions.classList.add('active');
                } else if (this.value === 'Mobile Money') {
                    mobileOptions.classList.add('active');
                } else if (this.value === 'Cash') {
                    cashOptions.classList.add('active');
                }
                
                // Reset provider selection except for Cash
                if (this.value !== 'Cash') {
                    providerInput.value = '';
                    document.querySelectorAll(`#edit_bank_options${accountId} .provider-option, #edit_mobile_options${accountId} .provider-option`).forEach(option => {
                        option.classList.remove('selected');
                    });
                } else {
                    // For Cash, set the value from the cash input
                    providerInput.value = cashProviderInput.value;
                }
            });
            
            // Handle provider selection in edit modals
            const accountId = select.getAttribute('data-account-id');
            document.querySelectorAll(`#edit_bank_options${accountId} .provider-option, #edit_mobile_options${accountId} .provider-option`).forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options in the same container
                    const container = this.closest('.provider-options-container');
                    container.querySelectorAll('.provider-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Set the provider value in the hidden input
                    document.getElementById(`edit_provider${accountId}`).value = this.getAttribute('data-provider');
                });
            });
            
            // Handle cash provider input in edit modals
            const cashProviderInput = document.getElementById(`edit_cash_provider${accountId}`);
            if (cashProviderInput) {
                cashProviderInput.addEventListener('input', function() {
                    document.getElementById(`edit_provider${accountId}`).value = this.value;
                });
            }
        });
        
        // Pre-select providers in edit modals based on existing values
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                const modalId = this.getAttribute('id');
                if (modalId && modalId.startsWith('editPaymentAccountModal')) {
                    const accountId = modalId.replace('editPaymentAccountModal', '');
                    const accountType = document.getElementById(`edit_account_type${accountId}`).value;
                    const providerValue = document.getElementById(`edit_provider${accountId}`).value;
                    
                    if (accountType === 'Bank Account' || accountType === 'Mobile Money') {
                        const container = accountType === 'Bank Account' ? 
                            document.getElementById(`edit_bank_options${accountId}`) : 
                            document.getElementById(`edit_mobile_options${accountId}`);
                        
                        if (container) {
                            container.querySelectorAll('.provider-option').forEach(option => {
                                if (option.getAttribute('data-provider') === providerValue) {
                                    option.classList.add('selected');
                                } else {
                                    option.classList.remove('selected');
                                }
                            });
                        }
                    } else if (accountType === 'Cash') {
                        const cashInput = document.getElementById(`edit_cash_provider${accountId}`);
                        if (cashInput) {
                            cashInput.value = providerValue;
                        }
                    }
                }
            });
        });
        
        // For form submission validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(event) {
                const accountTypeInput = this.querySelector('[name="account_type"]');
                const providerInput = this.querySelector('[name="provider"]');
                
                if (accountTypeInput && providerInput && accountTypeInput.value !== 'Cash' && !providerInput.value) {
                    event.preventDefault();
                    alert('Please select a provider for the account.');
                }
                
                if (accountTypeInput && providerInput && accountTypeInput.value === 'Cash' && !providerInput.value) {
                    const cashInputId = providerInput.id.replace('provider', 'cash_provider');
                    const cashInput = document.getElementById(cashInputId);
                    if (cashInput && cashInput.value) {
                        providerInput.value = cashInput.value;
                    } else {
                        event.preventDefault();
                        alert('Please enter a provider name for cash payment.');
                    }
                }
            });
        });
    });
    </script>

    <?php include '../views/footer.php'; ?>
    
    <!-- Note: Create a directory structure for payment gateway logos -->
    <!-- Directory path should be: ../assets/images/payment/ -->
    <!-- Required logo files: ecobank.png, absa.png, calbank.png, gcb.png, telecel.png, airteltigo.png, mtn.png, default.png -->
</body>
</html>


                                    