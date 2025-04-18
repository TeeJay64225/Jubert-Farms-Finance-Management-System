<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';

function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

// Initialize variables
$edit_mode = false;
$client_id = '';
$full_name = '';
$email = '';
$phone = '';
$address = '';
$notes = '';
$message = '';

// Handle Delete Operation
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $sql = "DELETE FROM clients WHERE client_id=$delete_id";

    if ($conn->query($sql) === TRUE) {
        $message = "<div class='alert alert-success'>Client record deleted successfully!</div>";
        log_action($conn, $_SESSION['user_id'], "Deleted client ID $delete_id");
    }
     else {
        $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}

// Handle Edit Operation - Load Data
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $result = $conn->query("SELECT * FROM clients WHERE client_id=$edit_id");

    if ($result->num_rows > 0) {
        $edit_mode = true;
        $row = $result->fetch_assoc();
        $client_id = $row['client_id'];
        $full_name = $row['full_name'];
        $email = $row['email'];
        $phone = $row['phone_number'];
        $address = $row['address'];
        $notes = $row['notes'];
    }
    log_action($conn, $_SESSION['user_id'], "Loaded client ID $edit_id for editing");
}

// Handle Form Submission - Add or Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $notes = $_POST['notes'];

    // Sanitize inputs
    $full_name = $conn->real_escape_string($full_name);
    $email = $conn->real_escape_string($email);
    $phone = $conn->real_escape_string($phone);
    $address = $conn->real_escape_string($address);
    $notes = $conn->real_escape_string($notes);

    if (isset($_POST['client_id']) && !empty($_POST['client_id'])) {
        // Update existing record
        $client_id = $_POST['client_id'];
        $sql = "UPDATE clients SET 
                full_name='$full_name', 
                email='$email', 
                phone_number='$phone', 
                address='$address', 
                notes='$notes' 
                WHERE client_id=$client_id";

if ($conn->query($sql) === TRUE) {
    $message = "<div class='alert alert-success'>Client record updated successfully!</div>";
    log_action($conn, $_SESSION['user_id'], "Updated client ID $client_id");
    // reset form...
      $edit_mode = false;
            $client_id = '';
            $full_name = '';
            $email = '';
            $phone = '';
            $address = '';
            $notes = '';
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    } else {
        // Add new record
        $sql = "INSERT INTO clients (full_name, email, phone_number, address, notes) 
                VALUES ('$full_name', '$email', '$phone', '$address', '$notes')";

if ($conn->query($sql) === TRUE) {
    $message = "<div class='alert alert-success'>Client added successfully!</div>";
    $new_client_id = $conn->insert_id;
    log_action($conn, $_SESSION['user_id'], "Created new client ID $new_client_id ($full_name)");
    // clear fields...
            $full_name = '';
            $email = '';
            $phone = '';
            $address = '';
            $notes = '';
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    }
}

// Get all client records for display
$sql = "SELECT * FROM clients ORDER BY full_name ASC";
$result = $conn->query($sql);

// Include header
include 'views/header.php';

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        .form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Client Management</h1>

        <?php echo $message; ?>

        <div class="form-section">
            <h3><?php echo $edit_mode ? 'Edit Client' : 'Add New Client'; ?></h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                <div class="mb-3">
                <label for="full_name">Client Name:</label>
<input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $full_name; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>">
                </div>

                <div class="mb-3">
                    <label for="phone">Phone:</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $phone; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="address">Address:</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?php echo $address; ?>">
                </div>

                <div class="mb-3">
                    <label for="notes">Notes:</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $notes; ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Update Client' : 'Add Client'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="clients.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <h3>Client Records</h3>
            <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['client_id']}</td>
                                <td>{$row['full_name']}</td>
                                <td>{$row['email']}</td>
                                <td>{$row['phone_number']}</td>
                                <td>{$row['address']}</td>
                                <td>{$row['notes']}</td>
                                <td>
                                    <a href='clients.php?edit_id={$row['client_id']}' class='btn btn-sm btn-primary'>Edit</a>
                                    <a href='clients.php?delete_id={$row['client_id']}' class='btn btn-sm btn-danger' 
                                       onclick='return confirm(\"Are you sure you want to delete this client?\")'>Delete</a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center'>No clients found</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
include 'views/footer.php';?>
<?php $conn->close(); ?>
