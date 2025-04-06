<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';

// Set default filters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$condition_filter = isset($_GET['condition']) ? $_GET['condition'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Base query
$query = "SELECT a.*, c.category_name, ac.condition_name 
          FROM assets a 
          LEFT JOIN asset_categories c ON a.category_id = c.category_id
          LEFT JOIN asset_conditions ac ON a.condition_id = ac.condition_id
          WHERE 1=1";

// Apply filters
$params = [];
if (!empty($category_filter)) {
    $query .= " AND a.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($condition_filter)) {
    $query .= " AND a.condition_id = ?";
    $params[] = $condition_filter;
}

if (!empty($date_from)) {
    $query .= " AND a.acquisition_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND a.acquisition_date <= ?";
    $params[] = $date_to;
}

// Prepare statement
$stmt = $conn->prepare($query);

// Bind parameters if any
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

// Execute query
$stmt->execute();
$result = $stmt->get_result();
$assets = [];
while ($row = $result->fetch_assoc()) {
    $assets[] = $row;
}

// Get all categories for the filter dropdown
$categories_result = $conn->query("SELECT * FROM asset_categories ORDER BY category_name");
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}

// Get all condition options for the filter dropdown
$conditions_result = $conn->query("SELECT * FROM asset_conditions ORDER BY condition_name");
$conditions = [];
while ($condition = $conditions_result->fetch_assoc()) {
    $conditions[] = $condition;
}

// Get summary statistics
$total_assets = count($assets);
$total_value = 0;
$assets_by_category = [];
$assets_by_condition = [];

foreach ($assets as $asset) {
    // Calculate total value
    $asset_value = isset($asset['current_value']) ? $asset['current_value'] : 
                  (isset($asset['acquisition_cost']) ? $asset['acquisition_cost'] : 0);
    $total_value += $asset_value * $asset['quantity'];
    
    // Group by category
    $category = $asset['category_name'] ?? 'Uncategorized';
    if (!isset($assets_by_category[$category])) {
        $assets_by_category[$category] = 0;
    }
    $assets_by_category[$category] += $asset['quantity'];
    
    // Group by condition
    $condition = $asset['condition_name'] ?? 'Unknown';
    if (!isset($assets_by_condition[$condition])) {
        $assets_by_condition[$condition] = 0;
    }
    $assets_by_condition[$condition] += $asset['quantity'];
}

// Include header
include 'views/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<style>
/* Custom CSS for Asset Reports Page */

