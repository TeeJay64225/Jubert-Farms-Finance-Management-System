<?php
session_start();
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['sale_id'])) {
    $sale_id = $_GET['sale_id'];
    $result = $conn->query("SELECT * FROM sales WHERE sale_id=$sale_id");
    $row = $result->fetch_assoc();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sale_id = $_POST['sale_id'];
    $product_name = $_POST['product_name'];
    $amount = $_POST['amount'];
    $sale_date = $_POST['sale_date'];
    $payment_status = $_POST['payment_status'];
    $customer_name = $_POST['customer_name'];
    $notes = $_POST['notes'];

    $sql = "UPDATE sales SET product_name='$product_name', amount='$amount', sale_date='$sale_date', 
            payment_status='$payment_status', customer_name='$customer_name', notes='$notes' 
            WHERE sale_id=$sale_id";

    if ($conn->query($sql) === TRUE) {
        echo "Sale record updated successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
