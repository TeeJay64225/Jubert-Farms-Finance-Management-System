<?php
// farm_calendar.php - Frontend for farm calendar management
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config/db.php';
include 'crop/calendar_functions.php';




// === AJAX HANDLERS - MUST COME BEFORE ANY HTML OUTPUT ===
// Handle AJAX requests for day details
if (isset($_GET['ajax'])) {
    // Turn off any output buffering
    while (ob_get_level()) ob_end_clean();
    
    if ($_GET['ajax'] === 'day_details') {
        // Set the content type header
        header('Content-Type: application/json');
        
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            $events = getEventsForDate($conn, $date);
            
            // Log the data we're about to return
            error_log("Events for date $date: " . json_encode($events));
            
            // Return the JSON response
            echo json_encode($events ?: []);
        } catch (Exception $e) {
            // Return error response
            echo json_encode([
                'error' => true,
                'message' => "Server error: " . $e->getMessage()
            ]);
            
            // Log the error
            error_log("AJAX day_details error: " . $e->getMessage());
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'reschedule_event') {
        header('Content-Type: application/json');
        
        $event_id = $_POST['event_id'] ?? '';
        $new_date = $_POST['new_date'] ?? '';
        $event_type = $_POST['event_type'] ?? 'task';
        
        $success = false;
        $message = 'Invalid request';
        
        if (!empty($event_id) && !empty($new_date)) {
            if ($event_type === 'task') {
                $task_id = str_replace('task_', '', $event_id);
                $stmt = $conn->prepare("UPDATE farm_tasks SET scheduled_date = ? WHERE task_id = ?");
                $stmt->bind_param("si", $new_date, $task_id);
                $success = $stmt->execute();
                $message = $success ? 'Task rescheduled successfully' : 'Failed to reschedule task';
            } else if ($event_type === 'harvest') {
                $harvest_id = str_replace('harvest_', '', $event_id);
                $stmt = $conn->prepare("UPDATE harvest_records SET harvest_date = ? WHERE harvest_id = ?");
                $stmt->bind_param("si", $new_date, $harvest_id);
                $success = $stmt->execute();
                $message = $success ? 'Harvest rescheduled successfully' : 'Failed to reschedule harvest';
            }
        }
        
        echo json_encode(['success' => $success, 'message' => $message]);
        exit();
    }
    
    if ($_GET['ajax'] === 'test') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'message' => 'AJAX is working']);
        exit();
    }
}

// Handle iCal export if requested
if (isset($_GET['export']) && $_GET['export'] === 'ical') {
    $start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
    $end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
    
    $ical_content = exportCalendarToICal($conn, $start_date, $end_date);
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="farm_calendar.ics"');
    echo $ical_content;
    exit();
}

// === AFTER THIS POINT, INCLUDE FILES THAT OUTPUT HTML ===
require_once 'views/header.php';

// Continue with the rest of your code...


// Get current view type (month, week, or day)
$view_type = isset($_GET['view']) ? $_GET['view'] : 'month';

// Get current year, month, and day based on request or current date
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$day = isset($_GET['day']) ? intval($_GET['day']) : intval(date('d'));

// Calculate date range based on view type
$start_date = '';
$end_date = '';

switch ($view_type) {
    case 'day':
        $start_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $end_date = $start_date;
        break;
        
    case 'week':
        // Get the first day of the week containing the given date
        $date = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        $day_of_week = $date->format('N'); // 1 (Monday) through 7 (Sunday)
        $days_to_subtract = $day_of_week - 1;
        $date->modify("-$days_to_subtract days");
        
        $start_date = $date->format('Y-m-d');
        $date->modify('+6 days');
        $end_date = $date->format('Y-m-d');
        
        // Extract year, month from start date for navigation
        $year = intval($date->format('Y'));
        $month = intval($date->format('m'));
        break;
        
    case 'month':
    default:
        $start_date = sprintf('%04d-%02d-%01d', $year, $month, 1);
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
}

