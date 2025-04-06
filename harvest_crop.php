<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';

// Function to get all crops with their data
function getCrops($conn, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = "";
    
    // Apply filters if provided
    if (!empty($filters)) {
        // Filter by crop type/category
        if (!empty($filters['category']) && $filters['category'] != 'all') {
            $where_clauses[] = "cc.category_name = ?";
            $params[] = $filters['category'];
            $types .= "s";
        }
        
        // Filter by season
        if (!empty($filters['season']) && $filters['season'] != 'all') {
            $where_clauses[] = "s.season_name = ?";
            $params[] = $filters['season'];
            $types .= "s";
        }
        
        // Filter by harvest month
        if (!empty($filters['harvest_month']) && $filters['harvest_month'] != 'all') {
            $where_clauses[] = "(cal.stage_id = (SELECT stage_id FROM growth_stages WHERE stage_name = 'Harvesting') 
                               AND ? BETWEEN cal.start_month AND cal.end_month)";
            $params[] = intval($filters['harvest_month']);
            $types .= "i";
        }
    }
    
    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
    }
    
    $sql = "SELECT DISTINCT c.crop_id, c.crop_name, cc.category_name 
            FROM crops c
            JOIN crop_categories cc ON c.category_id = cc.category_id
            LEFT JOIN crop_calendar cal ON c.crop_id = cal.crop_id
            LEFT JOIN crop_seasons cs ON c.crop_id = cs.crop_id
            LEFT JOIN seasons s ON cs.season_id = s.season_id
            $where_sql
            ORDER BY c.crop_name";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $crops = [];
    while ($row = $result->fetch_assoc()) {
        $crops[] = $row;
    }
    
    return $crops;
}

// Function to get crop calendar data
function getCropCalendar($conn, $crop_id) {
    $sql = "SELECT gs.stage_name, gs.color_code, cal.start_month, cal.end_month
            FROM crop_calendar cal
            JOIN growth_stages gs ON cal.stage_id = gs.stage_id
            WHERE cal.crop_id = ?
            ORDER BY cal.start_month, gs.stage_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $crop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $calendar_data = [];
    while ($row = $result->fetch_assoc()) {
        $calendar_data[] = $row;
    }
    
    return $calendar_data;
}

