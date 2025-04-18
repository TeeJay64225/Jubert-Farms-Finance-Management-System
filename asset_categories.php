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

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new category
            $category_name = $_POST['category_name'];
            $description = $_POST['description'];

            $sql = "INSERT INTO asset_categories (category_name, description)
                    VALUES ('$category_name', '$description')";
            
            if ($conn->query($sql) === TRUE) {
                $message = "Asset category added successfully!";
                log_action($conn, $_SESSION['user_id'], "Added asset category: $category_name");
            } else {
                $error = "Error: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'update') {
            // Update existing category
            $category_id = $_POST['category_id'];
            $category_name = $_POST['category_name'];
            $description = $_POST['description'];

            $sql = "UPDATE asset_categories SET category_name='$category_name', description='$description' 
                    WHERE category_id=$category_id";

            if ($conn->query($sql) === TRUE) {
                $message = "Asset category updated successfully!";
                log_action($conn, $_SESSION['user_id'], "Updated asset category ID $category_id");
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    // Check if category is in use
    $check_sql = "SELECT COUNT(*) as count FROM assets WHERE category_id = $category_id";
    $check_result = $conn->query($check_sql);
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['count'] > 0) {
        $error = "Cannot delete this category. It is being used by " . $check_data['count'] . " asset records.";
    } else {
        $sql = "DELETE FROM asset_categories WHERE category_id=$category_id";
        
        if ($conn->query($sql) === TRUE) {
            $message = "Asset category deleted successfully!";
            log_action($conn, $_SESSION['user_id'], "Deleted asset category ID $category_id");
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Edit category
$edit_data = null;
if (isset($_GET['edit'])) {
    $category_id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM asset_categories WHERE category_id=$category_id");
    $edit_data = $result->fetch_assoc();
}

// Fetch all categories
$sql = "SELECT c.*, COUNT(a.asset_id) as asset_count
        FROM asset_categories c
        LEFT JOIN assets a ON c.category_id = a.category_id
        GROUP BY c.category_id
        ORDER BY c.category_name";
$result = $conn->query($sql);
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Include header
include 'views/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Asset Categories</h1>
        <div>
            <a href="assets.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-list"></i> Asset List
            </a>
            <a href="asset_report.php" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar"></i> Asset Reports
            </a>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <?= $edit_data ? 'Edit Category' : 'Add New Category' ?>
                </div>
                <div class="card-body">
                    <form method="post" action="asset_categories.php">
                        <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'add' ?>">
                        <?php if ($edit_data): ?>
                            <input type="hidden" name="category_id" value="<?= $edit_data['category_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" 
                                   value="<?= $edit_data ? $edit_data['category_name'] : '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= $edit_data ? $edit_data['description'] : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary"><?= $edit_data ? 'Update' : 'Add' ?> Category</button>
                            <?php if ($edit_data): ?>
                                <a href="asset_categories.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Category List
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Asset Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($categories) > 0): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?= $category['category_id'] ?></td>
                                            <td><?= $category['category_name'] ?></td>
                                            <td><?= $category['description'] ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= $category['asset_count'] ?></span>
                                            </td>
                                            <td>
                                                <a href="asset_categories.php?edit=<?= $category['category_id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="asset_categories.php?delete=<?= $category['category_id'] ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                                   <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No categories found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
include 'views/footer.php';
$conn->close();
?>