// Get navigation info based on view type
$nav_info = [];
switch ($view_type) {
    case 'day':
        $prev_date = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        $prev_date->modify('-1 day');
        $next_date = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        $next_date->modify('+1 day');
        
        $nav_info = [
            'prev_year' => $prev_date->format('Y'),
            'prev_month' => $prev_date->format('m'),
            'prev_day' => $prev_date->format('d'),
            'next_year' => $next_date->format('Y'),
            'next_month' => $next_date->format('m'),
            'next_day' => $next_date->format('d'),
            'title' => date('F j, Y', strtotime($start_date))
        ];
        break;
        
    case 'week':
        $prev_date = new DateTime($start_date);
        $prev_date->modify('-7 days');
        $next_date = new DateTime($start_date);
        $next_date->modify('+7 days');
        
        $week_end = new DateTime($end_date);
        $nav_info = [
            'prev_year' => $prev_date->format('Y'),
            'prev_month' => $prev_date->format('m'),
            'prev_day' => $prev_date->format('d'),
            'next_year' => $next_date->format('Y'),
            'next_month' => $next_date->format('m'),
            'next_day' => $next_date->format('d'),
            'title' => date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))
        ];
        break;
        
    case 'month':
    default:
        $next_month = $month == 12 ? 1 : $month + 1;
        $next_year = $month == 12 ? $year + 1 : $year;
        $prev_month = $month == 1 ? 12 : $month - 1;
        $prev_year = $month == 1 ? $year - 1 : $year;
        
        $nav_info = [
            'prev_year' => $prev_year,
            'prev_month' => $prev_month,
            'prev_day' => $day,
            'next_year' => $next_year,
            'next_month' => $next_month,
            'next_day' => $day,
            'title' => date('F Y', strtotime($start_date))
        ];
        break;
}

// Build and populate calendar data based on view type
$calendar_data = [];
if ($view_type == 'month') {
    $calendar = buildCalendarMonth($year, $month);
    $calendar_data = populateCalendarWithEvents($conn, $calendar, $year, $month);
} else {
    // For week and day views, we'll fetch events directly
    $events = getTasksByDateRange($conn, $start_date, $end_date);
    $harvests = getHarvestsByDateRange($conn, $start_date, $end_date);
    
    // Structure data by date for the template
    $dates = [];
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    while ($current <= $end) {
        $date_key = $current->format('Y-m-d');
        $dates[$date_key] = [
            'date' => $date_key,
            'day' => $current->format('j'),
            'weekday' => $current->format('l'),
            'events' => []
        ];
        $current->modify('+1 day');
    }
    
    // Add events to dates
    foreach ($events as $task) {
        $date_key = $task['scheduled_date'];
        if (isset($dates[$date_key])) {
            $dates[$date_key]['events'][] = [
                'id' => 'task_' . $task['task_id'],
                'title' => $task['task_name'],
                'start' => $task['scheduled_date'],
                'color' => $task['color_code'],
                'type' => 'task',
                'icon' => $task['icon'],
                'crop' => $task['crop_name'],
                'location' => $task['field_or_location'],
                'completed' => $task['completion_status'],
                'task_type' => $task['type_name'],
                'notes' => $task['notes']
            ];
        }
    }
    
    foreach ($harvests as $harvest) {
        $date_key = $harvest['harvest_date'];
        if (isset($dates[$date_key])) {
            $dates[$date_key]['events'][] = [
                'id' => 'harvest_' . $harvest['harvest_id'],
                'title' => 'Harvest: ' . $harvest['crop_name'],
                'start' => $harvest['harvest_date'],
                'color' => '#ff9800',
                'type' => 'harvest',
                'icon' => 'harvest',
                'crop' => $harvest['crop_name'],
                'location' => $harvest['field_or_location'],
                'quantity' => $harvest['quantity'],
                'unit' => $harvest['unit'],
                'notes' => $harvest['notes']
            ];
        }
    }
    
    $calendar_data = ['dates' => $dates];
}

// Get upcoming events for the sidebar
$upcoming_events = getUpcomingEvents($conn, 14); // Next 14 days

// Get crops needing attention
$crops_needing_attention = getCropsNeedingAttention($conn);

// Get seasonal planting reminders
$seasonal_reminders = getSeasonalPlantingReminders($conn);

// Handle iCal export if requested
if (isset($_GET['export']) && $_GET['export'] === 'ical') {
    $start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
    $end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
    
    $ical_content = exportCalendarToICal($conn, $start_date, $end_date);
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="farm_calendar.ics"');
    echo $ical_content;
    exit();
}

