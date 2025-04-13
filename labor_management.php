

<?php
ob_start(); // Add this as the first line
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// rest of your code
include 'config/db.php';
require_once 'views/header.php';



// Handle form submissions for adding/editing categories
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        // Add new labor category
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $fee = mysqli_real_escape_string($conn, $_POST['fee_per_head']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Changed 'name' to 'category_name' to match the table structure
        $sql = "INSERT INTO labor_categories (category_name, fee_per_head, description) 
                VALUES ('$name', '$fee', '$description')";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "Labor category added successfully!";
        } else {
            $error_message = "Error: " . mysqli_error($conn);
        }
    } elseif (isset($_POST['edit_category'])) {
        // Edit existing labor category
        $id = mysqli_real_escape_string($conn, $_POST['category_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $fee = mysqli_real_escape_string($conn, $_POST['fee_per_head']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Changed 'name' to 'category_name' and 'id' to 'category_id'
        $sql = "UPDATE labor_categories 
                SET category_name = '$name', fee_per_head = '$fee', description = '$description' 
                WHERE category_id = $id";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "Labor category updated successfully!";
        } else {
            $error_message = "Error: " . mysqli_error($conn);
        }
    }
}
// Handle delete requests
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $sql = "DELETE FROM labor_categories WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        $success_message = "Labor category deleted successfully!";
    } else {
        $error_message = "Error: " . mysqli_error($conn);
    }
}

// Fetch all labor categories
$sql = "SELECT * FROM labor_categories ORDER BY category_name ASC";
$result = mysqli_query($conn, $sql);
$categories = [];

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="assets/css/labor_management_styles.css">

</head>
<body>

<div class="container-fluid px-4">
    <h1 class="mt-4">Labor Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Labor Management</li>
    </ol>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div>
            <!--    <a href="add_labor.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-tags"></i> add labor
                </a>
            </div>-->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-users-cog me-1"></i> Labor Categories</div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="laborCategoriesTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Fee Per Head (GHS)</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
    <tr>
        <td><?php echo $category['category_id']; ?></td>
        <td><?php echo htmlspecialchars($category['category_name']); ?></td>
        <td><?php echo number_format($category['fee_per_head'], 2); ?></td>
        <td><?php echo htmlspecialchars($category['description']); ?></td>
        <td>
    <button class="btn btn-sm btn-info edit-btn" 
            data-id="<?php echo $category['category_id']; ?>"
            data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
            data-fee="<?php echo $category['fee_per_head']; ?>"
            data-description="<?php echo htmlspecialchars($category['description']); ?>"
            data-bs-toggle="modal" data-bs-target="#editCategoryModal">
        <i class="fas fa-edit"></i>
    </button>
                            <a href="labor_management.php?delete=true&id=<?php echo $category['category_id']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Are you sure you want to delete this category?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Labor Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="fee_per_head" class="form-label">Fee Per Head (GHS)</label>
                        <input type="number" class="form-control" id="fee_per_head" name="fee_per_head" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Labor Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_fee_per_head" class="form-label">Fee Per Head (GHS)</label>
                        <input type="number" class="form-control" id="edit_fee_per_head" name="fee_per_head" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#laborCategoriesTable').DataTable();
        
        // Handle edit button clicks
        $('.edit-btn').click(function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const fee = $(this).data('fee');
            const description = $(this).data('description');
            
            $('#edit_category_id').val(id);
            $('#edit_name').val(name);
            $('#edit_fee_per_head').val(fee);
            $('#edit_description').val(description);
        });
    });
</script>


</body>
</html>
<?php
include 'views/footer.php';?>
<?php $conn->close(); ?>