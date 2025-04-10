<?php
session_start();
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = $_POST['product_name'];
    $amount = $_POST['amount'];
    $sale_date = $_POST['sale_date'];
    $payment_status = $_POST['payment_status'];
    $customer_name = $_POST['customer_name'];
    $notes = $_POST['notes'];

    $sql = "INSERT INTO sales (product_name, amount, sale_date, payment_status, customer_name, notes)
            VALUES ('$product_name', '$amount', '$sale_date', '$payment_status', '$customer_name', '$notes')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Sale record added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