// Handle AJAX requests for day details
// Handle AJAX requests for day details
// Handle AJAX requests for day details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'day_details') {
    // Turn off any prior output buffering
    while (ob_get_level()) ob_end_clean();
    
    // Set the content type header
    header('Content-Type: application/json');
    
    try {
        // Log the request
        error_log("AJAX day_details request received for date: " . ($_GET['date'] ?? 'not specified'));
        
        // Get date parameter
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Verify connection
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : "Connection not established"));
        }
        
        // Check if function exists
        if (!function_exists('getEventsForDate')) {
            throw new Exception("getEventsForDate function not found");
        }
        
        // Get events
        $events = getEventsForDate($conn, $date);
        
        // Log what we found
        error_log("Events found for date $date: " . count($events));
        error_log("Events data: " . json_encode($events));
        
        // Return events (empty array if none found)
        echo json_encode($events);
    } catch (Exception $e) {
        // Return error response
        error_log("AJAX day_details error: " . $e->getMessage());
        echo json_encode([
            'error' => true,
            'message' => "Server error: " . $e->getMessage()
        ]);
    }
    exit();
}

// Test AJAX handler - Add this right before your existing AJAX handler
if (isset($_GET['ajax']) && $_GET['ajax'] === 'test') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'AJAX is working']);
    exit();
}

