<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';



// Get crops with categories
$crop_sql = "SELECT c.crop_id, c.crop_name, cc.category_name, c.days_to_maturity, 
             c.sunlight_requirements, c.watering_needs 
             FROM crops c
             JOIN crop_categories cc ON c.category_id = cc.category_id
             WHERE c.is_active = 1
             ORDER BY cc.category_name, c.crop_name";
$crop_result = $conn->query($crop_sql);

// Get seasonal data
$season_sql = "SELECT s.season_name, COUNT(cs.crop_id) as crop_count
               FROM seasons s
               LEFT JOIN crop_seasons cs ON s.season_id = cs.season_id
               GROUP BY s.season_name
               ORDER BY 
                  CASE 
                     WHEN s.season_name = 'Spring' THEN 1
                     WHEN s.season_name = 'Summer' THEN 2
                     WHEN s.season_name = 'Fall' THEN 3
                     WHEN s.season_name = 'Winter' THEN 4
                  END";
$season_result = $conn->query($season_sql);

// Get common issues
$issues_sql = "SELECT ci.issue_name, ci.issue_type, COUNT(cri.crop_id) as affected_crops,
               GROUP_CONCAT(c.crop_name SEPARATOR ', ') as crops
               FROM common_issues ci
               LEFT JOIN crop_issues cri ON ci.issue_id = cri.issue_id
               LEFT JOIN crops c ON cri.crop_id = c.crop_id
               GROUP BY ci.issue_id
               ORDER BY affected_crops DESC";
$issues_result = $conn->query($issues_sql);


// Include header
include 'views/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jubert Farms Planner Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4 text-center">Jubert Farms  Planner Dashboard</h1>
        
        <!-- Quick Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Total Crops</h5>
                        <p class="card-text fs-2">
                            <?php
                            $count_sql = "SELECT COUNT(*) as total FROM crops WHERE is_active = 1";
                            $count_result = $conn->query($count_sql);
                            $count_row = $count_result->fetch_assoc();
                            echo $count_row['total'];
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Categories</h5>
                        <p class="card-text fs-2">
                            <?php
                            $cat_sql = "SELECT COUNT(*) as total FROM crop_categories";
                            $cat_result = $conn->query($cat_sql);
                            $cat_row = $cat_result->fetch_assoc();
                            echo $cat_row['total'];
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Current Season</h5>
                        <p class="card-text fs-2">
                            <?php
                            $month = date('n');
                            if ($month >= 3 && $month <= 5) echo "Spring";
                            else if ($month >= 6 && $month <= 8) echo "Summer";
                            else if ($month >= 9 && $month <= 11) echo "Fall";
                            else echo "Winter";
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Common Issues</h5>
                        <p class="card-text fs-2">
                            <?php
                            $issue_count_sql = "SELECT COUNT(*) as total FROM common_issues";
                            $issue_count_result = $conn->query($issue_count_sql);
                            $issue_count_row = $issue_count_result->fetch_assoc();
                            echo $issue_count_row['total'];
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Crops Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h2>Crop Inventory</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <th>Category</th>
                                <th>Days to Maturity</th>
                                <th>Sunlight</th>
                                <th>Water Needs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($crop_row = $crop_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($crop_row['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($crop_row['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($crop_row['days_to_maturity']); ?></td>
                                <td><?php echo htmlspecialchars($crop_row['sunlight_requirements']); ?></td>
                                <td><?php echo htmlspecialchars($crop_row['watering_needs']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Seasonal Analysis -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h2>Seasonal Crop Distribution</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Season</th>
                                        <th>Number of Crops</th>
                                        <th>Visualization</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($season_row = $season_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($season_row['season_name']); ?></td>
                                        <td><?php echo $season_row['crop_count']; ?></td>
                                        <td>
                                            <div class="progress">
                                                <?php 
                                                $width = ($season_row['crop_count'] > 0) ? 
                                                    min(100, $season_row['crop_count'] * 10) : 0;
                                                $color = "";
                                                switch($season_row['season_name']) {
                                                    case "Spring": $color = "bg-success"; break;
                                                    case "Summer": $color = "bg-danger"; break;
                                                    case "Fall": $color = "bg-warning"; break;
                                                    case "Winter": $color = "bg-info"; break;
                                                }
                                                ?>
                                                <div class="progress-bar <?php echo $color; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $width; ?>%" 
                                                     aria-valuenow="<?php echo $season_row['crop_count']; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="10">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Common Issues -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h2>Common Issues</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Issue</th>
                                        <th>Type</th>
                                        <th>Affected Crops</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($issue_row = $issues_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($issue_row['issue_name']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                            switch($issue_row['issue_type']) {
                                                case "Pest": echo "bg-danger"; break;
                                                case "Disease": echo "bg-warning"; break;
                                                case "Environmental": echo "bg-info"; break;
                                                default: echo "bg-secondary"; break;
                                            }
                                            ?>">
                                                <?php echo htmlspecialchars($issue_row['issue_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($issue_row['crops']); ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Monthly Planting Calendar -->
        <div class="card mb-4">
            <div class="card-header">
                <h2>Monthly Planting Calendar</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                <th><?php echo date('M', mktime(0, 0, 0, $i, 1)); ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $calendar_sql = "SELECT c.crop_name, cc.start_month, cc.end_month, gs.stage_name, gs.color_code 
                                           FROM crops c
                                           JOIN crop_calendar cc ON c.crop_id = cc.crop_id
                                           JOIN growth_stages gs ON cc.stage_id = gs.stage_id
                                           ORDER BY c.crop_name, cc.start_month";
                            $calendar_result = $conn->query($calendar_sql);
                            
                            $current_crop = "";
                            $months = [];
                            
                            while($cal_row = $calendar_result->fetch_assoc()) {
                                if($current_crop != $cal_row['crop_name']) {
                                    // Output previous crop data if it exists
                                    if($current_crop != "") {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($current_crop) . "</td>";
                                        for($i = 1; $i <= 12; $i++) {
                                            if(isset($months[$i])) {
                                                echo "<td style='background-color:" . $months[$i]['color'] . "'>";
                                                echo $months[$i]['stage'];
                                                echo "</td>";
                                            } else {
                                                echo "<td></td>";
                                            }
                                        }
                                        echo "</tr>";
                                    }
                                    
                                    // Reset for new crop
                                    $current_crop = $cal_row['crop_name'];
                                    $months = [];
                                }
                                
                                // Fill in the months for current crop/stage
                                for($i = $cal_row['start_month']; $i <= $cal_row['end_month']; $i++) {
                                    $months[$i] = [
                                        'stage' => $cal_row['stage_name'],
                                        'color' => $cal_row['color_code']
                                    ];
                                }
                            }
                            
                            // Output the last crop
                            if($current_crop != "") {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($current_crop) . "</td>";
                                for($i = 1; $i <= 12; $i++) {
                                    if(isset($months[$i])) {
                                        echo "<td style='background-color:" . $months[$i]['color'] . "'>";
                                        echo $months[$i]['stage'];
                                        echo "</td>";
                                    } else {
                                        echo "<td></td>";
                                    }
                                }
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Close connection
$conn->close();
?>