<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);



include __DIR__ . '/../config/db.php';
// calendar_functions.php - Functions for calendar management

require_once __DIR__ . '/../config/helpers.php';

//require_once 'task_functions.php';
require_once __DIR__ . '/harvest_functions.php';


/**
 * Get crop events for a specific date
 * 
 * @param mysqli $conn Database connection
 * @param string $date Date in Y-m-d format
 * @return array Array of crop events for the date
 */
function getCropEventsForDate($conn, $date) {
    $events = [];
    
    $stmt = $conn->prepare("SELECT e.*, c.crop_name FROM crop_events e 
                           INNER JOIN crops c ON e.crop_id = c.crop_id 
                           WHERE e.event_date = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'crop_event_' . $row['event_id'],
                'title' => $row['event_name'],
                'start' => $row['event_date'],
                'color' => '#9c27b0', // Purple color for crop events
                'type' => 'crop_event',
                'icon' => 'event',
                'crop' => $row['crop_name'],
                'description' => $row['description']
            ];
        }
    }
    
    $stmt->close();
    return $events;
}

/**
 * Get crop events for a date range
 * 
 * @param mysqli $conn Database connection
 * @param string $start_date Start date in Y-m-d format
 * @param string $end_date End date in Y-m-d format
 * @return array Array of crop events for the date range
 */
function getCropEventsByDateRange($conn, $start_date, $end_date) {
    $events = [];
    
    $stmt = $conn->prepare("SELECT e.*, c.crop_name FROM crop_events e 
                           INNER JOIN crops c ON e.crop_id = c.crop_id 
                           WHERE e.event_date BETWEEN ? AND ?
                           ORDER BY e.event_date");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'event_id' => $row['event_id'],
                'event_date' => $row['event_date'],
                'event_name' => $row['event_name'],
                'crop_name' => $row['crop_name'],
                'description' => $row['description'],
                'crop_id' => $row['crop_id']
            ];
        }
    }
    
    $stmt->close();
    return $events;
}




// Get tasks by date range
function getTasksByDateRange($conn, $start_date, $end_date) {
    $query = "SELECT ft.*, tt.type_name, tt.color_code, tt.icon, 
                     c.crop_name, cc.field_or_location
              FROM farm_tasks ft
              JOIN task_types tt ON ft.task_type_id = tt.task_type_id
              JOIN crop_cycles cc ON ft.cycle_id = cc.cycle_id
              JOIN crops c ON cc.crop_id = c.crop_id
              WHERE ft.scheduled_date BETWEEN ? AND ?
              ORDER BY ft.scheduled_date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    }
    
    return $tasks;
}