// Handle drag-and-drop rescheduling via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'reschedule_event') {
    $event_id = $_POST['event_id'] ?? '';
    $new_date = $_POST['new_date'] ?? '';
    $event_type = $_POST['event_type'] ?? 'task';
    
    $success = false;
    $message = 'Invalid request';
    
    if (!empty($event_id) && !empty($new_date)) {
        if ($event_type === 'task') {
            $task_id = str_replace('task_', '', $event_id);
            $stmt = $conn->prepare("UPDATE farm_tasks SET scheduled_date = ? WHERE task_id = ?");
            $stmt->bind_param("si", $new_date, $task_id);
            $success = $stmt->execute();
            $message = $success ? 'Task rescheduled successfully' : 'Failed to reschedule task';
        } else if ($event_type === 'harvest') {
            $harvest_id = str_replace('harvest_', '', $event_id);
            $stmt = $conn->prepare("UPDATE harvest_records SET harvest_date = ? WHERE harvest_id = ?");
            $stmt->bind_param("si", $new_date, $harvest_id);
            $success = $stmt->execute();
            $message = $success ? 'Harvest rescheduled successfully' : 'Failed to reschedule harvest';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Calendar | Farm Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
</head>
<body>
    <div class="container">
        
        <div class="content">
            <h1><i class="fas fa-calendar-alt"></i> Farm Calendar</h1>
            
            <div class="action-buttons">
                <a href="task.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Task</a>
                <a href="harvest_crop.php" class="btn btn-success"><i class="fas fa-leaf"></i> Record Harvest</a>
                <a href="?export=ical&start=<?= $start_date ?> &end= <?= $end_date ?>" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export Calendar
                </a>
            </div>
            
            <!-- Calendar View Tabs -->
            <div class="calendar-tabs">
                <a href="?view=month&year=<?= $year ?> &month= <?= $month ?> &day= <?= $day ?>" 
                   class="calendar-tab <?= $view_type === 'month' ? 'active' : '' ?>">
                    Month
                </a>
                <a href="?view=week&year=<?= $year ?>&month=<?= $month ?> &day= <?= $day ?>" 
                   class="calendar-tab <?= $view_type === 'week' ? 'active' : '' ?>">
                    Week
                </a>
                <a href="?view=day&year=<?= $year ?>&month=<?= $month ?> &day= <?= $day ?>" 
                   class="calendar-tab <?= $view_type === 'day' ? 'active' : '' ?>">
                    Day
                </a>
            </div>
            
            <!-- Legend for task types -->
            <div class="legend-container">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #8bc34a;"></div>
                    <span>Fertilizer</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #03a9f4;"></div>
                    <span>Watering</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ff5722;"></div>
                    <span>Spraying</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ff9800;"></div>
                    <span>Harvest</span>
                </div>
                <div class="legend-item">
    <div class="legend-color" style="background-color: #9c27b0;"></div>
    <span>Crop Event</span>
</div>
            </div>
            
            <div class="calendar-container">
                <!-- Calendar Navigation -->
                <div class="month-header">
                    <a href="?view=<?= $view_type ?>&year=<?= $nav_info['prev_year'] ?>&month=<?= $nav_info['prev_month'] ?>&day=<?= $nav_info['prev_day'] ?>" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <h2><?= $nav_info['title'] ?></h2>
                    <a href="?view=<?= $view_type ?>&year=<?= $nav_info['next_year'] ?>&month=<?= $nav_info['next_month'] ?>&day=<?= $nav_info['next_day'] ?>" class="btn btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <div class="calendar-main">
                    <?php if ($view_type === 'month'): ?>
                        <!-- Month View -->
                        <table class="calendar-table">
                            <thead>
                                <tr>
                                    <th>Monday</th>
                                    <th>Tuesday</th>
                                    <th>Wednesday</th>
                                    <th>Thursday</th>
                                    <th>Friday</th>
                                    <th>Saturday</th>
                                    <th>Sunday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calendar_data['weeks'] as $week): ?>
                                <tr>
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                    <?php $day = $week[$i]; ?>
                                    <td class="<?= $day['day'] === 0 ? 'inactive' : '' ?>" 
                                        <?php if ($day['day'] !== 0): ?>
                                        data-date="<?= sprintf('%04d-%02d-%02d', $year, $month, $day['day']) ?>"
                                        onclick="showDayDetails(this)"
                                        <?php endif; ?>
                                    >
                                        <?php if ($day['day'] !== 0): ?>
                                            <div class="calendar-day"><?= $day['day'] ?></div>
                                            <div class="day-events" id="day-<?= $day['day'] ?>">
                                                <?php foreach ($day['events'] as $event): ?>
                                                    <div class="event-item <?= $event['type'] ?>-event <?php 
                                                        // Add special classes for fertilizer/watering/spraying tasks
                                                        if ($event['type'] === 'task') {
                                                            if (stripos($event['task_type'], 'fertilizer') !== false) {
                                                                echo 'task-fertilizer';
                                                            } elseif (stripos($event['task_type'], 'water') !== false) {
                                                                echo 'task-watering';
                                                            } elseif (stripos($event['task_type'], 'spray') !== false) {
                                                                echo 'task-spraying';
                                                            }
                                                        }
                                                    ?>" 
                                                        style="<?php if(isset($event['color'])): ?>border-left-color: <?= $event['color'] ?>;<?php endif; ?>"
                                                        data-event-id="<?= $event['id'] ?>"
                                                        data-event-type="<?= $event['type'] ?>"
                                                        draggable="true">
                                                        <?= htmlspecialchars($event['title']) ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php elseif ($view_type === 'week'): ?>
                        <!-- Week View -->
                        <div class="week-view-container">
                            <?php foreach ($calendar_data['dates'] as $date_key => $date_data): ?>
                                <div class="week-day">
                                    <div class="week-day-date">
                                        <?= $date_data['weekday'] ?><br>
                                        <?= $date_data['day'] ?>
                                    </div>
                                    <div class="week-day-events" id="date-<?= $date_key ?>" data-date="<?= $date_key ?>">
                                        <?php foreach ($date_data['events'] as $event): ?>
                                            <div class="event-item <?= $event['type'] ?>-event <?php 
                                                // Add special classes for fertilizer/watering/spraying tasks
                                                if ($event['type'] === 'task') {
                                                    if (stripos($event['task_type'], 'fertilizer') !== false) {
                                                        echo 'task-fertilizer';
                                                    } elseif (stripos($event['task_type'], 'water') !== false) {
                                                        echo 'task-watering';
                                                    } elseif (stripos($event['task_type'], 'spray') !== false) {
                                                        echo 'task-spraying';
                                                    }
                                                }
                                            ?>" 
                                                style="<?php if(isset($event['color'])): ?>border-left-color: <?= $event['color'] ?>;<?php endif; ?>"
                                                data-event-id="<?= $event['id'] ?>"
                                                data-event-type="<?= $event['type'] ?>"
                                                onclick="showEventDetails(<?= htmlspecialchars(json_encode($event)) ?>)"
                                                draggable="true">
                                                <?= htmlspecialchars($event['title']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Day View -->
                        <div class="day-view-container">
                            <div class="day-header">
                                <?= date('l, F j, Y', strtotime($start_date)) ?>
                            </div>
                            <div class="day-events-list" id="day-events-list" data-date="<?= $start_date ?>">
                                <?php 
                                $day_events = $calendar_data['dates'][$start_date]['events'] ?? [];
                                if (empty($day_events)): 
                                ?>
                                    <p>No events scheduled for this day.</p>
                                <?php else: ?>
                                    <?php foreach ($day_events as $event): ?>
                                        <div class="event-item <?= $event['type'] ?>-event <?php 
                                            // Add special classes for fertilizer/watering/spraying tasks
                                            if ($event['type'] === 'task') {
                                                if (stripos($event['task_type'], 'fertilizer') !== false) {
                                                    echo 'task-fertilizer';
                                                } elseif (stripos($event['task_type'], 'water') !== false) {
                                                    echo 'task-watering';
                                                } elseif (stripos($event['task_type'], 'spray') !== false) {
                                                    echo 'task-spraying';
                                                }
                                            }
                                        ?>" 
                                            style="<?php if(isset($event['color'])): ?>border-left-color: <?= $event['color'] ?>;<?php endif; ?>"
                                            data-event-id="<?= $event['id'] ?>"
                                            data-event-type="<?= $event['type'] ?>"
                                            onclick="showEventDetails(<?= htmlspecialchars(json_encode($event)) ?>)"
                                            draggable="true">
                                            <?= htmlspecialchars($event['title']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="calendar-sidebar">
                    <div class="sidebar-section">
                        <h3>Upcoming Events (14 Days)</h3>
                        <?php if (count($upcoming_events) > 0): ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="event-list-item">
                                    <div class="event-date">
                                        <?= date('D, M j', strtotime($event['date'])) ?>
                                    </div>
                                    <div class="event-details">
                                        <div class="event-icon" style="color: <?= $event['color'] ?>">
                                            <?php if ($event['type'] === 'task'): ?>
                                                <?php if (stripos($event['task_type'], 'fertilizer') !== false): ?>
                                                    <i class="fas fa-seedling"></i>
                                                <?php elseif (stripos($event['task_type'], 'water') !== false): ?>
                                                    <i class="fas fa-tint"></i>
                                                <?php elseif (stripos($event['task_type'], 'spray') !== false): ?>
                                                    <i class="fas fa-spray-can"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-tasks"></i>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <i class="fas fa-leaf"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-title">
                                            <?= htmlspecialchars($event['title']) ?>
                                            <?php if ($event['type'] === 'task' && $event['completed'] == 1): ?>
                                                <span class="task-completed"><i class="fas fa-check"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No upcoming events in the next 14 days.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3>Crops Needing Attention</h3>
                        <?php if (count($crops_needing_attention) > 0): ?>
                            <?php foreach ($crops_needing_attention as $crop): ?>
                                <div class="event-list-item">
                                    <div class="event-details">
                                        <div class="event-icon" style="color: <?= $crop['color_code'] ?>">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="event-title">
                                            <?= htmlspecialchars($crop['crop_name']) ?> - <?= htmlspecialchars($crop['task_name']) ?>
                                            <div class="event-date">
                                                <?= $crop['days_overdue'] ?> days overdue
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No crops need immediate attention.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3>Seasonal Planting Reminders</h3>
                        <?php if (!empty($seasonal_reminders['planting'])): ?>
                            <h4>Time to Plant</h4>
                            <?php foreach ($seasonal_reminders['planting'] as $reminder): ?>
                                <div class="reminder-item">
                                    <?= htmlspecialchars($reminder['crop_name']) ?> (<?= htmlspecialchars($reminder['season_name']) ?>)
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($seasonal_reminders['harvesting'])): ?>
                            <h4>Ready for Harvest</h4>
                            <?php foreach ($seasonal_reminders['harvesting'] as $reminder): ?>
                                <div class="reminder-item">
                                    <?= htmlspecialchars($reminder['crop_name']) ?> (<?= htmlspecialchars($reminder['season_name']) ?>)
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (empty($seasonal_reminders['planting']) && empty($seasonal_reminders['harvesting'])): ?>
                            <p>No seasonal reminders for this month.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Day Details Modal -->
    <div id="dayDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" color="white" id="modalDate"></h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <div id="dayEvents"></div>
            </div>
            <div class="action-buttons">
            <a href="#" id="addTaskBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add Task</a>
                <a href="#" id="recordHarvestBtn" class="btn btn-success"><i class="fas fa-leaf"></i> Record Harvest</a>
            </div>
        </div>
    </div>
    
    <!-- Event Details Modal -->
    <div id="eventDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="eventModalTitle"></h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="eventModalBody">
                <div id="eventDetails"></div>
            </div>
            <div class="modal-footer">
                <a href="#" id="editEventBtn" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
                <a href="#" id="deleteEventBtn" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</a>
                <a href="#" id="markCompleteBtn" class="btn btn-success"><i class="fas fa-check"></i> Mark Complete</a>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize sortable for drag-and-drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sortable for week view and day view
            let weekContainers = document.querySelectorAll('.week-day-events');
            weekContainers.forEach(container => {
                new Sortable(container, {
                    group: 'calendar-events',
                    animation: 150,
                    onEnd: function(evt) {
                        handleEventDrop(evt);
                    }
                });
            });
            
            // Initialize sortable for day view
            let dayContainer = document.getElementById('day-events-list');
            if (dayContainer) {
                new Sortable(dayContainer, {
                    group: 'calendar-events',
                    animation: 150,
                    onEnd: function(evt) {
                        handleEventDrop(evt);
                    }
                });
            }
            
            // Month view - make each day's events sortable
            let monthDays = document.querySelectorAll('.day-events');
            monthDays.forEach(dayDiv => {
                new Sortable(dayDiv, {
                    group: 'calendar-events',
                    animation: 150,
                    onEnd: function(evt) {
                        handleEventDrop(evt);
                    }
                });
            });
        });
        
        // Handle event drop (drag-and-drop rescheduling)
        function handleEventDrop(evt) {
            const eventItem = evt.item;
            const eventId = eventItem.getAttribute('data-event-id');
            const eventType = eventItem.getAttribute('data-event-type');
            const newDate = evt.to.getAttribute('data-date');
            
            if (eventId && newDate) {
                // Send AJAX request to update the event date
                const formData = new FormData();
                formData.append('event_id', eventId);
                formData.append('event_type', eventType);
                formData.append('new_date', newDate);
                
                fetch('farm_calendar.php?ajax=reschedule_event', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'error');
                        // Revert the change by refreshing
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while rescheduling the event', 'error');
                    window.location.reload();
                });
            }
        }
        
      // Function to show day details
 // Function to show day details
 // Function to show day details
