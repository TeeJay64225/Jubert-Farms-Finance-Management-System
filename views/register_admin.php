<?php
include 'config/db.php';

$username = "admin"; // Change this to your preferred username
$password = "admin123"; // Change this to a strong password

$hashed_password = password_hash($password, PASSWORD_DEFAULT); // Consistent hashing

$stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
$stmt->bind_param('ss', $username, $hashed_password);

if ($stmt->execute()) {
    echo "Admin registered successfully!";
} else {
    echo "Error: " . $stmt->error;
}

// Close connection
$stmt->close();
$conn->close();
?>
