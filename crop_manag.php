<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
require_once 'config/db.php';

// Initialize variables
$crop_id = '';
$crop_name = '';
$category_id = '';
$description = '';
$soil_requirements = '';
$watering_needs = '';
$sunlight_requirements = '';
$days_to_maturity = '';
$spacing_requirements = '';
$common_issues = '';
$notes = '';
$is_active = 1;
$image_path = '';
$error = '';
$success = '';
$seasons = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Common fields processing
    $crop_name = htmlspecialchars($_POST['crop_name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $description = htmlspecialchars($_POST['description'] ?? '');
    $soil_requirements = htmlspecialchars($_POST['soil_requirements'] ?? '');
    $watering_needs = htmlspecialchars($_POST['watering_needs'] ?? '');
    $sunlight_requirements = htmlspecialchars($_POST['sunlight_requirements'] ?? '');
    $days_to_maturity = htmlspecialchars($_POST['days_to_maturity'] ?? '');
    $spacing_requirements = htmlspecialchars($_POST['spacing_requirements'] ?? '');
    $common_issues = htmlspecialchars($_POST['common_issues'] ?? '');
    $notes = htmlspecialchars($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $seasons = $_POST['seasons'] ?? [];

    // Form validation
    $operation = $_POST['operation'] ?? ''; // Prevent undefined variable warning
    $crop_name = $_POST['crop_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $error = '';
    $success = '';
        
    // Form validation
    if ($operation === 'delete') {
        // Skip validation for delete operations
        $crop_id = intval($_POST['crop_id'] ?? 0);
        handleDeleteCrop($crop_id);
    } elseif (empty($crop_name)) {
        $error = "Crop name is required";
    } elseif (empty($category_id)) {
        $error = "Category is required";
    } else {
        // If form is valid, process the create or update operation
        switch ($operation) {
            case 'create':
                handleCreateCrop();
                break;
            case 'update':
                $crop_id = intval($_POST['crop_id'] ?? 0);
                handleUpdateCrop($crop_id);
                break;
        }
    }
    
} elseif (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    // Load crop data for editing
    $crop_id = intval($_GET['edit']);
    loadCropData($crop_id);
} elseif (isset($_GET['view']) && is_numeric($_GET['view'])) {
    // View crop details
    $crop_id = intval($_GET['view']);
    loadCropData($crop_id);
}

// Function to handle image upload
function handleImageUpload() {
    global $error;
    
    // Check if an image was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No image uploaded
    }
    
    // Define upload directory
    $upload_dir = 'uploads/crops/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    
    // Check file type
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'JPG', 'HEIC', 'gif'];
    if (!in_array($file_extension, $allowed_extensions)) {
        $error = "Only JPG, JPEG, HEIC, PNG, and GIF files are allowed";
        return null;
    }
    
    // Generate a unique filename
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $new_filename;
    
    // Try to upload the file
    if (copy($_FILES["image"]["tmp_name"], $target_file)) {
        chmod($target_file, 0644); // Make the uploaded file readable
        return $target_file;
    } else {
        $error = "Failed to upload image";
        return null;
    }
}

// Function to create a new crop
function handleCreateCrop() {
    global $conn, $crop_name, $category_id, $description, $soil_requirements, 
           $watering_needs, $sunlight_requirements, $days_to_maturity, 
           $spacing_requirements, $common_issues, $notes, $is_active, $error, $success, $seasons;
    
    try {
        // Handle image upload
        $image_path = handleImageUpload();
        
        // Insert crop data
        $sql = "INSERT INTO crops (crop_name, category_id, image_path, description, 
                soil_requirements, watering_needs, sunlight_requirements, 
                days_to_maturity, spacing_requirements, common_issues, 
                notes, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssssi", 
            $crop_name, $category_id, $image_path, $description, 
            $soil_requirements, $watering_needs, $sunlight_requirements, 
            $days_to_maturity, $spacing_requirements, $common_issues, 
            $notes, $is_active
        );
        
        if ($stmt->execute()) {
            $crop_id = $conn->insert_id;
            
            // Add crop-season relationships
            if (!empty($seasons)) {
                foreach ($seasons as $season_id) {
                    $sql = "INSERT INTO crop_seasons (crop_id, season_id) VALUES (?, ?)";
                    $season_stmt = $conn->prepare($sql);
                    $season_stmt->bind_param("ii", $crop_id, $season_id);
                    $season_stmt->execute();
                    $season_stmt->close();
                }
            }
            
            $success = "Crop added successfully";
            // Reset form fields
            resetFormFields();
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Function to update an existing crop
function handleUpdateCrop($crop_id) {
    global $conn, $crop_name, $category_id, $description, $soil_requirements, 
           $watering_needs, $sunlight_requirements, $days_to_maturity, 
           $spacing_requirements, $common_issues, $notes, $is_active, $error, $success, $seasons;
    
    try {
        // Handle image upload if a new image is provided
        $image_sql = "";
        $image_path = handleImageUpload();
        
        if ($image_path !== null) {
            // If new image uploaded, update image path
            $image_sql = ", image_path = ?";
        }
        
        // Update crop data
        $sql = "UPDATE crops SET 
                crop_name = ?, 
                category_id = ?, 
                description = ?, 
                soil_requirements = ?, 
                watering_needs = ?, 
                sunlight_requirements = ?, 
                days_to_maturity = ?, 
                spacing_requirements = ?, 
                common_issues = ?, 
                notes = ?, 
                is_active = ?
                $image_sql
                WHERE crop_id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if ($image_path !== null) {
            $stmt->bind_param("sissssssssisi", 
                $crop_name, $category_id, $description, 
                $soil_requirements, $watering_needs, $sunlight_requirements, 
                $days_to_maturity, $spacing_requirements, $common_issues, 
                $notes, $is_active, $image_path, $crop_id
            );
        } else {
            $stmt->bind_param("sissssssssii", 
                $crop_name, $category_id, $description, 
                $soil_requirements, $watering_needs, $sunlight_requirements, 
                $days_to_maturity, $spacing_requirements, $common_issues, 
                $notes, $is_active, $crop_id
            );
        }
        
        if ($stmt->execute()) {
            // First delete existing crop-season relationships
            $delete_sql = "DELETE FROM crop_seasons WHERE crop_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $crop_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Add new crop-season relationships
            if (!empty($seasons)) {
                foreach ($seasons as $season_id) {
                    $sql = "INSERT INTO crop_seasons (crop_id, season_id) VALUES (?, ?)";
                    $season_stmt = $conn->prepare($sql);
                    $season_stmt->bind_param("ii", $crop_id, $season_id);
                    $season_stmt->execute();
                    $season_stmt->close();
                }
            }
            
            $success = "Crop updated successfully";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Function to delete a crop
function handleDeleteCrop($crop_id) {
    global $conn, $error, $success;
    
    try {
        // Get the image path before deleting
        $sql = "SELECT image_path FROM crops WHERE crop_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $crop_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $image_path = $row['image_path'];
        }
        $stmt->close();
        
        // Delete the crop record
        $sql = "DELETE FROM crops WHERE crop_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $crop_id);
        
        if ($stmt->execute()) {
            // Delete the associated image file if it exists
            if (!empty($image_path) && file_exists($image_path)) {
                unlink($image_path);
            }
            
            $success = "Crop deleted successfully";
            resetFormFields();
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Function to load crop data for editing
function loadCropData($crop_id) {
    global $conn, $crop_name, $category_id, $description, $soil_requirements, 
           $watering_needs, $sunlight_requirements, $days_to_maturity, 
           $spacing_requirements, $common_issues, $notes, $is_active, $image_path, $error, $seasons;
    
    try {
        // Get crop data
        $sql = "SELECT * FROM crops WHERE crop_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $crop_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            
            $crop_name = $row['crop_name'];
            $category_id = $row['category_id'];
            $description = $row['description'];
            $soil_requirements = $row['soil_requirements'];
            $watering_needs = $row['watering_needs'];
            $sunlight_requirements = $row['sunlight_requirements'];
            $days_to_maturity = $row['days_to_maturity'];
            $spacing_requirements = $row['spacing_requirements'];
            $common_issues = $row['common_issues'];
            $notes = $row['notes'];
            $is_active = $row['is_active'];
            $image_path = $row['image_path'];
            
            // Get associated seasons
            $seasons = [];
            $seasons_sql = "SELECT season_id FROM crop_seasons WHERE crop_id = ?";
            $seasons_stmt = $conn->prepare($seasons_sql);
            $seasons_stmt->bind_param("i", $crop_id);
            $seasons_stmt->execute();
            $seasons_result = $seasons_stmt->get_result();
            
            while ($season_row = $seasons_result->fetch_assoc()) {
                $seasons[] = $season_row['season_id'];
            }
            $seasons_stmt->close();
        } else {
            $error = "Crop not found";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Function to reset form fields
function resetFormFields() {
    global $crop_id, $crop_name, $category_id, $description, $soil_requirements, 
           $watering_needs, $sunlight_requirements, $days_to_maturity, 
           $spacing_requirements, $common_issues, $notes, $is_active, $image_path, $seasons;
    
    $crop_id = '';
    $crop_name = '';
    $category_id = '';
    $description = '';
    $soil_requirements = '';
    $watering_needs = '';
    $sunlight_requirements = '';
    $days_to_maturity = '';
    $spacing_requirements = '';
    $common_issues = '';
    $notes = '';
    $is_active = 1;
    $image_path = '';
    $seasons = [];
}

// Get all crops for display
function getAllCrops() {
    global $conn, $error;
    
    try {
        $sql = "SELECT c.*, cc.category_name 
                FROM crops c
                LEFT JOIN crop_categories cc ON c.category_id = cc.category_id
                ORDER BY c.crop_name";
        $result = $conn->query($sql);
        
        return $result;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        return false;
    }
}

// Get all categories for dropdown
function getAllCategories() {
    global $conn, $error;
    
    try {
        $sql = "SELECT * FROM crop_categories ORDER BY category_name";
        $result = $conn->query($sql);
        
        return $result;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        return false;
    }
}

// Get all seasons for checkboxes
function getAllSeasons() {
    global $conn, $error;
    
    try {
        $sql = "SELECT * FROM seasons ORDER BY season_name";
        $result = $conn->query($sql);
        
        return $result;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        return false;
    }
}

// Get crop's seasons (comma-separated list for display in table)
function getCropSeasons($crop_id) {
    global $conn;
    
    $sql = "SELECT s.season_name 
            FROM crop_seasons cs
            JOIN seasons s ON cs.season_id = s.season_id
            WHERE cs.crop_id = ?
            ORDER BY s.season_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $crop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $seasons = [];
    while ($row = $result->fetch_assoc()) {
        $seasons[] = $row['season_name'];
    }
    
    return implode(", ", $seasons);
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM crops";
$count_result = $conn->query($count_query);
$count_data = $count_result->fetch_assoc();
$total_records = $count_data['total'];
$total_pages = ceil($total_records / $records_per_page);

// Modify your crops query to include pagination
$crops_query = "SELECT c.*, cc.category_name 
                FROM crops c 
                LEFT JOIN crop_categories cc ON c.category_id = cc.category_id 
                ORDER BY c.crop_name ASC 
                LIMIT $offset, $records_per_page";
$crops = $conn->query($crops_query);
// Get data for display
$crops = getAllCrops();
$categories = getAllCategories();
$all_seasons = getAllSeasons();

// Include header
include 'views/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Management</title>
    <link rel="icon" type="image/svg+xml" href="assets/fab.svg">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/crop-management.css">
    <style>
        .preview-image {
            max-width: 100px;
            max-height: 100px;
        }
        .crop-table {
            font-size: 0.9rem;
        }
        .crop-table td, .crop-table th {
            vertical-align: middle;
        }
        .pagination {
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid pt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Crop Management</h1>
            <a href="add_crop.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Crop
            </a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col">
                                <h5 class="mb-0">All Crops</h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover crop-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Seasons</th>
                                        <th>Days to Maturity</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($crops && $crops->num_rows > 0): ?>
                                        <?php while ($crop = $crops->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($crop['image_path']) && file_exists($crop['image_path'])): ?>
                                                        <img src="<?php echo $crop['image_path']; ?>" class="preview-image" alt="<?php echo $crop['crop_name']; ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted">No image</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $crop['crop_name']; ?></td>
                                                <td><?php echo $crop['category_name']; ?></td>
                                                <td><?php echo getCropSeasons($crop['crop_id']); ?></td>
                                                <td><?php echo $crop['days_to_maturity']; ?></td>
                                                <td>
                                                    <?php if ($crop['is_active']): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?view=<?php echo $crop['crop_id']; ?>" class="btn btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_crop.php?id=<?php echo $crop['crop_id']; ?>" class="btn btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this crop?');">
                                                            <input type="hidden" name="operation" value="delete">
                                                            <input type="hidden" name="crop_id" value="<?php echo $crop['crop_id']; ?>">
                                                            <button type="submit" class="btn btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No crops found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
    
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Crop pagination">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>