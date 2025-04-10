<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include __DIR__ . '/../config/db.php';

// task_functions.php - Functions for managing farm tasks



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

// Add a new task
function addTask($conn, $cycle_id, $task_type_id, $task_name, $scheduled_date, $notes = '') {
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

// Update an existing task
function updateTask($conn, $task_id, $task_name, $scheduled_date, $notes, $completion_status = null) {
    // Check if completion status is provided
    if ($completion_status !== null) {
        $query = "UPDATE farm_tasks 
                  SET task_name = ?, scheduled_date = ?, notes = ?, completion_status = ?, 
                      completed_date = IF(? = 1, CURDATE(), NULL)
                  WHERE task_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssiii", $task_name, $scheduled_date, $notes, $completion_status, $completion_status, $task_id);
    } else {
        $query = "UPDATE farm_tasks 
                  SET task_name = ?, scheduled_date = ?, notes = ?
                  WHERE task_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $task_name, $scheduled_date, $notes, $task_id);
    }
    
    return $stmt->execute();
}

// Mark a task as complete
function completeTask($conn, $task_id) {
    $stmt = $conn->prepare("UPDATE farm_tasks 
                            SET completion_status = 1, completed_date = CURDATE()
                            WHERE task_id = ?");
    
    $stmt->bind_param("i", $task_id);
    return $stmt->execute();
}

// Delete a task
function deleteTask($conn, $task_id) {
    $stmt = $conn->prepare("DELETE FROM farm_tasks WHERE task_id = ?");
    $stmt->bind_param("i", $task_id);
    return $stmt->execute();
}

// Get all task types
function getTaskTypes($conn) {
    $query = "SELECT * FROM task_types ORDER BY type_name";
    $result = $conn->query($query);
    
    $task_types = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $task_types[] = $row;
        }
    }
    
    return $task_types;
}

// Get tasks for a specific crop cycle
function getTasksByCycle($conn, $cycle_id) {
    $query = "SELECT ft.*, tt.type_name, tt.color_code, tt.icon
              FROM farm_tasks ft
              JOIN task_types tt ON ft.task_type_id = tt.task_type_id
              WHERE ft.cycle_id = ?
              ORDER BY ft.scheduled_date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cycle_id);
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

// Get a specific task by ID
function getTaskById($conn, $task_id) {
    $query = "SELECT ft.*, tt.type_name, tt.color_code, tt.icon,
                     c.crop_name, cc.field_or_location
              FROM farm_tasks ft
              JOIN task_types tt ON ft.task_type_id = tt.task_type_id
              JOIN crop_cycles cc ON ft.cycle_id = cc.cycle_id
              JOIN crops c ON cc.crop_id = c.crop_id
              WHERE ft.task_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}

// Add a new task type
function addTaskType($conn, $type_name, $color_code, $icon, $description = '') {
    $stmt = $conn->prepare("INSERT INTO task_types 
                            (type_name, color_code, icon, description) 
                            VALUES (?, ?, ?, ?)");
    
    $stmt->bind_param("ssss", $type_name, $color_code, $icon, $description);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        return false;
    }
}
?>