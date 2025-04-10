<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Email Notifications</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2>Bulk Email Notifications</h2>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="card mt-3">
            <div class="card-body">
                <form action="process_bulk_email.php" method="post">
                    <div class="form-group">
                        <label for="notification_type">Notification Type:</label>
                        <select name="notification_type" id="notification_type" class="form-control" required>
                            <option value="">-- Select Type --</option>
                            <option value="payroll">Payroll Update</option>
                            <option value="announcement">General Announcement</option>
                            <option value="meeting">Meeting Invitation</option>
                            <option value="holiday">Holiday Notice</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipients">Select Recipients:</label>
                        <div class="recipient-options">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="all_employees" name="all_employees" value="1">
                                <label class="form-check-label" for="all_employees">All Active Employees</label>
                            </div>
                            
                            <div id="specific_options" class="ml-4">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="by_position" name="by_position" value="1">
                                    <label class="form-check-label" for="by_position">By Position</label>
                                </div>
                                
                                <div id="position_options" class="ml-4 mb-2" style="display: none;">
                                    <?php
                                    $positions = ['C.E.O', 'Manager', 'Marketing Director', 'Supervisor', 'Laborer'];
                                    foreach ($positions as $position) {
                                        echo '<div class="form-check">';
                                        echo '<input type="checkbox" class="form-check-input" id="pos_'.strtolower(str_replace('.', '', str_replace(' ', '_', $position))).'" name="positions[]" value="'.$position.'">';
                                        echo '<label class="form-check-label" for="pos_'.strtolower(str_replace('.', '', str_replace(' ', '_', $position))).'">'.$position.'</label>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="by_employment_type" name="by_employment_type" value="1">
                                    <label class="form-check-label" for="by_employment_type">By Employment Type</label>
                                </div>
                                
                                <div id="employment_type_options" class="ml-4 mb-2" style="display: none;">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="type_fulltime" name="employment_types[]" value="Fulltime">
                                        <label class="form-check-label" for="type_fulltime">Fulltime</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="type_byday" name="employment_types[]" value="By-Day">
                                        <label class="form-check-label" for="type_byday">By-Day</label>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="by_individual" name="by_individual" value="1">
                                    <label class="form-check-label" for="by_individual">Select Individual Employees</label>
                                </div>
                                
                                <div id="individual_options" class="ml-4" style="display: none;">
                                    <select name="employees[]" class="form-control" multiple size="6">
                                        <?php
                                        $sql = "SELECT id, first_name, last_name, position FROM employees WHERE status = 'Active' ORDER BY last_name";
                                        $result = $conn->query($sql);
                                        
                                        if ($result->num_rows > 0) {
                                            while($row = $result->fetch_assoc()) {
                                                echo "<option value='".$row['id']."'>".$row['last_name'].", ".$row['first_name']." (".$row['position'].")</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                    <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple employees</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Email Subject:</label>
                        <input type="text" name="subject" id="subject" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Email Message:</label>
                        <textarea name="message" id="message" class="form-control" rows="10" required></textarea>
                        <small class="form-text text-muted">
                            You can use the following placeholders:<br>
                            {first_name} - Employee's first name<br>
                            {last_name} - Employee's last name<br>
                            {position} - Employee's position<br>
                            {company_name} - Company name
                        </small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="save_template" name="save_template" value="1">
                        <label class="form-check-label" for="save_template">Save as template for future use</label>
                    </div>
                    
                    <div id="template_name_div" class="form-group" style="display: none;">
                        <label for="template_name">Template Name:</label>
                        <input type="text" name="template_name" id="template_name" class="form-control">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Send Notification</button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Email Templates</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Template Name</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- This would be populated from a templates table, but for now showing hardcoded examples -->
                        <tr>
                            <td>Monthly Payroll Update</td>
                            <td>Payroll</td>
                            <td>Your Monthly Payroll Has Been Processed</td>
                            <td>
                                <button class="btn btn-sm btn-info load-template" data-id="1">Use</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </td>
                        </tr>
                        <tr>
                            <td>Quarterly Meeting</td>
                            <td>Meeting</td>
                            <td>Quarterly Team Meeting - Attendance Required</td>
                            <td>
                                <button class="btn btn-sm btn-info load-template" data-id="2">Use</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle recipient selection options
            $('#all_employees').change(function() {
                if($(this).is(':checked')) {
                    $('#specific_options').hide();
                } else {
                    $('#specific_options').show();
                }
            });
            
            // Toggle position options
            $('#by_position').change(function() {
                $('#position_options').toggle($(this).is(':checked'));
            });
            
            // Toggle employment type options
            $('#by_employment_type').change(function() {
                $('#employment_type_options').toggle($(this).is(':checked'));
            });
            
            // Toggle individual employee selection
            $('#by_individual').change(function() {
                $('#individual_options').toggle($(this).is(':checked'));
            });
            
            // Toggle template name field
            $('#save_template').change(function() {
                $('#template_name_div').toggle($(this).is(':checked'));
            });
            
            // Load template functionality (would need backend implementation)
            $('.load-template').click(function() {
                // This would be an AJAX call to get template data
                // For demonstration, just showing an alert
                alert('Template would be loaded here. In a real implementation, this would fetch the template data via AJAX.');
            });
        });
    </script>
</body>
</html>