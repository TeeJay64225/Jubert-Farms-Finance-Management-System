<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Only allow Admin users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}

include 'config/db.php';

// Handle form submission for adding a new chemical product
if (isset($_POST['add_product'])) {
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $category_id = (int)$_POST['category_id'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $unit_of_measure = mysqli_real_escape_string($conn, $_POST['unit_of_measure']);
    $price_per_unit = (float)$_POST['price_per_unit'];
    $application_rate = mysqli_real_escape_string($conn, $_POST['application_rate']);
    $safety_info = mysqli_real_escape_string($conn, $_POST['safety_info']);
    $composition = mysqli_real_escape_string($conn, $_POST['composition']);
    $registration_number = mysqli_real_escape_string($conn, $_POST['registration_number']);
    
    // Check if company exists first
    $checkCompany = mysqli_query($conn, "SELECT supplier_id FROM suppliers WHERE company_name = '$company_name'");
    
    if (mysqli_num_rows($checkCompany) > 0) {
        // Company exists, get the supplier_id
        $supplierRow = mysqli_fetch_assoc($checkCompany);
        $supplier_id = $supplierRow['supplier_id'];
    } else {
        // Create new company
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        
        $insertSupplier = mysqli_query($conn, "INSERT INTO suppliers (company_name, phone, email, address) 
                                           VALUES ('$company_name', '$phone', '$email', '$address')");
                                           
        if (!$insertSupplier) {
            $error = "Error creating supplier: " . mysqli_error($conn);
        } else {
            $supplier_id = mysqli_insert_id($conn);
        }
    }
    
    // Insert the chemical product
    if (isset($supplier_id)) {
        $insertProduct = mysqli_query($conn, "INSERT INTO chemical_products 
                                      (product_name, supplier_id, category_id, description, unit_of_measure, 
                                      price_per_unit, application_rate, safety_info, composition, registration_number) 
                                      VALUES 
                                      ('$product_name', $supplier_id, $category_id, '$description', '$unit_of_measure', 
                                      $price_per_unit, '$application_rate', '$safety_info', '$composition', '$registration_number')");
                                      
        if ($insertProduct) {
            $success = "Product added successfully!";
            
            // Also add initial inventory if provided
            if (isset($_POST['initial_quantity']) && $_POST['initial_quantity'] > 0) {
                $product_id = mysqli_insert_id($conn);
                $quantity = (float)$_POST['initial_quantity'];
                $purchase_date = date('Y-m-d');
                $purchase_price = $price_per_unit * $quantity;
                
                $insertInventory = mysqli_query($conn, "INSERT INTO chemical_inventory 
                                                 (product_id, quantity, purchase_date, purchase_price) 
                                                 VALUES 
                                                 ($product_id, $quantity, '$purchase_date', $purchase_price)");
                                                 
                if (!$insertInventory) {
                    $warning = "Product saved but error adding inventory: " . mysqli_error($conn);
                }
            }
            
            // Redirect to products list after successful add
            if (!isset($warning)) {
                header("Location: add_chem.php?added=1");
                exit();
            }
        } else {
            $error = "Error adding product: " . mysqli_error($conn);
        }
    }
}

// Fetch product categories for dropdown
$categories = mysqli_query($conn, "SELECT * FROM product_categories ORDER BY category_name");

// Fetch existing companies for autocomplete
$companies = mysqli_query($conn, "SELECT company_name, phone, email, address FROM suppliers");
$companyList = [];
while ($row = mysqli_fetch_assoc($companies)) {
    $companyList[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Chemical Product</title>
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
                        <h2><i class="fas fa-plus-circle"></i> Add New Chemical Product</h2>
                        <p class="text-muted">Create a new chemical product entry in the database</p>
                    </div>
                    <div>
                        <a href="chem_supply.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Products List
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
                
                <?php if(isset($warning)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php echo $warning; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-flask"></i> New Chemical Product Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" id="addProductForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Product Details</h5>
                                    <div class="mb-3">
                                        <label for="product_name" class="form-label required">Product Name</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label required">Category</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">-- Select Category --</option>
                                            <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                                                <option value="<?php echo $cat['category_id']; ?>"><?php echo $cat['category_name']; ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="unit_of_measure" class="form-label required">Unit of Measure</label>
                                                <select class="form-select" id="unit_of_measure" name="unit_of_measure" required>
                                                    <option value="">-- Select Unit --</option>
                                                    <option value="kg">Kilogram (kg)</option>
                                                    <option value="g">Gram (g)</option>
                                                    <option value="L">Liter (L)</option>
                                                    <option value="ml">Milliliter (ml)</option>
                                                    <option value="bottle">Bottle</option>
                                                    <option value="packet">Packet</option>
                                                    <option value="bag">Bag</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="price_per_unit" class="form-label required">Price Per Unit</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">GHS</span>
                                                    <input type="number" step="0.01" min="0" class="form-control" id="price_per_unit" name="price_per_unit" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="application_rate" class="form-label">Application Rate</label>
                                                <input type="text" class="form-control" id="application_rate" name="application_rate" placeholder="e.g. 5kg per acre">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="registration_number" class="form-label">Registration Number</label>
                                                <input type="text" class="form-control" id="registration_number" name="registration_number">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="composition" class="form-label">Chemical Composition</label>
                                        <textarea class="form-control" id="composition" name="composition" rows="2" placeholder="e.g. N-P-K: 17-17-17"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="safety_info" class="form-label">Safety Information</label>
                                        <textarea class="form-control" id="safety_info" name="safety_info" rows="3" placeholder="Safety precautions, handling instructions, etc."></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="initial_quantity" class="form-label">Initial Stock Quantity</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="initial_quantity" name="initial_quantity">
                                        <div class="form-text">Leave as 0 if you don't have this product in stock yet</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Supplier Information</h5>
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label required">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" list="company_list" required>
                                        <datalist id="company_list">
                                            <?php foreach($companyList as $company): ?>
                                                <option value="<?php echo $company['company_name']; ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                        <div class="form-text">Select an existing supplier or enter a new one</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label required">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label required">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label required">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> If you select an existing supplier, their contact information will be automatically filled.
                                    </div>
                                    
                                    <div class="card mt-4 bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-lightbulb"></i> Tips</h6>
                                            <ul class="small mb-0">
                                                <li>Be sure to include accurate application rates for proper usage.</li>
                                                <li>Include detailed safety information and handling instructions.</li>
                                                <li>For fertilizers, include NPK values in the composition field.</li>
                                                <li>If this is a regulated product, don't forget to add the registration number.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                                <a href="add_chem.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" name="add_product" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Chemical Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill supplier details when selecting existing company
        document.getElementById('company_name').addEventListener('change', function() {
            const selectedCompany = this.value;
            const companies = <?php echo json_encode($companyList); ?>;
            
            for (let i = 0; i < companies.length; i++) {
                if (companies[i].company_name === selectedCompany) {
                    document.getElementById('phone').value = companies[i].phone;
                    document.getElementById('email').value = companies[i].email;
                    document.getElementById('address').value = companies[i].address;
                    break;
                }
            }
        });
    </script>
</body>
</html>