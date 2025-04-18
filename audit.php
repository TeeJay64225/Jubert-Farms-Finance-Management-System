<?php
// audit_log.php - Place this in your includes or utilities folder

/**
 * Records a user action in the audit_logs table
 * 
 * @param int $user_id - The ID of the user performing the action
 * @param string $action - Description of the action performed
 * @param mysqli $conn - Database connection object (optional if using global $conn)
 * @return bool - True if successfully logged, false otherwise
 */
function log_user_action($user_id, $action, $conn = null) {
    // Use global connection if not provided
    if ($conn === null) {
        global $conn;
        if (!$conn) {
            // If no connection available, try to establish one
            require_once(__DIR__ . '/../config/db_connect.php');
        }
    }
    
    // Ensure we have a valid connection
    if (!$conn) {
        error_log("Database connection not available in log_user_action()");
        return false;
    }
    
    // Prepare the query
    $query = "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Failed to prepare statement in log_user_action(): " . $conn->error);
        return false;
    }
    
    // Bind parameters and execute
    $stmt->bind_param('is', $user_id, $action);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Failed to execute statement in log_user_action(): " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

/**
 * Example usage:
 * 
 * // At the top of your PHP files that need logging
 * require_once('audit_log.php');
 * 
 * // When an action occurs
 * log_user_action($_SESSION['user_id'], "Created new employee: John Doe (ID: 123)");
 * log_user_action($_SESSION['user_id'], "Updated expense category: Food/Water");
 * log_user_action($_SESSION['user_id'], "Deleted asset: Laptop (ID: 456)");
 * log_user_action($_SESSION['user_id'], "Generated payroll for March 2025");
 */
?>