<?php
session_start();
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['expense_id'])) {
    $expense_id = $_GET['expense_id'];
    $result = $conn->query("SELECT * FROM expenses WHERE expense_id=$expense_id");
    $row = $result->fetch_assoc();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $expense_id = $_POST['expense_id'];
    $expense_reason = $_POST['expense_reason'];
    $amount = $_POST['amount'];
    $expense_date = $_POST['expense_date'];
    $payment_status = $_POST['payment_status'];
    $vendor_name = $_POST['vendor_name'];
    $notes = $_POST['notes'];

    $sql = "UPDATE expenses SET expense_reason='$expense_reason', amount='$amount', expense_date='$expense_date', 
            payment_status='$payment_status', vendor_name='$vendor_name', notes='$notes' 
            WHERE expense_id=$expense_id";

    if ($conn->query($sql) === TRUE) {
        echo "Expense record updated successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