function showDayDetails(dayElement) {
    const date = dayElement.getAttribute('data-date');
    if (!date) return;
    
    const modal = document.getElementById('dayDetailsModal');
    const modalDate = document.getElementById('modalDate');
    const dayEvents = document.getElementById('dayEvents');
    const addTaskBtn = document.getElementById('addTaskBtn');
    const recordHarvestBtn = document.getElementById('recordHarvestBtn');
    
    // Format the date for display
    const displayDate = new Date(date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    modalDate.textContent = displayDate;
    dayEvents.innerHTML = '<p>Loading events...</p>';
    
    // Set up the action buttons with the correct date
    addTaskBtn.href = `task.php?date=${date}`;
    recordHarvestBtn.href = `harvest_crop.php?date=${date}`;
    
    // Show the modal now, so user sees something happening immediately
    modal.style.display = 'block';
    
    // First try a test AJAX call
    fetch('farm_calendar.php?ajax=test')
        .then(response => response.json())
        .then(data => {
            console.log('Test AJAX response:', data);
            
            // Now proceed with the actual events request
            return fetch(`farm_calendar.php?ajax=day_details&date=${date}`);
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }
            
            // Convert response to text first to check if it's empty
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            
            if (!text || text.trim() === '') {
                throw new Error('Empty response received from server');
            }
            
            // Parse the text as JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error(`Invalid JSON response: ${text.substring(0, 100)}`);
            }
        })
        .then(events => {
            console.log('Events received:', events);
            
            if (!events || events.length === 0) {
                dayEvents.innerHTML = '<p>No events scheduled for this day.</p>';
            } else {
                dayEvents.innerHTML = '';
                events.forEach(event => {
                    const eventDiv = document.createElement('div');
                    eventDiv.className = 'event-list-item';
                    
                    let iconClass = 'fas fa-tasks';
                    if (event.type === 'harvest') {
                        iconClass = 'fas fa-leaf';
                    } else if (event.task_type) {
                        if (event.task_type.toLowerCase().includes('fertilizer')) {
                            iconClass = 'fas fa-seedling';
                        } else if (event.task_type.toLowerCase().includes('water')) {
                            iconClass = 'fas fa-tint';
                        } else if (event.task_type.toLowerCase().includes('spray')) {
                            iconClass = 'fas fa-spray-can';
                        }
                    }
                    
                    eventDiv.innerHTML = `
                        <div class="event-details">
                            <div class="event-icon" style="color: ${event.color || '#666'}">
                                <i class="${iconClass}"></i>
                            </div>
                            <div class="event-title">
                                ${event.title}
                                ${event.type === 'task' && event.completed == 1 ? 
                                    '<span class="task-completed"><i class="fas fa-check"></i></span>' : ''}
                            </div>
                        </div>
                    `;
                    
                    eventDiv.addEventListener('click', () => {
                        showEventDetails(event);
                    });
                    
                    dayEvents.appendChild(eventDiv);
                });
            }
        })
        .catch(error => {
            console.error('Error fetching events:', error);
            dayEvents.innerHTML = `
                <p>Error loading events: ${error.message}</p>
                <p>Please check the browser console and server logs for details.</p>
                <p><button onclick="location.reload()">Refresh Page</button></p>
            `;
        });
    
    // Close modal when 'X' is clicked
    const closeButtons = modal.querySelectorAll('.close-modal');
    closeButtons.forEach(button => {
        button.onclick = function() {
            modal.style.display = 'none';
        };
    });
}
        
        // Show event details modal
        // Show event details modal
