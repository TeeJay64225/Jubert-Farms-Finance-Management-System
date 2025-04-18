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


// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new asset
            $asset_name = $_POST['asset_name'];
            $category_id = $_POST['category_id'];
            $quantity = $_POST['quantity'];
            $condition_id = $_POST['condition_id'];
            $acquisition_date = $_POST['acquisition_date'];
            $acquisition_cost = !empty($_POST['acquisition_cost']) ? $_POST['acquisition_cost'] : "NULL";
            $current_value = !empty($_POST['current_value']) ? $_POST['current_value'] : "NULL";
            $location = $_POST['location'];
            $serial_number = $_POST['serial_number'];
            $remarks = $_POST['remarks'];

            $sql = "INSERT INTO assets (asset_name, category_id, quantity, condition_id, acquisition_date, 
                    acquisition_cost, current_value, location, serial_number, remarks)
                    VALUES ('$asset_name', $category_id, $quantity, $condition_id, '$acquisition_date', 
                    $acquisition_cost, $current_value, '$location', '$serial_number', '$remarks')";
            
            if ($conn->query($sql) === TRUE) {
                $asset_id = $conn->insert_id;
                
                // Record transaction
                $transaction_sql = "INSERT INTO asset_transactions (asset_id, transaction_type, quantity, 
                                    to_location, cost, reason, performed_by)
                                    VALUES ($asset_id, 'Addition', $quantity, '$location', 
                                    $acquisition_cost, 'Initial acquisition', '{$_SESSION['username']}')";
                $conn->query($transaction_sql);
                
                $message = "Asset added successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'update') {
            // Update existing asset
            $asset_id = $_POST['asset_id'];
            $asset_name = $_POST['asset_name'];
            $category_id = $_POST['category_id'];
            $quantity = $_POST['quantity'];
            $condition_id = $_POST['condition_id'];
            $acquisition_date = $_POST['acquisition_date'];
            $acquisition_cost = !empty($_POST['acquisition_cost']) ? $_POST['acquisition_cost'] : "NULL";
            $current_value = !empty($_POST['current_value']) ? $_POST['current_value'] : "NULL";
            $location = $_POST['location'];
            $serial_number = $_POST['serial_number'];
            $remarks = $_POST['remarks'];

            $sql = "UPDATE assets SET asset_name='$asset_name', category_id=$category_id, quantity=$quantity, 
                    condition_id=$condition_id, acquisition_date='$acquisition_date', acquisition_cost=$acquisition_cost, 
                    current_value=$current_value, location='$location', serial_number='$serial_number', remarks='$remarks' 
                    WHERE asset_id=$asset_id";

            if ($conn->query($sql) === TRUE) {
                // Record transaction for value adjustment if current_value changed
                $get_old_value = "SELECT current_value FROM assets WHERE asset_id=$asset_id";
                $old_value_result = $conn->query($get_old_value);
                $old_value_data = $old_value_result->fetch_assoc();
                
                if ($old_value_data['current_value'] != $current_value) {
                    $transaction_sql = "INSERT INTO asset_transactions (asset_id, transaction_type, 
                                        cost, reason, performed_by)
                                        VALUES ($asset_id, 'Value Adjustment', $current_value, 
                                        'Value updated during edit', '{$_SESSION['username']}')";
                    $conn->query($transaction_sql);
                }
                
                $message = "Asset updated successfully!";
                log_action($conn, $_SESSION['user_id'], "Updated asset ID $asset_id: $asset_name");
            } else {
                $error = "Error: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'remove_asset') {
            // Remove asset (update status to inactive)
            $asset_id = $_POST['asset_id'];
            $reason = $_POST['reason'];
            $quantity = $_POST['remove_quantity'];
            
            // Get current quantity
            $get_current = "SELECT quantity, asset_name, location FROM assets WHERE asset_id=$asset_id";
            $current_result = $conn->query($get_current);
            $current_data = $current_result->fetch_assoc();
            
            if ($quantity >= $current_data['quantity']) {
                // Remove all - mark as inactive
                $sql = "UPDATE assets SET is_active=FALSE WHERE asset_id=$asset_id";
                $remove_all = true;
            } else {
                // Reduce quantity
                $new_quantity = $current_data['quantity'] - $quantity;
                $sql = "UPDATE assets SET quantity=$new_quantity WHERE asset_id=$asset_id";
                $remove_all = false;
            }

            if ($conn->query($sql) === TRUE) {
                // Record transaction
                $transaction_sql = "INSERT INTO asset_transactions (asset_id, transaction_type, quantity, 
                                    from_location, reason, performed_by)
                                    VALUES ($asset_id, 'Removal', $quantity, '{$current_data['location']}', 
                                    '$reason', '{$_SESSION['username']}')";
                $conn->query($transaction_sql);
                
                $message = $remove_all ? 
                    "Asset '{$current_data['asset_name']}' has been removed from inventory." : 
                    "Quantity reduced by $quantity for asset '{$current_data['asset_name']}'.";
                    log_action($conn, $_SESSION['user_id'], "Removed $quantity of asset ID $asset_id due to '$reason'");
            } else {
                $error = "Error: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'maintenance') {
            // Record maintenance
            $asset_id = $_POST['asset_id'];
            $maintenance_date = $_POST['maintenance_date'];
            $next_maintenance = $_POST['next_maintenance_date'];
            $maintenance_notes = $_POST['maintenance_notes'];
            $cost = !empty($_POST['maintenance_cost']) ? $_POST['maintenance_cost'] : "NULL";
            
            $sql = "UPDATE assets SET last_maintenance_date='$maintenance_date', 
                    next_maintenance_date='$next_maintenance' WHERE asset_id=$asset_id";
                    
            if ($conn->query($sql) === TRUE) {
                // Record transaction
                $transaction_sql = "INSERT INTO asset_transactions (asset_id, transaction_type, 
                                    cost, reason, performed_by, notes)
                                    VALUES ($asset_id, 'Maintenance', $cost, 
                                    'Scheduled maintenance', '{$_SESSION['username']}', '$maintenance_notes')";
                $conn->query($transaction_sql);
                
                $message = "Maintenance record added successfully!";
                log_action($conn, $_SESSION['user_id'], "Recorded maintenance for asset ID $asset_id on $maintenance_date");
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}