// Get all calendar events for a specific month
function getCalendarEvents($conn, $year, $month) {
    // First and last day of the month
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $events = [];
    
    // Get all tasks in the date range
    $tasks = getTasksByDateRange($conn, $start_date, $end_date);
    foreach ($tasks as $task) {
        $events[] = [
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
    
    // Get all harvests in the date range
    $harvests = getHarvestsByDateRange($conn, $start_date, $end_date);
    foreach ($harvests as $harvest) {
        $events[] = [
            'id' => 'harvest_' . $harvest['harvest_id'],
            'title' => 'Harvest: ' . $harvest['crop_name'],
            'start' => $harvest['harvest_date'],
            'color' => '#ff9800', // Harvest color
            'type' => 'harvest',
            'icon' => 'harvest',
            'crop' => $harvest['crop_name'],
            'location' => $harvest['field_or_location'],
            'quantity' => $harvest['quantity'],
            'unit' => $harvest['unit'],
            'notes' => $harvest['notes']
        ];
    }
    
    return $events;
}

// Simplified version for testing
// Add this to calendar_functions.php
function getEventsForDate($conn, $date) {
    $events = [];
    
    try {
        // Query for tasks
        $sql = "SELECT t.task_id, t.task_name, t.scheduled_date, tt.type_name, 
                   t.completion_status, t.notes, t.field_or_location,
                   c.crop_name, tt.color_code 
            FROM farm_tasks t
            JOIN task_types tt ON t.task_type_id = tt.type_id
            LEFT JOIN crops c ON t.crop_id = c.crop_id
            WHERE t.scheduled_date = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'task_' . $row['task_id'],
                'title' => $row['task_name'],
                'start' => $row['scheduled_date'],
                'color' => $row['color_code'],
                'type' => 'task',
                'crop' => $row['crop_name'] ?? 'N/A',
                'location' => $row['field_or_location'] ?? 'N/A',
                'completed' => $row['completion_status'],
                'task_type' => $row['type_name'],
                'notes' => $row['notes']
            ];
        }

        // Query for harvests
        $sql = "SELECT h.harvest_id, c.crop_name, h.harvest_date, h.field_or_location,
                   h.quantity, h.unit, h.notes
            FROM harvest_records h
            JOIN crops c ON h.crop_id = c.crop_id
            WHERE h.harvest_date = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'harvest_' . $row['harvest_id'],
                'title' => 'Harvest: ' . $row['crop_name'],
                'start' => $row['harvest_date'],
                'color' => '#ff9800', // Default color for harvests
                'type' => 'harvest',
                'crop' => $row['crop_name'] ?? 'N/A',
                'location' => $row['field_or_location'] ?? 'N/A',
                'quantity' => $row['quantity'],
                'unit' => $row['unit'],
                'notes' => $row['notes']
            ];
        }

        // Query for crop events
        $sql = "SELECT e.event_id, e.event_title, e.event_date, e.description, 
                       c.crop_name, e.field_or_location
                FROM crop_events e
                LEFT JOIN crops c ON e.crop_id = c.crop_id
                WHERE e.event_date = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => 'event_' . $row['event_id'],
                'title' => 'Event: ' . $row['event_title'],
                'start' => $row['event_date'],
                'color' => '#3f51b5', // Color for crop events
                'type' => 'event',
                'crop' => $row['crop_name'] ?? 'N/A',
                'location' => $row['field_or_location'] ?? 'N/A',
                'description' => $row['description']
            ];
        }

    } catch (Exception $e) {
        error_log("Error in getEventsForDate: " . $e->getMessage());
    }

    return $events;
}




// Function to build a basic calendar array for a given month
function buildCalendarMonth($year, $month) {
    $num_days = date('t', mktime(0, 0, 0, $month, 1, $year));
    $month_start = date('N', mktime(0, 0, 0, $month, 1, $year)); // 1-7 (Mon-Sun)
    $month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
    
    // Initialize the calendar array
    $calendar = [
        'year' => $year,
        'month' => $month,
        'month_name' => $month_name,
        'num_days' => $num_days,
        'start_day' => $month_start,
        'weeks' => []
    ];
    
    // Build the weeks
    $day_counter = 1;
    $week_counter = 0;
    
    // Fill in days before the first day of the month
    $calendar['weeks'][$week_counter] = array_fill(0, 7, ['day' => 0, 'events' => []]);
    
    // Start from the actual first day of the month
    for ($i = $month_start - 1; $i < 7; $i++) {
        $calendar['weeks'][$week_counter][$i] = [
            'day' => $day_counter,
            'events' => []
        ];
        $day_counter++;
    }
    
    // Continue with remaining weeks
    while ($day_counter <= $num_days) {
        $week_counter++;
        $calendar['weeks'][$week_counter] = array_fill(0, 7, ['day' => 0, 'events' => []]);
        
        for ($i = 0; $i < 7 && $day_counter <= $num_days; $i++) {
            $calendar['weeks'][$week_counter][$i] = [
                'day' => $day_counter,
                'events' => []
            ];
            $day_counter++;
        }
    }
    
    return $calendar;
}

