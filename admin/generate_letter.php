<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $letter_type = $_POST['letter_type'];
    $letter_content = $_POST['letter_content'];

    $stmt = $conn->prepare("INSERT INTO letters (employee_id, letter_type, letter_content) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $employee_id, $letter_type, $letter_content);

    if ($stmt->execute()) {
        echo "Letter generated successfully!";
        // Log action
        $user_id = $_SESSION['user_id'];
        $action = "Generated a $letter_type letter for employee ID $employee_id";
        $conn->query("INSERT INTO audit_logs (user_id, action) VALUES ($user_id, '$action')");
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>
