<?php
// File: views/harvest_records.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session at the beginning


// Process form submissions - MOVED TO BEGINNING
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include necessary files for processing
    require_once 'crop/harvest_functions.php';
    include 'config/db.php';
    
    if (isset($_POST['action'])) {
        $redirect = true; // Flag to determine if we should redirect
        
        switch ($_POST['action']) {
            case 'add_harvest':
                // Validate required fields
                if (!isset($_POST['cycle_id']) || empty($_POST['cycle_id']) || 
                    !isset($_POST['harvest_date']) || empty($_POST['harvest_date'])) {
                    $_SESSION['error'] = "Crop cycle and harvest date are required.";
                } else {
                    // Prepare data for insertion
                    $cycle_id = (int)$_POST['cycle_id'];
                    $harvest_date = $_POST['harvest_date'];
                    $quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : null;
                    $unit = isset($_POST['unit']) && $_POST['unit'] !== '' ? $_POST['unit'] : null;
                    $quality_rating = isset($_POST['quality_rating']) && $_POST['quality_rating'] !== '' ? (int)$_POST['quality_rating'] : null;
                    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
                    
                    // Create an array with named variables (not direct array elements)
                    $harvestData = [
                        'cycle_id' => $cycle_id,
                        'harvest_date' => $harvest_date,
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'quality_rating' => $quality_rating,
                        'notes' => $notes
                    ];
                    
                    $result = addHarvestRecord($conn, $harvestData);
                    
                    if ($result) {
                        $_SESSION['success'] = "Harvest record added successfully.";
                    } else {
                        $_SESSION['error'] = "Failed to add harvest record: " . $conn->error;
                    }
                }
                break;
                
            case 'update_harvest':
                // Validate required fields
                if (!isset($_POST['harvest_id']) || empty($_POST['harvest_id']) ||
                    !isset($_POST['cycle_id']) || empty($_POST['cycle_id']) || 
                    !isset($_POST['harvest_date']) || empty($_POST['harvest_date'])) {
                    $_SESSION['error'] = "Harvest ID, crop cycle, and harvest date are required.";
                } else {
                    $harvest_id = (int)$_POST['harvest_id'];
                    $cycle_id = (int)$_POST['cycle_id'];
                    $harvest_date = $_POST['harvest_date'];
                    $quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : null;
                    $unit = isset($_POST['unit']) && $_POST['unit'] !== '' ? $_POST['unit'] : null;
                    $quality_rating = isset($_POST['quality_rating']) && $_POST['quality_rating'] !== '' ? (int)$_POST['quality_rating'] : null;
                    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
                    
                    // Create array with variables (not direct array elements)
                    $harvestData = [
                        'cycle_id' => $cycle_id,
                        'harvest_date' => $harvest_date,
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'quality_rating' => $quality_rating,
                        'notes' => $notes
                    ];
                    
                    $result = updateHarvestRecord($conn, $harvest_id, $harvestData);
                    
                    if ($result) {
                        $_SESSION['success'] = "Harvest record updated successfully.";
                    } else {
                        $_SESSION['error'] = "Failed to update harvest record: " . $conn->error;
                    }
                }
                break;
                
            case 'delete_harvest':
                if (!isset($_POST['harvest_id']) || empty($_POST['harvest_id'])) {
                    $_SESSION['error'] = "Harvest ID is required for deletion.";
                } else {
                    $harvest_id = (int)$_POST['harvest_id'];
                    $result = deleteHarvestRecord($conn, $harvest_id);
                    
                    if ($result) {
                        $_SESSION['success'] = "Harvest record deleted successfully.";
                    } else {
                        $_SESSION['error'] = "Failed to delete harvest record: " . $conn->error;
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        if ($redirect) {
            // Store the current URL before redirecting
            $current_url = $_SERVER['PHP_SELF'];
            header("Location: " . $current_url);
            exit();
        }
    }
}

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    // Include necessary files for processing
    require_once 'crop/harvest_functions.php';
    include 'config/db.php';
    
    header('Content-Type: application/json');
    
    if (isset($_GET['action']) && $_GET['action'] === 'get_harvest') {
        if (!isset($_GET['harvest_id']) || empty($_GET['harvest_id'])) {
            echo json_encode(['success' => false, 'message' => 'Harvest ID is required']);
            exit();
        }
        
        $harvest_id = (int)$_GET['harvest_id'];
        $harvest = getHarvestById($conn, $harvest_id);
        
        if ($harvest) {
            echo json_encode(['success' => true, 'harvest' => $harvest]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Harvest record not found']);
        }
        exit();
    }
}

// Now include necessary files for display
require_once 'crop/harvest_functions.php';
include 'config/db.php';
require_once 'views/header.php';

// Function to get all active crop cycles
function getAllActiveCropCycles($conn) {
    $sql = "SELECT cc.cycle_id, cc.start_date, cc.expected_first_harvest, cc.status, cc.field_or_location,
            CONCAT(c.crop_name, ' - ', cc.field_or_location, ' (', 
            DATE_FORMAT(cc.start_date, '%b %Y'), ')') AS cycle_name,
            c.crop_name
            FROM crop_cycles cc
            JOIN crops c ON cc.crop_id = c.crop_id
            WHERE cc.status = 'In Progress' OR cc.status = 'Planned'
            ORDER BY c.crop_name, cc.start_date DESC";
    
    $result = $conn->query($sql);
    
    $cycles = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $cycles[] = $row;
        }
    }
    
    return $cycles;
}

