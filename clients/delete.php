<?php
session_start();
include '../config/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Client ID is missing.");
}

$client_id = intval($_GET['id']);

$sql = "DELETE FROM clients WHERE client_id = $client_id";

if ($conn->query($sql) === TRUE) {
    header("Location: index.php");
    exit();
} else {
    echo "Error deleting record: " . $conn->error;
}
?>
