<?php
// crop_events.php - CRUD operations for crop events
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config/db.php';
include 'crop/calendar_functions.php';


function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

// Initialize variables
$event_id = $event_date = $event_name = $crop_id = $description = '';
$error_message = $success_message = '';
$crops = [];

// Fetch all crops for the dropdown
$sql = "SELECT crop_id, crop_name FROM crops ORDER BY crop_name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $crops[] = $row;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $event_date = $_POST['event_date'] ?? '';
    $event_name = $_POST['event_name'] ?? '';
    $crop_id = $_POST['crop_id'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate form data
    if (empty($event_date)) {
        $error_message = "Event date is required";
    } elseif (empty($event_name)) {
        $error_message = "Event name is required";
    } elseif (empty($crop_id)) {
        $error_message = "Crop selection is required";
    } else {
        // Form data is valid, proceed with database operation
        
        // Check if we're creating a new event or updating an existing one
        if (!empty($_POST['action']) && $_POST['action'] === 'create') {
            // Create new event
            $stmt = $conn->prepare("INSERT INTO crop_events (event_date, event_name, crop_id, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $event_date, $event_name, $crop_id, $description);
            
            if ($stmt->execute()) {
                $success_message = "Crop event created successfully";
                // Reset form fields after successful submission
                $event_id = $event_date = $event_name = $crop_id = $description = '';
            } else {
                $error_message = "Error creating crop event: " . $conn->error;
            }
            
            $stmt->close();
        } elseif (!empty($_POST['action']) && $_POST['action'] === 'update') {
            // Update existing event
            $stmt = $conn->prepare("UPDATE crop_events SET event_date = ?, event_name = ?, crop_id = ?, description = ? WHERE event_id = ?");
            $stmt->bind_param("ssisi", $event_date, $event_name, $crop_id, $description, $event_id);
            
            if ($stmt->execute()) {
                $success_message = "Crop event updated successfully";
            } else {
                $error_message = "Error updating crop event: " . $conn->error;
            }
            
            $stmt->close();
        }
    }
}

// Handle delete operation
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Delete the crop event
    $stmt = $conn->prepare("DELETE FROM crop_events WHERE event_id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $success_message = "Crop event deleted successfully";
    } else {
        $error_message = "Error deleting crop event: " . $conn->error;
    }
    
    $stmt->close();
}

// Handle edit operation - load event data for editing
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    
    // Fetch the crop event data
    $stmt = $conn->prepare("SELECT * FROM crop_events WHERE event_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $event_data = $result->fetch_assoc();
        $event_id = $event_data['event_id'];
        $event_date = $event_data['event_date'];
        $event_name = $event_data['event_name'];
        $crop_id = $event_data['crop_id'];
        $description = $event_data['description'];
    }
    
    $stmt->close();
}

// Fetch all crop events for listing
$events = [];
$sql = "SELECT e.*, c.crop_name FROM crop_events e 
        INNER JOIN crops c ON e.crop_id = c.crop_id 
        ORDER BY e.event_date DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Events | Farm Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .form-container {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .events-list {
            margin-top: 30px;
        }
        
        .event-item {
            background-color: #fff;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .event-name {
            font-weight: bold;
            font-size: 18px;
        }
        
        .event-date {
            color: #666;
            font-size: 14px;
        }
        
        .event-crop {
            color: #4CAF50;
            margin-bottom: 10px;
        }
        
        .event-description {
            color: #333;
            line-height: 1.5;
        }
        
        .action-buttons {
            margin-top: 10px;
        }
        
        .btn-edit, .btn-delete {
            padding: 5px 10px;
            margin-right: 5px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success {
            background-color: #DFF2BF;
            color: #4F8A10;
        }
        
        .error {
            background-color: #FFBABA;
            color: #D8000C;
        }
    </style>
</head>
<body>
    <?php require_once 'views/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <h1><i class="fas fa-calendar-plus"></i> Crop Events</h1>
            
            <div class="action-buttons">
                <a href="farm_calendar.php" class="btn btn-secondary"><i class="fas fa-calendar-alt"></i> Back to Calendar</a>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <h2><?php echo empty($event_id) ? 'Create New Crop Event' : 'Edit Crop Event'; ?></h2>
                
                <form method="post" action="crop_events.php">
                    <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
                    <input type="hidden" name="action" value="<?php echo empty($event_id) ? 'create' : 'update'; ?>">
                    
                    <div class="form-group">
                        <label for="event_date">Event Date:</label>
                        <input type="date" id="event_date" name="event_date" value="<?php echo htmlspecialchars($event_date); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_name">Event Name:</label>
                        <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($event_name); ?>" placeholder="Enter event name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="crop_id">Crop:</label>
                        <select id="crop_id" name="crop_id" required>
                            <option value="">Select Crop</option>
                            <?php foreach ($crops as $crop): ?>
                                <option value="<?php echo $crop['crop_id']; ?>" <?php echo ($crop_id == $crop['crop_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($crop['crop_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4" placeholder="Enter event description"><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo empty($event_id) ? 'Create Event' : 'Update Event'; ?>
                        </button>
                        
                        <?php if (!empty($event_id)): ?>
                            <a href="crop_events.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="events-list">
                <h2>All Crop Events</h2>
                
                <?php if (empty($events)): ?>
                    <p>No crop events found.</p>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="event-item">
                            <div class="event-header">
                                <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                <div class="event-date"><?php echo date('F j, Y', strtotime($event['event_date'])); ?></div>
                            </div>
                            <div class="event-crop">
                                <i class="fas fa-seedling"></i> <?php echo htmlspecialchars($event['crop_name']); ?>
                            </div>
                            <?php if (!empty($event['description'])): ?>
                                <div class="event-description"><?php echo htmlspecialchars($event['description']); ?></div>
                            <?php endif; ?>
                            <div class="action-buttons">
                                <a href="crop_events.php?edit=<?php echo $event['event_id']; ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="crop_events.php?delete=<?php echo $event['event_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this event?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <a href="farm_calendar.php?view=day&year=<?php echo date('Y', strtotime($event['event_date'])); ?>&month=<?php echo date('m', strtotime($event['event_date'])); ?>&day=<?php echo date('d', strtotime($event['event_date'])); ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> View in Calendar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php require_once 'views/footer.php'; ?>
    
    <script>
        // Ensure the form is reset when creating a new event
        document.addEventListener('DOMContentLoaded', function() {
            // Add JavaScript for any additional interactivity
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('edit')) {
                // Set default date to today for new events
                const dateField = document.getElementById('event_date');
                if (dateField && !dateField.value) {
                    const today = new Date();
                    const yyyy = today.getFullYear();
                    const mm = String(today.getMonth() + 1).padStart(2, '0');
                    const dd = String(today.getDate()).padStart(2, '0');
                    dateField.value = `${yyyy}-${mm}-${dd}`;
                }
            }
        });
    </script>
</body>
</html>