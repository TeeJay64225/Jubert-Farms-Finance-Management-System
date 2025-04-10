<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}
require_once __DIR__ . '/../config/helpers.php';
include __DIR__ . '/../config/db.php';

// harvest_functions.php - Functions for harvest record management



// Get all harvest records for a specific crop cycle
function getHarvestRecordsByCycle($conn, $cycle_id) {
    $cycle_id = (int)$cycle_id;
    $sql = "SELECT * FROM harvest_records 
            WHERE cycle_id = ? 
            ORDER BY harvest_date";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cycle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
    
    return $records;
}

// Get harvest records in a date range
function getHarvestsByDateRange($conn, $start_date, $end_date) {
    $sql = "SELECT hr.*, c.crop_name, cc.field_or_location 
            FROM harvest_records hr 
            JOIN crop_cycles cc ON hr.cycle_id = cc.cycle_id
            JOIN crops c ON cc.crop_id = c.crop_id
            WHERE hr.harvest_date BETWEEN ? AND ? 
            ORDER BY hr.harvest_date";
    
    $stmt = $conn->prepare($sql);

    $start = formatDateForMySQL($start_date);
    $end = formatDateForMySQL($end_date);
    
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $harvests = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $harvests[] = $row;
        }
    }
    
    return $harvests;
}


// Get a specific harvest record by ID
function getHarvestById($conn, $harvest_id) {
    $harvest_id = (int)$harvest_id;
    $sql = "SELECT hr.*, c.crop_name 
            FROM harvest_records hr 
            JOIN crop_cycles cc ON hr.cycle_id = cc.cycle_id
            JOIN crops c ON cc.crop_id = c.crop_id
            WHERE hr.harvest_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $harvest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Add a new harvest record
function addHarvestRecord($conn, $data) {
    $sql = "INSERT INTO harvest_records (cycle_id, harvest_date, quantity, unit, quality_rating, notes) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    // Handle optional fields
    $quantity = isset($data['quantity']) ? $data['quantity'] : null;
    $unit = isset($data['unit']) ? $data['unit'] : null;
    $quality_rating = isset($data['quality_rating']) ? $data['quality_rating'] : null;
    
    $stmt->bind_param("isdsss", 
        $data['cycle_id'], 
        formatDateForMySQL($data['harvest_date']), 
        $quantity, 
        $unit, 
        $quality_rating, 
        $data['notes']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

// Update an existing harvest record
function updateHarvestRecord($conn, $harvest_id, $data) {
    $sql = "UPDATE harvest_records 
            SET cycle_id = ?, 
                harvest_date = ?, 
                quantity = ?, 
                unit = ?, 
                quality_rating = ?, 
                notes = ? 
            WHERE harvest_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    // Handle optional fields
    $quantity = isset($data['quantity']) ? $data['quantity'] : null;
    $unit = isset($data['unit']) ? $data['unit'] : null;
    $quality_rating = isset($data['quality_rating']) ? $data['quality_rating'] : null;
    
    $stmt->bind_param("isdsssi", 
        $data['cycle_id'], 
        formatDateForMySQL($data['harvest_date']), 
        $quantity, 
        $unit, 
        $quality_rating, 
        $data['notes'],
        $harvest_id
    );
    
    return $stmt->execute();
}

// Delete a harvest record
function deleteHarvestRecord($conn, $harvest_id) {
    $harvest_id = (int)$harvest_id;
    $sql = "DELETE FROM harvest_records WHERE harvest_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $harvest_id);
    
    return $stmt->execute();
}
?>