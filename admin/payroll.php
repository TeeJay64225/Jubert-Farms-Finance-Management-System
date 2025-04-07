<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new employee
    if (isset($_POST['add_employee'])) {
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $dob = mysqli_real_escape_string($conn, $_POST['dob']);
        $position = mysqli_real_escape_string($conn, $_POST['position']);
        $salary = mysqli_real_escape_string($conn, $_POST['salary']);
        $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        // Check if email already exists
        $check_email = mysqli_query($conn, "SELECT email FROM employees WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $_SESSION['error'] = "Error: Email already exists in the database!";
            header("Location: ../admin/payroll.php");
            exit();
        }
        
        // Handle photo upload
        $photo = NULL;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            // Use absolute path instead of relative path
            $base_path = dirname(dirname(__FILE__)); // Go up one directory level from current script
            $target_dir = $base_path . "/uploads/employees/";
            
            // For debugging
            error_log("Base path: " . $base_path);
            error_log("Target directory: " . $target_dir);
            
            // Create the directory with proper permissions if it doesn't exist
            if (!file_exists($target_dir)) {
                if (!mkdir($target_dir, 0777, true)) {
                    $_SESSION['error'] = "Failed to create upload directory. Permission denied.";
                    header("Location: ../admin/payroll.php");
                    exit();
                }
            }
            
            // Force permissions on the directory
            chmod($target_dir, 0777);
            
            // Check if directory is writable
            if (!is_writable($target_dir)) {
                $_SESSION['error'] = "Upload directory is not writable. Please check permissions.";
                header("Location: ../admin/payroll.php");
                exit();
            }
            
            $file_extension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            $allowed_extensions = array("jpg", "jpeg", "png");
            if (in_array($file_extension, $allowed_extensions)) {
                // Ensure temporary file is readable
                if (is_readable($_FILES["photo"]["tmp_name"])) {
                    // For debugging
                    error_log("Temp file: " . $_FILES["photo"]["tmp_name"]);
                    error_log("Target file: " . $target_file);
                    
                    if (copy($_FILES["photo"]["tmp_name"], $target_file)) {
                        $photo = $new_filename;
                        chmod($target_file, 0644); // Make the uploaded file readable
                    } else {
                        $error = error_get_last();
                        $_SESSION['error'] = "Error copying photo: " . ($error ? $error['message'] : "Unknown error");
                        header("Location: ../admin/payroll.php");
                        exit();
                    }
                } else {
                    $_SESSION['error'] = "Error: Cannot read the temporary file.";
                    header("Location: ../admin/payroll.php");
                    exit();
                }
            } else {
                $_SESSION['error'] = "Error: Only JPG, JPEG, and PNG files are allowed.";
                header("Location: ../admin/payroll.php");
                exit();
            }
        }
        
        $sql = "INSERT INTO employees (first_name, last_name, dob, position, salary, employment_type, phone, email, address, emergency_contact, photo, status) 
                VALUES ('$first_name', '$last_name', '$dob', '$position', '$salary', '$employment_type', '$phone', '$email', '$address', '$emergency_contact', " . ($photo ? "'$photo'" : "NULL") . ", '$status')";
        
        if (mysqli_query($conn, $sql)) {
            // Log this action
            $user_id = $_SESSION['user_id'];
            $action = "Added new employee: $first_name $last_name";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')");
            
            $_SESSION['success'] = "Employee added successfully!";
        } else {
            $_SESSION['error'] = "Error adding employee: " . mysqli_error($conn);
        }
        
        header("Location: ../admin/payroll.php");
        exit();
    }
    
    // Update employee
    if (isset($_POST['update_employee'])) {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $dob = mysqli_real_escape_string($conn, $_POST['dob']);
        $position = mysqli_real_escape_string($conn, $_POST['position']);
        $salary = mysqli_real_escape_string($conn, $_POST['salary']);
        $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $emergency_contact = mysqli_real_escape_string($conn, $_POST['emergency_contact']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        // Check if the email already exists for another employee
        $check_email = mysqli_query($conn, "SELECT id FROM employees WHERE email = '$email' AND id != '$id'");
        if (mysqli_num_rows($check_email) > 0) {
            $_SESSION['error'] = "Error: Email already exists for another employee!";
            header("Location: ../admin/payroll.php");
            exit();
        }
        
        // Handle photo upload
        $photo_sql = "";
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            // Use absolute path instead of relative path
            $base_path = dirname(dirname(__FILE__)); // Go up one directory level from current script
            $target_dir = $base_path . "/uploads/employees/";
            
            // For debugging
            error_log("Base path: " . $base_path);
            error_log("Target directory: " . $target_dir);
            
            // Create the directory with proper permissions if it doesn't exist
            if (!file_exists($target_dir)) {
                if (!mkdir($target_dir, 0777, true)) {
                    $_SESSION['error'] = "Failed to create upload directory. Permission denied.";
                    header("Location: ../admin/payroll.php");
                    exit();
                }
            }
            
            // Force permissions on the directory
            chmod($target_dir, 0777);
            
            // Check if directory is writable
            if (!is_writable($target_dir)) {
                $_SESSION['error'] = "Upload directory is not writable. Please check permissions.";
                header("Location: ../admin/payroll.php");
                exit();
            }
            
            $file_extension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            $allowed_extensions = array("jpg", "jpeg", "png");
            if (in_array($file_extension, $allowed_extensions)) {
                // For debugging
                error_log("Temp file: " . $_FILES["photo"]["tmp_name"]);
                error_log("Target file: " . $target_file);
                
                if (copy($_FILES["photo"]["tmp_name"], $target_file)) {
                    chmod($target_file, 0644); // Make the uploaded file readable
                    
                    // Get existing photo and delete if exists
                    $result = mysqli_query($conn, "SELECT photo FROM employees WHERE id = '$id'");
                    $row = mysqli_fetch_assoc($result);
                    if ($row['photo'] && file_exists($target_dir . $row['photo'])) {
                        unlink($target_dir . $row['photo']);
                    }
                    
                    $photo_sql = ", photo = '$new_filename'";
                } else {
                    $error = error_get_last();
                    $_SESSION['error'] = "Error copying photo: " . ($error ? $error['message'] : "Unknown error");
                    header("Location: ../admin/payroll.php");
                    exit();
                }
            } else {
                $_SESSION['error'] = "Error: Only JPG, JPEG, and PNG files are allowed.";
                header("Location: ../admin/payroll.php");
                exit();
            }
        }
        
        $sql = "UPDATE employees SET 
                first_name = '$first_name', 
                last_name = '$last_name', 
                dob = '$dob', 
                position = '$position', 
                salary = '$salary', 
                employment_type = '$employment_type', 
                phone = '$phone', 
                email = '$email', 
                address = '$address', 
                emergency_contact = '$emergency_contact', 
                status = '$status'
                $photo_sql
                WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql)) {
            // Log this action
            $user_id = $_SESSION['user_id'];
            $action = "Updated employee: $first_name $last_name (ID: $id)";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')");
            
            $_SESSION['success'] = "Employee updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating employee: " . mysqli_error($conn);
        }
        
        header("Location: ../admin/payroll.php");
        exit();
    }
    
    // Delete employee
    if (isset($_POST['delete_employee'])) {
        $id = mysqli_real_escape_string($conn, $_POST['delete_id']);
        
        // Get employee details for logging
        $result = mysqli_query($conn, "SELECT first_name, last_name, photo FROM employees WHERE id = '$id'");
        $employee = mysqli_fetch_assoc($result);
        
        $sql = "DELETE FROM employees WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql)) {
            // Delete employee photo if exists
            $base_path = dirname(dirname(__FILE__));
            $target_dir = $base_path . "/uploads/employees/";
            
            if ($employee['photo'] && file_exists($target_dir . $employee['photo'])) {
                unlink($target_dir . $employee['photo']);
            }
            
            // Log this action
            $user_id = $_SESSION['user_id'];
            $action = "Deleted employee: " . $employee['first_name'] . " " . $employee['last_name'] . " (ID: $id)";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')");
            
            $_SESSION['success'] = "Employee deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting employee: " . mysqli_error($conn);
        }
        
        header("Location: ../admin/payroll.php");
        exit();
    }
}

