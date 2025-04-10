<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database configuration
$servername = "localhost"; // Change if using a remote server
$username = "root"; // Your MySQL username
$password = ""; // Your MySQL password
$database = "farm_finance"; // change this to the correct DB name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



// config.php - Database configuration file

?>