// Edit asset
$edit_data = null;
if (isset($_GET['edit'])) {
    $asset_id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM assets WHERE asset_id=$asset_id");
    $edit_data = $result->fetch_assoc();
}

// Get maintenance form data
$maintenance_data = null;
if (isset($_GET['maintenance'])) {
    $asset_id = $_GET['maintenance'];
    $result = $conn->query("SELECT * FROM assets WHERE asset_id=$asset_id");
    $maintenance_data = $result->fetch_assoc();
}

// Get removal form data
$removal_data = null;
if (isset($_GET['remove'])) {
    $asset_id = $_GET['remove'];
    $result = $conn->query("SELECT * FROM assets WHERE asset_id=$asset_id");
    $removal_data = $result->fetch_assoc();
}

// Fetch all categories
$categories_result = $conn->query("SELECT * FROM asset_categories ORDER BY category_name");
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}

// Fetch all conditions
$conditions_result = $conn->query("SELECT * FROM asset_conditions ORDER BY condition_id");
$conditions = [];
while ($condition = $conditions_result->fetch_assoc()) {
    $conditions[] = $condition;
}

// Fetch all assets
$assets_sql = "SELECT a.*, c.category_name, ac.condition_name 
              FROM assets a
              LEFT JOIN asset_categories c ON a.category_id = c.category_id
              LEFT JOIN asset_conditions ac ON a.condition_id = ac.condition_id
              WHERE a.is_active = TRUE
              ORDER BY a.asset_name";
