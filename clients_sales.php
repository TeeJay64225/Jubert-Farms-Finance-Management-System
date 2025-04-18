<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';

function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}
log_action($conn, $_SESSION['user_id'], "Viewed sales for client ID $client_id");

// Check if client_id is provided in URL
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    // Redirect back to clients page if no ID provided
    header('Location: clients.php');
    exit;
}

$client_id = intval($_GET['client_id']);

// Fetch client information
$clientSql = "SELECT * FROM clients WHERE client_id = ?";
$clientStmt = $conn->prepare($clientSql);
$clientStmt->bind_param('i', $client_id);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();

// Check if client exists
if ($clientResult->num_rows === 0) {
    header('Location: clients.php');
    exit;
}

$client = $clientResult->fetch_assoc();

// Handle new sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sale'])) {
    $product_name = trim($_POST['product_name']);
    $amount = floatval($_POST['amount']);
    $sale_date = $_POST['sale_date'];
    $payment_status = $_POST['payment_status'];
    $notes = trim($_POST['notes']);
    
    // Validate inputs
    $errors = [];
    if (empty($product_name)) {
        $errors[] = "Product name is required";
    }
    
    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    }
    
    if (empty($sale_date)) {
        $errors[] = "Sale date is required";
    }
    
    // If no errors, insert the sale
    if (empty($errors)) {
        $insertSql = "INSERT INTO sales (product_name, amount, sale_date, payment_status, client_id, notes) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('sdssis', $product_name, $amount, $sale_date, $payment_status, $client_id, $notes);
        
        if ($insertStmt->execute()) {
            $success_message = "Sale added successfully!";
            log_action($conn, $_SESSION['user_id'], "Added sale for client ID $client_id: $product_name, $$amount on $sale_date");
        } else {
            $errors[] = "Error adding sale: " . $conn->error;
        }
        
        
        $insertStmt->close();
    }
}

// Fetch all sales for this client
$salesSql = "SELECT * FROM sales WHERE client_id = ? ORDER BY sale_date DESC";
$salesStmt = $conn->prepare($salesSql);
$salesStmt->bind_param('i', $client_id);
$salesStmt->execute();
$salesResult = $salesStmt->get_result();

$totalSales = 0;
$totalPaid = 0;
$totalUnpaid = 0;
$sales = [];

while ($row = $salesResult->fetch_assoc()) {
    $sales[] = $row;
    $totalSales += $row['amount'];
    
    if ($row['payment_status'] === 'Paid') {
        $totalPaid += $row['amount'];
    } else {
        $totalUnpaid += $row['amount'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales for <?= htmlspecialchars($client['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="clients.php">Clients</a></li>
                <li class="breadcrumb-item active" aria-current="page">Sales for <?= htmlspecialchars($client['full_name']) ?></li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-6">
                <h2 class="mb-4">Sales for <?= htmlspecialchars($client['full_name']) ?></h2>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                    Add New Sale
                </button>
            </div>
        </div>

        <!-- Client Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                Client Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?= htmlspecialchars($client['full_name']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($client['phone_number']) ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($client['address']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Sales Summary</h5>
                                <p class="card-text"><strong>Total Sales:</strong> $<?= number_format($totalSales, 2) ?></p>
                                <p class="card-text"><strong>Total Paid:</strong> $<?= number_format($totalPaid, 2) ?></p>
                                <p class="card-text"><strong>Total Unpaid:</strong> $<?= number_format($totalUnpaid, 2) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display success/error messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Sales Table -->
        <div class="card">
            <div class="card-header">
                Sales History
            </div>
            <div class="card-body">
                <?php if (count($sales) > 0): ?>
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><?= $sale['sale_id'] ?></td>
                                    <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                    <td>$<?= number_format($sale['amount'], 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($sale['sale_date'])) ?></td>
                                    <td>
                                        <?php if ($sale['payment_status'] === 'Paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Not Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit_sale.php?id=<?= $sale['sale_id'] ?>&client_id=<?= $client_id ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="delete_sale.php?id=<?= $sale['sale_id'] ?>&client_id=<?= $client_id ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this sale?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">
                        No sales records found for this client.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for Adding New Sale -->
    <div class="modal fade" id="addSaleModal" tabindex="-1" aria-labelledby="addSaleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSaleModalLabel">Add New Sale for <?= htmlspecialchars($client['full_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="product_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount ($)</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                        </div>
                        <div class="mb-3">
                            <label for="sale_date" class="form-label">Sale Date</label>
                            <input type="date" class="form-control" id="sale_date" name="sale_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status" required>
                                <option value="Paid">Paid</option>
                                <option value="Not Paid">Not Paid</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_sale" class="btn btn-primary">Add Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$clientStmt->close();
$salesStmt->close();
$conn->close();
<?php
include 'views/footer.php';?>
?>