// Function to get detailed crop cycle information
function getCropCycleDetails($conn, $cycle_id) {
    $sql = "SELECT cc.*, c.crop_name 
            FROM crop_cycles cc
            JOIN crops c ON cc.crop_id = c.crop_id
            WHERE cc.cycle_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cycle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get all crop cycles (including inactive)
function getAllCropCycles($conn) {
    $sql = "SELECT cc.cycle_id, CONCAT(c.crop_name, ' - ', cc.field_or_location, ' (', 
            DATE_FORMAT(cc.start_date, '%b %Y'), ')') AS cycle_name  
            FROM crop_cycles cc
            JOIN crops c ON cc.crop_id = c.crop_id
            ORDER BY c.crop_name, cc.start_date DESC";
    
    $result = $conn->query($sql);
    
    $cycles = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $cycles[] = $row;
        }
    }
    
    return $cycles;
}

// Get data for display
$allCropCycles = getAllCropCycles($conn);
$activeCropCycles = getAllActiveCropCycles($conn);

// Handle filters
$cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get harvest records based on filters
$harvests = [];
if ($cycle_id > 0) {
    $harvests = getHarvestRecordsByCycle($conn, $cycle_id);
} else {
    $harvests = getHarvestsByDateRange($conn, $start_date, $end_date);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harvest Records Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/harvest-records.css">
    <style>
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .harvest-quality {
            width: 80px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            color: white;
            font-weight: bold;
        }
        .quality-excellent {
            background-color: #28a745;
        }
        .quality-good {
            background-color: #17a2b8;
        }
        .quality-average {
            background-color: #ffc107;
            color: #212529;
        }
        .quality-poor {
            background-color: #dc3545;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }
        .alert {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Harvest Records</li>
            </ol>
        </nav>
        
        <!-- Alert Messages -->
        <div class="alert-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Harvest Records</h1>
            <div class="header-actions">
                <button type="button" class="btn btn-outline-secondary" id="filterToggle">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHarvestModal">
                    <i class="fas fa-plus"></i> Add Harvest Record
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section" id="filterSection" style="<?php echo !isset($_GET['cycle_id']) && !isset($_GET['start_date']) ? 'display: none;' : ''; ?>">
            <form id="filterForm" method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="cycle_id" class="form-label">Crop Cycle</label>
                        <select class="form-select" id="cycle_id" name="cycle_id">
                            <option value="">All Cycles</option>
                            <?php foreach ($allCropCycles as $cycle): ?>
                                <option value="<?php echo $cycle['cycle_id']; ?>" <?php echo $cycle_id == $cycle['cycle_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cycle['cycle_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <button type="button" class="btn btn-outline-secondary" id="clearFilters">Clear</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Harvest Records Display -->
        <div class="row" id="harvestRecordsList">
            <?php if (count($harvests) > 0): ?>
                <?php foreach ($harvests as $harvest): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo isset($harvest['crop_name']) ? htmlspecialchars($harvest['crop_name']) : 'Unknown Crop'; ?></h5>
                                <?php 
                                // Fix quality rating display
                                $qualityClass = '';
                                $qualityText = '';
                                
                                if (!empty($harvest['quality_rating'])) {
                                    switch ((int)$harvest['quality_rating']) {
                                        case 5:
                                            $qualityClass = 'excellent';
                                            $qualityText = 'Excellent';
                                            break;
                                        case 4:
                                            $qualityClass = 'good';
                                            $qualityText = 'Good';
                                            break;
                                        case 3:
                                            $qualityClass = 'average';
                                            $qualityText = 'Average';
                                            break;
                                        case 2:
                                        case 1:
                                            $qualityClass = 'poor';
                                            $qualityText = 'Poor';
                                            break;
                                    }
                                }
                                
                                if (!empty($qualityClass)): 
                                ?>
                                    <div class="harvest-quality quality-<?php echo $qualityClass; ?>">
                                        <?php echo $qualityText; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-1">
                                    <i class="fas fa-calendar-alt"></i> 
                                    <?php echo date('M d, Y', strtotime($harvest['harvest_date'])); ?>
                                </p>
                                <?php if (isset($harvest['field_or_location'])): ?>
                                    <p class="mb-1">
                                        <strong>Location:</strong> 
                                        <?php echo htmlspecialchars($harvest['field_or_location']); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="mb-1">
                                    <strong>Yield:</strong> 
                                    <?php 
                                    if (!empty($harvest['quantity'])) {
                                        echo htmlspecialchars($harvest['quantity']) . ' ' . htmlspecialchars($harvest['unit'] ?? '');
                                    } else {
                                        echo 'Not recorded';
                                    }
                                    ?>
                                </p>
                                <?php if (!empty($harvest['notes'])): ?>
                                    <p class="mb-0">
                                        <strong>Notes:</strong> 
                                        <?php echo htmlspecialchars($harvest['notes']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-2 edit-harvest" 
                                        data-id="<?php echo $harvest['harvest_id']; ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-harvest" 
                                        data-id="<?php echo $harvest['harvest_id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteHarvestModal">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No harvest records found for the selected criteria.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Harvest Modal -->
    <div class="modal fade" id="addHarvestModal" tabindex="-1" aria-labelledby="addHarvestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addHarvestModalLabel">Add Harvest Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addHarvestForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <input type="hidden" name="action" value="add_harvest">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add_cycle_id" class="form-label">Crop Cycle</label>
                            <select class="form-select" id="add_cycle_id" name="cycle_id" required>
                                <option value="">Select Crop Cycle</option>
                                <?php foreach ($activeCropCycles as $cycle): ?>
                                    <option value="<?php echo $cycle['cycle_id']; ?>">
                                        <?php echo htmlspecialchars($cycle['cycle_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="cycleDetails" class="mt-2 small text-muted" style="display: none;">
                                <div class="p-2 border rounded">
                                    <p class="mb-1"><strong>Location:</strong> <span id="cycleLocation"></span></p>
                                    <p class="mb-1"><strong>Start Date:</strong> <span id="cycleStartDate"></span></p>
                                    <p class="mb-1"><strong>Expected First Harvest:</strong> <span id="cycleExpectedHarvest"></span></p>
                                    <p class="mb-0"><strong>Status:</strong> <span id="cycleStatus"></span></p>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="add_harvest_date" class="form-label">Harvest Date</label>
                            <input type="date" class="form-control" id="add_harvest_date" name="harvest_date" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-8">
                                <label for="add_quantity" class="form-label">Quantity</label>
                                <input type="number" step="0.01" class="form-control" id="add_quantity" name="quantity">
                            </div>
                            <div class="col-4">
                                <label for="add_unit" class="form-label">Unit</label>
                                <select class="form-select" id="add_unit" name="unit">
                                    <option value="">Select</option>
                                    <option value="kg">kg</option>
                                    <option value="lbs">lbs</option>
                                    <option value="bushels">bushels</option>
                                    <option value="tons">tons</option>
                                    <option value="pieces">pieces</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="add_quality_rating" class="form-label">Quality Rating</label>
                            <select class="form-select" id="add_quality_rating" name="quality_rating">
                                <option value="">Select Quality</option>
                                <option value="5">Excellent</option>
                                <option value="4">Good</option>
                                <option value="3">Average</option>
                                <option value="2">Poor</option>
                                <option value="1">Bad</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="add_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Harvest Modal -->
    <div class="modal fade" id="editHarvestModal" tabindex="-1" aria-labelledby="editHarvestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editHarvestModalLabel">Edit Harvest Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editHarvestForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <input type="hidden" name="action" value="update_harvest">
                    <input type="hidden" name="harvest_id" id="edit_harvest_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_cycle_id" class="form-label">Crop Cycle</label>
                            <select class="form-select" id="edit_cycle_id" name="cycle_id" required>
                                <option value="">Select Crop Cycle</option>
                                <?php foreach ($allCropCycles as $cycle): ?>
                                    <option value="<?php echo $cycle['cycle_id']; ?>">
                                        <?php echo htmlspecialchars($cycle['cycle_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_harvest_date" class="form-label">Harvest Date</label>
                            <input type="date" class="form-control" id="edit_harvest_date" name="harvest_date" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-8">
                                <label for="edit_quantity" class="form-label">Quantity</label>
                                <input type="number" step="0.01" class="form-control" id="edit_quantity" name="quantity">
                            </div>
                            <div class="col-4">
                                <label for="edit_unit" class="form-label">Unit</label>
                                <select class="form-select" id="edit_unit" name="unit">
                                    <option value="">Select</option>
                                    <option value="kg">kg</option>
                                    <option value="lbs">lbs</option>
                                    <option value="bushels">bushels</option>
                                    <option value="tons">tons</option>
                                    <option value="pieces">pieces</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_quality_rating" class="form-label">Quality Rating</label>
                            <select class="form-select" id="edit_quality_rating" name="quality_rating">
                                <option value="">Select Quality</option>
                                <option value="5">Excellent</option>
                                <option value="4">Good</option>
                                <option value="3">Average</option>
                                <option value="2">Poor</option>
                                <option value="1">Bad</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteHarvestModal" tabindex="-1" aria-labelledby="deleteHarvestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteHarvestModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this harvest record? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <form id="deleteHarvestForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                        <input type="hidden" name="action" value="delete_harvest">
                        <input type="hidden" name="harvest_id" id="delete_harvest_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = bootstrap.Alert.getInstance(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                });
            }, 5000);
            
            // Crop cycle details data
            // Crop cycle details data
            const cycleData = <?php echo json_encode($activeCropCycles); ?>;

            // Handle crop cycle selection to show details
            document.getElementById('add_cycle_id').addEventListener('change', function() {
                const cycleId = this.value;
                const cycleDetailsDiv = document.getElementById('cycleDetails');
                
                if (cycleId) {
                    // Find the selected cycle in the data
                    const selectedCycle = cycleData.find(cycle => cycle.cycle_id === cycleId);
                    
                    if (selectedCycle) {
                        // Update the details
                        document.getElementById('cycleLocation').textContent = selectedCycle.field_or_location || 'N/A';
                        document.getElementById('cycleStartDate').textContent = formatDate(selectedCycle.start_date) || 'N/A';
                        document.getElementById('cycleExpectedHarvest').textContent = formatDate(selectedCycle.expected_first_harvest) || 'N/A';
                        document.getElementById('cycleStatus').textContent = selectedCycle.status || 'N/A';
                        
                        // Show the details section
                        cycleDetailsDiv.style.display = 'block';
                    } else {
                        cycleDetailsDiv.style.display = 'none';
                    }
                } else {
                    cycleDetailsDiv.style.display = 'none';
                }
            });
            
            // Helper function to format dates
            function formatDate(dateString) {
                if (!dateString) return null;
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }
            
            // Filter toggle button
            document.getElementById('filterToggle').addEventListener('click', function() {
                const filterSection = document.getElementById('filterSection');
                filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
            });
            
            // Clear filters button
            document.getElementById('clearFilters').addEventListener('click', function() {
                document.getElementById('cycle_id').value = '';
                document.getElementById('start_date').value = '';
                document.getElementById('end_date').value = '';
                document.getElementById('filterForm').submit();
            });
            
            // Edit harvest button clicks
            document.querySelectorAll('.edit-harvest').forEach(button => {
                button.addEventListener('click', function() {
                    const harvestId = this.getAttribute('data-id');
                    
                    // Set the harvest ID in the form
                    document.getElementById('edit_harvest_id').value = harvestId;
                    
                    // Fetch harvest data via AJAX
                    fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?ajax=true&action=get_harvest&harvest_id=${harvestId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const harvest = data.harvest;
                                
                                // Populate the form fields
                                document.getElementById('edit_cycle_id').value = harvest.cycle_id;
                                document.getElementById('edit_harvest_date').value = harvest.harvest_date;
                                document.getElementById('edit_quantity').value = harvest.quantity || '';
                                document.getElementById('edit_unit').value = harvest.unit || '';
                                document.getElementById('edit_quality_rating').value = harvest.quality_rating || '';
                                document.getElementById('edit_notes').value = harvest.notes || '';
                                
                                // Open the modal
                                const editModal = new bootstrap.Modal(document.getElementById('editHarvestModal'));
                                editModal.show();
                            } else {
                                alert('Error loading harvest record: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while fetching the harvest record.');
                        });
                });
            });
            
            // Delete harvest button clicks
            document.querySelectorAll('.delete-harvest').forEach(button => {
                button.addEventListener('click', function() {
                    const harvestId = this.getAttribute('data-id');
                    document.getElementById('delete_harvest_id').value = harvestId;
                });
            });
            
            // Set default date for new harvest to today
            document.getElementById('add_harvest_date').valueAsDate = new Date();
        });
    </script>
</body>
</html>
<?php
// Close the database connection
if (isset($conn)) {
    $conn->close();
}
?>