// Get all employees
$sql = "SELECT * FROM employees ORDER BY last_name, first_name";
$result = mysqli_query($conn, $sql);
$employees = mysqli_fetch_all($result, MYSQLI_ASSOC);
include '../views/pay_header.php';
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
<div class="container-fluid px-4">
    <h1 class="mt-4">Employee Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Employees</li>
    </ol>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Employees
            <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                <i class="fas fa-plus"></i> Add Employee
            </button>
        </div>
        <div class="card-body">
            <table id="employeesTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Employment Type</th>
                        <th>Salary</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?php echo $employee['id']; ?></td>
                        <td>
                            <?php if ($employee['photo']): ?>
                                <img src="../uploads/employees/<?php echo $employee['photo']; ?>" width="50" height="50" class="rounded-circle">
                            <?php else: ?>
                                <img src="../assets/img/default-avatar.png" width="50" height="50" class="rounded-circle">
                            <?php endif; ?>
                        </td>
                        <td><?php echo $employee['last_name'] . ', ' . $employee['first_name']; ?></td>
                        <td><?php echo $employee['position']; ?></td>
                        <td><?php echo $employee['employment_type']; ?></td>
                        <td><?php echo number_format($employee['salary'], 2); ?></td>
                        <td>
                            <small>
                                <strong>Phone:</strong> <?php echo $employee['phone']; ?><br>
                                <strong>Email:</strong> <?php echo $employee['email']; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $employee['status'] === 'Active' ? 'success' : ($employee['status'] === 'Suspended' ? 'warning' : 'danger'); ?>">
                                <?php echo $employee['status']; ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewEmployeeModal<?php echo $employee['id']; ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editEmployeeModal<?php echo $employee['id']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal<?php echo $employee['id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEmployeeModalLabel">Add New Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../admin/payroll.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="dob" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="position" class="form-label">Position</label>
                            <select class="form-select" id="position" name="position" required>
                                <option value="">Select Position</option>
                                <option value="C.E.O">C.E.O</option>
                                <option value="Manager">Manager</option>
                                <option value="Marketing Director">Marketing Director</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Laborer">Laborer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="salary" class="form-label">Salary</label>
                            <input type="number" class="form-control" id="salary" name="salary" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="employment_type" class="form-label">Employment Type</label>
                            <select class="form-select" id="employment_type" name="employment_type" required>
                                <option value="">Select Type</option>
                                <option value="Fulltime">Fulltime</option>
                                <option value="By-Day">By-Day</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact" class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active" selected>Active</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="photo" class="form-label">Employee Photo</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg, image/png">
                        <small class="form-text text-muted">Upload a clear photo of the employee (JPG, JPEG, or PNG format)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_employee" class="btn btn-primary">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Employee View/Edit/Delete Modals -->
<?php foreach ($employees as $employee): ?>
<!-- View Employee Modal -->
<div class="modal fade" id="viewEmployeeModal<?php echo $employee['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <?php if ($employee['photo']): ?>
                            <img src="../uploads/employees/<?php echo $employee['photo']; ?>" class="img-fluid rounded" style="max-height: 200px;">
                        <?php else: ?>
                            <img src="../assets/img/default-avatar.png" class="img-fluid rounded" style="max-height: 200px;">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <h4><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h4>
                        <p class="badge bg-<?php echo $employee['status'] === 'Active' ? 'success' : ($employee['status'] === 'Suspended' ? 'warning' : 'danger'); ?>">
                            <?php echo $employee['status']; ?>
                        </p>
                        <p><strong>Position:</strong> <?php echo $employee['position']; ?></p>
                        <p><strong>Employment Type:</strong> <?php echo $employee['employment_type']; ?></p>
                        <p><strong>Salary:</strong> <?php echo number_format($employee['salary'], 2); ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Date of Birth:</strong> <?php echo date('M d, Y', strtotime($employee['dob'])); ?></p>
                        <p><strong>Phone:</strong> <?php echo $employee['phone']; ?></p>
                        <p><strong>Email:</strong> <?php echo $employee['email']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Address:</strong> <?php echo $employee['address']; ?></p>
                        <p><strong>Emergency Contact:</strong> <?php echo $employee['emergency_contact']; ?></p>
                        <p><strong>Employed Since:</strong> <?php echo date('M d, Y', strtotime($employee['created_at'])); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal<?php echo $employee['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../admin/payroll.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_first_name<?php echo $employee['id']; ?>" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="edit_first_name<?php echo $employee['id']; ?>" name="first_name" value="<?php echo $employee['first_name']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_last_name<?php echo $employee['id']; ?>" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="edit_last_name<?php echo $employee['id']; ?>" name="last_name" value="<?php echo $employee['last_name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_dob<?php echo $employee['id']; ?>" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="edit_dob<?php echo $employee['id']; ?>" name="dob" value="<?php echo $employee['dob']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_position<?php echo $employee['id']; ?>" class="form-label">Position</label>
                            <select class="form-select" id="edit_position<?php echo $employee['id']; ?>" name="position" required>
                                <option value="C.E.O" <?php echo $employee['position'] === 'C.E.O' ? 'selected' : ''; ?>>C.E.O</option>
                                <option value="Manager" <?php echo $employee['position'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="Marketing Director" <?php echo $employee['position'] === 'Marketing Director' ? 'selected' : ''; ?>>Marketing Director</option>
                                <option value="Supervisor" <?php echo $employee['position'] === 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="Laborer" <?php echo $employee['position'] === 'Laborer' ? 'selected' : ''; ?>>Laborer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_salary<?php echo $employee['id']; ?>" class="form-label">Salary</label>
                            <input type="number" class="form-control" id="edit_salary<?php echo $employee['id']; ?>" name="salary" step="0.01" value="<?php echo $employee['salary']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_employment_type<?php echo $employee['id']; ?>" class="form-label">Employment Type</label>
                            <select class="form-select" id="edit_employment_type<?php echo $employee['id']; ?>" name="employment_type" required>
                                <option value="Fulltime" <?php echo $employee['employment_type'] === 'Fulltime' ? 'selected' : ''; ?>>Fulltime</option>
                                <option value="By-Day" <?php echo $employee['employment_type'] === 'By-Day' ? 'selected' : ''; ?>>By-Day</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_phone<?php echo $employee['id']; ?>" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="edit_phone<?php echo $employee['id']; ?>" name="phone" value="<?php echo $employee['phone']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_email<?php echo $employee['id']; ?>" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email<?php echo $employee['id']; ?>" name="email" value="<?php echo $employee['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_address<?php echo $employee['id']; ?>" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address<?php echo $employee['id']; ?>" name="address" rows="2" required><?php echo $employee['address']; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_emergency_contact<?php echo $employee['id']; ?>" class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" id="edit_emergency_contact<?php echo $employee['id']; ?>" name="emergency_contact" value="<?php echo $employee['emergency_contact']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_status<?php echo $employee['id']; ?>" class="form-label">Status</label>
                            <select class="form-select" id="edit_status<?php echo $employee['id']; ?>" name="status" required>
                                <option value="Active" <?php echo $employee['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Suspended" <?php echo $employee['status'] === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                                <option value="Terminated" <?php echo $employee['status'] === 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_photo<?php echo $employee['id']; ?>" class="form-label">Employee Photo</label>
                        <?php if ($employee['photo']): ?>
                            <div class="mb-2">
                                <img src="../uploads/employees/<?php echo $employee['photo']; ?>" width="100" class="img-thumbnail">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="edit_photo<?php echo $employee['id']; ?>" name="photo" accept="image/jpeg, image/png">
                        <small class="form-text text-muted">Leave empty to keep current photo</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_employee" class="btn btn-primary">Update Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Employee Modal -->
<div class="modal fade" id="deleteEmployeeModal<?php echo $employee['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></strong>?</p>
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone and will also delete all associated payroll records and letters.</p>
            </div>
            <div class="modal-footer">
                <form action="../admin/payroll.php" method="post">
                    <input type="hidden" name="delete_id" value="<?php echo $employee['id']; ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_employee" class="btn btn-danger">Delete Employee</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // For the Add Employee button
    const addEmployeeBtn = document.querySelector('[data-bs-target="#addEmployeeModal"]');
    if (addEmployeeBtn) {
        addEmployeeBtn.addEventListener('click', function() {
            const myModal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
            myModal.show();
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    new DataTable('#employeesTable', {
        responsive: true,
        order: [[2, 'asc']], // Sort by name
        lengthMenu: [10, 25, 50, 100],
        pageLength: 10
    });
});
</script>

<?php include '../views/footer.php'; ?>

</body>
</html>