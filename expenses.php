<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new expense
            $expense_reason = $_POST['expense_reason'];
            $amount = $_POST['amount'];
            $expense_date = $_POST['expense_date'];
            $payment_status = $_POST['payment_status'];
            $category_id = $_POST['category_id'];
            $vendor_name = $_POST['vendor_name'];
            $notes = $_POST['notes'];

            $sql = "INSERT INTO expenses (expense_reason, amount, expense_date, payment_status, category_id, vendor_name, notes)
                    VALUES ('$expense_reason', '$amount', '$expense_date', '$payment_status', $category_id, '$vendor_name', '$notes')";
            
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
            $category_id = $_POST['category_id'];
            $vendor_name = $_POST['vendor_name'];
            $notes = $_POST['notes'];

            $sql = "UPDATE expenses SET expense_reason='$expense_reason', amount='$amount', expense_date='$expense_date', 
                    payment_status='$payment_status', category_id=$category_id, vendor_name='$vendor_name', notes='$notes' 
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

// Fetch all expense categories
$categories_result = $conn->query("SELECT * FROM expense_categories ORDER BY category_name");
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}

// Fetch all expenses with category names
$sql = "SELECT e.*, c.category_name 
        FROM expenses e 
        LEFT JOIN expense_categories c ON e.category_id = c.category_id 
        ORDER BY e.expense_date DESC";
$result = $conn->query($sql);
$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}

// Include header
include 'views/header.php';

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
<body>
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
            <form method="post" action="expenses.php">
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
                    <div class="col-md-4 mb-3">
                        <label for="expense_date" class="form-label">Expense Date</label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" 
                               value="<?= $edit_data ? $edit_data['expense_date'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <option value="Paid" <?= ($edit_data && $edit_data['payment_status'] == 'Paid') ? 'selected' : '' ?>>Paid</option>
                            <option value="Not Paid" <?= ($edit_data && $edit_data['payment_status'] == 'Not Paid') ? 'selected' : '' ?>>Not Paid</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>" <?= ($edit_data && $edit_data['category_id'] == $category['category_id']) ? 'selected' : '' ?>>
                                    <?= $category['category_name'] ?>
                                </option>
                            <?php endforeach; ?>
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

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Expense Records</span>
            <div>
                <a href="expense_categories.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-tags"></i> Manage Categories
                </a>
            </div>
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
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Notes</th>
                            <th>Actions</th>
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
                                    <td><?= $expense['category_name'] ?? 'Uncategorized' ?></td>
                                    <td><?= $expense['vendor_name'] ?></td>
                                    <td><?= $expense['notes'] ?></td>
                                    <td>
                                        <a href="expenses.php?edit=<?= $expense['expense_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <a href="expenses.php?delete=<?= $expense['expense_id'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this expense?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No expense records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Expense Analysis
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
    
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Prepare data for category chart
    document.addEventListener('DOMContentLoaded', function() {
        // Category chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php 
                    $category_data = [];
                    $category_amounts = [];
                    
                    foreach ($expenses as $expense) {
                        $cat_name = $expense['category_name'] ?? 'Uncategorized';
                        if (!isset($category_data[$cat_name])) {
                            $category_data[$cat_name] = 0;
                        }
                        $category_data[$cat_name] += $expense['amount'];
                    }
                    
                    foreach ($category_data as $cat => $amount) {
                        echo "'" . $cat . "', ";
                        $category_amounts[] = $amount;
                    }
                    ?>
                ],
                datasets: [{
                    data: [<?= implode(', ', $category_amounts) ?>],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'
                    ]
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Expenses by Category'
                    }
                }
            }
        });

        // Status chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Not Paid'],
                datasets: [{
                    data: [
                        <?php 
                        $paid = 0;
                        $not_paid = 0;
                        
                        foreach ($expenses as $expense) {
                            if ($expense['payment_status'] == 'Paid') {
                                $paid += $expense['amount'];
                            } else {
                                $not_paid += $expense['amount'];
                            }
                        }
                        
                        echo $paid . ', ' . $not_paid;
                        ?>
                    ],
                    backgroundColor: ['#1cc88a', '#e74a3b']
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Payment Status'
                    }
                }
            }
        });
    });
</script>
</body>
</html>
<?php
include 'views/footer.php';
$conn->close();
?>