$assets_result = $conn->query($assets_sql);
$assets = [];
while ($row = $assets_result->fetch_assoc()) {
    $assets[] = $row;
}

// Get data for charts
// Asset count by category
$category_chart_sql = "SELECT c.category_name, COUNT(a.asset_id) as count, SUM(a.quantity) as total_quantity
                      FROM asset_categories c
                      LEFT JOIN assets a ON c.category_id = a.category_id AND a.is_active = TRUE
                      GROUP BY c.category_id
                      ORDER BY total_quantity DESC";
$category_chart_result = $conn->query($category_chart_sql);
$category_labels = [];
$category_counts = [];
while ($row = $category_chart_result->fetch_assoc()) {
    $category_labels[] = $row['category_name'];
    $category_counts[] = $row['total_quantity'] ?? 0;
}

// Asset count by condition
$condition_chart_sql = "SELECT ac.condition_name, COUNT(a.asset_id) as count
                       FROM asset_conditions ac
                       LEFT JOIN assets a ON ac.condition_id = a.condition_id AND a.is_active = TRUE
                       GROUP BY ac.condition_id
                       ORDER BY ac.condition_id";
$condition_chart_result = $conn->query($condition_chart_sql);
$condition_labels = [];
$condition_counts = [];
while ($row = $condition_chart_result->fetch_assoc()) {
    $condition_labels[] = $row['condition_name'];
    $condition_counts[] = $row['count'] ?? 0;
}

// Count maintenance due
$maintenance_due_sql = "SELECT COUNT(*) as count FROM assets 
                       WHERE is_active = TRUE AND next_maintenance_date IS NOT NULL 
                       AND next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$maintenance_due_result = $conn->query($maintenance_due_sql);
$maintenance_due = $maintenance_due_result->fetch_assoc()['count'];