/* General Styling */
body {
    background-color: #f8f9fc;
    font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* Header Styling */
h1 {
    color: #4c956c;
    font-weight: 600;
}

/* Card Enhancements */
.card {
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
    border: none;
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
}

.card-header {
    border-top-left-radius: 8px !important;
    border-top-right-radius: 8px !important;
    font-weight: 600;
}

/* Stats Cards */
.bg-primary {
    background:  #2c6e49 !important;
}

.bg-success {
    background: linear-gradient(135deg, #1cc88a 0%, #169a6b 100%) !important;
}

.bg-info {
    background: linear-gradient(135deg, #36b9cc 0%, #258391 100%) !important;
}

/* Table Enhancements */
.table {
    border-collapse: separate;
    border-spacing: 0;
}

.table th {
    background-color: #f8f9fc;
    color: #5a5c69;
    font-weight: 600;
    border-bottom: 2px solid #e3e6f0;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(78, 115, 223, 0.05);
}

.table-hover tbody tr:hover {
    background-color: rgba(78, 115, 223, 0.1);
}

/* Button Styling */
.btn {
    border-radius: 6px;
    padding: 8px 16px;
    font-weight: 500;
    letter-spacing: 0.5px;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #4e73df;
    border-color: #4e73df;
}

.btn-primary:hover {
    background-color: #2e59d9;
    border-color: #2653d4;
}

.btn-outline-primary {
    color: #4e73df;
    border-color: #4e73df;
}

.btn-outline-primary:hover {
    background-color: #4e73df;
    color: white;
}

.btn-success {
    background-color: #1cc88a;
    border-color: #1cc88a;
}

.btn-success:hover {
    background-color: #17a673;
    border-color: #169b6b;
}

.btn-danger {
    background-color: #e74a3b;
    border-color: #e74a3b;
}

.btn-danger:hover {
    background-color: #e02d1b;
    border-color: #d52a1a;
}

/* Form Controls */
.form-control, .form-select {
    border-radius: 6px;
    border: 1px solid #d1d3e2;
    padding: 10px 15px;
    height: auto;
}

.form-control:focus, .form-select:focus {
    border-color: #bac8f3;
    box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
}

/* Badge Styling */
.badge {
    padding: 6px 10px;
    font-weight: 600;
    font-size: 0.75rem;
    border-radius: 6px;
}

/* Chart Container */
canvas {
    max-width: 100%;
    margin: 10px auto;
}

/* Export Buttons */
.btn-success, .btn-danger {
    padding: 8px 20px;
    margin-left: 10px;
}

/* Modern Summary Statistics Cards */

/* Container for all stats cards */
.stats-container {
  padding: 15px 0;
}

/* Base card styling */
.stats-card {
  border-radius: 16px;
  padding: 24px;
  height: 100%;
  position: relative;
  overflow: hidden;
  border: none;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
}

.stats-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
}

/* Card backgrounds with gradients */
.stats-card.assets {
  background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
}

.stats-card.value {
  background: linear-gradient(135deg, #10B981 0%, #059669 100%);
}

.stats-card.categories {
  background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
}

/* Card content styling */
.stats-card-content {
  position: relative;
  z-index: 2;
}

.stats-card-title {
  color: rgba(255, 255, 255, 0.9);
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: 0.5px;
  margin-bottom: 12px;
  text-transform: uppercase;
}

.stats-card-value {
  color: white;
  font-size: 2.5rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 0;
}

.stats-card-prefix {
  font-size: 1.5rem;
  font-weight: 500;
  opacity: 0.9;
}

/* Decorative elements */
.stats-card::before {
  content: "";
  position: absolute;
  top: -50px;
  right: -50px;
  width: 150px;
  height: 150px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  z-index: 1;
}

.stats-card::after {
  content: "";
  position: absolute;
  bottom: -30px;
  left: -30px;
  width: 100px;
  height: 100px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
  z-index: 1;
}

/* Icons for each card */
.stats-card-icon {
  position: absolute;
  top: 20px;
  right: 20px;
  font-size: 1.75rem;
  color: rgba(255, 255, 255, 0.4);
}
/* Responsive Adjustments */
@media (max-width: 768px) {
    .row .col-md-4 {
        margin-bottom: 15px;
    }
    
    .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .d-flex .btn {
        margin-top: 10px;
        margin-left: 0 !important;
    }
}

/* Footer Styling */
footer {
    background-color: #f8f9fc;
    padding: 20px 0;
    border-top: 1px solid #e3e6f0;
    margin-top: 30px;
}

/* Print Media Query */
@media print {
    .btn, .card-header {
        display: none;
    }
    
    .container {
        width: 100%;
        max-width: 100%;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    body {
        background-color: white;
    }
}
</style>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Asset Reports</h1>
        <div>
            <a href="assets.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-list"></i> Asset List
            </a>
            <a href="asset_categories.php" class="btn btn-outline-primary">
                <i class="fas fa-tags"></i> Manage Categories
            </a>
        </div>
    </div>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-filter"></i> Filter Assets
        </div>
        <div class="card-body">
            <form method="get" action="asset_report.php" class="row g-3">
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['category_id'] ?>" <?= $category_filter == $category['category_id'] ? 'selected' : '' ?>>
                                <?= $category['category_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="condition" class="form-label">Condition</label>
                    <select class="form-select" id="condition" name="condition">
                        <option value="">All Conditions</option>
                        <?php foreach ($conditions as $condition): ?>
                            <option value="<?= $condition['condition_id'] ?>" <?= $condition_filter == $condition['condition_id'] ? 'selected' : '' ?>>
                                <?= $condition['condition_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Acquired From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Acquired To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="asset_report.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
<!-- Summary Statistics -->
<div class="row mb-4 stats-container">
    <div class="col-md-4 mb-4 mb-md-0">
        <div class="stats-card assets">
            <div class="stats-card-content">
                <h5 class="stats-card-title">Total Assets</h5>
                <h1 class="stats-card-value"><?= $total_assets ?></h1>
            </div>
            <div class="stats-card-icon">
                <i class="fas fa-boxes"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4 mb-md-0">
        <div class="stats-card value">
            <div class="stats-card-content">
                <h5 class="stats-card-title">Total Value</h5>
                <h1 class="stats-card-value">
                    <span class="stats-card-prefix">GHS</span> 
                    <?= number_format($total_value, 2) ?>
                </h1>
            </div>
            <div class="stats-card-icon">
                <i class="fas fa-coins"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card categories">
            <div class="stats-card-content">
                <h5 class="stats-card-title">Categories</h5>
                <h1 class="stats-card-value"><?= count($assets_by_category) ?></h1>
            </div>
            <div class="stats-card-icon">
                <i class="fas fa-tags"></i>
            </div>
        </div>
    </div>
</div>
    
    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Assets by Category
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Assets by Condition
                </div>
                <div class="card-body">
                    <canvas id="conditionChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Asset List -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-list"></i> Asset List
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Asset Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Condition</th>
                            <th>Acquisition Date</th>
                            <th>Current Value</th>
                            <th>Location</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($assets) > 0): ?>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td><?= $asset['asset_name'] ?></td>
                                    <td><?= $asset['category_name'] ?? 'Uncategorized' ?></td>
                                    <td><?= $asset['quantity'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= getConditionBadgeClass($asset['condition_name']) ?>">
                                            <?= $asset['condition_name'] ?? 'Unknown' ?>
                                        </span>
                                    </td>
                                    <td><?= $asset['acquisition_date'] ?></td>
                                    <td>
                                        <?php 
                                        $display_value = isset($asset['current_value']) ? $asset['current_value'] : 
                                                        (isset($asset['acquisition_cost']) ? $asset['acquisition_cost'] : 'N/A');
                                        echo is_numeric($display_value) ? 'GHS ' . number_format($display_value, 2) : $display_value;
                                        ?>
                                    </td>
                                    <td><?= $asset['location'] ?? 'N/A' ?></td>
                                    <td><?= $asset['remarks'] ?? '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No assets found matching the filter criteria</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Export buttons -->
    <div class="mt-4 text-end">
        <button class="btn btn-success" onclick="exportToExcel()">
            <i class="fas fa-file-excel"></i> Export to Excel
        </button>
        <button class="btn btn-danger" onclick="exportToPDF()">
            <i class="fas fa-file-pdf"></i> Export to PDF
        </button>
    </div>
</div>
    
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Helper function for getting badge colors based on condition
    <?php
    function getConditionBadgeClass($condition) {
        switch($condition) {
            case 'Excellent':
                return 'success';
            case 'Good':
                return 'info';
            case 'Fair':
                return 'warning';
            case 'Poor':
                return 'danger';
            case 'Needs Repair':
                return 'secondary';
            case 'Not Functional':
                return 'dark';
            default:
                return 'light';
        }
    }
    ?>

    // Category chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: [<?php echo "'" . implode("', '", array_keys($assets_by_category)) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(", ", array_values($assets_by_category)); ?>],
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Condition chart
    const conditionCtx = document.getElementById('conditionChart').getContext('2d');
    const conditionChart = new Chart(conditionCtx, {
        type: 'bar',
        data: {
            labels: [<?php echo "'" . implode("', '", array_keys($assets_by_condition)) . "'"; ?>],
            datasets: [{
                label: 'Number of Assets',
                data: [<?php echo implode(", ", array_values($assets_by_condition)); ?>],
                backgroundColor: [
                    '#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6c757d', '#343a40'
                ]
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Excel export function (placeholder - would need actual implementation or library)
    function exportToExcel() {
        alert("This feature would export the current asset list to Excel. You would need to implement this with a library like SheetJS or server-side export.");
    }

    // PDF export function (placeholder - would need actual implementation or library)
    function exportToPDF() {
        alert("This feature would export the current asset list to PDF. You would need to implement this with a library like jsPDF or server-side PDF generation.");
    }
</script>
</body>
</html>
<?php
include 'views/footer.php';
$conn->close();
?>