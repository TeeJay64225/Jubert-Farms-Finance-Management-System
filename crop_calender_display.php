<?php session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';
require_once 'views/header.php';
require_once 'crop/crop_functions.php';


function getHarvestYieldSummary($db, $startDate = null, $endDate = null, $cropId = null) {
    // Base query
    $sql = "SELECT 
                c.crop_name,
                SUM(hr.quantity) as total_yield,
                hr.unit,
                COUNT(DISTINCT hr.harvest_id) as harvest_count,
                MIN(hr.harvest_date) as first_harvest,
                MAX(hr.harvest_date) as last_harvest
            FROM harvest_records hr
            JOIN crop_cycles cc ON hr.cycle_id = cc.cycle_id
            JOIN crops c ON cc.crop_id = c.crop_id
            WHERE 1=1";
    
    // Add optional filters
    $params = [];
    $types = "";
    
    if ($cropId) {
        $sql .= " AND c.crop_id = ?";
        $params[] = $cropId;
        $types .= "i"; // integer parameter
    }
    
    if ($startDate) {
        $sql .= " AND hr.harvest_date >= ?";
        $params[] = $startDate;
        $types .= "s"; // string parameter
    }
    
    if ($endDate) {
        $sql .= " AND hr.harvest_date <= ?";
        $params[] = $endDate;
        $types .= "s"; // string parameter
    }
    
    // Group and order
    $sql .= " GROUP BY c.crop_id, hr.unit
              ORDER BY c.crop_name";
    
    // Prepare and execute the statement
    $stmt = $db->prepare($sql);
    
    if (!empty($params)) {
        // Dynamically bind parameters
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    // Get results
    $result = $stmt->get_result();
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $stmt->close();
    
    // Return the results
    return $data;
}


// Default to current month/year if not specified
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$view = isset($_GET['view']) ? $_GET['view'] : 'month';

// Build and populate the calendar
$calendar_data = buildCalendarMonth($year, $month);
$calendar_grid = $calendar_data['grid']; // Use this where you loop days

$calendar_grid = populateCalendarWithEvents($conn, $calendar_grid, $year, $month);
$calendar_data['grid'] = $calendar_grid;

// Get upcoming events for the sidebar
$upcoming_events = getUpcomingEvents($conn, 14); // Show next 14 days

// Get crops needing attention
$crops_needing_attention = getCropsNeedingAttention($conn);

// Handle export to iCal if requested
if (isset($_GET['export']) && $_GET['export'] === 'ical') {
    $start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
    $end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
    
    $ical_content = exportCalendarToICal($conn, $start_date, $end_date);
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="farm_calendar.ics"');
    echo $ical_content;
    exit;
}

// Get current date for highlighting today
$today = date('Y-m-d');
$current_day = date('j');
$current_month = date('n');
$current_year = date('Y');

// Get previous and next month for navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// For daily view if needed
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$daily_events = [];
if ($view === 'day') {
    $daily_events = getEventsForDate($conn, $selected_date);
}

// Get seasonal planting reminders
$seasonal_reminders = getSeasonalPlantingReminders($conn);

// Get harvest yield summary for current year
// Get harvest yield summary for current year
$start_of_year = date('Y-01-01');
$end_of_year = date('Y-12-31');
$harvest_summary = getHarvestYieldSummary($conn, $start_of_year, $end_of_year);

// Header
$page_title = "Farm Calendar";

?>

<div class="container-fluid my-4">
    <div class="row">
        <!-- Main Calendar Area -->
        <div class="col-lg-9">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                    <h4 class="mb-0"><?php echo $calendar_data['month_name'] . ' ' . $year; ?></h4>

                    </div>
                    <div class="btn-group">
                        <a href="?year=<?php echo $prev_year; ?>&month=<?php echo $prev_month; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('n'); ?>" class="btn btn-outline-secondary">Today</a>
                        <a href="?year=<?php echo $next_year; ?>&month=<?php echo $next_month; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="calendarActions" data-bs-toggle="dropdown" aria-expanded="false">
                            Actions
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="calendarActions">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addTaskModal">Add Task</a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addHarvestModal">Add Harvest</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?export=ical&start=<?php echo $year.'-'.$month.'-01'; ?>&end=<?php echo date('Y-m-t', strtotime($year.'-'.$month.'-01')); ?>">Export Month (iCal)</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Calendar View -->
                    <div class="table-responsive">
                        <table class="table table-bordered calendar-table mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">Mon</th>
                                    <th class="text-center">Tue</th>
                                    <th class="text-center">Wed</th>
                                    <th class="text-center">Thu</th>
                                    <th class="text-center">Fri</th>
                                    <th class="text-center">Sat</th>
                                    <th class="text-center">Sun</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($calendar_data['grid'] as $week): ?>
                                <tr style="height: 120px;">
                                <?php foreach ($week as $day_idx => $day): ?>
<?php 
    if (!is_array($day)) {
        $day = ['day' => 0, 'events' => []]; // fallback to avoid null access
    }

    $is_today = ($day['day'] == $current_day && $month == $current_month && $year == $current_year);
    $day_class = $is_today ? 'today bg-light' : '';
    $day_class .= $day['day'] == 0 ? ' text-muted' : '';
?>

                                    <td class="<?php echo $day_class; ?> calendar-day">
                                        <?php if ($day['day'] > 0): ?>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="day-number"><?php echo $day['day']; ?></span>
                                            <?php if (count($day['events']) > 0): ?>
                                            <a href="?view=day&date=<?php echo sprintf('%04d-%02d-%02d', $year, $month, $day['day']); ?>" 
                                               class="btn btn-sm btn-outline-primary day-view-btn">
                                                <i class="fas fa-search"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="day-events">
                                            <?php 
                                            $event_counter = 0;
                                            foreach ($day['events'] as $event): 
                                                $event_counter++;
                                                if ($event_counter <= 3): // Show only first 3 events
                                            ?>
                                            <div class="calendar-event" style="background-color: <?php echo $event['color']; ?>;" 
                                                data-event-id="<?php echo $event['id']; ?>"
                                                data-bs-toggle="tooltip" 
                                                title="<?php echo htmlspecialchars($event['title']); ?>: <?php echo $event['crop']; ?> at <?php echo $event['location']; ?>">
                                                <i class="fas fa-<?php echo $event['icon']; ?>"></i>
                                                <?php echo htmlspecialchars(substr($event['title'], 0, 20) . (strlen($event['title']) > 20 ? '...' : '')); ?>
                                            </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            
                                            // If there are more events than shown
                                            if (count($day['events']) > 3):
                                            ?>
                                            <div class="more-events text-center">
                                                <small>+<?php echo (count($day['events']) - 3); ?> more</small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($view === 'day'): ?>
            <!-- Daily View -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Events for <?php echo date('F j, Y', strtotime($selected_date)); ?></h5>
                    <a href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="btn btn-sm btn-outline-secondary">
                        Back to Month View
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($daily_events)): ?>
                    <p class="text-center text-muted">No events scheduled for this day.</p>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($daily_events as $event): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">
                                    <span class="badge" style="background-color: <?php echo $event['color']; ?>;">
                                        <i class="fas fa-<?php echo $event['icon']; ?>"></i>
                                    </span>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </h5>
                                <div>
                                    <?php if ($event['type'] === 'task'): ?>
                                    <button class="btn btn-sm btn-success toggle-completion" data-task-id="<?php echo substr($event['id'], 5); ?>" data-status="<?php echo $event['completed']; ?>">
                                        <?php echo $event['completed'] ? 'Completed' : 'Mark Complete'; ?>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary edit-event" data-event-id="<?php echo $event['id']; ?>" data-event-type="<?php echo $event['type']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="mb-1"><strong>Crop:</strong> <?php echo $event['crop']; ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo $event['location']; ?></p>
                            
                            <?php if ($event['type'] === 'task'): ?>
                            <p class="mb-1"><strong>Task Type:</strong> <?php echo $event['task_type']; ?></p>
                            <?php elseif ($event['type'] === 'harvest'): ?>
                            <p class="mb-1"><strong>Quantity:</strong> <?php echo $event['quantity'] . ' ' . $event['unit']; ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($event['notes'])): ?>
                            <p class="mb-0"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($event['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Upcoming Events -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Upcoming Events</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($upcoming_events)): ?>
                        <li class="list-group-item text-center text-muted">No upcoming events</li>
                        <?php else: ?>
                        <?php 
                        $current_date = null;
                        foreach ($upcoming_events as $event): 
                            // Group by date
                            $event_date = date('Y-m-d', strtotime($event['date']));
                            if ($event_date !== $current_date):
                                $current_date = $event_date;
                        ?>
                        <li class="list-group-item bg-light">
                            <strong><?php echo date('l, F j', strtotime($event_date)); ?></strong>
                        </li>
                        <?php endif; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge rounded-pill" style="background-color: <?php echo $event['color']; ?>;">
                                    <i class="fas fa-<?php echo $event['icon']; ?>"></i>
                                </span>
                                <?php echo htmlspecialchars($event['title']); ?>
                                <div class="small text-muted"><?php echo $event['crop']; ?></div>
                            </div>
                            <?php if ($event['type'] === 'task'): ?>
                            <span class="badge bg-<?php echo $event['completed'] ? 'success' : 'warning'; ?> rounded-pill">
                                <?php echo $event['completed'] ? 'Done' : 'Todo'; ?>
                            </span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Seasonal Reminders -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Seasonal Reminders</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($seasonal_reminders['planting'])): ?>
                    <h6 class="card-subtitle mb-2">Time to Plant</h6>
                    <ul class="list-unstyled mb-3">
                        <?php foreach ($seasonal_reminders['planting'] as $crop): ?>
                        <li><i class="fas fa-seedling text-success"></i> <?php echo $crop['crop_name']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if (!empty($seasonal_reminders['harvesting'])): ?>
                    <h6 class="card-subtitle mb-2">Ready for Harvest</h6>
                    <ul class="list-unstyled">
                        <?php foreach ($seasonal_reminders['harvesting'] as $crop): ?>
                        <li><i class="fas fa-carrot text-warning"></i> <?php echo $crop['crop_name']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attention Needed -->
            <?php if (!empty($crops_needing_attention)): ?>
            <div class="card shadow-sm mb-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Attention Needed</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($crops_needing_attention as $crop): ?>
                        <li class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo $crop['crop_name']; ?></h6>
                                <span class="badge bg-danger"><?php echo $crop['days_overdue']; ?> days overdue</span>
                            </div>
                            <p class="mb-1"><?php echo $crop['task_name']; ?></p>
                            <small class="text-muted"><?php echo $crop['field_or_location']; ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addTaskForm" action="../processing/save_task.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="task_name" class="form-label">Task Name</label>
                        <input type="text" class="form-control" id="task_name" name="task_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="cycle_id" class="form-label">Crop Cycle</label>
                        <select class="form-select" id="cycle_id" name="cycle_id" required>
                            <option value="">Select Crop Cycle</option>
                            <?php
                            // Get active crop cycles
                            $cycles_query = "SELECT cc.cycle_id, c.crop_name, cc.field_or_location 
                                           FROM crop_cycles cc 
                                           JOIN crops c ON cc.crop_id = c.crop_id 
                                           WHERE cc.status = 'Active'
                                           ORDER BY c.crop_name";
                            $cycles_result = $conn->query($cycles_query);
                            
                            while ($cycle = $cycles_result->fetch_assoc()) {
                                echo "<option value='{$cycle['cycle_id']}'>{$cycle['crop_name']} - {$cycle['field_or_location']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="task_type_id" class="form-label">Task Type</label>
                        <select class="form-select" id="task_type_id" name="task_type_id" required>
                            <option value="">Select Task Type</option>
                            <?php
                            // Get task types
                            $types_query = "SELECT task_type_id, type_name FROM task_types ORDER BY type_name";
                            $types_result = $conn->query($types_query);
                            
                            while ($type = $types_result->fetch_assoc()) {
                                echo "<option value='{$type['task_type_id']}'>{$type['type_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="scheduled_date" class="form-label">Scheduled Date</label>
                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" 
                               value="<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_recurring" name="is_recurring">
                        <label class="form-check-label" for="is_recurring">Make this a recurring task</label>
                    </div>
                    <div id="recurring_options" style="display: none;">
                        <div class="row mb-3">
                            <div class="col">
                                <label for="frequency" class="form-label">Repeat every</label>
                                <input type="number" class="form-control" id="frequency" name="frequency" min="1" value="1">
                            </div>
                            <div class="col">
                                <label for="frequency_unit" class="form-label">Unit</label>
                                <select class="form-select" id="frequency_unit" name="frequency_unit">
                                    <option value="day">Day(s)</option>
                                    <option value="week" selected>Week(s)</option>
                                    <option value="month">Month(s)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Harvest Modal -->
<div class="modal fade" id="addHarvestModal" tabindex="-1" aria-labelledby="addHarvestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addHarvestModalLabel">Record Harvest</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addHarvestForm" action="../processing/save_harvest.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="harvest_cycle_id" class="form-label">Crop Cycle</label>
                        <select class="form-select" id="harvest_cycle_id" name="cycle_id" required>
                            <option value="">Select Crop Cycle</option>
                            <?php
                            // Reuse the same query for crop cycles
                            $cycles_result = $conn->query($cycles_query);
                            
                            while ($cycle = $cycles_result->fetch_assoc()) {
                                echo "<option value='{$cycle['cycle_id']}'>{$cycle['crop_name']} - {$cycle['field_or_location']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="harvest_date" class="form-label">Harvest Date</label>
                        <input type="date" class="form-control" id="harvest_date" name="harvest_date" 
                               value="<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); ?>" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" required>
                        </div>
                        <div class="col">
                            <label for="unit" class="form-label">Unit</label>
                            <select class="form-select" id="unit" name="unit" required>
                                <option value="kg">Kilograms (kg)</option>
                                <option value="lb">Pounds (lb)</option>
                                <option value="bunches">Bunches</option>
                                <option value="pieces">Pieces</option>
                                <option value="boxes">Boxes</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="harvest_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="harvest_notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="plan_future_harvests" name="plan_future_harvests">
                        <label class="form-check-label" for="plan_future_harvests">Plan future harvests</label>
                    </div>
                    <div id="future_harvest_options" style="display: none;">
                        <div class="row mb-3">
                            <div class="col">
                                <label for="harvest_frequency" class="form-label">Harvest every</label>
                                <input type="number" class="form-control" id="harvest_frequency" name="harvest_frequency" min="1" value="7">
                            </div>
                            <div class="col">
                                <label for="harvests_count" class="form-label">Number of harvests</label>
                                <input type="number" class="form-control" id="harvests_count" name="harvests_count" min="1" max="20" value="5">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Harvest</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal (placeholder - to be filled by JavaScript) -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Content will be filled dynamically -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle recurring task options
    document.getElementById('is_recurring').addEventListener('change', function() {
        document.getElementById('recurring_options').style.display = this.checked ? 'block' : 'none';
    });
    
    // Toggle future harvest options
    document.getElementById('plan_future_harvests').addEventListener('change', function() {
        document.getElementById('future_harvest_options').style.display = this.checked ? 'block' : 'none';
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Task completion toggle
    document.querySelectorAll('.toggle-completion').forEach(function(button) {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            const currentStatus = parseInt(this.getAttribute('data-status'));
            const newStatus = currentStatus ? 0 : 1;
            
            // Send AJAX request to update task status
            fetch('../processing/update_task_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'task_id=' + taskId + '&status=' + newStatus
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button text and data attribute
                    this.textContent = newStatus ? 'Completed' : 'Mark Complete';
                    this.setAttribute('data-status', newStatus);
                    
                    // Update button class
                    if (newStatus) {
                        this.classList.remove('btn-outline-success');
                        this.classList.add('btn-success');
                    } else {
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-success');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update task status.');
            });
        });
    });
    
    // Edit event handler
    document.querySelectorAll('.edit-event').forEach(function(button) {
        button.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            const eventType = this.getAttribute('data-event-type');
            
            // Fetch event details
            fetch('../processing/get_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'event_id=' + eventId + '&event_type=' + eventType
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate and show the edit modal
                    const modalContent = document.querySelector('#editEventModal .modal-content');
                    modalContent.innerHTML = data.html;
                    
                    // Initialize the modal
                    const editModal = new bootstrap.Modal(document.getElementById('editEventModal'));
                    editModal.show();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load event details.');
            });
        });
    });
    
   // Calendar event click handler