// Function to get crop details
function getCropDetails($conn, $crop_id) {
    $sql = "SELECT c.crop_id, c.crop_name, cc.category_name, c.soil_requirements, 
            c.watering_needs, c.sunlight_requirements, c.days_to_maturity, 
            c.spacing_requirements, c.common_issues, c.notes
            FROM crops c
            JOIN crop_categories cc ON c.category_id = cc.category_id
            WHERE c.crop_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $crop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// Function to get all categories
function getCategories($conn) {
    $sql = "SELECT category_id, category_name FROM crop_categories ORDER BY category_name";
    $result = $conn->query($sql);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

// Function to get all seasons
function getSeasons($conn) {
    $sql = "SELECT season_id, season_name FROM seasons ORDER BY season_id";
    $result = $conn->query($sql);
    
    $seasons = [];
    while ($row = $result->fetch_assoc()) {
        $seasons[] = $row;
    }
    
    return $seasons;
}

// Function to get growth stages
function getGrowthStages($conn) {
    $sql = "SELECT stage_id, stage_name, color_code FROM growth_stages ORDER BY stage_id";
    $result = $conn->query($sql);
    
    $stages = [];
    while ($row = $result->fetch_assoc()) {
        $stages[] = $row;
    }
    
    return $stages;
}

// Function to add a new crop
function addCrop($conn, $crop_data) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into crops table
        $sql = "INSERT INTO crops (crop_name, category_id, soil_requirements, watering_needs, 
                sunlight_requirements, days_to_maturity, spacing_requirements, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssss", 
            $crop_data['crop_name'], 
            $crop_data['category_id'],
            $crop_data['soil_requirements'],
            $crop_data['watering_needs'],
            $crop_data['sunlight_requirements'],
            $crop_data['days_to_maturity'],
            $crop_data['spacing_requirements'],
            $crop_data['notes']
        );
        $stmt->execute();
        
        $crop_id = $conn->insert_id;
        
        // Insert into crop_calendar table
        
        // Planting stage
        if (!empty($crop_data['planting_start']) && !empty($crop_data['planting_end'])) {
            $sql = "INSERT INTO crop_calendar (crop_id, stage_id, start_month, end_month)
                    VALUES (?, (SELECT stage_id FROM growth_stages WHERE stage_name = 'Planting'), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $crop_id, $crop_data['planting_start'], $crop_data['planting_end']);
            $stmt->execute();
        }
        
        // Growing stage
        if (!empty($crop_data['growing_start']) && !empty($crop_data['growing_end'])) {
            $sql = "INSERT INTO crop_calendar (crop_id, stage_id, start_month, end_month)
                    VALUES (?, (SELECT stage_id FROM growth_stages WHERE stage_name = 'Growing'), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $crop_id, $crop_data['growing_start'], $crop_data['growing_end']);
            $stmt->execute();
        }
        
        // Harvesting stage
        if (!empty($crop_data['harvesting_start']) && !empty($crop_data['harvesting_end'])) {
            $sql = "INSERT INTO crop_calendar (crop_id, stage_id, start_month, end_month)
                    VALUES (?, (SELECT stage_id FROM growth_stages WHERE stage_name = 'Harvesting'), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $crop_id, $crop_data['harvesting_start'], $crop_data['harvesting_end']);
            $stmt->execute();
        }
        
        // Add seasons
        if (!empty($crop_data['seasons'])) {
            foreach ($crop_data['seasons'] as $season_id) {
                $sql = "INSERT INTO crop_seasons (crop_id, season_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $crop_id, $season_id);
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        return false;
    }
}

// Handle form submissions
$message = '';

// Add new crop
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_crop'])) {
    $crop_data = [
        'crop_name' => $_POST['cropName'],
        'category_id' => $_POST['cropCategory'],
        'soil_requirements' => $_POST['soilRequirements'] ?? '',
        'watering_needs' => $_POST['wateringNeeds'] ?? '',
        'sunlight_requirements' => $_POST['sunlightRequirements'] ?? '',
        'days_to_maturity' => $_POST['daysToMaturity'] ?? '',
        'spacing_requirements' => $_POST['spacingRequirements'] ?? '',
        'notes' => $_POST['cropNotes'] ?? '',
        'planting_start' => $_POST['plantingStart'] ?? null,
        'planting_end' => $_POST['plantingEnd'] ?? null,
        'growing_start' => $_POST['growingStart'] ?? null,
        'growing_end' => $_POST['growingEnd'] ?? null,
        'harvesting_start' => $_POST['harvestingStart'] ?? null,
        'harvesting_end' => $_POST['harvestingEnd'] ?? null,
        'seasons' => $_POST['seasons'] ?? []
    ];
    
    if (addCrop($conn, $crop_data)) {
        $message = "Crop added successfully!";
    } else {
        $message = "Error adding crop. Please try again.";
    }
}

// Get filter values
$category_filter = $_GET['cropType'] ?? 'all';
$season_filter = $_GET['season'] ?? 'all';
$harvest_month_filter = $_GET['harvestMonth'] ?? 'all';

$filters = [
    'category' => $category_filter,
    'season' => $season_filter,
    'harvest_month' => $harvest_month_filter
];

// Get data from database
$crops = getCrops($conn, $filters);
$categories = getCategories($conn);
$seasons = getSeasons($conn);
$growth_stages = getGrowthStages($conn);

// Initialize arrays to store calendar data for each crop
$calendar_data = [];
foreach ($crops as $crop) {
    $calendar_data[$crop['crop_id']] = getCropCalendar($conn, $crop['crop_id']);
}

// Get crop details if requested
$selected_crop = null;
if (isset($_GET['crop_id'])) {
    $selected_crop = getCropDetails($conn, $_GET['crop_id']);
}


