<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);



include __DIR__ . '/../config/db.php';

// crop_functions.php - Functions for crop management
// Get upcoming events for a specified number of days
function getUpcomingEvents($conn, $days = 14) {
    // Calculate the date range
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$days days"));
    
    $sql = "SELECT ce.*, c.crop_name 
            FROM crop_events ce
            JOIN crops c ON ce.crop_id = c.crop_id
            WHERE ce.event_date BETWEEN ? AND ?
            ORDER BY ce.event_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    return $events;
}

// Get crops that need attention (based on upcoming tasks or events)
function getCropsNeedingAttention($conn) {
    // You can customize this function based on your criteria for "needs attention"
    // For example, crops with events/tasks in the next 7 days
    $days = 7;
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$days days"));
    
    $sql = "SELECT DISTINCT c.crop_id, c.crop_name, ce.event_date, ce.event_name
            FROM crops c
            JOIN crop_events ce ON c.crop_id = ce.crop_id
            WHERE ce.event_date BETWEEN ? AND ?
            ORDER BY ce.event_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $crops = [];
    while ($row = $result->fetch_assoc()) {
        $crops[] = $row;
    }
    
    return $crops;
}


function buildCalendarMonth($year, $month) {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $first_day_of_month = strtotime("$year-$month-01");
    $first_weekday = date('w', $first_day_of_month);

    // Initialize a calendar grid with empty cells
    $calendar_grid = [];
    for ($week = 0; $week < 6; $week++) {
        for ($day = 0; $day < 7; $day++) {
            $calendar_grid[$week][$day] = ['day' => 0, 'events' => []];
        }
    }

    $current_day = 1;
    for ($week = 0; $week < 6; $week++) {
        for ($day = 0; $day < 7; $day++) {
            if (($week == 0 && $day >= $first_weekday) || ($week > 0 && $current_day <= $days_in_month)) {
                $calendar_grid[$week][$day]['day'] = $current_day++;
            }
        }
    }

    return [
        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
        'grid' => $calendar_grid
    ];
}


function populateCalendarWithEvents($conn, $calendar, $year, $month) {
    $sql = "SELECT * FROM crop_events WHERE YEAR(event_date) = ? AND MONTH(event_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($event = $result->fetch_assoc()) {
        $event_day = (int)date('j', strtotime($event['event_date']));
        for ($week = 0; $week < 6; $week++) {
            for ($day = 0; $day < 7; $day++) {
                if ($calendar[$week][$day]['day'] == $event_day) {
                    $calendar[$week][$day]['events'][] = $event;
                    break 2; // Found the cell, no need to keep looping
                }
            }
        }
    }

    return $calendar;
}


// Get all crops
function getAllCrops($conn) {
    $sql = "SELECT c.*, cc.category_name 
            FROM crops c 
            LEFT JOIN crop_categories cc ON c.category_id = cc.category_id 
            ORDER BY c.crop_name";
    $result = $conn->query($sql);
    
    $crops = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $crops[] = $row;
        }
    }
    
    return $crops;
}