document.querySelectorAll('.calendar-event').forEach(function(eventDiv) {
    eventDiv.addEventListener('click', function() {
        const eventId = this.getAttribute('data-event-id');
        const parts = eventId.split('_');
        const eventType = parts[0]; // 'task' or 'harvest'
        const id = parts[1]; // actual ID
        
        // Fetch event details
        fetch('../processing/get_event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'event_id=' + eventId + '&event_type=' + eventType
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate and show the edit modal
                const modalContent = document.querySelector('#editEventModal .modal-content');
                modalContent.innerHTML = data.html;
                
                // Initialize the modal
                const editModal = new bootstrap.Modal(document.getElementById('editEventModal'));
                editModal.show();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load event details.');
        });
        
        // Prevent event bubbling to parent day cell
        event.stopPropagation();
    });
});

// Day cell click handler for easy task/event adding
document.querySelectorAll('.calendar-day').forEach(function(dayCell) {
    dayCell.addEventListener('click', function(event) {
        // Only proceed if the click was directly on the day cell (not on an event)
        if (event.target === this || event.target.classList.contains('day-number') || 
            event.target.parentElement === this) {
            
            // Get the date from the day cell
            const day = this.querySelector('.day-number');
            if (day) {
                const dayNum = parseInt(day.textContent);
                if (dayNum > 0) {
                    // Format the date for the modal
                    const selectedDate = `${year}-${String(month).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
                    
                    // Set the date in the add task modal
                    document.getElementById('scheduled_date').value = selectedDate;
                    document.getElementById('harvest_date').value = selectedDate;
                    
                    // Show the add task modal
                    const addTaskModal = new bootstrap.Modal(document.getElementById('addTaskModal'));
                    addTaskModal.show();
                }
            }
        }
    });
});

// Form submission handlers with validation
document.getElementById('addTaskForm').addEventListener('submit', function(event) {
    // Basic form validation
    const taskName = document.getElementById('task_name').value.trim();
    const cycleId = document.getElementById('cycle_id').value;
    const taskTypeId = document.getElementById('task_type_id').value;
    const scheduledDate = document.getElementById('scheduled_date').value;
    
    if (!taskName || !cycleId || !taskTypeId || !scheduledDate) {
        event.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    // Additional validation for recurring tasks
    if (document.getElementById('is_recurring').checked) {
        const frequency = parseInt(document.getElementById('frequency').value);
        const endDate = document.getElementById('end_date').value;
        
        if (frequency < 1 || !endDate) {
            event.preventDefault();
            alert('Please provide valid recurring task details');
            return false;
        }
        
        // Check that end date is after start date
        if (new Date(endDate) <= new Date(scheduledDate)) {
            event.preventDefault();
            alert('End date must be after the start date');
            return false;
        }
    }
    
    return true;
});

document.getElementById('addHarvestForm').addEventListener('submit', function(event) {
    // Basic form validation
    const cycleId = document.getElementById('harvest_cycle_id').value;
    const harvestDate = document.getElementById('harvest_date').value;
    const quantity = parseFloat(document.getElementById('quantity').value);
    
    if (!cycleId || !harvestDate || isNaN(quantity) || quantity <= 0) {
        event.preventDefault();
        alert('Please fill in all required fields with valid values');
        return false;
    }
    
    // Additional validation for future harvests
    if (document.getElementById('plan_future_harvests').checked) {
        const harvestFrequency = parseInt(document.getElementById('harvest_frequency').value);
        const harvestsCount = parseInt(document.getElementById('harvests_count').value);
        
        if (harvestFrequency < 1 || harvestsCount < 1) {
            event.preventDefault();
            alert('Please provide valid future harvest details');
            return false;
        }
    }
    
    return true;
});

// Dynamically add event handlers to edit forms when they are created
document.getElementById('editEventModal').addEventListener('shown.bs.modal', function() {
    const form = this.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            // Basic validation - make sure required fields have values
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            return true;
        });
    }
});
});
</script>