// Include header
include 'views/header.php';
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Harvest Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .header {
            background: linear-gradient(to right, #43a047, #2e7d32);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
    
        
        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .month {
            text-align: center;
            font-weight: bold;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
        
        .crop-row {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .crop-name {
            width: 150px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .month-block {
            flex: 1;
            height: 35px;
            text-align: center;
            border: 1px solid #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .planting {
            background-color: #bbdefb;  /* Light blue */
        }
        
        .growing {
            background-color: #c8e6c9;  /* Light green */
        }
        
        .harvesting {
            background-color: #ffcc80;  /* Light orange */
        }
        
        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 5px;
        }
        
        .filter-section {
            margin-bottom: 20px;
        }
        
        .add-crop-section {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex align-items-center">
                <h1>Farm Harvest Calendar</h1>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="filter-section d-flex gap-3">
                    <form method="GET" action="" class="d-flex gap-3 w-100">
                        <div class="form-group flex-fill">
                            <label for="cropType" class="form-label">Filter by Crop Type:</label>
                            <select id="cropType" name="cropType" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Crops</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['category_name']); ?>" 
                                            <?php echo $category_filter == $category['category_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group flex-fill">
                            <label for="season" class="form-label">Filter by Season:</label>
                            <select id="season" name="season" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $season_filter == 'all' ? 'selected' : ''; ?>>All Seasons</option>
                                <?php foreach ($seasons as $season): ?>
                                    <option value="<?php echo htmlspecialchars($season['season_name']); ?>" 
                                            <?php echo $season_filter == $season['season_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($season['season_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group flex-fill">
                            <label for="harvestMonth" class="form-label">Filter by Harvest Month:</label>
                            <select id="harvestMonth" name="harvestMonth" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $harvest_month_filter == 'all' ? 'selected' : ''; ?>>All Months</option>
                                <?php
                                $months = [
                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                ];
                                foreach ($months as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo $harvest_month_filter == $num ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="calendar-container">
                    <div class="legend">
                        <?php foreach ($growth_stages as $stage): ?>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: <?php echo htmlspecialchars($stage['color_code']); ?>"></div>
                                <span><?php echo htmlspecialchars($stage['stage_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="calendar">
                        <div class="d-flex mb-2">
                            <div style="width: 150px;"></div>
                            <?php
                            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            foreach ($months as $month): ?>
                                <div class="month flex-fill"><?php echo $month; ?></div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php foreach ($crops as $crop): ?>
                            <div class="crop-row">
                                <div class="crop-name" onclick="window.location.href='?crop_id=<?php echo $crop['crop_id']; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key != 'crop_id'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>'">
                                    <?php echo htmlspecialchars($crop['crop_name']); ?>
                                </div>
                                
                                <?php
                                // Initialize month blocks
                                $crop_months = [];
                                for ($i = 1; $i <= 12; $i++) {
                                    $crop_months[$i] = '';
                                }
                                
                                // Fill in data from calendar_data
                                if (isset($calendar_data[$crop['crop_id']])) {
                                    foreach ($calendar_data[$crop['crop_id']] as $period) {
                                        $start = intval($period['start_month']);
                                        $end = intval($period['end_month']);
                                        $stage_class = strtolower($period['stage_name']);
                                        
                                        for ($m = $start; $m <= $end; $m++) {
                                            $crop_months[$m] = $stage_class;
                                        }
                                    }
                                }
                                
                                // Display month blocks
                                for ($m = 1; $m <= 12; $m++): ?>
                                    <div class="month-block <?php echo $crop_months[$m]; ?>"></div>
                                <?php endfor; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
            <div>
                <a href="harvest_crop_analysis.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-tags"></i> Crop Analysis
                </a>
            </div>
                <div class="add-crop-section">
                    <h3>Add New Crop</h3>
                  
                    <form class="row g-3" method="POST" action="">
                        <div class="col-md-6">
                            <label for="cropName" class="form-label">Crop Name:</label>
                            <input type="text" class="form-control" id="cropName" name="cropName" required placeholder="Enter crop name">
                        </div>
                        <div class="col-md-6">
                            <label for="cropCategory" class="form-label">Crop Category:</label>
                            <select class="form-select" id="cropCategory" name="cropCategory" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Planting Season:</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="plantingStart" name="plantingStart">
                                    <option value="">Start</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-select" id="plantingEnd" name="plantingEnd">
                                    <option value="">End</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Growing Season:</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="growingStart" name="growingStart">
                                    <option value="">Start</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-select" id="growingEnd" name="growingEnd">
                                    <option value="">End</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Harvesting Season:</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="harvestingStart" name="harvestingStart">
                                    <option value="">Start</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-select" id="harvestingEnd" name="harvestingEnd">
                                    <option value="">End</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Seasons:</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($seasons as $season): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="seasons[]" id="season<?php echo $season['season_id']; ?>" value="<?php echo $season['season_id']; ?>">
                                        <label class="form-check-label" for="season<?php echo $season['season_id']; ?>">
                                            <?php echo htmlspecialchars($season['season_name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="soilRequirements" class="form-label">Soil Requirements:</label>
                            <input type="text" class="form-control" id="soilRequirements" name="soilRequirements" placeholder="Soil requirements">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="wateringNeeds" class="form-label">Watering Needs:</label>
                            <input type="text" class="form-control" id="wateringNeeds" name="wateringNeeds" placeholder="Watering needs">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="sunlightRequirements" class="form-label">Sunlight:</label>
                            <input type="text" class="form-control" id="sunlightRequirements" name="sunlightRequirements" placeholder="Sunlight requirements">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="daysToMaturity" class="form-label">Days to Maturity:</label>
                            <input type="text" class="form-control" id="daysToMaturity" name="daysToMaturity" placeholder="Days to maturity">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="spacingRequirements" class="form-label">Spacing:</label>
                            <input type="text" class="form-control" id="spacingRequirements" name="spacingRequirements" placeholder="Spacing requirements">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="cropNotes" class="form-label">Notes:</label>
                            <textarea class="form-control" id="cropNotes" name="cropNotes" rows="3" placeholder="Add any special notes or instructions for this crop"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" name="add_crop" class="btn btn-success">Add Crop to Calendar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Growing Tips</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($selected_crop): ?>
                            <h5 id="selectedCropName"><?php echo htmlspecialchars($selected_crop['crop_name']); ?></h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Category:</strong> <?php echo htmlspecialchars($selected_crop['category_name']); ?></li>
                                <li class="list-group-item"><strong>Soil Requirements:</strong> <?php echo htmlspecialchars($selected_crop['soil_requirements'] ?: 'Not specified'); ?></li>
                                <li class="list-group-item"><strong>Watering Needs:</strong> <?php echo htmlspecialchars($selected_crop['watering_needs'] ?: 'Not specified'); ?></li>
                                <li class="list-group-item"><strong>Sunlight:</strong> <?php echo htmlspecialchars($selected_crop['sunlight_requirements'] ?: 'Not specified'); ?></li>
                                <li class="list-group-item"><strong>Days to Maturity:</strong> <?php echo htmlspecialchars($selected_crop['days_to_maturity'] ?: 'Not specified'); ?></li>
                                <li class="list-group-item"><strong>Spacing:</strong> <?php echo htmlspecialchars($selected_crop['spacing_requirements'] ?: 'Not specified'); ?></li>
                            </ul>
                            <?php if (!empty($selected_crop['common_issues'])): ?>
                                <div class="mt-3">
                                    <h6>Common Issues:</h6>
                                    <p><?php echo htmlspecialchars($selected_crop['common_issues']); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($selected_crop['notes'])): ?>
                                <div class="mt-3">
                                    <h6>Notes:</h6>
                                    <p><?php echo htmlspecialchars($selected_crop['notes']); ?></p>
                                </div>
                            <?php endif; ?>
                            




                            <?php else: ?>
                                <div class="alert alert-info">
                                    <p>Click on a crop name in the calendar to view detailed growing information.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="mt-5 mb-3 text-center text-muted">
            <p>&copy; <?php echo date('Y'); ?> Farm Management System. All rights reserved.</p>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript to enhance user experience
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                });
            }, 5000);
            
            // Form validation for the add crop form
            const addCropForm = document.querySelector('form[name="add_crop"]');
            if (addCropForm) {
                addCropForm.addEventListener('submit', function(event) {
                    const cropName = document.getElementById('cropName').value;
                    const cropCategory = document.getElementById('cropCategory').value;
                    
                    if (!cropName || !cropCategory) {
                        event.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                    
                    // Validate planting season
                    const plantingStart = document.getElementById('plantingStart').value;
                    const plantingEnd = document.getElementById('plantingEnd').value;
                    if ((plantingStart && !plantingEnd) || (!plantingStart && plantingEnd)) {
                        event.preventDefault();
                        alert('Please select both start and end months for planting season.');
                    }
                    
                    // Validate growing season
                    const growingStart = document.getElementById('growingStart').value;
                    const growingEnd = document.getElementById('growingEnd').value;
                    if ((growingStart && !growingEnd) || (!growingStart && growingEnd)) {
                        event.preventDefault();
                        alert('Please select both start and end months for growing season.');
                    }
                    
                    // Validate harvesting season
                    const harvestingStart = document.getElementById('harvestingStart').value;
                    const harvestingEnd = document.getElementById('harvestingEnd').value;
                    if ((harvestingStart && !harvestingEnd) || (!harvestingStart && harvestingEnd)) {
                        event.preventDefault();
                        alert('Please select both start and end months for harvesting season.');
                    }
                });
            }
        });
    </script>
</body>
</html>