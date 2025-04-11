<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';
 // Near the top of your PHP file where you get other data
// Function to get all common issues

// Define the getAllIssues function here
function getAllIssues($conn) {
    $sql = "SELECT issue_id, issue_name FROM common_issues ORDER BY issue_name";
    $result = $conn->query($sql);
    
    $issues = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $issues[] = $row;
        }
    }
    
    return $issues;
}
// Now call the function
$issues = getAllIssues($conn);
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

// Function to get crop lifecycle data
function getCropLifecycleData($conn, $crop_id) {
    $sql = "SELECT nursing_duration, growth_duration 
            FROM crops 
            WHERE crop_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $crop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
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
            c.spacing_requirements, c.common_issues, c.notes, 
            c.nursing_duration, c.growth_duration
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







function addCrop($conn, $crop_data) {
    // Start transaction
    $conn->begin_transaction();

    try {
        // 1. Insert into crops table
        $sql = "INSERT INTO crops (crop_name, category_id, image_path, description, soil_requirements, 
                watering_needs, sunlight_requirements, days_to_maturity, spacing_requirements, 
                common_issues, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Set default values for optional fields
        $image_path = $crop_data['image_path'] ?? null;
        $description = $crop_data['description'] ?? null;
        $common_issues = $crop_data['common_issues'] ?? null;
        $notes = $crop_data['notes'] ?? null;

        $stmt->bind_param("sisssssssss", 
            $crop_data['crop_name'], 
            $crop_data['category_id'],
            $image_path,
            $description,
            $crop_data['soil_requirements'],
            $crop_data['watering_needs'],
            $crop_data['sunlight_requirements'],
            $crop_data['days_to_maturity'],
            $crop_data['spacing_requirements'],
            $common_issues,
            $notes
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Crop insert failed: " . $stmt->error);
        }

        // Get the inserted crop_id
        $crop_id = $stmt->insert_id;

        // Add issues if selected
        if (!empty($crop_data['issue_id']) && is_array($crop_data['issue_id'])) {
            foreach ($crop_data['issue_id'] as $issue_id) {
                if (empty($issue_id)) continue; // Skip empty selections
                
                $severity = $crop_data['issue_severity'][$issue_id] ?? 'Medium';
                $notes = $crop_data['issue_notes'][$issue_id] ?? '';
                
                $sql = "INSERT INTO crop_issues (crop_id, issue_id, severity, notes) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $crop_id, $issue_id, $severity, $notes);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add issue association: " . $stmt->error);
                }
            }
        }

        // Insert into crop_calendar table if data is provided
        
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
        
        // Add crop events if provided
        if (!empty($crop_data['events'])) {
            foreach ($crop_data['events'] as $event) {
                $sql = "INSERT INTO crop_events (event_date, event_name, crop_id, description) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssis", 
                    $event['date'], 
                    $event['name'], 
                    $crop_id, 
                    $event['description'] ?? null
                );
                $stmt->execute();
            }
        }
        
        // Add crop cycle if planting date is provided
        if (!empty($crop_data['planting_date'])) {
            // Get nursing and growth durations
            $nursing_duration = !empty($crop_data['nursing_duration']) ? $crop_data['nursing_duration'] : 0;
            $growth_duration = !empty($crop_data['growth_duration']) ? $crop_data['growth_duration'] : 0;
            
            // Calculate expected first harvest date
            $planting_date = new DateTime($crop_data['planting_date']);
            $total_days = $nursing_duration + $growth_duration;
            $harvest_date = clone $planting_date;
            $harvest_date->add(new DateInterval("P{$total_days}D"));
            
            $formatted_planting_date = $planting_date->format('Y-m-d');
            $formatted_harvest_date = $harvest_date->format('Y-m-d');
            
            $status = 'Planned';
            $field_location = $crop_data['field_location'] ?? 'Main Field';
            $harvest_frequency = $crop_data['harvest_frequency'] ?? 7;
            $expected_end_date = date('Y-m-d', strtotime("+90 days", strtotime($formatted_harvest_date))); // Example default
            $cycle_notes = $crop_data['cycle_notes'] ?? '';

            $sql = "INSERT INTO crop_cycles (crop_id, field_or_location, start_date, nursing_duration, growth_duration, 
                    expected_first_harvest, harvest_frequency, expected_end_date, status, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issiisisss", 
                $crop_id, 
                $field_location,
                $formatted_planting_date,
                $nursing_duration,
                $growth_duration,
                $formatted_harvest_date,
                $harvest_frequency,
                $expected_end_date,
                $status,
                $cycle_notes
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Crop cycle insert failed: " . $stmt->error);
            }
            
            // Create default tasks for this crop cycle
            $cycle_id = $stmt->insert_id;
            
            // Example: Create planting task
            $planting_task = [
                'task_type_id' => 4, // Planting type
                'task_name' => 'Plant ' . $crop_data['crop_name'],
                'scheduled_date' => $formatted_planting_date
            ];
            
            $sql = "INSERT INTO farm_tasks (cycle_id, task_type_id, task_name, scheduled_date) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", 
                $cycle_id, 
                $planting_task['task_type_id'],
                $planting_task['task_name'],
                $planting_task['scheduled_date']
            );
            $stmt->execute();
            
            // Example: Create harvesting task
            $harvest_task = [
                'task_type_id' => 5, // Harvesting type
                'task_name' => 'Harvest ' . $crop_data['crop_name'],
                'scheduled_date' => $formatted_harvest_date
            ];
            
            $sql = "INSERT INTO farm_tasks (cycle_id, task_type_id, task_name, scheduled_date) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", 
                $cycle_id, 
                $harvest_task['task_type_id'],
                $harvest_task['task_name'],
                $harvest_task['scheduled_date']
            );
            $stmt->execute();
        }
        
        // Add issues if provided
        if (!empty($crop_data['issues'])) {
            foreach ($crop_data['issues'] as $issue) {
                $sql = "INSERT INTO crop_issues (crop_id, issue_id, severity, notes) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", 
                    $crop_id, 
                    $issue['issue_id'],
                    $issue['severity'] ?? 'Medium',
                    $issue['notes'] ?? null
                );
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        return $crop_id;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Add Crop Error: " . $e->getMessage()); // logs to PHP error log
        echo "Error: " . $e->getMessage(); // optional: show error directly
        return false;
    }
}