function showEventDetails(event) {
    document.getElementById('eventModalTitle').textContent = event.title;
    
    // Build detailed event information
    let detailsHtml = `
        <div class="event-details-content">
            <p><strong>Type:</strong> ${event.type === 'task' ? 'Task' : (event.type === 'harvest' ? 'Harvest' : 'Crop Event')}</p>
            <p><strong>Crop:</strong> ${event.crop}</p>`;
            
    if (event.location) {
        detailsHtml += `<p><strong>Location:</strong> ${event.location}</p>`;
    }
    
    if (event.type === 'task') {
        detailsHtml += `<p><strong>Task Type:</strong> ${event.task_type}</p>`;
        detailsHtml += `<p><strong>Status:</strong> ${event.completed == 1 ? 'Completed' : 'Pending'}</p>`;
    } else if (event.type === 'harvest') {
        detailsHtml += `<p><strong>Yield:</strong> ${event.quantity} ${event.unit}</p>`;
    } else if (event.type === 'crop_event') {
        if (event.description) {
            detailsHtml += `<p><strong>Description:</strong> ${event.description}</p>`;
        }
    }
    
    if (event.notes && event.type !== 'crop_event') {
        detailsHtml += `<p><strong>Notes:</strong> ${event.notes}</p>`;
    }
    
    detailsHtml += `</div>`;
    
    document.getElementById('eventDetails').innerHTML = detailsHtml;
    
    // Set up action buttons
    const editEventBtn = document.getElementById('editEventBtn');
    const deleteEventBtn = document.getElementById('deleteEventBtn');
    const markCompleteBtn = document.getElementById('markCompleteBtn');
    
    if (event.type === 'task') {
        const taskId = event.id.replace('task_', '');
        editEventBtn.href = `task.php?id=${taskId}`;
        deleteEventBtn.href = `task.php?id=${taskId}`;
        markCompleteBtn.href = `task.php?id=${taskId}`;
        markCompleteBtn.style.display = 'inline-block';
        markCompleteBtn.innerHTML = `<i class="fas fa-check"></i> ${event.completed == 1 ? 'Mark Incomplete' : 'Mark Complete'}`;
    } else if (event.type === 'harvest') {
        const harvestId = event.id.replace('harvest_', '');
        editEventBtn.href = `harvest.php?id=${harvestId}`;
        deleteEventBtn.href = `harvest.php?id=${harvestId}`;
        markCompleteBtn.style.display = 'none';
    } else if (event.type === 'crop_event') {
        const eventId = event.id.replace('crop_event_', '');
        editEventBtn.href = `crop_events.php?edit=${eventId}`;
        deleteEventBtn.href = `crop_events.php?delete=${eventId}`;
        markCompleteBtn.style.display = 'none';
    }
    
    // Show the modal
    document.getElementById('eventDetailsModal').style.display = 'block';
}
        
        // Close modals when the X is clicked
        document.querySelectorAll('.close-modal').forEach(function(closeBtn) {
            closeBtn.addEventListener('click', function() {
                this.closest('.modal').style.display = 'none';
            });
        });
        
        // Close modals when clicking outside the modal content
        window.addEventListener('click', function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        });
        
        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = message;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('fade-out');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 500);
            }, 3000);
        }
    </script>
    
    
<?php require_once 'views/footer.php'; ?>
</body>
</html>