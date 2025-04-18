<?php session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';
require_once 'views/header.php';
require_once 'crop/crop_functions.php';


function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$crop_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$crop = null;
$categories = getAllCategories($conn);
$errors = [];
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                handleAddCrop($conn);
                break;
            case 'update':
                handleUpdateCrop($conn);
                break;
            case 'delete':
                handleDeleteCrop($conn);
                break;
        }
    }
}

// Process message from session if exists
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get crop details if ID is provided
if ($crop_id > 0 && ($action == 'edit' || $action == 'view')) {
    $crop = getCropById($conn, $crop_id);
    if (!$crop) {
        $_SESSION['message'] = "Crop not found";
        header("Location: crop_management.php");
        exit();
    }
}

// Function to handle adding a crop
function handleAddCrop($conn) {
    global $errors, $action;
    
    // Validate and sanitize input
    $crop_data = [
        'crop_name' => trim($_POST['crop_name']),
        'category_id' => (int)$_POST['category_id'],
        'description' => trim($_POST['description']),
        'soil_requirements' => trim($_POST['soil_requirements']),
        'watering_needs' => trim($_POST['watering_needs']),
        'sunlight_requirements' => trim($_POST['sunlight_requirements']),
        'days_to_maturity' => trim($_POST['days_to_maturity']),
        'spacing_requirements' => trim($_POST['spacing_requirements']),
        'common_issues' => trim($_POST['common_issues']),
        'notes' => trim($_POST['notes']),
        'image_path' => ''
    ];
    
    // Validate required fields
    if (empty($crop_data['crop_name'])) {
        $errors[] = "Crop name is required";
    }
    
    if ($crop_data['category_id'] <= 0) {
        $errors[] = "Please select a valid category";
    }
    
    // Handle image upload if provided
    if (isset($_FILES['crop_image']) && $_FILES['crop_image']['size'] > 0) {
        $target_dir = __DIR__ . "/uploads/crops/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['crop_image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid('crop_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is a valid image
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
        } else {
            // Try to upload the file
            if (copy($_FILES['crop_image']['tmp_name'], $target_file)) {
                $crop_data['image_path'] = $target_file;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // If no errors, proceed with adding the crop
    if (empty($errors)) {
        $result = addCrop($conn, $crop_data);
        
        if ($result) {
            $_SESSION['message'] = "Crop added successfully!";
            header("Location: crop_management.php");
            exit();
        } else {
            $errors[] = "Failed to add crop. Please try again.";
        }
    }
    
    $action = 'add'; // Stay on add form if there were errors
}

// Function to handle updating a crop
function handleUpdateCrop($conn) {
    global $errors, $action, $crop_id;
    
    $crop_id = (int)$_POST['crop_id'];
    $crop = getCropById($conn, $crop_id);
    
    if (!$crop) {
        $_SESSION['message'] = "Crop not found";
        header("Location: crop_management.php");
        exit();
    }
    
    // Validate and sanitize input
    $crop_data = [
        'crop_name' => trim($_POST['crop_name']),
        'category_id' => (int)$_POST['category_id'],
        'description' => trim($_POST['description']),
        'soil_requirements' => trim($_POST['soil_requirements']),
        'watering_needs' => trim($_POST['watering_needs']),
        'sunlight_requirements' => trim($_POST['sunlight_requirements']),
        'days_to_maturity' => trim($_POST['days_to_maturity']),
        'spacing_requirements' => trim($_POST['spacing_requirements']),
        'common_issues' => trim($_POST['common_issues']),
        'notes' => trim($_POST['notes']),
        'image_path' => $crop['image_path'] // Keep existing image path by default
    ];
    
    // Validate required fields
    if (empty($crop_data['crop_name'])) {
        $errors[] = "Crop name is required";
    }
    
    if ($crop_data['category_id'] <= 0) {
        $errors[] = "Please select a valid category";
    }
    
    // Handle image upload if a new image is provided
    if (isset($_FILES['crop_image']) && $_FILES['crop_image']['size'] > 0) {
        $target_dir = __DIR__ . "/uploads/crops/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        
        $file_extension = strtolower(pathinfo($_FILES['crop_image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid('crop_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is a valid image
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
        } else {
            // Try to upload the file
            if (copy($_FILES['crop_image']['tmp_name'], $target_file)) {
                // Delete old image if it exists
                if (!empty($crop['image_path']) && file_exists($crop['image_path'])) {
                    unlink($crop['image_path']);
                }
                $crop_data['image_path'] = $target_file;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // If no errors, proceed with updating the crop
 // If no errors, proceed with updating the crop
if (empty($errors)) {
    $result = updateCrop($conn, $crop_id, $crop_data);
    
    if ($result) {
        $_SESSION['message'] = "Crop updated successfully!";
        // Call header before any output
        header("Location: crop_management.php");
        exit(); // Always use exit after header redirection
    } else {
        $errors[] = "Failed to update crop. Please try again.";
    }
}

    
    $action = 'edit'; // Stay on edit form if there were errors
}

// Function to handle deleting a crop
function handleDeleteCrop($conn) {
    $crop_id = (int)$_POST['crop_id'];
    $crop = getCropById($conn, $crop_id);
    
    if (!$crop) {
        $_SESSION['message'] = "Crop not found";
        header("Location: crop_management.php");
        exit();
    }
    
    $result = deleteCrop($conn, $crop_id);
    
    if ($result) {
        // Delete image file if it exists
        if (!empty($crop['image_path']) && file_exists($crop['image_path'])) {
            unlink($crop['image_path']);
        }
        
        $_SESSION['message'] = "Crop deleted successfully!";
    } else {
        $_SESSION['message'] = "Failed to delete crop. Please try again.";
    }
    
    header("Location: crop_management.php");
    exit();
}

// Get all crops for listing
$crops = getAllCrops($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .crop-image {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<div class="container mt-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $action == 'list' ? 'Crop Management' : ($action == 'add' ? 'Add New Crop' : ($action == 'edit' ? 'Edit Crop' : 'View Crop')); ?></h1>
        <?php if ($action == 'list'): ?>
            <a href="?action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Crop
            </a>
        <?php else: ?>
            <a href="crop_management.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Messages Section -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Main Content Section -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if ($action == 'list'): ?>
                <!-- Crops List Section -->
                <div class="table-responsive">
                    <table class="table table-hover" id="crops-table">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Crop Name</th>
                                <th>Category</th>
                                <th>Days to Maturity</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($crops) > 0): ?>
                                <?php foreach ($crops as $crop): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($crop['crop_id']) ?></td>
                                        <td>
                                            <?php if (!empty($crop['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($crop['image_path']) ?>" alt="<?= htmlspecialchars($crop['crop_name']) ?>" class="img-thumbnail" style="max-width: 50px;">
                                            <?php else: ?>
                                                <span class="text-muted">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($crop['crop_name']) ?></td>
                                        <td><?= htmlspecialchars($crop['category_name']) ?></td>
                                        <td><?= htmlspecialchars($crop['days_to_maturity']) ?></td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a href="?action=view&id=<?= $crop['crop_id'] ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="?action=edit&id=<?= $crop['crop_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $crop['crop_id'] ?>" data-name="<?= htmlspecialchars($crop['crop_name']) ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-3">No crops found. <a href="?action=add">Add your first crop</a>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <!-- Form for Add/Edit Crop -->
                <form action="crop_management.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $action == 'add' ? 'add' : 'update' ?>">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="crop_id" value="<?= $crop_id ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="crop_name" class="form-label">Crop Name *</label>
                                <input type="text" class="form-control" id="crop_name" name="crop_name" required
                                       value="<?= isset($crop) ? htmlspecialchars($crop['crop_name']) : (isset($_POST['crop_name']) ? htmlspecialchars($_POST['crop_name']) : '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>" <?= (isset($crop) && $crop['category_id'] == $category['category_id']) || (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="crop_image" class="form-label">Crop Image</label>
                                <?php if ($action == 'edit' && !empty($crop['image_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($crop['image_path']) ?>" alt="<?= htmlspecialchars($crop['crop_name']) ?>" class="img-thumbnail" style="max-height: 100px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="crop_image" name="crop_image" accept="image/*">
                                <div class="form-text"><?= $action == 'add' ? 'Upload an image of the crop (optional)' : 'Upload a new image to replace the current one (optional)' ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="days_to_maturity" class="form-label">Days to Maturity</label>
                                <input type="text" class="form-control" id="days_to_maturity" name="days_to_maturity"
                                       value="<?= isset($crop) ? htmlspecialchars($crop['days_to_maturity']) : (isset($_POST['days_to_maturity']) ? htmlspecialchars($_POST['days_to_maturity']) : '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="soil_requirements" class="form-label">Soil Requirements</label>
                                <input type="text" class="form-control" id="soil_requirements" name="soil_requirements"
                                       value="<?= isset($crop) ? htmlspecialchars($crop['soil_requirements']) : (isset($_POST['soil_requirements']) ? htmlspecialchars($_POST['soil_requirements']) : '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="watering_needs" class="form-label">Watering Needs</label>
                                <input type="text" class="form-control" id="watering_needs" name="watering_needs"
                                       value="<?= isset($crop) ? htmlspecialchars($crop['watering_needs']) : (isset($_POST['watering_needs']) ? htmlspecialchars($_POST['watering_needs']) : '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sunlight_requirements" class="form-label">Sunlight Requirements</label>
                                <input type="text" class="form-control" id="sunlight_requirements" name="sunlight_requirements"
                                       value="<?= isset($crop) ? htmlspecialchars($crop['sunlight_requirements']) : (isset($_POST['sunlight_requirements']) ? htmlspecialchars($_POST['sunlight_requirements']) : '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="spacing_requirements" class="form-label">Spacing Requirements</label>
                                <input type="text" class="form-control" id="spacing_requirements" name="spacing_requirements"
                                       value="<?= isset($crop) ? htmlspecialchars($crop['spacing_requirements']) : (isset($_POST['spacing_requirements']) ? htmlspecialchars($_POST['spacing_requirements']) : '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="common_issues" class="form-label">Common Issues</label>
                                <input type="text" class="form-control" id="common_issues" name="common_issues"
                                       value="<?= isset($crop) ? htmlspecialchars($crop['common_issues']) : (isset($_POST['common_issues']) ? htmlspecialchars($_POST['common_issues']) : '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= isset($crop) ? htmlspecialchars($crop['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= isset($crop) ? htmlspecialchars($crop['notes']) : (isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><?= $action == 'add' ? 'Add Crop' : 'Update Crop' ?></button>
                        <a href="crop_management.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            <?php elseif ($action == 'view' && $crop): ?>
                <!-- View Crop Details Section -->
                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <?php if (!empty($crop['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($crop['image_path']) ?>" alt="<?= htmlspecialchars($crop['crop_name']) ?>" class="img-fluid rounded mb-3" style="max-height: 200px;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <span class="text-muted">No image available</span>
                                    </div>
                                <?php endif; ?>
                                
                                <h4 class="card-title"><?= htmlspecialchars($crop['crop_name']) ?></h4>
                                <p class="badge bg-secondary"><?= htmlspecialchars($crop['category_name']) ?></p>

                                <div class="mt-3">
                                    <a href="?action=edit&id=<?= $crop_id ?>" class="btn btn-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $crop_id ?>" data-name="<?= htmlspecialchars($crop['crop_name']) ?>">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Crop Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Days to Maturity:</strong> <?= !empty($crop['days_to_maturity']) ? htmlspecialchars($crop['days_to_maturity']) : 'N/A' ?></p>
                                        <p><strong>Soil Requirements:</strong> <?= !empty($crop['soil_requirements']) ? htmlspecialchars($crop['soil_requirements']) : 'N/A' ?></p>
                                        <p><strong>Watering Needs:</strong> <?= !empty($crop['watering_needs']) ? htmlspecialchars($crop['watering_needs']) : 'N/A' ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Sunlight Requirements:</strong> <?= !empty($crop['sunlight_requirements']) ? htmlspecialchars($crop['sunlight_requirements']) : 'N/A' ?></p>
                                        <p><strong>Spacing Requirements:</strong> <?= !empty($crop['spacing_requirements']) ? htmlspecialchars($crop['spacing_requirements']) : 'N/A' ?></p>
                                        <p><strong>Common Issues:</strong> <?= !empty($crop['common_issues']) ? htmlspecialchars($crop['common_issues']) : 'N/A' ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($crop['description'])): ?>
                                    <div class="mt-3">
                                        <h6 class="fw-bold">Description</h6>
                                        <p><?= nl2br(htmlspecialchars($crop['description'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($crop['notes'])): ?>
                                    <div class="mt-3">
                                        <h6 class="fw-bold">Additional Notes</h6>
                                        <p><?= nl2br(htmlspecialchars($crop['notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong id="cropNameToDelete"></strong>? This action cannot be undone.
            </div>
            <form action="crop_management.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="crop_id" id="cropIdToDelete">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTables for better table functionality if available
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#crops-table').DataTable({
                "order": [[2, "asc"]], // Sort by crop name by default
                "pageLength": 25,
                "responsive": true
            });
        }
        
        // Auto-close alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        
        // Set up delete modal
        var deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var cropId = button.getAttribute('data-id');
                var cropName = button.getAttribute('data-name');
                
                document.getElementById('cropIdToDelete').value = cropId;
                document.getElementById('cropNameToDelete').textContent = cropName;
            });
        }
    });
</script>

