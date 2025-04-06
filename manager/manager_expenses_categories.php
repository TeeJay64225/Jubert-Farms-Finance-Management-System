<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();


include '../config/db.php';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new category
            $category_name = $_POST['category_name'];
            $description = $_POST['description'];

            $sql = "INSERT INTO expense_categories (category_name, description)
                    VALUES ('$category_name', '$description')";
            
            if ($conn->query($sql) === TRUE) {
                $message = "Category added successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'update') {
            // Update existing category
            $category_id = $_POST['category_id'];
            $category_name = $_POST['category_name'];
            $description = $_POST['description'];

            $sql = "UPDATE expense_categories SET category_name='$category_name', description='$description' 
                    WHERE category_id=$category_id";

            if ($conn->query($sql) === TRUE) {
                $message = "Category updated successfully!";
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
    $check_sql = "SELECT COUNT(*) as count FROM expenses WHERE category_id = $category_id";
    $check_result = $conn->query($check_sql);
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['count'] > 0) {
        $error = "Cannot delete this category. It is being used by " . $check_data['count'] . " expense records.";
    } else {
        $sql = "DELETE FROM expense_categories WHERE category_id=$category_id";
        
        if ($conn->query($sql) === TRUE) {
            $message = "Category deleted successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Edit category
$edit_data = null;
if (isset($_GET['edit'])) {
    $category_id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM expense_categories WHERE category_id=$category_id");
    $edit_data = $result->fetch_assoc();
}

// Fetch all categories
$sql = "SELECT c.*, COUNT(e.expense_id) as expense_count, SUM(e.amount) as total_amount 
        FROM expense_categories c
        LEFT JOIN expenses e ON c.category_id = e.category_id
        GROUP BY c.category_id
        ORDER BY c.category_name";
$result = $conn->query($sql);
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Include header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
               :root {
            --primary-color: #2c6e49;
            --secondary-color: #4c956c;
            --accent-color: #fefee3;
            --light-color: #f0f3f5;
            --dark-color: #1a3a1a;
        }
        
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 0.8rem 1.5rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
     
        
        .footer {
            background-color: var(--light-color);
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
        }

         /* New styles specifically for the navbar logo container */
 .logo-container-nav {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: black;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
    }
    
    .logo-nav {
        width: 100%;
        height: auto;
    }
    
    /* Keep your original logo-container for the login/register pages */
    .logo-container {
        text-align: center;
        margin: 0 auto 20px;
        width: 150px;
        height: 120px;
        border-radius: 50%;
        background-color: black;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
    }

        .form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
        <!-- Navbar -->
        <nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand d-flex align-items-center">
            <div class="logo-container-nav me-2">
                <img src="../assets/logo2.JPG" alt="Farm Logo" class="logo-nav">
            </div>
            Jubert Farms Finance 
        </span>
            <!-- Main Navigation Links -->
            <div class="me-4">
            <a href="../manager/manager_dashboard.php" class="btn btn-outline-light me-2"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="../manager/manager_sale.php" class="btn btn-outline-light me-2"><i class="fas fa-dollar-sign"></i> Sales</a>
                <a href="../manager/manager_expense.php" class="btn btn-outline-light me-2"><i class="fas fa-receipt"></i> Expenses</a>
                <a href="../manager/manager_report.php" class="btn btn-outline-light"><i class="fas fa-file-alt"></i> Reports</a>
</div>
            <!-- User Info and Logout -->
            <div>
            <span class="text-white me-3"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['manager_name'] ?? 'Manager'; ?></span>
                <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Expense Categories</h1>
        <a href="../manager/manager_expense.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Expenses
        </a>
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
                <div class="card-header">
                    <?= $edit_data ? 'Edit Category' : 'Add New Category' ?>
                </div>
                <div class="card-body">
                    <form method="post" action="../manager/manager_expenses_categories.php">
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
                                <a href="../manager/manager_expenses_categories.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
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
                                    <th>Expense Count</th>
                                    <th>Total Amount</th>
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
                                            <td><?= $category['expense_count'] ?></td>
                                            <td><?= $category['expense_count'] > 0 ? 'GHS' . number_format($category['total_amount'], 2) : '-' ?></td>
                                            <td>
                                                <a href="../manager/manager_expenses_categories.php?edit=<?= $category['category_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="../manager/manager_expenses_categories.php?delete=<?= $category['category_id'] ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No categories found</td>
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