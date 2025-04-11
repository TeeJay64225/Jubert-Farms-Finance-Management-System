<?php
ob_start(); // Add this as the first line
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// rest of your code
include 'config/db.php';
require_once 'views/header.php';



// Initialize messages
$success_message = "";
$error_message = "";

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new category
    if (isset($_POST['add_category'])) {
        $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $sql = "INSERT INTO crop_categories (category_name, description) VALUES ('$category_name', '$description')";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "New crop category created successfully";
        } else {
            $error_message = "Error: " . $sql . "<br>" . $conn->error;
        }
    }
    
    // Update existing category
    if (isset($_POST['update_category'])) {
        $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
        $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $sql = "UPDATE crop_categories SET category_name='$category_name', description='$description' WHERE category_id=$category_id";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Crop category updated successfully";
        } else {
            $error_message = "Error: " . $sql . "<br>" . $conn->error;
        }
    }
    
    // Delete category
    if (isset($_POST['delete_category'])) {
        $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
        
        $sql = "DELETE FROM crop_categories WHERE category_id=$category_id";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Crop category deleted successfully";
        } else {
            $error_message = "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Get category for editing if edit_id is provided
$edit_category = null;
if (isset($_GET['edit_id'])) {
    $edit_id = mysqli_real_escape_string($conn, $_GET['edit_id']);
    $result = $conn->query("SELECT * FROM crop_categories WHERE category_id=$edit_id");
    if ($result->num_rows > 0) {
        $edit_category = $result->fetch_assoc();
    }
}

// Fetch all categories
$sql = "SELECT * FROM crop_categories ORDER BY category_name";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Categories Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin-top: 30px;
        }
        .alert {
            margin-bottom: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .table {
            margin-bottom: 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .btn-action {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Crop Categories Management</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Add/Edit Category Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($edit_category): ?>
                                <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="category_name">Category Name</label>
                                <input type="text" class="form-control" id="category_name" name="category_name" 
                                       value="<?php echo $edit_category ? $edit_category['category_name'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_category ? $edit_category['description'] : ''; ?></textarea>
                            </div>
                            
                            <?php if ($edit_category): ?>
                                <button type="submit" name="update_category" class="btn btn-success">Update Category</button>
                                <a href="crop_categories.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Categories List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        Crop Categories List
                    </div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Category Name</th>
                                            <th>Description</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['category_id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                <td><?php echo $row['created_at']; ?></td>
                                                <td>
                                                    <a href="?edit_id=<?php echo $row['category_id']; ?>" class="btn btn-sm btn-warning btn-action">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                        <input type="hidden" name="category_id" value="<?php echo $row['category_id']; ?>">
                                                        <button type="submit" name="delete_category" class="btn btn-sm btn-danger btn-action">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">No crop categories found. Add your first category using the form.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Close connection
$conn->close();
?>