<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent the "headers already sent" error
ob_start();

include 'config/db.php';
require_once 'views/header.php';



// Handle form submissions for adding labor records
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_labor_record'])) {
    $date = mysqli_real_escape_string($conn, $_POST['labor_date']);
    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $worker_count = mysqli_real_escape_string($conn, $_POST['worker_count']);
    $override_fee = isset($_POST['override_fee']) ? mysqli_real_escape_string($conn, $_POST['custom_fee']) : null;
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Get the standard fee for the selected category
    $fee_query = "SELECT fee_per_head FROM labor_categories WHERE category_id = $category_id";
    $fee_result = mysqli_query($conn, $fee_query);
    $fee_row = mysqli_fetch_assoc($fee_result);
    $standard_fee = $fee_row['fee_per_head'];
    
    
    // Use override fee if provided, otherwise use standard fee
    $actual_fee = $override_fee ? $override_fee : $standard_fee;
    $total_cost = $actual_fee * $worker_count;
    
    // Create the labor records table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS labor_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        labor_date DATE NOT NULL,
        category_id INT NOT NULL,
        worker_count INT NOT NULL,
        fee_per_head DECIMAL(10,2) NOT NULL,
        total_cost DECIMAL(10,2) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES labor_categories(id) ON DELETE CASCADE
    )";
    
    mysqli_query($conn, $create_table_sql);
    
    // Insert the labor record
    $sql = "INSERT INTO labor_records (labor_date, category_id, worker_count, fee_per_head, total_cost, notes) 
            VALUES ('$date', $category_id, $worker_count, $actual_fee, $total_cost, '$notes')";
    
    if (mysqli_query($conn, $sql)) {
        // Also add to expenses table for financial tracking
        $category_query = "SELECT category_name FROM labor_categories WHERE category_id = $category_id";
        $category_result = mysqli_query($conn, $category_query);
        $category_row = mysqli_fetch_assoc($category_result);
        $category_name = $category_row['category_name'];
        
        
        $expense_reason = "Labor: " . $category_name . " (" . $worker_count . " workers)";
        
        // Get the Labor category ID from expense_categories
        $labor_category_query = "SELECT category_id FROM expense_categories WHERE category_name = 'Labor'";
        $labor_category_result = mysqli_query($conn, $labor_category_query);
        
        if (mysqli_num_rows($labor_category_result) > 0) {
            $labor_category_row = mysqli_fetch_assoc($labor_category_result);
            $labor_category_id = $labor_category_row['category_id'];
            
            $expense_sql = "INSERT INTO expenses (expense_reason, amount, expense_date, payment_status, category_id, notes) 
                           VALUES ('$expense_reason', $total_cost, '$date', 'Paid', $labor_category_id, '$notes')";
            
            mysqli_query($conn, $expense_sql);
        }
        
        $success_message = "Labor record added successfully!";
    } else {
        $error_message = "Error: " . mysqli_error($conn);
    }
}

// Fetch all labor categories for the dropdown
$categories_sql = "SELECT * FROM labor_categories ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_sql);
$categories = [];

if (mysqli_num_rows($categories_result) > 0) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row;
    }
}

// Fetch existing labor records
$records_sql = "SELECT lr.id, lr.labor_date, lc.category_name as category_name, lr.worker_count, 
                lr.fee_per_head, lr.total_cost, lr.notes 
                FROM labor_records lr
                JOIN labor_categories lc ON lr.category_id = lc.category_id
                ORDER BY lr.labor_date DESC, lc.category_name ASC";


$records_result = mysqli_query($conn, $records_sql);
$records = [];

if ($records_result && mysqli_num_rows($records_result) > 0) {
    while ($row = mysqli_fetch_assoc($records_result)) {
        $records[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labor Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/labor-tracking.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>


</head>
<body>
<div class="container-fluid px-4">
    <h1 class="mt-4">Labor Tracking</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="labor_management.php">Labor Management</a></li>
        <li class="breadcrumb-item active">Labor Tracking</li>
    </ol>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-clock me-1"></i>
                    Add Labor Record
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="labor_date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="labor_date" name="labor_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Labor Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
    <option value="">Select a category</option>
    <?php foreach ($categories as $category): ?>
        <option value="<?php echo $category['category_id']; ?>" data-fee="<?php echo $category['fee_per_head']; ?>">
            <?php echo htmlspecialchars($category['category_name']); ?> 
            (GHS <?php echo number_format($category['fee_per_head'], 2); ?>/person)
        </option>
    <?php endforeach; ?>
</select>

                        </div>
                        
                        <div class="mb-3">
                            <label for="worker_count" class="form-label">Number of Workers</label>
                            <input type="number" class="form-control" id="worker_count" name="worker_count" min="1" value="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="override_fee" name="override_fee">
                                <label class="form-check-label" for="override_fee">
                                    Override Standard Fee
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="custom_fee_container" style="display: none;">
                            <label for="custom_fee" class="form-label">Custom Fee Per Head (GHS)</label>
                            <input type="number" class="form-control" id="custom_fee" name="custom_fee" step="0.01" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="estimated_cost" class="form-label">Estimated Total Cost (GHS)</label>
                            <input type="text" class="form-control" id="estimated_cost" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" name="add_labor_record" class="btn btn-primary">Add Labor Record</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-1"></i>
                    Labor Records
                </div>
                <div class="card-body">
                    <table id="laborRecordsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Workers</th>
                                <th>Fee/Head</th>
                                <th>Total Cost</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($record['labor_date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['category_name']); ?></td>
                                <td><?php echo $record['worker_count']; ?></td>
                                <td>GHS <?php echo number_format($record['fee_per_head'], 2); ?></td>
                                <td>GHS <?php echo number_format($record['total_cost'], 2); ?></td>
                                <td><?php echo htmlspecialchars($record['notes']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#laborRecordsTable').DataTable({
            order: [[0, 'desc']]
        });
        
        // Toggle custom fee input based on checkbox
        $('#override_fee').change(function() {
            if($(this).is(':checked')) {
                $('#custom_fee_container').show();
                calculateEstimatedCost();
            } else {
                $('#custom_fee_container').hide();
                calculateEstimatedCost();
            }
        });
        
        // Calculate estimated cost when inputs change
        $('#category_id, #worker_count, #custom_fee').on('change keyup', function() {
            calculateEstimatedCost();
        });
        
        // Function to calculate and display estimated cost
        function calculateEstimatedCost() {
            const workerCount = parseInt($('#worker_count').val()) || 0;
            let feePerHead = 0;
            
            if ($('#override_fee').is(':checked')) {
                feePerHead = parseFloat($('#custom_fee').val()) || 0;
            } else {
                feePerHead = parseFloat($('#category_id option:selected').data('fee')) || 0;
            }
            
            const totalCost = workerCount * feePerHead;
            $('#estimated_cost').val(totalCost.toFixed(2));
        }
        
        // Calculate initial estimate
        calculateEstimatedCost();
    });
</script>

<?php
include 'views/footer.php';
?>