// Function to get crop cycles
function getCropCycles($conn, $crop_id = null) {
    $sql = "SELECT cc.cycle_id, c.crop_name, cc.start_date, cc.nursing_duration, 
            cc.growth_duration, cc.expected_first_harvest, cc.status 
            FROM crop_cycles cc
            JOIN crops c ON cc.crop_id = c.crop_id";
    
    if ($crop_id) {
        $sql .= " WHERE cc.crop_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $crop_id);
    } else {
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cycles = [];
    while ($row = $result->fetch_assoc()) {
        $cycles[] = $row;
    }
    
    return $cycles;
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
        'seasons' => $_POST['seasons'] ?? [],
        'nursing_duration' => $_POST['nursingDuration'] ?? 0,
        'growth_duration' => $_POST['growthDuration'] ?? 0,
        'planting_date' => $_POST['plantingDate'] ?? null
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
    $crop_cycles = getCropCycles($conn, $_GET['crop_id']);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .lifecycle-section {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #43a047;
        }
        
        .lifecycle-schedule {
            background-color: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .timeline {
            position: relative;
            margin: 20px 0;
            padding-left: 40px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding-left: 30px;
        }
        
        .timeline-item:before {
            content: "";
            position: absolute;
            left: -10px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #4caf50;
            z-index: 1;
        }
        
        .timeline-item:after {
            content: "";
            position: absolute;
            left: 0;
            top: 20px;
            width: 2px;
            height: 100%;
            background-color: #e0e0e0;
        }
        
        .timeline-item:last-child:after {
            display: none;
        }
        
        .timeline-date {
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .harvest-schedule {
            background-color: #fff8e1;
            border-left: 4px solid #ffa000;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-top: 15px;
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
                <h1>Farm Harvest </h1>
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
        <div>
                <a href="crop_category.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-tags"></i> Manage Categories
                </a>
            </div>
        <div class="row">
    <div class="col-md-12">
        <div class="add-crop-section">
            <h3>Add New Crop</h3>
            
            <form class="row g-3" method="POST" action="" enctype="multipart/form-data">
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
                
                <!-- Image Upload Field -->
                <div class="col-md-6">
                    <label for="imagePath" class="form-label">Crop Image:</label>
                    <input type="file" class="form-control" id="imagePath" name="imagePath">
                    <small class="text-muted">Upload an image of the crop (optional)</small>
                </div>
                
                <!-- Description Field -->
                <div class="col-md-6">
                    <label for="description" class="form-label">Description:</label>
                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="Brief description of the crop..."></textarea>
                </div>
                
                <!-- Lifecycle Scheduling Section -->
                <div class="col-12">
                    <div class="lifecycle-section">
                        <h5><i class="fas fa-leaf"></i> Crop Lifecycle Scheduling</h5>
                        <p class="text-muted">Input the lifecycle durations to help calculate expected harvest dates</p>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="nursingDuration" class="form-label">Nursing Duration (days):</label>
                                <input type="number" class="form-control" id="nursingDuration" name="nursingDuration" min="0" placeholder="e.g. 21" onchange="updateHarvestDate()">
                            </div>
                            <div class="col-md-4">
                                <label for="growthDuration" class="form-label">Growth Duration (days):</label>
                                <input type="number" class="form-control" id="growthDuration" name="growthDuration" min="0" placeholder="e.g. 60" onchange="updateHarvestDate()">
                            </div>
                            <div class="col-md-4">
                                <label for="plantingDate" class="form-label">Initial Planting Date:</label>
                                <input type="date" class="form-control" id="plantingDate" name="plantingDate" onchange="updateHarvestDate()">
                            </div>
                            
                            <!-- Field Location -->
                            <div class="col-md-4">
                                <label for="fieldLocation" class="form-label">Field Location:</label>
                                <input type="text" class="form-control" id="fieldLocation" name="fieldLocation" placeholder="e.g. Main Field, Section A">
                            </div>
                            
                            <!-- Harvest Frequency -->
                            <div class="col-md-4">
                                <label for="harvestFrequency" class="form-label">Harvest Frequency (days):</label>
                                <input type="number" class="form-control" id="harvestFrequency" name="harvestFrequency" min="1" placeholder="e.g. 7" value="7">
                                <small class="text-muted">Days between harvests for recurring crops</small>
                            </div>
                            
                            <!-- Cycle Notes -->
                            <div class="col-md-4">
                                <label for="cycleNotes" class="form-label">Cycle Notes:</label>
                                <textarea class="form-control" id="cycleNotes" name="cycleNotes" rows="1" placeholder="Specific notes for this planting cycle..."></textarea>
                            </div>
                        </div>
                        
                        <div class="harvest-schedule mt-3" id="harvestDatePreview" style="display: none;">
                            <h6><i class="fas fa-calendar-alt"></i> Expected First Harvest</h6>
                            <p class="mb-0">Based on your inputs, the first harvest is expected on: <strong id="expectedHarvestDate">-</strong></p>
                            <small class="text-muted">Total time from planting to harvest: <span id="totalDays">0</span> days</small>
                        </div>
                    </div>
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
                    <label class="form-label">Suitable Seasons:</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($seasons as $season): ?>
                            <div class="form-check me-3">
                                <input class="form-check-input" type="checkbox" name="seasons[]" value="<?php echo $season['season_id']; ?>" id="season<?php echo $season['season_id']; ?>">
                                <label class="form-check-label" for="season<?php echo $season['season_id']; ?>">
                                    <?php echo htmlspecialchars($season['season_name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label for="daysToMaturity" class="form-label">Days to Maturity:</label>
                    <input type="text" class="form-control" id="daysToMaturity" name="daysToMaturity" placeholder="e.g. 60-90">
                </div>
                
                <div class="col-md-6">
                    <label for="soilRequirements" class="form-label">Soil Requirements:</label>
                    <input type="text" class="form-control" id="soilRequirements" name="soilRequirements" placeholder="e.g. Well-drained, rich in organic matter">
                </div>
                
                <div class="col-md-6">
                    <label for="wateringNeeds" class="form-label">Watering Needs:</label>
                    <input type="text" class="form-control" id="wateringNeeds" name="wateringNeeds" placeholder="e.g. Regular, consistent moisture">
                </div>
                
                <div class="col-md-6">
                    <label for="sunlightRequirements" class="form-label">Sunlight Requirements:</label>
                    <input type="text" class="form-control" id="sunlightRequirements" name="sunlightRequirements" placeholder="e.g. Full sun (6-8 hours daily)">
                </div>
                
                <div class="col-md-6">
                    <label for="spacingRequirements" class="form-label">Spacing Requirements:</label>
                    <input type="text" class="form-control" id="spacingRequirements" name="spacingRequirements" placeholder="e.g. 18-24 inches apart">
                </div>
                
                <!-- Common Issues Field -->
                <div class="col-md-6">
                    <label for="commonIssues" class="form-label">Common Issues:</label>
                    <textarea class="form-control" id="commonIssues" name="commonIssues" rows="2" placeholder="Common pests or diseases for this crop..."></textarea>
                </div>
                
                <!-- Active Status Field -->
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                        <label class="form-check-label" for="isActive">Active Status</label>
                        <div><small class="text-muted">Inactive crops won't appear in main listings</small></div>
                    </div>
                </div>
                
                <!-- Event Section -->
                <div class="col-12 mt-3">
                    <div class="crop-events-section">
                        <h5><i class="fas fa-calendar"></i> Crop Events</h5>
                        <p class="text-muted">Add important events related to this crop</p>
                        
                        <div id="eventContainer">
                            <div class="event-item row g-2">
                                <div class="col-md-3">
                                    <input type="date" class="form-control" name="event_date[]" placeholder="Date">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="event_name[]" placeholder="Event Name">
                                </div>
                                <div class="col-md-5">
                                    <input type="text" class="form-control" name="event_description[]" placeholder="Description">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm event-remove" onclick="removeEvent(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addEvent()">
                                <i class="fas fa-plus"></i> Add Event
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Issues Section -->
                <div class="col-12 mt-3">
                    <div class="crop-issues-section">
                        <h5><i class="fas fa-exclamation-triangle"></i> Known Issues</h5>
                        <p class="text-muted">Add specific issues this crop may face</p>
                        
                        <div id="issueContainer">
                            <div class="issue-item row g-2">
                                <div class="col-md-4">
                                <select class="form-select" name="issue_id[]">
                                        <option value="">Select Issue</option>
                                        <?php foreach ($issues as $issue): ?>
                                            <option value="<?php echo $issue['issue_id']; ?>">
                                                <?php echo htmlspecialchars($issue['issue_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="issue_severity[]">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="issue_notes[]" placeholder="Notes on this issue">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm issue-remove" onclick="removeIssue(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addIssue()">
                                <i class="fas fa-plus"></i> Add Issue
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <label for="cropNotes" class="form-label">Additional Notes:</label>
                    <textarea class="form-control" id="cropNotes" name="cropNotes" rows="3" placeholder="Add any additional information about this crop..."></textarea>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="add_crop" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Add Crop
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for dynamic form elements -->
<script>
    // Update expected harvest date based on planting date and durations
    function updateHarvestDate() {
        const plantingDate = document.getElementById('plantingDate').value;
        const nursingDuration = parseInt(document.getElementById('nursingDuration').value) || 0;
        const growthDuration = parseInt(document.getElementById('growthDuration').value) || 0;
        
        if (plantingDate) {
            const totalDays = nursingDuration + growthDuration;
            document.getElementById('totalDays').textContent = totalDays;
            
            const expectedDate = new Date(plantingDate);
            expectedDate.setDate(expectedDate.getDate() + totalDays);
            
            // Format the date as YYYY-MM-DD
            const formattedDate = expectedDate.toISOString().split('T')[0];
            document.getElementById('expectedHarvestDate').textContent = formattedDate;
            
            // Show the harvest date preview
            document.getElementById('harvestDatePreview').style.display = 'block';
        } else {
            document.getElementById('harvestDatePreview').style.display = 'none';
        }
    }
    
    // Add a new event row
    function addEvent() {
        const container = document.getElementById('eventContainer');
        const newRow = document.createElement('div');
        newRow.className = 'event-item row g-2 mt-2';
        newRow.innerHTML = `
            <div class="col-md-3">
                <input type="date" class="form-control" name="event_date[]" placeholder="Date">
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="event_name[]" placeholder="Event Name">
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="event_description[]" placeholder="Description">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm event-remove" onclick="removeEvent(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        container.appendChild(newRow);
    }
    
    // Remove an event row
    function removeEvent(button) {
        const row = button.closest('.event-item');
        row.remove();
    }
    
    // Add a new issue row
    function addIssue() {
        const container = document.getElementById('issueContainer');
        const newRow = document.createElement('div');
        newRow.className = 'issue-item row g-2 mt-2';
        newRow.innerHTML = `
            <div class="col-md-4">
                <select class="form-select" name="issue_id[]">
                    <option value="">Select Issue</option>
                    <?php foreach ($issues as $issue): ?>
                        <option value="<?php echo $issue['issue_id']; ?>">
                            <?php echo htmlspecialchars($issue['issue_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="issue_severity[]">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" name="issue_notes[]" placeholder="Notes on this issue">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm issue-remove" onclick="removeIssue(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        container.appendChild(newRow);
    }
    
    // Remove an issue row
    function removeIssue(button) {
        const row = button.closest('.issue-item');
        row.remove();
    }
</script>   
        <?php if ($selected_crop): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4><?php echo htmlspecialchars($selected_crop['crop_name']); ?> Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>General Information</h5>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($selected_crop['category_name']); ?></p>
                                <p><strong>Days to Maturity:</strong> <?php echo htmlspecialchars($selected_crop['days_to_maturity']); ?></p>
                                <p><strong>Soil Requirements:</strong> <?php echo htmlspecialchars($selected_crop['soil_requirements']); ?></p>
                                <p><strong>Watering Needs:</strong> <?php echo htmlspecialchars($selected_crop['watering_needs']); ?></p>
                                <p><strong>Sunlight Requirements:</strong> <?php echo htmlspecialchars($selected_crop['sunlight_requirements']); ?></p>
                                <p><strong>Spacing Requirements:</strong> <?php echo htmlspecialchars($selected_crop['spacing_requirements']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Lifecycle Information</h5>
                                <p><strong>Nursing Duration:</strong> <?php echo htmlspecialchars($selected_crop['nursing_duration']); ?> days</p>
                                <p><strong>Growth Duration:</strong> <?php echo htmlspecialchars($selected_crop['growth_duration']); ?> days</p>
                                <p><strong>Total Time to Harvest:</strong> <?php echo ($selected_crop['nursing_duration'] + $selected_crop['growth_duration']); ?> days</p>
                                
                                <h5 class="mt-4">Growth Calendar</h5>
                                <div class="d-flex mb-2">
                                    <?php
                                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                    foreach ($months as $month): ?>
                                        <div class="month flex-fill"><?php echo $month; ?></div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="crop-row">
                                    <?php
                                    // Initialize month blocks
                                    $crop_months = [];
                                    for ($i = 1; $i <= 12; $i++) {
                                        $crop_months[$i] = '';
                                    }
                                    
                                    // Fill in data from calendar_data
                                    foreach ($calendar_data[$selected_crop['crop_id']] as $period) {
                                        $start = intval($period['start_month']);
                                        $end = intval($period['end_month']);
                                        $stage_class = strtolower($period['stage_name']);
                                        
                                        for ($m = $start; $m <= $end; $m++) {
                                            $crop_months[$m] = $stage_class;
                                        }
                                    }
                                    
                                    // Display month blocks
                                    for ($m = 1; $m <= 12; $m++): ?>
                                        <div class="month-block <?php echo $crop_months[$m]; ?>"></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (isset($crop_cycles) && !empty($crop_cycles)): ?>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>Active Crop Cycles</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Planting Date</th>
                                                <th>Expected First Harvest</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($crop_cycles as $cycle): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($cycle['start_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($cycle['expected_first_harvest'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $cycle['status'] == 'In Progress' ? 'bg-success' : ($cycle['status'] == 'Planned' ? 'bg-primary' : 'bg-secondary'); ?>">
                                                        <?php echo $cycle['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="crop_lifecycle.php?cycle_id=<?php echo $cycle['cycle_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="crop_lifecycle.php?crop_id=<?php echo $selected_crop['crop_id']; ?>" class="btn btn-outline-success">
                                <i class="fas fa-seedling"></i> Plan New Crop Cycle
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateHarvestDate() {
            const nursingDuration = parseInt(document.getElementById('nursingDuration').value) || 0;
            const growthDuration = parseInt(document.getElementById('growthDuration').value) || 0;
            const plantingDate = document.getElementById('plantingDate').value;
            
            if (nursingDuration > 0 && growthDuration > 0 && plantingDate) {
                const totalDays = nursingDuration + growthDuration;
                document.getElementById('totalDays').textContent = totalDays;
                
                const harvestDate = new Date(plantingDate);
                harvestDate.setDate(harvestDate.getDate() + totalDays);
                
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                document.getElementById('expectedHarvestDate').textContent = harvestDate.toLocaleDateString('en-US', options);
                document.getElementById('harvestDatePreview').style.display = 'block';
            } else {
                document.getElementById('harvestDatePreview').style.display = 'none';
            }
        }
        
        // Auto-populate growing and harvesting periods based on planting period
        document.getElementById('plantingStart').addEventListener('change', function() {
            const plantingStart = parseInt(this.value) || 0;
            if (plantingStart > 0) {
                // Set growing period to start after planting
                let growingStart = plantingStart + 1;
                if (growingStart > 12) growingStart = 1;
                document.getElementById('growingStart').value = growingStart;
                
                // Set harvesting to start after growing (approximately)
                let harvestingStart = growingStart + 2;
                if (harvestingStart > 12) harvestingStart = harvestingStart - 12;
                document.getElementById('harvestingStart').value = harvestingStart;
            }
        });
        
        document.getElementById('plantingEnd').addEventListener('change', function() {
            const plantingEnd = parseInt(this.value) || 0;
            if (plantingEnd > 0) {
                // Set growing period to end after planting end
                let growingEnd = plantingEnd + 2;
                if (growingEnd > 12) growingEnd = growingEnd - 12;
                document.getElementById('growingEnd').value = growingEnd;
                
                // Set harvesting to end after growing end (approximately)
                let harvestingEnd = growingEnd + 1;
                if (harvestingEnd > 12) harvestingEnd = harvestingEnd - 12;
                document.getElementById('harvestingEnd').value = harvestingEnd;
            }
        });
        
        // Initialize form with current date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const formattedDate = today.toISOString().substr(0, 10);
            document.getElementById('plantingDate').value = formattedDate;
            updateHarvestDate();
        });
    </script>
</body>
</html>