// Get a specific crop by ID
function getCropById($conn, $crop_id) {
    $crop_id = (int)$crop_id;
    $sql = "SELECT c.*, cc.category_name 
            FROM crops c 
            LEFT JOIN crop_categories cc ON c.category_id = cc.category_id 
            WHERE c.crop_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $crop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Add a new crop
function addCrop($conn, $data) {
    $sql = "INSERT INTO crops (crop_name, category_id, image_path, description, 
                              soil_requirements, watering_needs, sunlight_requirements, 
                              days_to_maturity, spacing_requirements, common_issues, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisssssssss", 
        $data['crop_name'], 
        $data['category_id'], 
        $data['image_path'], 
        $data['description'], 
        $data['soil_requirements'], 
        $data['watering_needs'], 
        $data['sunlight_requirements'], 
        $data['days_to_maturity'], 
        $data['spacing_requirements'], 
        $data['common_issues'], 
        $data['notes']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

// Update an existing crop
function updateCrop($conn, $crop_id, $data) {
    $sql = "UPDATE crops 
            SET crop_name = ?, 
                category_id = ?, 
                image_path = ?, 
                description = ?, 
                soil_requirements = ?, 
                watering_needs = ?, 
                sunlight_requirements = ?, 
                days_to_maturity = ?, 
                spacing_requirements = ?, 
                common_issues = ?, 
                notes = ? 
            WHERE crop_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisssssssssi", 
        $data['crop_name'], 
        $data['category_id'], 
        $data['image_path'], 
        $data['description'], 
        $data['soil_requirements'], 
        $data['watering_needs'], 
        $data['sunlight_requirements'], 
        $data['days_to_maturity'], 
        $data['spacing_requirements'], 
        $data['common_issues'], 
        $data['notes'],
        $crop_id
    );
    
    return $stmt->execute();
}

// Delete a crop
function deleteCrop($conn, $crop_id) {
    $crop_id = (int)$crop_id;
    $sql = "DELETE FROM crops WHERE crop_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $crop_id);
    
    return $stmt->execute();
}

// Get all crop categories
function getAllCategories($conn) {
    $sql = "SELECT * FROM crop_categories ORDER BY category_name";
    $result = $conn->query($sql);
    
    $categories = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    return $categories;
}

// Get seasonal planting reminders
function getSeasonalPlantingReminders($conn) {
    // Get current month
    $current_month = date('n');
    $next_month = $current_month == 12 ? 1 : $current_month + 1;
    
    // Find crops that should be planted in the current or next month based on crop calendar
    $sql = "SELECT c.crop_id, c.crop_name, cal.start_month, cal.end_month, 
                   gs.stage_name, gs.color_code
            FROM crops c
            JOIN crop_calendar cal ON c.crop_id = cal.crop_id
            JOIN growth_stages gs ON cal.stage_id = gs.stage_id
            WHERE gs.stage_name = 'Planting' 
            AND ((cal.start_month <= ? AND cal.end_month >= ?) 
                OR (cal.start_month <= ? AND cal.end_month >= ?))
            ORDER BY cal.start_month, c.crop_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $current_month, $current_month, $next_month, $next_month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reminders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $month_names = [
                1 => 'January', 2 => 'February', 3 => 'March', 
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September', 
                10 => 'October', 11 => 'November', 12 => 'December'
            ];
            
            // Format the planting period
            if ($row['start_month'] == $row['end_month']) {
                $period = $month_names[$row['start_month']];
            } else {
                $period = $month_names[$row['start_month']] . " to " . $month_names[$row['end_month']];
            }
            
            $row['planting_period'] = $period;
            $reminders[] = $row;
        }
    }
    
    return $reminders;
}


// Export calendar to iCal format
function exportCalendarToICal($conn, $start_date, $end_date) {
    // Query events within the date range
    $sql = "SELECT ce.*, c.crop_name 
            FROM crop_events ce
            JOIN crops c ON ce.crop_id = c.crop_id
            WHERE ce.event_date BETWEEN ? AND ?
            ORDER BY ce.event_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Start building iCal content
    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//Jubert Farms//Crop Calendar//EN\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";
    
    // Add events
    while ($event = $result->fetch_assoc()) {
        $event_date = date('Ymd', strtotime($event['event_date']));
        $created = date('Ymd\THis\Z');
        $uid = md5($event['event_id'] . $event['event_date'] . $event['event_name']);
        
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:{$uid}\r\n";
        $ical .= "DTSTART;VALUE=DATE:{$event_date}\r\n";
        $ical .= "SUMMARY:{$event['crop_name']}: {$event['event_name']}\r\n";
        if (!empty($event['description'])) {
            $ical .= "DESCRIPTION:" . str_replace("\n", "\\n", $event['description']) . "\r\n";
        }
        $ical .= "DTSTAMP:{$created}\r\n";
        $ical .= "END:VEVENT\r\n";
    }
    
    $ical .= "END:VCALENDAR";
    
    return $ical;
}


?>