<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Authentication check
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


// Function to get employee details
function getEmployeeDetails($conn, $employee_id) {
    $sql = "SELECT * FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to calculate salary based on employee type
function calculateSalary($employee, $deductions = [], $additions = []) {
    $base_salary = $employee['salary'];
    
    // Add up all deductions
    $total_deductions = 0;
    foreach ($deductions as $deduction) {
        $total_deductions += $deduction['amount'];
    }
    
    // Add up all additions/bonuses
    $total_additions = 0;
    foreach ($additions as $addition) {
        $total_additions += $addition['amount'];
    }
    
    // Calculate final salary
    $final_salary = $base_salary + $total_additions - $total_deductions;
    return [
        'base_salary' => $base_salary,
        'deductions' => $total_deductions,
        'additions' => $total_additions,
        'final_salary' => $final_salary
    ];
}

// Add deduction functionality
function addDeduction($conn, $employee_id, $description, $amount, $date) {
    $sql = "INSERT INTO payroll_deductions (employee_id, description, amount, deduction_date) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isds", $employee_id, $description, $amount, $date);
    return $stmt->execute();
}

// Add addition/bonus functionality
function addAddition($conn, $employee_id, $description, $amount, $date) {
    $sql = "INSERT INTO payroll_additions (employee_id, description, amount, addition_date) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isds", $employee_id, $description, $amount, $date);
    return $stmt->execute();
}

// Get all deductions for an employee
function getDeductions($conn, $employee_id, $start_date = null, $end_date = null) {
    $sql = "SELECT * FROM payroll_deductions WHERE employee_id = ?";
    
    if ($start_date && $end_date) {
        $sql .= " AND deduction_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $employee_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all additions for an employee
function getAdditions($conn, $employee_id, $start_date = null, $end_date = null) {
    $sql = "SELECT * FROM payroll_additions WHERE employee_id = ?";
    
    if ($start_date && $end_date) {
        $sql .= " AND addition_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $employee_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Process payroll for an employee
function processPayroll($conn, $employee_id, $payment_date, $notes = '') {
    $employee = getEmployeeDetails($conn, $employee_id);
    if (!$employee) {
        return false;
    }
    
    // Get deductions and additions for the current pay period
    $payPeriodStart = date('Y-m-01', strtotime($payment_date)); // First day of month
    $payPeriodEnd = date('Y-m-t', strtotime($payment_date)); // Last day of month
    
    $deductions = getDeductions($conn, $employee_id, $payPeriodStart, $payPeriodEnd);
    $additions = getAdditions($conn, $employee_id, $payPeriodStart, $payPeriodEnd);
    
    // Calculate final salary
    $salary_details = calculateSalary($employee, $deductions, $additions);
    
    // Insert into payroll table
    $sql = "INSERT INTO payroll (employee_id, amount, base_salary, total_deductions, total_additions, payment_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iddddss", 
        $employee_id, 
        $salary_details['final_salary'], 
        $salary_details['base_salary'],
        $salary_details['deductions'],
        $salary_details['additions'],
        $payment_date,
        $notes
    );
    
    if ($stmt->execute()) {
        $payroll_id = $conn->insert_id;
        
        // Link deductions to this payroll entry
        foreach ($deductions as $deduction) {
            $sql = "UPDATE payroll_deductions SET payroll_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $payroll_id, $deduction['id']);
            $stmt->execute();
        }
        
        // Link additions to this payroll entry
        foreach ($additions as $addition) {
            $sql = "UPDATE payroll_additions SET payroll_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $payroll_id, $addition['id']);
            $stmt->execute();
        }
        
        return $payroll_id;
    }
    
    return false;
}

// Generate automated payroll for all full-time employees
function generateAutomatedPayroll($conn, $payment_date) {
    $sql = "SELECT id FROM employees WHERE employment_type = 'Fulltime' AND status = 'Active'";
    $result = $conn->query($sql);
    $processed_count = 0;
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (processPayroll($conn, $row['id'], $payment_date)) {
                $processed_count++;
            }
        }
    }
    
    return $processed_count;
}

// Get payroll history for an employee
function getPayrollHistory($conn, $employee_id, $limit = 10) {
    $sql = "SELECT * FROM payroll WHERE employee_id = ? ORDER BY payment_date DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $employee_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get a specific payroll record
function getPayrollRecord($conn, $payroll_id) {
    $sql = "SELECT p.*, e.first_name, e.last_name, e.position, e.employment_type 
            FROM payroll p 
            JOIN employees e ON p.employee_id = e.id 
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $payroll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Generate PDF payslip
function generatePayslip($conn, $payroll_id) {
    // Redirect to the dedicated payslip generator
    header("Location: ../payslip_generator.php?payroll_id=$payroll_id");
    exit;
}


// Create required tables if they don't exist
function createRequiredTables($conn) {
    // Create payroll_deductions table
    $sql = "CREATE TABLE IF NOT EXISTS payroll_deductions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        payroll_id INT DEFAULT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        deduction_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE SET NULL
    )";
    $conn->query($sql);
    
    // Create payroll_additions table
    $sql = "CREATE TABLE IF NOT EXISTS payroll_additions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        payroll_id INT DEFAULT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        addition_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE SET NULL
    )";
    $conn->query($sql);
    
    // Alter payroll table to add necessary columns
    $sql = "ALTER TABLE payroll 
            ADD COLUMN base_salary DECIMAL(10,2) DEFAULT 0 AFTER amount,
            ADD COLUMN total_deductions DECIMAL(10,2) DEFAULT 0 AFTER base_salary,
            ADD COLUMN total_additions DECIMAL(10,2) DEFAULT 0 AFTER total_deductions,
            ADD COLUMN notes TEXT AFTER payment_date";
    
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        // Columns might already exist, that's okay
    }
    
    // Ensure uploads directory exists
    $uploads_dir = '../uploads/payslips';
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
}