// Function to populate calendar with events
function populateCalendarWithEvents($conn, $calendar, $year, $month) {
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Get all events for the month
    $tasks = getTasksByDateRange($conn, $start_date, $end_date);
    $harvests = getHarvestsByDateRange($conn, $start_date, $end_date);
    $crop_events = getCropEventsByDateRange($conn, $start_date, $end_date);
    
    // Group all events by date
    $events_by_date = [];

    // === Add tasks ===
foreach ($tasks as $task) {
    $date = $task['scheduled_date'];
    $events_by_date[$date][] = [
        'id' => 'task_' . $task['task_id'],
        'title' => $task['task_name'],
        'type' => 'task',
        'color' => $task['color_code'] ?? '#666',
        'icon' => $task['icon'] ?? 'task',
        'task_type' => $task['type_name'] ?? 'General',
        'crop' => $task['crop_name'] ?? 'N/A',
        'location' => $task['field_or_location'] ?? 'N/A',
        'completed' => $task['completion_status'] ?? 0,
        'notes' => $task['notes'] ?? ''
    ];
}

// === Add harvests ===
foreach ($harvests as $harvest) {
    $date = $harvest['harvest_date'];
    $events_by_date[$date][] = [
        'id' => 'harvest_' . $harvest['harvest_id'],
        'title' => 'Harvest: ' . $harvest['crop_name'],
        'type' => 'harvest',
        'color' => '#ff9800',
        'icon' => 'harvest',
        'crop' => $harvest['crop_name'] ?? 'N/A',
        'location' => $harvest['field_or_location'] ?? 'N/A',
        'quantity' => $harvest['quantity'] ?? 0,
        'unit' => $harvest['unit'] ?? 'kg',
        'notes' => $harvest['notes'] ?? ''
    ];
}

// === Add crop events ===
foreach ($crop_events as $event) {
    $date = $event['event_date'];
    $events_by_date[$date][] = [
        'id' => 'crop_event_' . $event['event_id'],
        'title' => $event['event_name'],
        'type' => 'crop_event',
        'color' => '#9c27b0',
        'icon' => 'event',
        'crop' => $event['crop_name'],
        'description' => $event['description'],
        'location' => $event['field_or_location'] ?? 'N/A'  // Add null coalescing operator here
    ];
}

    // === Populate calendar ===
    foreach ($calendar['weeks'] as &$week) {
        foreach ($week as &$day_data) {
            if ($day_data['day'] > 0) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day_data['day']);
                $day_data['events'] = $events_by_date[$date] ?? [];
            } else {
                $day_data['events'] = [];
            }
        }
    }

    return $calendar;
}