// Include header
include 'views/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Assets Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Farm Assets Inventory</h1>
        <div>
            <a href="asset_categories.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-tags"></i> Manage Categories
            </a>
            <a href="asset_report.php" class="btn btn-outline-success">
                <i class="fas fa-file-alt"></i> Generate Reports
            </a>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($maintenance_data): ?>
    <!-- Maintenance Form -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <i class="fas fa-tools"></i> Record Maintenance for "<?= $maintenance_data['asset_name'] ?>"
        </div>
        <div class="card-body">
            <form method="post" action="assets.php">
                <input type="hidden" name="action" value="maintenance">
                <input type="hidden" name="asset_id" value="<?= $maintenance_data['asset_id'] ?>">
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="maintenance_date" class="form-label">Maintenance Date</label>
                        <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="next_maintenance_date" class="form-label">Next Scheduled Maintenance</label>
                        <input type="date" class="form-control" id="next_maintenance_date" name="next_maintenance_date" 
                               value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="maintenance_cost" class="form-label">Maintenance Cost</label>
                        <input type="number" step="0.01" class="form-control" id="maintenance_cost" name="maintenance_cost">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="maintenance_notes" class="form-label">Maintenance Notes</label>
                    <textarea class="form-control" id="maintenance_notes" name="maintenance_notes" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Record Maintenance</button>
                    <a href="assets.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($removal_data): ?>
    <!-- Asset Removal Form -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <i class="fas fa-minus-circle"></i> Remove Asset: "<?= $removal_data['asset_name'] ?>"
        </div>
        <div class="card-body">
            <form method="post" action="assets.php">
                <input type="hidden" name="action" value="remove_asset">
                <input type="hidden" name="asset_id" value="<?= $removal_data['asset_id'] ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="remove_quantity" class="form-label">Quantity to Remove</label>
                        <input type="number" class="form-control" id="remove_quantity" name="remove_quantity" 
                               min="1" max="<?= $removal_data['quantity'] ?>" value="<?= $removal_data['quantity'] ?>" required>
                        <div class="form-text">Current quantity: <?= $removal_data['quantity'] ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="reason" class="form-label">Reason for Removal</label>
                        <select class="form-select" id="reason" name="reason" required>
                            <option value="">-- Select Reason --</option>
                            <option value="Sold">Sold</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Lost">Lost</option>
                            <option value="Retired">Retired</option>
                            <option value="Transferred">Transferred</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirm_removal" required>
                        <label class="form-check-label" for="confirm_removal">
                            I confirm that this asset should be removed from inventory
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-danger">Remove Asset</button>
                    <a href="assets.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$maintenance_data && !$removal_data): ?>
    <div class="card mb-4">
        <div class="card-header">
            <?= $edit_data ? 'Edit Asset' : 'Add New Asset' ?>
        </div>
        <div class="card-body">
            <form method="post" action="assets.php">
                <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'add' ?>">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="asset_id" value="<?= $edit_data['asset_id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="asset_name" class="form-label">Asset Name</label>
                        <input type="text" class="form-control" id="asset_name" name="asset_name" 
                               value="<?= $edit_data ? $edit_data['asset_name'] : '' ?>" required>
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
                    <div class="col-md-4 mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" min="1" class="form-control" id="quantity" name="quantity" 
                               value="<?= $edit_data ? $edit_data['quantity'] : '1' ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="condition_id" class="form-label">Condition</label>
                        <select class="form-select" id="condition_id" name="condition_id" required>
                            <option value="">-- Select Condition --</option>
                            <?php foreach ($conditions as $condition): ?>
                                <option value="<?= $condition['condition_id'] ?>" <?= ($edit_data && $edit_data['condition_id'] == $condition['condition_id']) ? 'selected' : '' ?>>
                                    <?= $condition['condition_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="acquisition_date" class="form-label">Acquisition Date</label>
                        <input type="date" class="form-control" id="acquisition_date" name="acquisition_date" 
                               value="<?= $edit_data ? $edit_data['acquisition_date'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" 
                               value="<?= $edit_data ? $edit_data['location'] : '' ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="acquisition_cost" class="form-label">Acquisition Cost</label>
                        <input type="number" step="0.01" class="form-control" id="acquisition_cost" name="acquisition_cost" 
                               value="<?= $edit_data ? $edit_data['acquisition_cost'] : '' ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="current_value" class="form-label">Current Value</label>
                        <input type="number" step="0.01" class="form-control" id="current_value" name="current_value" 
                               value="<?= $edit_data ? $edit_data['current_value'] : '' ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="serial_number" class="form-label">Serial Number</label>
                        <input type="text" class="form-control" id="serial_number" name="serial_number" 
                               value="<?= $edit_data ? $edit_data['serial_number'] : '' ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="2"><?= $edit_data ? $edit_data['remarks'] : '' ?></textarea>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary"><?= $edit_data ? 'Update' : 'Add' ?> Asset</button>
                    <?php if ($edit_data): ?>
                        <a href="assets.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Asset Inventory Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clipboard-list"></i> Asset Inventory</span>
            <div>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#filterOptions">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </div>
        
        <div class="collapse" id="filterOptions">
            <div class="card-body bg-light">
                <form id="filterForm" method="get">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label for="filterCategory" class="form-label">Category</label>
                            <select class="form-select form-select-sm" id="filterCategory">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_name'] ?>">
                                        <?= $category['category_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="filterCondition" class="form-label">Condition</label>
                            <select class="form-select form-select-sm" id="filterCondition">
                                <option value="">All Conditions</option>
                                <?php foreach ($conditions as $condition): ?>
                                    <option value="<?= $condition['condition_name'] ?>">
                                        <?= $condition['condition_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="filterLocation" class="form-label">Location</label>
                            <input type="text" class="form-control form-control-sm" id="filterLocation">
                        </div>
                        <div class="col-md-3 d-flex align-items-end mb-2">
                            <button type="button" class="btn btn-sm btn-primary me-2" onclick="applyFilter()">Apply</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="resetFilter()">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="assetsTable">
                    <thead>
                        <tr>
                            <th>Asset Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Condition</th>
                            <th>Date Acquired</th>
                            <th>Location</th>
                            <th>Value</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($assets) > 0): ?>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td><?= $asset['asset_name'] ?></td>
                                    <td><?= $asset['category_name'] ?? 'Uncategorized' ?></td>
                                    <td><?= $asset['quantity'] ?></td>
                                    <td><?= $asset['condition_name'] ?></td>
                                    <td><?= $asset['acquisition_date'] ?></td>
                                    <td><?= $asset['location'] ?></td>
                                    <td><?= $asset['current_value'] ? 'GHS' . number_format($asset['current_value'], 2) : '-' ?></td>
                                    <td><?= $asset['remarks'] ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="actionDropdown<?= $asset['asset_id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="actionDropdown<?= $asset['asset_id'] ?>">
                                                <li><a class="dropdown-item" href="assets.php?edit=<?= $asset['asset_id'] ?>">Edit</a></li>
                                                <li><a class="dropdown-item" href="assets.php?maintenance=<?= $asset['asset_id'] ?>">Record Maintenance</a></li>
                                                <li><a class="dropdown-item" href="asset_history.php?id=<?= $asset['asset_id'] ?>">View History</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="assets.php?remove=<?= $asset['asset_id'] ?>">Remove</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No assets found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Asset Summary Charts -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Assets by Category
                </div>
                <div class="card-body">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i> Assets by Condition
                </div>
                <div class="card-body">
                    <canvas id="conditionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Alerts -->
    <div class="card mb-4">
        <div class="card-header bg-warning">
            <i class="fas fa-exclamation-triangle"></i> Maintenance Alerts
        </div>
        <div class="card-body">
            <?php if ($maintenance_due > 0): ?>
                <div class="alert alert-warning">
                    <strong><?= $maintenance_due ?> asset(s)</strong> require maintenance in the next 30 days. 
                    <a href="maintenance_due.php" class="alert-link">View details</a>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    No assets require maintenance in the next 30 days.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to apply table filters
    function applyFilter() {
        const category = document.getElementById('filterCategory').value.toLowerCase();
        const condition = document.getElementById('filterCondition').value.toLowerCase();
        const location = document.getElementById('filterLocation').value.toLowerCase();
        
        const table = document.getElementById('assetsTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const categoryCell = rows[i].getElementsByTagName('td')[1];
            const conditionCell = rows[i].getElementsByTagName('td')[3];
            const locationCell = rows[i].getElementsByTagName('td')[5];
            
            if (categoryCell && conditionCell && locationCell) {
                const categoryValue = categoryCell.textContent.toLowerCase();
                const conditionValue = conditionCell.textContent.toLowerCase();
                const locationValue = locationCell.textContent.toLowerCase();
                
                const matchesCategory = !category || categoryValue.includes(category);
                const matchesCondition = !condition || conditionValue.includes(condition);
                const matchesLocation = !location || locationValue.includes(location);
                
                if (matchesCategory && matchesCondition && matchesLocation) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }
    
    // Function to reset table filters
    function resetFilter() {
        document.getElementById('filterCategory').value = '';
        document.getElementById('filterCondition').value = '';
        document.getElementById('filterLocation').value = '';
        
        const table = document.getElementById('assetsTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        for (let i = 0; i < rows.length; i++) {
            rows[i].style.display = '';
        }
    }

    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        // Category chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($category_labels) ?>,
                datasets: [{
                    data: <?= json_encode($category_counts) ?>,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#fd7e14', '#6f42c1', '#20c9a6', '#5a5c69', '#858796'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        
        // Condition chart
        const conditionCtx = document.getElementById('conditionChart').getContext('2d');
        const conditionChart = new Chart(conditionCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($condition_labels) ?>,
                datasets: [{
                    label: 'Number of Assets',
                    data: <?= json_encode($condition_counts) ?>,
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    });
</script>
</body>
</html>