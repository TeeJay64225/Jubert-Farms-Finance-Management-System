<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}

include 'config/db.php'; // ✅ First include the DB connection

// ✅ Then define the function
function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

// ✅ Then use it
if (isset($_SESSION['user_id'])) {
    log_action($conn, $_SESSION['user_id'], "Accessed expense management page");
}
// Handle product deletion
if (isset($_GET['delete_id']) && $_GET['delete_id'] > 0) {
    $delete_id = (int)$_GET['delete_id'];
    $deleteQuery = mysqli_query($conn, "UPDATE chemical_products SET is_active = 0 WHERE product_id = $delete_id");
    
    if ($deleteQuery) {
        $success = "Product deactivated successfully.";
    } else {
        $error = "Error deactivating product: " . mysqli_error($conn);
    }
}

// Handle view product details
$viewProduct = null;
if (isset($_GET['view_id']) && $_GET['view_id'] > 0) {
    $view_id = (int)$_GET['view_id'];
    $viewQuery = mysqli_query($conn, "SELECT cp.*, s.company_name, s.phone, s.email, s.address, pc.category_name,
                                    (SELECT SUM(quantity) FROM chemical_inventory WHERE product_id = cp.product_id) as current_stock
                                    FROM chemical_products cp 
                                    JOIN suppliers s ON cp.supplier_id = s.supplier_id 
                                    LEFT JOIN product_categories pc ON cp.category_id = pc.category_id 
                                    WHERE cp.product_id = $view_id");
    $viewProduct = mysqli_fetch_assoc($viewQuery);
}

// Handle edit product form submission
if (isset($_POST['update_product'])) {
    $product_id = (int)$_POST['product_id'];
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category_id = (int)$_POST['category_id'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $unit_of_measure = mysqli_real_escape_string($conn, $_POST['unit_of_measure']);
    $price_per_unit = (float)$_POST['price_per_unit'];
    $application_rate = mysqli_real_escape_string($conn, $_POST['application_rate']);
    $safety_info = mysqli_real_escape_string($conn, $_POST['safety_info']);
    $composition = mysqli_real_escape_string($conn, $_POST['composition']);
    $registration_number = mysqli_real_escape_string($conn, $_POST['registration_number']);
    
    $updateProduct = mysqli_query($conn, "UPDATE chemical_products SET
                                        product_name = '$product_name',
                                        category_id = $category_id,
                                        description = '$description',
                                        unit_of_measure = '$unit_of_measure',
                                        price_per_unit = $price_per_unit,
                                        application_rate = '$application_rate',
                                        safety_info = '$safety_info',
                                        composition = '$composition',
                                        registration_number = '$registration_number'
                                        WHERE product_id = $product_id");
                                        
    if ($updateProduct) {
        $success = "Product updated successfully!";
    } else {
        $error = "Error updating product: " . mysqli_error($conn);
    }
}

// Handle edit product form display
$editProduct = null;
$categories = null;
if (isset($_GET['edit_id']) && $_GET['edit_id'] > 0) {
    $edit_id = (int)$_GET['edit_id'];
    $editQuery = mysqli_query($conn, "SELECT * FROM chemical_products WHERE product_id = $edit_id");
    $editProduct = mysqli_fetch_assoc($editQuery);
    
    // Fetch product categories for dropdown
    $categories = mysqli_query($conn, "SELECT * FROM product_categories ORDER BY category_name");
}

// Handle add inventory submission
if (isset($_POST['add_inventory'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (float)$_POST['quantity'];
    $purchase_date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $purchase_price = (float)$_POST['purchase_price'];
    
    $insertInventory = mysqli_query($conn, "INSERT INTO chemical_inventory 
                                        (product_id, quantity, purchase_date, purchase_price) 
                                        VALUES 
                                        ($product_id, $quantity, '$purchase_date', $purchase_price)");
                                        
    if ($insertInventory) {
        $success = "Inventory added successfully!";
    } else {
        $error = "Error adding inventory: " . mysqli_error($conn);
    }
}

// Handle add inventory form display
$addInventoryProduct = null;
if (isset($_GET['add_inventory_id']) && $_GET['add_inventory_id'] > 0) {
    $inventory_id = (int)$_GET['add_inventory_id'];
    $inventoryQuery = mysqli_query($conn, "SELECT cp.*, s.company_name 
                                        FROM chemical_products cp 
                                        JOIN suppliers s ON cp.supplier_id = s.supplier_id 
                                        WHERE cp.product_id = $inventory_id");
    $addInventoryProduct = mysqli_fetch_assoc($inventoryQuery);
}

// Fetch all active chemical products with supplier info
$productsQuery = "SELECT cp.*, s.company_name, pc.category_name, 
                 (SELECT SUM(quantity) FROM chemical_inventory WHERE product_id = cp.product_id) as current_stock 
                 FROM chemical_products cp 
                 JOIN suppliers s ON cp.supplier_id = s.supplier_id 
                 LEFT JOIN product_categories pc ON cp.category_id = pc.category_id 
                 WHERE cp.is_active = 1 
                 ORDER BY cp.product_name";
$products = mysqli_query($conn, $productsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chemical Products List</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .btn-action {
            margin-right: 5px;
        }
        .stock-low {
            color: #dc3545;
            font-weight: bold;
        }
        .stock-ok {
            color: #198754;
        }
        .form-label {
            font-weight: 500;
        }
        .required::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <?php include 'views/header.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-flask"></i> Chemical Products List</h2>
                        <p class="text-muted">View and manage all chemical products in inventory</p>
                    </div>
                    <div>
                        <a href="add_chem.php" class="btn btn-success">
                            <i class="fas fa-plus-circle"></i> Add New Product
                        </a>
                    </div>
                </div>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <!-- Chemical Products List -->
            <div class="col-lg-<?php echo (isset($viewProduct) || isset($editProduct) || isset($addInventoryProduct)) ? '8' : '12'; ?>">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Chemical Products Inventory</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Stock</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($products) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($products)): ?>
                                            <tr>
                                                <td><?php echo $row['product_name']; ?></td>
                                                <td><?php echo $row['category_name']; ?></td>
                                                <td><?php echo $row['company_name']; ?></td>
                                                <td class="<?php echo ($row['current_stock'] < 5) ? 'stock-low' : 'stock-ok'; ?>">
                                                    <?php echo $row['current_stock'] ? $row['current_stock'] . ' ' . $row['unit_of_measure'] : 'No stock'; ?>
                                                </td>
                                                <td>
                                                    <a href="?view_id=<?php echo $row['product_id']; ?>" class="btn btn-sm btn-info btn-action">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?edit_id=<?php echo $row['product_id']; ?>" class="btn btn-sm btn-warning btn-action">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete_id=<?php echo $row['product_id']; ?>" class="btn btn-sm btn-danger btn-action" 
                                                       onclick="return confirm('Are you sure you want to deactivate this product?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <a href="?add_inventory_id=<?php echo $row['product_id']; ?>" class="btn btn-sm btn-success btn-action">
                                                        <i class="fas fa-plus"></i> Stock
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No chemical products found. Add your first product!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if(isset($viewProduct)): ?>
            <!-- View Product Details -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-eye"></i> View Product Details</h5>
                    </div>
                    <div class="card-body">
                        <h4><?php echo $viewProduct['product_name']; ?></h4>
                        <p class="badge bg-primary"><?php echo $viewProduct['category_name']; ?></p>
                        
                        <div class="mb-3">
                            <strong>Description:</strong>
                            <p><?php echo $viewProduct['description'] ?: 'No description available'; ?></p>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Unit of Measure:</strong>
                                <p><?php echo $viewProduct['unit_of_measure']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Price Per Unit:</strong>
                                <p>GHS <?php echo number_format($viewProduct['price_per_unit'], 2); ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Current Stock:</strong>
                                <p class="<?php echo ($viewProduct['current_stock'] < 5) ? 'stock-low' : 'stock-ok'; ?>">
                                    <?php echo $viewProduct['current_stock'] ? $viewProduct['current_stock'] . ' ' . $viewProduct['unit_of_measure'] : 'No stock'; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <strong>Application Rate:</strong>
                                <p><?php echo $viewProduct['application_rate'] ?: 'Not specified'; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Registration Number:</strong>
                            <p><?php echo $viewProduct['registration_number'] ?: 'Not specified'; ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Chemical Composition:</strong>
                            <p><?php echo $viewProduct['composition'] ?: 'Not specified'; ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Safety Information:</strong>
                            <p><?php echo $viewProduct['safety_info'] ?: 'No safety information available'; ?></p>
                        </div>
                        
                        <h5 class="mt-4 border-top pt-3">Supplier Information</h5>
                        <div class="mb-3">
                            <strong>Company:</strong>
                            <p><?php echo $viewProduct['company_name']; ?></p>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Phone:</strong>
                                <p><?php echo $viewProduct['phone']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Email:</strong>
                                <p><?php echo $viewProduct['email']; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Address:</strong>
                            <p><?php echo $viewProduct['address']; ?></p>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="chem_supply.php" class="btn btn-secondary">Back to List</a>
                            <a href="?edit_id=<?php echo $viewProduct['product_id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit Product
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(isset($editProduct) && $categories): ?>
            <!-- Edit Product Form -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Product</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <input type="hidden" name="product_id" value="<?php echo $editProduct['product_id']; ?>">
                            
                            <div class="mb-3">
                                <label for="product_name" class="form-label required">Product Name</label>
                                <input type="text" class="form-control" id="product_name" name="product_name" 
                                       value="<?php echo $editProduct['product_name']; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label required">Category</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" <?php echo ($cat['category_id'] == $editProduct['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo $cat['category_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="unit_of_measure" class="form-label required">Unit of Measure</label>
                                        <select class="form-select" id="unit_of_measure" name="unit_of_measure" required>
                                            <option value="kg" <?php echo ($editProduct['unit_of_measure'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                            <option value="g" <?php echo ($editProduct['unit_of_measure'] == 'g') ? 'selected' : ''; ?>>Gram (g)</option>
                                            <option value="L" <?php echo ($editProduct['unit_of_measure'] == 'L') ? 'selected' : ''; ?>>Liter (L)</option>
                                            <option value="ml" <?php echo ($editProduct['unit_of_measure'] == 'ml') ? 'selected' : ''; ?>>Milliliter (ml)</option>
                                            <option value="bottle" <?php echo ($editProduct['unit_of_measure'] == 'bottle') ? 'selected' : ''; ?>>Bottle</option>
                                            <option value="packet" <?php echo ($editProduct['unit_of_measure'] == 'packet') ? 'selected' : ''; ?>>Packet</option>
                                            <option value="bag" <?php echo ($editProduct['unit_of_measure'] == 'bag') ? 'selected' : ''; ?>>Bag</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="price_per_unit" class="form-label required">Price Per Unit</label>
                                        <div class="input-group">
                                            <span class="input-group-text">GHS</span>
                                            <input type="number" step="0.01" min="0" class="form-control" id="price_per_unit" 
                                                   name="price_per_unit" value="<?php echo $editProduct['price_per_unit']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"><?php echo $editProduct['description']; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="application_rate" class="form-label">Application Rate</label>
                                        <input type="text" class="form-control" id="application_rate" name="application_rate" 
                                               value="<?php echo $editProduct['application_rate']; ?>" placeholder="e.g. 5kg per acre">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="registration_number" class="form-label">Registration Number</label>
                                        <input type="text" class="form-control" id="registration_number" name="registration_number"
                                               value="<?php echo $editProduct['registration_number']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="composition" class="form-label">Chemical Composition</label>
                                <textarea class="form-control" id="composition" name="composition" rows="2" 
                                          placeholder="e.g. N-P-K: 17-17-17"><?php echo $editProduct['composition']; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="safety_info" class="form-label">Safety Information</label>
                                <textarea class="form-control" id="safety_info" name="safety_info" rows="2" 
                                          placeholder="Safety precautions, handling instructions, etc."><?php echo $editProduct['safety_info']; ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="chem_supply.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_product" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(isset($addInventoryProduct)): ?>
            <!-- Add Inventory Form -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-plus"></i> Add Inventory</h5>
                    </div>
                    <div class="card-body">
                        <h4><?php echo $addInventoryProduct['product_name']; ?></h4>
                        <p class="text-muted">Supplier: <?php echo $addInventoryProduct['company_name']; ?></p>
                        
                        <form action="" method="post">
                            <input type="hidden" name="product_id" value="<?php echo $addInventoryProduct['product_id']; ?>">
                            
                            <div class="mb-3">
                                <label for="quantity" class="form-label required">Quantity (<?php echo $addInventoryProduct['unit_of_measure']; ?>)</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="quantity" name="quantity" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="purchase_date" class="form-label required">Purchase Date</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="purchase_price" class="form-label required">Total Purchase Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">GHS</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="purchase_price" name="purchase_price" required>
                                </div>
                                <div class="form-text">
                                    Unit price: GHS <?php echo number_format($addInventoryProduct['price_per_unit'], 2); ?> per <?php echo $addInventoryProduct['unit_of_measure']; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="chem_supply.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="add_inventory" class="btn btn-success">
                                    <i class="fas fa-plus-circle"></i> Add to Inventory
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate purchase price based on quantity and unit price
        <?php if(isset($addInventoryProduct)): ?>
        document.getElementById('quantity').addEventListener('input', function() {
            const quantity = parseFloat(this.value) || 0;
            const unitPrice = <?php echo $addInventoryProduct['price_per_unit']; ?>;
            const totalPrice = quantity * unitPrice;
            document.getElementById('purchase_price').value = totalPrice.toFixed(2);
        });
        <?php endif; ?>
    </script>
</body>
</html>