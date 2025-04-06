<?php
session_start();
include '../config/db.php';

if (isset($_GET['expense_id'])) {
    $expense_id = $_GET['expense_id'];
    $sql = "DELETE FROM expenses WHERE expense_id=$expense_id";

    if ($conn->query($sql) === TRUE) {
        echo "Expense record deleted successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
