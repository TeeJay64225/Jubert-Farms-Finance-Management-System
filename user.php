<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';

// Initialize variables
$edit_mode = false;
$user_id = '';
$username = '';
$password = '';
$role = 'Employee';
$message = '';

// Handle Delete Operation
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $sql = "DELETE FROM users WHERE id=$delete_id";

    if ($conn->query($sql) === TRUE) {
        $message = "<div class='alert alert-success'>User record deleted successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}

// Handle Edit Operation - Load Data
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $result = $conn->query("SELECT * FROM users WHERE id=$edit_id");

    if ($result->num_rows > 0) {
        $edit_mode = true;
        $row = $result->fetch_assoc();
        $user_id = $row['id'];
        $username = $row['username'];
        $role = $row['role'];
    }
}

// Handle Form Submission - Add or Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Sanitize inputs
    $username = $conn->real_escape_string($username);
    $role = $conn->real_escape_string($role);

    // Hash the password before saving
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        // Update existing record
        $user_id = $_POST['user_id'];
        $sql = "UPDATE users SET 
                username='$username', 
                password='$password_hash', 
                role='$role' 
                WHERE id=$user_id";

        if ($conn->query($sql) === TRUE) {
            $message = "<div class='alert alert-success'>User record updated successfully!</div>";
            $edit_mode = false;
            $user_id = '';
            $username = '';
            $password = '';
            $role = 'Employee';
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    } else {
        // Add new record
        $sql = "INSERT INTO users (username, password, role) 
                VALUES ('$username', '$password_hash', '$role')";

        if ($conn->query($sql) === TRUE) {
            $message = "<div class='alert alert-success'>User added successfully!</div>";
            $username = '';
            $password = '';
            $role = 'Employee';
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    }
}

// Get all user records for display
$sql = "SELECT * FROM users ORDER BY username ASC";
$result = $conn->query($sql);

// Include header
include 'views/header.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
        <h1 class="mb-4">User Management</h1>

        <?php echo $message; ?>

        <div class="form-section">
            <h3><?php echo $edit_mode ? 'Edit User' : 'Add New User'; ?></h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                <div class="mb-3">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password">Password:</label>
                    <input type="password" class="form-control" id="password" name="password" value="<?php echo $password; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="role">Role:</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="Admin" <?php echo $role == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="Manager" <?php echo $role == 'Manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="Employee" <?php echo $role == 'Employee' ? 'selected' : ''; ?>>Employee</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Update User' : 'Add User'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <h3>User Records</h3>
            <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['id']}</td>
                                <td>{$row['username']}</td>
                                <td>{$row['role']}</td>
                                <td>
                                    <a href='user.php?edit_id={$row['id']}' class='btn btn-sm btn-primary'>Edit</a>
                                    <a href='user.php?delete_id={$row['id']}' class='btn btn-sm btn-danger' 
                                       onclick='return confirm(\"Are you sure you want to delete this user?\")'>Delete</a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' class='text-center'>No users found</td></tr>";
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
