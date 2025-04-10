<?php
session_start();
include '../config/db.php';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new expense
            $expense_reason = $_POST['expense_reason'];
            $amount = $_POST['amount'];
            $expense_date = $_POST['expense_date'];
            $payment_status = $_POST['payment_status'];
            $vendor_name = $_POST['vendor_name'];
            $notes = $_POST['notes'];

            $sql = "INSERT INTO expenses (expense_reason, amount, expense_date, payment_status, vendor_name, notes)
                    VALUES ('$expense_reason', '$amount', '$expense_date', '$payment_status', '$vendor_name', '$notes')";
            
            if ($conn->query($sql) === TRUE) {
                $message = "Expense record added successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'update') {
            // Update existing expense
            $expense_id = $_POST['expense_id'];
            $expense_reason = $_POST['expense_reason'];
            $amount = $_POST['amount'];
            $expense_date = $_POST['expense_date'];
            $payment_status = $_POST['payment_status'];
            $vendor_name = $_POST['vendor_name'];
            $notes = $_POST['notes'];

            $sql = "UPDATE expenses SET expense_reason='$expense_reason', amount='$amount', expense_date='$expense_date', 
                    payment_status='$payment_status', vendor_name='$vendor_name', notes='$notes' 
                    WHERE expense_id=$expense_id";

            if ($conn->query($sql) === TRUE) {
                $message = "Expense record updated successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}

// Delete expense
if (isset($_GET['delete'])) {
    $expense_id = $_GET['delete'];
    $sql = "DELETE FROM expenses WHERE expense_id=$expense_id";

    if ($conn->query($sql) === TRUE) {
        $message = "Expense record deleted successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Edit expense
$edit_data = null;
if (isset($_GET['edit'])) {
    $expense_id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM expenses WHERE expense_id=$expense_id");
    $edit_data = $result->fetch_assoc();
}

// Fetch all expenses
$sql = "SELECT * FROM expenses ORDER BY expense_date DESC";
$result = $conn->query($sql);
$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}

// Include header


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    </head>
    <style>
    :root {
            --primary-color: #2c6e49;
            --secondary-color: #4c956c;
            --accent-color: #fefee3;
            --light-color: #f0f3f5;
            --dark-color: #1a3a1a;
        }
        
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 0.8rem 1.5rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        </style>
<body>
        <!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-leaf"></i> Farm Finance Dashboard</span>
        <div class="d-flex">
            <!-- Main Navigation Links -->
            <div class="me-4">
            <a href="../employee/employee_dashboard.php" class="btn btn-outline-light me-2"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="../employee/employee_sale.php" class="btn btn-outline-light me-2"><i class="fas fa-dollar-sign"></i> Sales</a>
                <a href="../employee/employee_expense.php" class="btn btn-outline-light me-2"><i class="fas fa-receipt"></i> Expenses</a>
            </div>
            <!-- User Info and Logout -->
            <div>
            <span class="text-white me-3"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['employee_name'] ?? 'Employee'; ?></span>
                <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>
<div class="container mt-4">
    <h1>Expenses Management</h1>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <?= $edit_data ? 'Edit Expense' : 'Add New Expense' ?>
        </div>
        <div class="card-body">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'add' ?>">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="expense_id" value="<?= $edit_data['expense_id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="expense_reason" class="form-label">Expense Reason</label>
                        <input type="text" class="form-control" id="expense_reason" name="expense_reason" 
                               value="<?= $edit_data ? $edit_data['expense_reason'] : '' ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                               value="<?= $edit_data ? $edit_data['amount'] : '' ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="expense_date" class="form-label">Expense Date</label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" 
                               value="<?= $edit_data ? $edit_data['expense_date'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <option value="Paid" <?= ($edit_data && $edit_data['payment_status'] == 'Paid') ? 'selected' : '' ?>>Paid</option>
                            <option value="Not Paid" <?= ($edit_data && $edit_data['payment_status'] == 'Not Paid') ? 'selected' : '' ?>>Not Paid</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vendor_name" class="form-label">Vendor Name</label>
                        <input type="text" class="form-control" id="vendor_name" name="vendor_name" 
                               value="<?= $edit_data ? $edit_data['vendor_name'] : '' ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?= $edit_data ? $edit_data['notes'] : '' ?></textarea>
                    </div>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary"><?= $edit_data ? 'Update' : 'Add' ?> Expense</button>
                    <?php if ($edit_data): ?>
                        <a href="expenses.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Expense Records
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reason</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Vendor</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($expenses) > 0): ?>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?= $expense['expense_id'] ?></td>
                                    <td><?= $expense['expense_reason'] ?></td>
                                    <td>GHS<?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= $expense['expense_date'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $expense['payment_status'] == 'Paid' ? 'success' : 'warning' ?>">
                                            <?= $expense['payment_status'] ?>
                                        </span>
                                    </td>
                                    <td><?= $expense['vendor_name'] ?></td>
                                    <td><?= $expense['notes'] ?></td>
                              
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No expense records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
    
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>