// Generate upcoming tasks and harvests list
function getUpcomingEvents($conn, $days = 7) {
    $today = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+{$days} days"));
    
    $events = [];
    
    // Get upcoming tasks
    $tasks = getTasksByDateRange($conn, $today, $end_date);
    foreach ($tasks as $task) {
        $events[] = [
            'id' => 'task_' . $task['task_id'],
            'title' => $task['task_name'],
            'date' => $task['scheduled_date'],
            'color' => $task['color_code'],
            'type' => 'task',
            'icon' => $task['icon'],
            'crop' => $task['crop_name'],
            'task_type' => $task['type_name'],
            'completed' => $task['completion_status']
        ];
    }
    
    // Get upcoming harvests
    $harvests = getHarvestsByDateRange($conn, $today, $end_date);
    foreach ($harvests as $harvest) {
        $events[] = [
            'id' => 'harvest_' . $harvest['harvest_id'],
            'title' => 'Harvest: ' . $harvest['crop_name'],
            'date' => $harvest['harvest_date'],
            'color' => '#ff9800',
            'type' => 'harvest',
            'icon' => 'harvest',
            'crop' => $harvest['crop_name']
        ];
    }
    
    // Sort by date
    usort($events, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    
    return $events;
}

// Function to generate future harvest dates based on first harvest and frequency
function generateHarvestDates($conn, $cycle_id, $first_harvest_date, $harvest_frequency, $number_of_harvests = 10) {
    $dates = [];
    $current_date = new DateTime($first_harvest_date);
    
    // Add the first harvest date
    $dates[] = $current_date->format('Y-m-d');
    
    // Generate subsequent harvest dates based on frequency
    for ($i = 1; $i < $number_of_harvests; $i++) {
        $current_date->modify("+{$harvest_frequency} days");
        $dates[] = $current_date->format('Y-m-d');
    }
    
    // Insert the planned harvest dates into the database
    foreach ($dates as $index => $date) {
        // Skip the first one if it's already recorded
        if ($index === 0 && $first_harvest_date == $date) {
            continue;
        }
        
        // Add as a planned harvest
        $stmt = $conn->prepare("INSERT INTO harvest_records (cycle_id, harvest_date, status) 
                                VALUES (?, ?, 'Planned')");
        $stmt->bind_param("is", $cycle_id, $date);
        $stmt->execute();
    }
    
    return $dates;
}

// Function to update recurring task schedules
function updateRecurringTasks($conn) {
    // Get all recurring tasks that need to be scheduled
    $query = "SELECT rt.*, t.task_name, t.notes, t.task_type_id 
              FROM recurring_tasks rt
              JOIN task_templates t ON rt.template_id = t.template_id
              WHERE rt.next_occurrence <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND rt.end_date >= CURDATE()";
    
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Create the next occurrence of this task
            $stmt = $conn->prepare("INSERT INTO farm_tasks 
                                    (cycle_id, task_type_id, task_name, scheduled_date, notes) 
                                    VALUES (?, ?, ?, ?, ?)");
            
            $stmt->bind_param("iisss", 
                $row['cycle_id'], 
                $row['task_type_id'], 
                $row['task_name'], 
                $row['next_occurrence'], 
                $row['notes']
            );
            $stmt->execute();
            
            // Calculate the next occurrence date
            $next_date = new DateTime($row['next_occurrence']);
            $interval = $row['frequency'] . " " . $row['frequency_unit'];
            $next_date->modify("+{$interval}");
            $next_occurrence = $next_date->format('Y-m-d');
            
            // Update the recurring task with the new next_occurrence
            $update = $conn->prepare("UPDATE recurring_tasks 
                                      SET next_occurrence = ? 
                                      WHERE recurring_task_id = ?");
            $update->bind_param("si", $next_occurrence, $row['recurring_task_id']);
            $update->execute();
        }
    }
}

// Function to create recurring tasks
function createRecurringTask($conn, $cycle_id, $task_type_id, $task_name, $start_date, 
                            $frequency, $frequency_unit, $end_date, $notes = '') {
    // First, create a task template
    $stmt = $conn->prepare("INSERT INTO task_templates 
                            (task_name, task_type_id, notes) 
                            VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $task_name, $task_type_id, $notes);
    $stmt->execute();
    $template_id = $conn->insert_id;
    
    // Then create the recurring task entry
    $stmt = $conn->prepare("INSERT INTO recurring_tasks 
                            (template_id, cycle_id, frequency, frequency_unit, start_date, next_occurrence, end_date) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("iiissss", 
        $template_id, 
        $cycle_id, 
        $frequency, 
        $frequency_unit, 
        $start_date, 
        $start_date, // first occurrence is the start date
        $end_date
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        return false;
    }
}

// Function to get crops that need attention based on overdue tasks
function getCropsNeedingAttention($conn) {
    $query = "SELECT c.crop_name, cc.field_or_location, 
                    ft.task_name, ft.scheduled_date, 
                    DATEDIFF(CURDATE(), ft.scheduled_date) as days_overdue,
                    tt.type_name as task_type, 
                    tt.color_code
              FROM farm_tasks ft
              JOIN crop_cycles cc ON ft.cycle_id = cc.cycle_id
              JOIN crops c ON cc.crop_id = c.crop_id
              JOIN task_types tt ON ft.task_type_id = tt.task_type_id
              WHERE ft.completion_status = 0
              AND ft.scheduled_date < CURDATE()
              ORDER BY days_overdue DESC";
    
    $result = $conn->query($query);
    $crops_needing_attention = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $crops_needing_attention[] = $row;
        }
    }
    
    return $crops_needing_attention;
}

// Function to get harvest yield summary by crop
function getHarvestYieldSummary($conn, $start_date = null, $end_date = null) {
    $where_clause = "";
    
    if ($start_date && $end_date) {
        $where_clause = "WHERE hr.harvest_date BETWEEN ? AND ?";
    } elseif ($start_date) {
        $where_clause = "WHERE hr.harvest_date >= ?";
    } elseif ($end_date) {
        $where_clause = "WHERE hr.harvest_date <= ?";
    }
    
    $query = "SELECT c.crop_name, SUM(hr.quantity) as total_yield, hr.unit,
                    COUNT(hr.harvest_id) as harvest_count
              FROM harvest_records hr
              JOIN crop_cycles cc ON hr.cycle_id = cc.cycle_id
              JOIN crops c ON cc.crop_id = c.crop_id
              $where_clause
              GROUP BY c.crop_id, hr.unit
              ORDER BY total_yield DESC";
    
    $stmt = $conn->prepare($query);
    
    if ($start_date && $end_date) {
        $stmt->bind_param("ss", $start_date, $end_date);
    } elseif ($start_date) {
        $stmt->bind_param("s", $start_date);
    } elseif ($end_date) {
        $stmt->bind_param("s", $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $yield_summary = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $yield_summary[] = $row;
        }
    }
    
    return $yield_summary;
}

// Function to check what crops are currently in season or need to be planted
function getSeasonalPlantingReminders($conn) {
    $current_month = date('n'); // 1-12
    
    $query = "SELECT c.crop_name, s.season_name, gs.stage_name, 
                    cc.start_month, cc.end_month
              FROM crop_calendar cc
              JOIN crops c ON cc.crop_id = c.crop_id
              JOIN growth_stages gs ON cc.stage_id = gs.stage_id
              JOIN crop_seasons cs ON c.crop_id = cs.crop_id
              JOIN seasons s ON cs.season_id = s.season_id
              WHERE cc.start_month <= ? AND cc.end_month >= ?
              ORDER BY cc.start_month, c.crop_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $current_month, $current_month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reminders = [
        'planting' => [],
        'growing' => [],
        'harvesting' => []
    ];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stage = strtolower($row['stage_name']);
            if (isset($reminders[$stage])) {
                $reminders[$stage][] = $row;
            }
        }
    }
    
    return $reminders;
}

