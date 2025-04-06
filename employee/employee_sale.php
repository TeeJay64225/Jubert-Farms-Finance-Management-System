<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../config/db.php';

// Initialize variables
$edit_mode = false;
$sale_id = '';
$product_name = '';
$amount = '';
$sale_date = date('Y-m-d'); // Default to today
$payment_status = 'Not Paid';
$customer_name = '';
$notes = '';
$message = '';
$client_id = NULL; // Initialize client_id variable


// Handle Delete Operation
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $sql = "DELETE FROM sales WHERE sale_id=$delete_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "<div class='alert alert-success'>Sale record deleted successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}

// Handle Edit Operation - Load Data
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $result = $conn->query("SELECT * FROM sales WHERE sale_id=$edit_id");
    
    if ($result->num_rows > 0) {
        $edit_mode = true;
        $row = $result->fetch_assoc();
        $sale_id = $row['sale_id'];
        $product_name = $row['product_name'];
        $amount = $row['amount'];
        $sale_date = $row['sale_date'];
        $payment_status = $row['payment_status'];
        $notes = $row['notes'];
        $client_id = $row['client_id']; // Load client_id from the database
        
        // Get customer name from clients table if client_id exists
        if ($client_id) {
            $client_result = $conn->query("SELECT full_name FROM clients WHERE client_id = $client_id");
            if ($client_result && $client_result->num_rows > 0) {
                $client_row = $client_result->fetch_assoc();
                $customer_name = $client_row['full_name'];
            }
        }
    }
}

// Handle Form Submission - Add or Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $product_name = $_POST['product_name'];
    $amount = $_POST['amount'];
    $sale_date = $_POST['sale_date'];
    $payment_status = $_POST['payment_status'];
    $customer_name = $_POST['customer_name'];
    $notes = $_POST['notes'];
    $client_id = isset($_POST['client_id']) && !empty($_POST['client_id']) ? $_POST['client_id'] : NULL; // Get client_id from the form or set to NULL
    
    // Sanitize inputs to prevent SQL injection
    $product_name = $conn->real_escape_string($product_name);
    $amount = $conn->real_escape_string($amount);
    $sale_date = $conn->real_escape_string($sale_date);
    $payment_status = $conn->real_escape_string($payment_status);
    $customer_name = $conn->real_escape_string($customer_name);
    $notes = $conn->real_escape_string($notes);
    
    if (isset($_POST['sale_id']) && !empty($_POST['sale_id'])) {
        // Update existing record
        $sale_id = $_POST['sale_id'];
        $sql = "UPDATE sales SET 
                product_name='$product_name', 
                amount='$amount', 
                sale_date='$sale_date', 
                payment_status='$payment_status', 
                notes='$notes',
                client_id=" . ($client_id ? $client_id : "NULL") . "
                WHERE sale_id=$sale_id";
        
        if ($conn->query($sql) === TRUE) {
            $message = "<div class='alert alert-success'>Sale record updated successfully!</div>";
            // Reset form
            $edit_mode = false;
            $sale_id = '';
            $product_name = '';
            $amount = '';
            $sale_date = date('Y-m-d');
            $payment_status = 'Not Paid';
            $customer_name = '';
            $notes = '';
            $client_id = NULL;
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    } else {
        // Add new record - removed customer_name field since it doesn't exist in the table
        $sql = "INSERT INTO sales (product_name, amount, sale_date, payment_status, client_id, notes)
                VALUES ('$product_name', '$amount', '$sale_date', '$payment_status', " . ($client_id ? $client_id : "NULL") . ", '$notes')";
        
        if ($conn->query($sql) === TRUE) {
            $message = "<div class='alert alert-success'>Sale record added successfully!</div>";
            // Reset form
            $product_name = '';
            $amount = '';
            $sale_date = date('Y-m-d');
            $payment_status = 'Not Paid';
            $customer_name = '';
            $notes = '';
            $client_id = NULL;
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    }
}

// Get all sales records for display
$sql = "SELECT s.*, c.full_name as client_name FROM sales s 
        LEFT JOIN clients c ON s.client_id = c.client_id 
        ORDER BY sale_date DESC";
$result = $conn->query($sql);

// Get all clients for the dropdown
$clients_query = "SELECT client_id, full_name FROM clients ORDER BY full_name";
$clients_result = $conn->query($clients_query);

// Include header


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
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

        .form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
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
    <div class="container">
        <h1 class="mb-4">Sales Management</h1>
        
        <?php echo $message; ?>
        
        <div class="form-section">
            <h3><?php echo $edit_mode ? 'Edit Sale Record' : 'Add New Sale'; ?></h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="sale_id" value="<?php echo $sale_id; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="product_name">Product Name:</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" value="<?php echo $product_name; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="amount">Amount:</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo $amount; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="sale_date">Sale Date:</label>
                            <input type="date" class="form-control" id="sale_date" name="sale_date" value="<?php echo $sale_date; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="payment_status">Payment Status:</label>
                            <select class="form-control" id="payment_status" name="payment_status">
                                <option value="Paid" <?php if($payment_status == 'Paid') echo 'selected'; ?>>Paid</option>
                                <option value="Not Paid" <?php if($payment_status == 'Not Paid') echo 'selected'; ?>>Not Paid</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="client_id">Select Client:</label>
                            <select class="form-control" id="client_id" name="client_id">
                                <option value="">-- No Client --</option>
                                <?php
                                if ($clients_result && $clients_result->num_rows > 0) {
                                    while ($client = $clients_result->fetch_assoc()) {
                                        $selected = ($client_id == $client['client_id']) ? 'selected' : '';
                                        echo "<option value='{$client['client_id']}' $selected>{$client['full_name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="customer_name">Customer Name (Non-Client):</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?php echo $customer_name; ?>">
                            <small class="text-muted">Use this field if the customer is not in your client list</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="notes">Notes:</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $notes; ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Update Sale' : 'Add Sale'; ?></button>
                <?php if($edit_mode): ?>
                    <a href="sales.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-responsive">
            <h3>Sales Records</h3>
            <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Amount</th>
                        <th>Sale Date</th>
                        <th>Status</th>
                        <th>Client</th>
                        <th>Notes</th>

                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['sale_id']}</td>
                                <td>{$row['product_name']}</td>
                                <td>GHS" . number_format($row['amount'], 2) . "</td>
                                <td>" . date('M d, Y', strtotime($row['sale_date'])) . "</td>
                                <td>" . ($row['payment_status'] == 'Paid' ? 
                                    "<span class='badge bg-success'>Paid</span>" : 
                                    "<span class='badge bg-warning'>Not Paid</span>") . "</td>
                                <td>" . ($row['client_name'] ? $row['client_name'] : '-') . "</td>
                                <td>" . (strlen($row['notes']) > 50 ? substr($row['notes'], 0, 50) . "..." : $row['notes']) . "</td>
                              
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='8' class='text-center'>No sales records found</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>