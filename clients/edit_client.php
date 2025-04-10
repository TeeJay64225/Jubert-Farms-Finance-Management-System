<?php
session_start();
include '../config/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Client ID is missing.");
}

$client_id = intval($_GET['id']);

// Fetch client details
$sql = "SELECT * FROM clients WHERE client_id = $client_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Client not found.");
}

$client = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $phone_number = $conn->real_escape_string($_POST['phone_number']);
    $address = $conn->real_escape_string($_POST['address']);

    $sql = "UPDATE clients SET full_name = '$full_name', phone_number = '$phone_number', address = '$address' WHERE client_id = $client_id";

    if ($conn->query($sql) === TRUE) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error updating record: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Client</h2>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($client['full_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($client['phone_number']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($client['address']) ?>" required>
        </div>
        <button type="submit" class="btn btn-warning">Update Client</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
