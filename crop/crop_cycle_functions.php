<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';
// crop_cycle_functions.php - Functions for managing crop cycles

$calendar_data = buildCalendarMonth($year, $month);
$calendar_data['grid'] = populateCalendarWithEvents($conn, $calendar_data['grid'], $year, $month);

// Create a new crop cycle
function createCropCycle($conn, $crop_id, $field_location, $start_date, $nursing_duration, 
                         $growth_duration, $harvest_frequency = null) {
    // Calculate expected first harvest date
    $start = new DateTime($start_date);
    $total_days = $nursing_duration + $growth_duration;
    $start->modify("+{$total_days} days");
    $expected_first_harvest = $start->format('Y-m-d');
    
    // Calculate expected end date (arbitrary: 3 months after first harvest)
    $end = new DateTime($expected_first_harvest);
    $end->modify("+3 months");
    $expected_end_date = $end->format('Y-m-d');
    
    $stmt = $conn->prepare("INSERT INTO crop_cycles 
                           (crop_id, field_or_location, start_date, nursing_duration, 
                            growth_duration, expected_first_harvest, harvest_frequency, 
                            expected_end_date, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Planned')");
    
    $stmt->bind_param("issiisis", 
        $crop_id, 
        $field_location, 
        $start_date, 
        $nursing_duration, 
        $growth_duration, 
        $expected_first_harvest, 
        $harvest_frequency, 
        $expected_end_date
    );
    
    if ($stmt->execute()) {
        $cycle_id = $conn->insert_id;
        
        // If harvest frequency is set, generate planned harvest dates
        if ($harvest_frequency) {
            generateHarvestDates($conn, $cycle_id, $expected_first_harvest, $harvest_frequency);
        } else {
            // Just create the first harvest as planned
            $stmt = $conn->prepare("INSERT INTO harvest_records 
                                   (cycle_id, harvest_date, status) 
                                   VALUES (?, ?, 'Planned')");
            $stmt->bind_param("is", $cycle_id, $expected_first_harvest);
            $stmt->execute();
        }
        
        return $cycle_id;
    } else {
        return false;
    }
}

// Generate projected harvest dates based on frequency
function generateHarvestDates($conn, $cycle_id, $first_harvest_date, $harvest_frequency) {
    // Get the cycle end date
    $stmt = $conn->prepare("SELECT expected_end_date FROM crop_cycles WHERE cycle_id = ?");
    $stmt->bind_param("i", $cycle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $end_date = $row['expected_end_date'];
    
    // Start with first harvest
    $current_date = new DateTime($first_harvest_date);
    $end = new DateTime($end_date);
    
    // Insert first harvest
    $stmt = $conn->prepare("INSERT INTO harvest_records 
                           (cycle_id, harvest_date, status) 
                           VALUES (?, ?, 'Planned')");
    $stmt->bind_param("is", $cycle_id, $first_harvest_date);
    $stmt->execute();
    
    // Generate subsequent harvests
    while ($current_date < $end) {
        // Add harvest frequency days to get next harvest
        $current_date->modify("+{$harvest_frequency} days");
        
        // If we've gone past the end date, break
        if ($current_date > $end) {
            break;
        }
        
        // Insert the harvest date
        $harvest_date = $current_date->format('Y-m-d');
        $stmt->bind_param("is", $cycle_id, $harvest_date);
        $stmt->execute();
    }
    
    return true;
}

// Update a crop cycle's details
function updateCropCycle($conn, $cycle_id, $field_location, $start_date, $nursing_duration, 
                        $growth_duration, $harvest_frequency, $status, $notes) {
    // Calculate expected first harvest date
    $start = new DateTime($start_date);
    $total_days = $nursing_duration + $growth_duration;
    $start->modify("+{$total_days} days");
    $expected_first_harvest = $start->format('Y-m-d');
    
    // Calculate expected end date (arbitrary: 3 months after first harvest)
    $end = new DateTime($expected_first_harvest);
    $end->modify("+3 months");
    $expected_end_date = $end->format('Y-m-d');
    
    $stmt = $conn->prepare("UPDATE crop_cycles 
                           SET field_or_location = ?, 
                               start_date = ?,
                               nursing_duration = ?,
                               growth_duration = ?,
                               expected_first_harvest = ?,
                               harvest_frequency = ?,
                               expected_end_date = ?,
                               status = ?,
                               notes = ?
                           WHERE cycle_id = ?");
    
    $stmt->bind_param("ssiisissssi", 
        $field_location,
        $start_date,
        $nursing_duration,
        $growth_duration,
        $expected_first_harvest,
        $harvest_frequency,
        $expected_end_date,
        $status,
        $notes,
        $cycle_id
    );
    
    if ($stmt->execute()) {
        // Clear existing planned harvests
        clearPlannedHarvests($conn, $cycle_id);
        
        // Regenerate harvest dates if frequency is set
        if ($harvest_frequency) {
            generateHarvestDates($conn, $cycle_id, $expected_first_harvest, $harvest_frequency);
        } else {
            // Just create the first harvest as planned if it doesn't already exist
            $stmt = $conn->prepare("INSERT INTO harvest_records 
                                   (cycle_id, harvest_date, status) 
                                   VALUES (?, ?, 'Planned')
                                   ON DUPLICATE KEY UPDATE status = 'Planned'");
            $stmt->bind_param("is", $cycle_id, $expected_first_harvest);
            $stmt->execute();
        }
        
        return true;
    } else {
        return false;
    }
}

// Delete a crop cycle and all associated records
function deleteCropCycle($conn, $cycle_id) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete associated harvest records
        $stmt = $conn->prepare("DELETE FROM harvest_records WHERE cycle_id = ?");
        $stmt->bind_param("i", $cycle_id);
        $stmt->execute();
        
        // Delete associated farm tasks
        $stmt = $conn->prepare("DELETE FROM farm_tasks WHERE cycle_id = ?");
        $stmt->bind_param("i", $cycle_id);
        $stmt->execute();
        
        // Delete the crop cycle
        $stmt = $conn->prepare("DELETE FROM crop_cycles WHERE cycle_id = ?");
        $stmt->bind_param("i", $cycle_id);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        return false;
    }
}

// Get details of a specific crop cycle
function getCropCycle($conn, $cycle_id) {
    $stmt = $conn->prepare("
        SELECT cc.*, c.crop_name, c.image_path 
        FROM crop_cycles cc
        JOIN crops c ON cc.crop_id = c.crop_id
        WHERE cc.cycle_id = ?
    ");
    
    $stmt->bind_param("i", $cycle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Get all crop cycles
function getAllCropCycles($conn, $filter_status = null) {
    $query = "
        SELECT cc.*, c.crop_name, c.image_path 
        FROM crop_cycles cc
        JOIN crops c ON cc.crop_id = c.crop_id
    ";
    
    if ($filter_status) {
        $query .= " WHERE cc.status = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $filter_status);
    } else {
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cycles = [];
    while ($row = $result->fetch_assoc()) {
        $cycles[] = $row;
    }
    
    return $cycles;
}

// Helper function to clear planned harvests for a cycle
function clearPlannedHarvests($conn, $cycle_id) {
    $stmt = $conn->prepare("DELETE FROM harvest_records 
                           WHERE cycle_id = ? AND status = 'Planned'");
    $stmt->bind_param("i", $cycle_id);
    return $stmt->execute();
}

// Record an actual harvest
function recordHarvest($conn, $cycle_id, $harvest_date, $quantity, $unit, $quality_rating, $notes) {
    // Check if this was a planned harvest
    $stmt = $conn->prepare("SELECT harvest_id FROM harvest_records 
                           WHERE cycle_id = ? AND harvest_date = ? AND status = 'Planned'");
    $stmt->bind_param("is", $cycle_id, $harvest_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update the planned harvest
        $row = $result->fetch_assoc();
        $harvest_id = $row['harvest_id'];
        
        $stmt = $conn->prepare("UPDATE harvest_records 
                               SET quantity = ?, 
                                   unit = ?, 
                                   quality_rating = ?, 
                                   notes = ?,
                                   status = 'Completed'
                               WHERE harvest_id = ?");
        $stmt->bind_param("dsisi", $quantity, $unit, $quality_rating, $notes, $harvest_id);
    } else {
        // Insert as a new harvest
        $stmt = $conn->prepare("INSERT INTO harvest_records 
                               (cycle_id, harvest_date, quantity, unit, quality_rating, notes, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'Completed')");
        $stmt->bind_param("isdsiss", $cycle_id, $harvest_date, $quantity, $unit, $quality_rating, $notes);
    }
    
    return $stmt->execute();
}

// Create a farm task
function createFarmTask($conn, $cycle_id, $task_type_id, $task_name, $scheduled_date, $notes = '') {
    $stmt = $conn->prepare("INSERT INTO farm_tasks 
                           (cycle_id, task_type_id, task_name, scheduled_date, notes) 
                           VALUES (?, ?, ?, ?, ?)");
    
    $stmt->bind_param("iisss", $cycle_id, $task_type_id, $task_name, $scheduled_date, $notes);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        return false;
    }
}

// Update a farm task
function updateFarmTask($conn, $task_id, $task_name, $scheduled_date, $notes, $completion_status = false) {
    $completed_date = null;
    if ($completion_status) {
        $completed_date = date('Y-m-d');
    }
    
    $stmt = $conn->prepare("UPDATE farm_tasks 
                           SET task_name = ?, 
                               scheduled_date = ?, 
                               notes = ?,
                               completion_status = ?,
                               completed_date = ?
                           WHERE task_id = ?");
    
    $stmt->bind_param("sssisi", 
        $task_name, 
        $scheduled_date, 
        $notes, 
        $completion_status, 
        $completed_date, 
        $task_id
    );
    
    return $stmt->execute();
}

// Delete a farm task
function deleteFarmTask($conn, $task_id) {
    $stmt = $conn->prepare("DELETE FROM farm_tasks WHERE task_id = ?");
    $stmt->bind_param("i", $task_id);
    return $stmt->execute();
}

// Mark a task as completed
function completeTask($conn, $task_id) {
    $completed_date = date('Y-m-d');
    
    $stmt = $conn->prepare("UPDATE farm_tasks 
                           SET completion_status = 1, 
                               completed_date = ? 
                           WHERE task_id = ?");
    
    $stmt->bind_param("si", $completed_date, $task_id);
    return $stmt->execute();
}

// Get calendar items (tasks and harvests) for a given date range
function getCalendarItems($conn, $start_date, $end_date) {
    $calendar_items = [];
    
    // Get tasks in date range
    $stmt = $conn->prepare("
        SELECT ft.*, tt.type_name, tt.color_code, cc.field_or_location, c.crop_name
        FROM farm_tasks ft
        JOIN task_types tt ON ft.task_type_id = tt.task_type_id
        JOIN crop_cycles cc ON ft.cycle_id = cc.cycle_id
        JOIN crops c ON cc.crop_id = c.crop_id
        WHERE ft.scheduled_date BETWEEN ? AND ?
    ");
    
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $calendar_items[] = [
            'id' => 'task_' . $row['task_id'],
            'title' => $row['task_name'] . ' (' . $row['crop_name'] . ')',
            'start' => $row['scheduled_date'],
            'color' => $row['color_code'],
            'type' => 'task',
            'status' => $row['completion_status'] ? 'Completed' : 'Pending',
            'notes' => $row['notes'],
            'location' => $row['field_or_location'],
            'item_id' => $row['task_id']
        ];
    }
    
    // Get harvests in date range
    $stmt = $conn->prepare("
        SELECT hr.*, cc.field_or_location, c.crop_name
        FROM harvest_records hr
        JOIN crop_cycles cc ON hr.cycle_id = cc.cycle_id
        JOIN crops c ON cc.crop_id = c.crop_id
        WHERE hr.harvest_date BETWEEN ? AND ?
    ");
    
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $calendar_items[] = [
            'id' => 'harvest_' . $row['harvest_id'],
            'title' => 'Harvest ' . $row['crop_name'],
            'start' => $row['harvest_date'],
            'color' => '#ffcc80', // Harvest color
            'type' => 'harvest',
            'status' => $row['status'],
            'notes' => $row['notes'],
            'location' => $row['field_or_location'],
            'item_id' => $row['harvest_id'],
            'quantity' => $row['quantity'],
            'unit' => $row['unit']
        ];
    }
    
    return $calendar_items;
}

// Get pending tasks for a specific date
function getTasksForDate($conn, $date) {
    $stmt = $conn->prepare("
        SELECT ft.*, tt.type_name, tt.color_code, c.crop_name, cc.field_or_location
        FROM farm_tasks ft
        JOIN task_types tt ON ft.task_type_id = tt.task_type_id
        JOIN crop_cycles cc ON ft.cycle_id = cc.cycle_id
        JOIN crops c ON cc.crop_id = c.crop_id
        WHERE ft.scheduled_date = ?
        ORDER BY c.crop_name, ft.task_name
    ");
    
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    return $tasks;
}

// Get harvests for a specific date
function getHarvestsForDate($conn, $date) {
    $stmt = $conn->prepare("
        SELECT hr.*, c.crop_name, cc.field_or_location
        FROM harvest_records hr
        JOIN crop_cycles cc ON hr.cycle_id = cc.cycle_id
        JOIN crops c ON cc.crop_id = c.crop_id
        WHERE hr.harvest_date = ?
        ORDER BY c.crop_name
    ");
    
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $harvests = [];
    while ($row = $result->fetch_assoc()) {
        $harvests[] = $row;
    }
    
    return $harvests;
}

// Function to get upcoming tasks and harvests for a dashboard
function getUpcomingEvents($conn, $days = 7) {
    $today = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+{$days} days"));
    
    return getCalendarItems($conn, $today, $end_date);
}
?>