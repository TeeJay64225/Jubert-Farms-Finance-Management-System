<?php
session_start();
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $expense_reason = $_POST['expense_reason'];
    $amount = $_POST['amount'];
    $expense_date = $_POST['expense_date'];
    $payment_status = $_POST['payment_status'];
    $vendor_name = $_POST['vendor_name'];
    $notes = $_POST['notes'];

    $sql = "INSERT INTO expenses (expense_reason, amount, expense_date, payment_status, vendor_name, notes)
            VALUES ('$expense_reason', '$amount', '$expense_date', '$payment_status', '$vendor_name', '$notes')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Expense record added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
