<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
include 'config/db.php';
require_once 'views/header.php';


function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}


// Fetch labor categories for dropdown
$category_sql = "SELECT category_id, category_name FROM labor_categories ORDER BY category_name ASC";
$category_result = mysqli_query($conn, $category_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $labor_date = $_POST['labor_date'];
    $worker_name = $_POST['worker_name'];
    $hours_worked = $_POST['hours_worked'];
    $hourly_rate = $_POST['hourly_rate'];
    $category_id = $_POST['category_id'];
    $task_description = $_POST['task_description'];
    $payment_status = $_POST['payment_status'];
    $payment_date = $_POST['payment_date'] ?? null;
    $notes = $_POST['notes'];

    $total_amount = $hours_worked * $hourly_rate;

    $stmt = $conn->prepare("INSERT INTO labor (labor_date, worker_name, hours_worked, hourly_rate, total_amount, category_id, task_description, payment_status, payment_date, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssdddissss", $labor_date, $worker_name, $hours_worked, $hourly_rate, $total_amount, $category_id, $task_description, $payment_status, $payment_date, $notes);

    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>Labor entry added successfully!</div>";
        log_action($conn, $_SESSION['user_id'], "Added labor record for {$worker_name} on {$labor_date}.");

    } else {
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }

    $stmt->close();
}
?>

<div class="container mt-4">
    <h2>Add Labor Entry</h2>
    <form method="POST" action="">
        <div class="mb-3">
            <label for="labor_date" class="form-label">Date</label>
            <input type="date" class="form-control" name="labor_date" required>
        </div>

        <div class="mb-3">
            <label for="worker_name" class="form-label">Worker Name</label>
            <input type="text" class="form-control" name="worker_name" required>
        </div>

        <div class="mb-3">
            <label for="hours_worked" class="form-label">Hours Worked</label>
            <input type="number" step="0.01" class="form-control" name="hours_worked" required>
        </div>

        <div class="mb-3">
            <label for="hourly_rate" class="form-label">Hourly Rate</label>
            <input type="number" step="0.01" class="form-control" name="hourly_rate" required>
        </div>

        <div class="mb-3">
            <label for="category_id" class="form-label">Labor Category</label>
            <select class="form-control" name="category_id" required>
                <option value="">Select Category</option>
                <?php while ($row = mysqli_fetch_assoc($category_result)): ?>
                    <option value="<?= $row['category_id'] ?>"><?= htmlspecialchars($row['category_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="task_description" class="form-label">Task Description</label>
            <textarea class="form-control" name="task_description"></textarea>
        </div>

        <div class="mb-3">
            <label for="payment_status" class="form-label">Payment Status</label>
            <select class="form-control" name="payment_status" required>
                <option value="Not Paid">Not Paid</option>
                <option value="Paid">Paid</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="payment_date" class="form-label">Payment Date (optional)</label>
            <input type="date" class="form-control" name="payment_date">
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" name="notes"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Add Labor Entry</button>
    </form>
</div>

<?php require_once 'views/footer.php'; ?>
