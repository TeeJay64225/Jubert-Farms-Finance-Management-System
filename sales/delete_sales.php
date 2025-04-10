<?php
session_start();
include '../config/db.php';

if (isset($_GET['sale_id'])) {
    $sale_id = $_GET['sale_id'];
    $sql = "DELETE FROM sales WHERE sale_id=$sale_id";

    if ($conn->query($sql) === TRUE) {
        echo "Sale record deleted successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