// Export calendar events to iCal format
function exportCalendarToICal($conn, $start_date, $end_date) {
    $events = getCalendarEvents($conn, date('Y', strtotime($start_date)), date('m', strtotime($start_date)));
    
    // Add events from additional months if needed
    $current_date = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    while ($current_date <= $end) {
        $month = $current_date->format('m');
        $year = $current_date->format('Y');
        
        if ($month != date('m', strtotime($start_date)) || $year != date('Y', strtotime($start_date))) {
            $additional_events = getCalendarEvents($conn, $year, $month);
            $events = array_merge($events, $additional_events);
        }
        
        $current_date->modify('+1 month');
    }
    
    // Filter events to only include those within date range
    $filtered_events = [];
    foreach ($events as $event) {
        $event_date = new DateTime($event['start']);
        if ($event_date >= new DateTime($start_date) && $event_date <= new DateTime($end_date)) {
            $filtered_events[] = $event;
        }
    }
    
    // Generate iCal content
    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//Farm Management System//Calendar//EN\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";
    
    foreach ($filtered_events as $event) {
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . $event['id'] . "@farmcalendar.com\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART;VALUE=DATE:" . date('Ymd', strtotime($event['start'])) . "\r\n";
        $ical .= "SUMMARY:" . $event['title'] . "\r\n";
        
        // Add description with relevant details
        $description = "";
        if (isset($event['crop'])) $description .= "Crop: " . $event['crop'] . "\\n";
        if (isset($event['location'])) $description .= "Location: " . $event['location'] . "\\n";
        if (isset($event['notes'])) $description .= "Notes: " . $event['notes'] . "\\n";
        
        if (!empty($description)) {
            $ical .= "DESCRIPTION:" . $description . "\r\n";
        }
        
        // Add categories/tags
        if ($event['type'] == 'task') {
            $ical .= "CATEGORIES:Task," . $event['task_type'] . "\r\n";
        } else {
            $ical .= "CATEGORIES:Harvest\r\n";
        }
        
        $ical .= "END:VEVENT\r\n";
    }
    
    $ical .= "END:VCALENDAR\r\n";
    
    return $ical;
}
?>