// Initialize tables
createRequiredTables($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process payroll for an employee
    if (isset($_POST['process_payroll'])) {
        $employee_id = $_POST['employee_id'];
        $payment_date = $_POST['payment_date'];
        $notes = $_POST['notes'] ?? '';
        
        $payroll_id = processPayroll($conn, $employee_id, $payment_date, $notes);
        
        if ($payroll_id) {
            log_action($conn, $_SESSION['user_id'], "Processed payroll for Employee ID {$employee_id}");
        
            
            // Generate PDF if requested
            if (isset($_POST['generate_pdf']) && $_POST['generate_pdf'] == 1) {
                generatePayslip($conn, $payroll_id);
                // The function above will redirect, so this code won't be reached
            }
            
            header('Location: payroll.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to process payroll.";
            header('Location: payroll.php');
            exit;
        }
    }

    // Generate automated payroll for all full-time employees
    if (isset($_POST['generate_automated_payroll'])) {
        $payment_date = $_POST['automated_payment_date'];
        $processed_count = generateAutomatedPayroll($conn, $payment_date);
        
        if ($processed_count > 0) {
            log_action($conn, $_SESSION['user_id'], "Generated automated payroll for {$processed_count} employees on {$payment_date}");
        }
         else {
            $_SESSION['error_message'] = "No payroll records were generated.";
        }
        
        header('Location: payroll.php');
        exit;
    }
    
    // Add deduction
    if (isset($_POST['add_deduction'])) {
        $employee_id = $_POST['employee_id'];
        $description = $_POST['description'];
        $amount = $_POST['amount'];
        $date = $_POST['deduction_date'];
        
        if (addDeduction($conn, $employee_id, $description, $amount, $date)) {
            log_action($conn, $_SESSION['user_id'], "Added deduction for Employee ID {$employee_id}: {$description} - {$amount}");
        }
         else {
            $_SESSION['error_message'] = "Failed to add deduction.";
        }
        
        header('Location: payroll.php?action=deductions&employee_id=' . $employee_id);
        exit;
    }
    
    // Add addition/bonus
    if (isset($_POST['add_addition'])) {
        $employee_id = $_POST['employee_id'];
        $description = $_POST['description'];
        $amount = $_POST['amount'];
        $date = $_POST['addition_date'];
        
        if (addAddition($conn, $employee_id, $description, $amount, $date)) {
            log_action($conn, $_SESSION['user_id'], "Added addition/bonus for Employee ID {$employee_id}: {$description} - {$amount}");
        }
         else {
            $_SESSION['error_message'] = "Failed to add addition/bonus.";
        }
        
        header('Location: payroll.php?action=additions&employee_id=' . $employee_id);
        exit;
    }
}



// Get action from URL
// Get action from URL
$action = $_GET['action'] ?? 'list';
$employee_id = $_GET['employee_id'] ?? null;
$payroll_id = $_GET['payroll_id'] ?? null;

// Handle the generate_pdf action
if ($action === 'generate_pdf' && $payroll_id) {
    generatePayslip($conn, $payroll_id);
    // The function above will redirect, so this code won't be reached
}

// Flash messages
// Get all active employees
$sql = "SELECT id, first_name, last_name, position, employment_type FROM employees WHERE status = 'Active' ORDER BY last_name, first_name";
$employees_result = $conn->query($sql);
$employees = $employees_result->fetch_all(MYSQLI_ASSOC);

// Flash messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/payroll_dashboard.css">
    <style>
        .card {
            margin-bottom: 20px;
        }
        .actions-column {
            width: 150px;
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

    
            <a href="../admin/payroll.php" class="nav-btn"><i class="fas fa-dollar-sign"></i> Payroll</a>
            <a href="../admin/employee_management.php" class="nav-btn"><i class="fas fa-dollar-sign"></i> employee management</a>
            <a href="../admin/payment_account_management.php" class="nav-btn active"><i class="fas fa-university"></i> Payment Accounts</a>
        </div>
        
        <!-- User Info and Logout on the right -->
        <div>
            <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>
    
    <div class="container mt-4">
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h1><i class="fas fa-money-check-alt"></i> Payroll Management</h1>
            </div>
            <div class="col-md-6 text-right">
                <div class="btn-group">
                    <a href="payroll.php" class="btn btn-primary"><i class="fas fa-list"></i> Payroll List</a>
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#automatedPayrollModal">
                        <i class="fas fa-sync-alt"></i> Run Automated Payroll
                    </button>
                </div>
            </div>
        </div>
        
        <?php if ($action === 'list'): ?>
            <!-- Payroll List View -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-list"></i> Recent Payroll Records</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
// This code goes in your payroll view where you display the payroll records
$sql = "SELECT p.id, p.amount, p.payment_date, e.first_name, e.last_name 
        FROM payroll p 
        JOIN employees e ON p.employee_id = e.id 
        ORDER BY p.payment_date DESC LIMIT 20";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>GHS " . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . date('M d, Y', strtotime($row['payment_date'])) . "</td>";
        echo "<td>";
        echo "<a href='payroll.php?action=view&payroll_id={$row['id']}' class='btn btn-sm btn-info mr-1'><i class='fas fa-eye'></i></a>";
        // Updated link to view the PDF directly
        echo "<a href='../admin/view_payslip.php?payroll_id={$row['id']}' target='_blank' class='btn btn-sm btn-secondary mr-1'><i class='fas fa-file-pdf'></i></a>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' class='text-center'>No payroll records found</td></tr>";
}
?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5><i class="fas fa-user-plus"></i> Process Individual Payroll</h5>
                </div>
                <div class="card-body">
                    <form action="payroll.php" method="post">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="employee_id">Select Employee</label>
                                <select name="employee_id" id="employee_id" class="form-control" required>
                                    <option value="">-- Select Employee --</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo $employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['position'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="payment_date">Payment Date</label>
                                <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="notes">Notes (Optional)</label>
                                <input type="text" name="notes" id="notes" class="form-control">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="generate_pdf" id="generate_pdf" value="1" class="form-check-input">
                            <label for="generate_pdf" class="form-check-label">Generate PDF Payslip</label>
                        </div>
                        <button type="submit" name="process_payroll" class="btn btn-primary">Process Payroll</button>
                    </form>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-minus-circle"></i> Manage Deductions</h5>
                        </div>
                        <div class="card-body">
                            <form action="payroll.php" method="get">
                                <input type="hidden" name="action" value="deductions">
                                <div class="form-group">
                                    <label for="employee_id_deductions">Select Employee</label>
                                    <select name="employee_id" id="employee_id_deductions" class="form-control" required>
                                        <option value="">-- Select Employee --</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>">
                                                <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-info">View/Manage Deductions</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5><i class="fas fa-plus-circle"></i> Manage Additions/Bonuses</h5>
                        </div>
                        <div class="card-body">
                            <form action="payroll.php" method="get">
                                <input type="hidden" name="action" value="additions">
                                <div class="form-group">
                                    <label for="employee_id_additions">Select Employee</label>
                                    <select name="employee_id" id="employee_id_additions" class="form-control" required>
                                        <option value="">-- Select Employee --</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>">
                                                <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning">View/Manage Additions</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($action === 'deductions' && $employee_id): ?>
            <!-- Deductions Management View -->
            <?php 
            $employee = getEmployeeDetails($conn, $employee_id);
            $deductions = getDeductions($conn, $employee_id);
            ?>
            
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5><i class="fas fa-minus-circle"></i> Deductions for <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h5>
                </div>
                <div class="card-body">
                    <form action="payroll.php" method="post" class="mb-4">
                        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="description">Description</label>
                                <input type="text" name="description" id="description" class="form-control" required placeholder="e.g., Tax, Insurance, Loan">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="amount">Amount (GHS)</label>
                                <input type="number" name="amount" id="amount" class="form-control" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="deduction_date">Deduction Date</label>
                                <input type="date" name="deduction_date" id="deduction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="add_deduction" class="btn btn-primary">Add Deduction</button>
                        <a href="payroll.php" class="btn btn-secondary">Back to Payroll</a>
                    </form>
                    
                    <h6>Current Deductions</h6>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Applied to Payroll</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($deductions) > 0): ?>
                                <?php foreach ($deductions as $deduction): ?>
                                    <tr>
                                        <td><?php echo $deduction['id']; ?></td>
                                        <td><?php echo htmlspecialchars($deduction['description']); ?></td>
                                        <td>$<?php echo number_format($deduction['amount'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($deduction['deduction_date'])); ?></td>
                                        <td>
                                            <?php if ($deduction['payroll_id']): ?>
                                                <span class="badge badge-success">Yes - Payroll #<?php echo $deduction['payroll_id']; ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Not yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$deduction['payroll_id']): ?>
                                                <a href="payroll.php?action=delete_deduction&id=<?php echo $deduction['id']; ?>&employee_id=<?php echo $employee_id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this deduction?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Cannot delete deduction applied to payroll">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No deductions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($action === 'additions' && $employee_id): ?>
            <!-- Additions Management View -->
            <?php 
            $employee = getEmployeeDetails($conn, $employee_id);
            $additions = getAdditions($conn, $employee_id);
            ?>
            
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="fas fa-plus-circle"></i> Additions/Bonuses for <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h5>
                </div>
                <div class="card-body">
                    <form action="payroll.php" method="post" class="mb-4">
                        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="description">Description</label>
                                <input type="text" name="description" id="description" class="form-control" required placeholder="e.g., Bonus, Overtime, Allowance">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="amount">Amount (GHS)</label>
                                <input type="number" name="amount" id="amount" class="form-control" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="addition_date">Addition Date</label>
                                <input type="date" name="addition_date" id="addition_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="add_addition" class="btn btn-primary">Add Addition/Bonus</button>
                        <a href="payroll.php" class="btn btn-secondary">Back to Payroll</a>
                    </form>
                    
                    <h6>Current Additions/Bonuses</h6>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Applied to Payroll</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($additions) > 0): ?>
                                <?php foreach ($additions as $addition): ?>
                                    <tr>
                                        <td><?php echo $addition['id']; ?></td>
                                        <td><?php echo htmlspecialchars($addition['description']); ?></td>
                                        <td>GHS<?php echo number_format($addition['amount'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($addition['addition_date'])); ?></td>
                                        <td>
                                            <?php if ($addition['payroll_id']): ?>
                                                <span class="badge badge-success">Yes - Payroll #<?php echo $addition['payroll_id']; ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Not yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$addition['payroll_id']): ?>
                                                <a href="payroll.php?action=delete_addition&id=<?php echo $addition['id']; ?>&employee_id=<?php echo $employee_id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this addition?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Cannot delete addition applied to payroll">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No additions/bonuses found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($action === 'view' && $payroll_id): ?>
            <!-- View Payroll Details -->
            <?php 
            $payroll = getPayrollRecord($conn, $payroll_id);
            
            if (!$payroll) {
                echo '<div class="alert alert-danger">Payroll record not found.</div>';
                echo '<a href="payroll.php" class="btn btn-primary">Back to Payroll</a>';
            } else {
                // Get deductions and additions for this payroll
                $sql = "SELECT * FROM payroll_deductions WHERE payroll_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $payroll_id);
                $stmt->execute();
                $deductions_result = $stmt->get_result();
                $deductions = $deductions_result->fetch_all(MYSQLI_ASSOC);
                
                $sql = "SELECT * FROM payroll_additions WHERE payroll_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $payroll_id);
                $stmt->execute();
                $additions_result = $stmt->get_result();
                $additions = $additions_result->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-file-invoice-dollar"></i> Payroll Details #<?php echo $payroll_id; ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Employee Information</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Name</th>
                                    <td><?php echo $payroll['first_name'] . ' ' . $payroll['last_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Position</th>
                                    <td><?php echo $payroll['position']; ?></td>
                                </tr>
                                <tr>
                                    <th>Employment Type</th>
                                    <td><?php echo $payroll['employment_type']; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Payroll Information</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Payment Date</th>
                                    <td><?php echo date('F d, Y', strtotime($payroll['payment_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Base Salary</th>
                                    <td>$<?php echo number_format($payroll['base_salary'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Final Amount</th>
                                    <td class="font-weight-bold text-success">$<?php echo number_format($payroll['amount'], 2); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6>Deductions</h6>
                            <?php if (count($deductions) > 0): ?>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deductions as $deduction): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($deduction['description']); ?></td>
                                                <td>$<?php echo number_format($deduction['amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($deduction['deduction_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary">
                                            <th>Total Deductions</th>
                                            <th>$<?php echo number_format($payroll['total_deductions'], 2); ?></th>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted">No deductions</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Additions/Bonuses</h6>
                            <?php if (count($additions) > 0): ?>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($additions as $addition): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($addition['description']); ?></td>
                                                <td>$<?php echo number_format($addition['amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($addition['addition_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary">
                                            <th>Total Additions</th>
                                            <th>$<?php echo number_format($payroll['total_additions'], 2); ?></th>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted">No additions/bonuses</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($payroll['notes'])): ?>
                        <div class="mt-4">
                            <h6>Notes</h6>
                            <p><?php echo nl2br(htmlspecialchars($payroll['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="payroll.php" class="btn btn-secondary">Back to Payroll</a>
                        <a href="payroll.php?action=generate_pdf&payroll_id=<?php echo $payroll_id; ?>" class="btn btn-primary">
                            <i class="fas fa-file-pdf"></i> Generate PDF Payslip
                        </a>
                    </div>
                </div>
            </div>
            <?php } ?>
            

<?php elseif ($action === 'generate_pdf' && $payroll_id): ?>
    <!-- Generate PDF View -->
    <?php 
    // Generate a unique filename
    $payroll = getPayrollRecord($conn, $payroll_id);
    if ($payroll) {
        $filename = 'payslip_' . $payroll_id . '_' . str_replace(' ', '_', $payroll['first_name'] . '_' . $payroll['last_name']) . '.pdf';
        $filepath = 'payslips/' . $filename;
        
        // Create directory for payslips if it doesn't exist
        if (!file_exists('payslips')) {
            mkdir('payslips', 0755, true);
        }
        
        // Redirect to the payslip generator
        header("Location: ../payslip_generator.php?payroll_id=$payroll_id&output_path=" . urlencode($filepath));
        exit;
    } else {
        echo '<div class="alert alert-danger">Failed to generate PDF payslip. Employee record not found.</div>';
        echo '<a href=" payroll.php" class="btn btn-primary">Back to Payroll</a>';
    }
    ?>
            
        <?php elseif ($action === 'delete_deduction' && isset($_GET['id'])): ?>
            <!-- Delete Deduction -->
            <?php 
            $deduction_id = $_GET['id'];
            $employee_id = $_GET['employee_id'] ?? null;
            
            $sql = "DELETE FROM payroll_deductions WHERE id = ? AND payroll_id IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $deduction_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Deduction deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete deduction.";
            }
            
            header('Location: payroll.php?action=deductions&employee_id=' . $employee_id);
            exit;
            ?>
            
        <?php elseif ($action === 'delete_addition' && isset($_GET['id'])): ?>
            <!-- Delete Addition -->
            <?php 
            $addition_id = $_GET['id'];
            $employee_id = $_GET['employee_id'] ?? null;
            
            $sql = "DELETE FROM payroll_additions WHERE id = ? AND payroll_id IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $addition_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Addition deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete addition.";
            }
            
            header('Location: payroll.php?action=additions&employee_id=' . $employee_id);
            exit;
            ?>
            
        <?php else: ?>
            <div class="alert alert-danger">Invalid action or missing parameters.</div>
            <a href=" payroll.php" class="btn btn-primary">Back to Payroll</a>
        <?php endif; ?>
    </div>
    
    <!-- Automated Payroll Modal -->
    <div class="modal fade" id="automatedPayrollModal" tabindex="-1" role="dialog" aria-labelledby="automatedPayrollModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="automatedPayrollModalLabel">Run Automated Payroll</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="payroll.php" method="post">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This will generate payroll records for all active full-time employees.
                        </div>
                        <div class="form-group">
                            <label for="automated_payment_date">Payment Date</label>
                            <input type="date" name="automated_payment_date" id="automated_payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" name="generate_automated_payroll" class="btn btn-success">Generate Payroll</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>