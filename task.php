<?php
// task.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


include 'config/db.php';



require_once  'crop/task_functions.php';

require_once 'views/header.php';
// Check if user is logged in with admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Process form submissions
$message = '';
$messageType = '';

// Get all active crop cycles for the dropdown
$cyclesQuery = "SELECT cc.cycle_id, c.crop_name, cc.field_or_location 
                FROM crop_cycles cc 
                JOIN crops c ON cc.crop_id = c.crop_id 
                WHERE cc.status IN ('Planned', 'In Progress')
                ORDER BY c.crop_name";
$cyclesResult = $conn->query($cyclesQuery);
$cycles = [];
while ($row = $cyclesResult->fetch_assoc()) {
    $cycles[] = $row;
}

// Get all task types for the dropdown
$taskTypes = getTaskTypes($conn);

// Handle form submission for adding/editing tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new task
        if ($_POST['action'] === 'add') {
            $cycle_id = $_POST['cycle_id'];
            $task_type_id = $_POST['task_type_id'];
            $task_name = $_POST['task_name'];
            $scheduled_date = $_POST['scheduled_date'];
            $notes = $_POST['notes'] ?? '';
            
            if (addTask($conn, $cycle_id, $task_type_id, $task_name, $scheduled_date, $notes)) {
                $message = "Task added successfully!";
                $messageType = "success";
            } else {
                $message = "Error adding task.";
                $messageType = "danger";
            }
        }
        // Edit existing task
        elseif ($_POST['action'] === 'edit') {
            $task_id = $_POST['task_id'];
            $task_name = $_POST['task_name'];
            $scheduled_date = $_POST['scheduled_date'];
            $notes = $_POST['notes'] ?? '';
            $completion_status = isset($_POST['completion_status']) ? 1 : 0;
            
            if (updateTask($conn, $task_id, $task_name, $scheduled_date, $notes, $completion_status)) {
                $message = "Task updated successfully!";
                $messageType = "success";
            } else {
                $message = "Error updating task.";
                $messageType = "danger";
            }
        }
        // Delete task
        elseif ($_POST['action'] === 'delete') {
            $task_id = $_POST['task_id'];
            
            if (deleteTask($conn, $task_id)) {
                $message = "Task deleted successfully!";
                $messageType = "success";
            } else {
                $message = "Error deleting task.";
                $messageType = "danger";
            }
        }
    }
}

// Get date range for displaying tasks (default: current month)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get tasks for the selected date range
$tasks = getTasksByDateRange($conn, $start_date, $end_date);

// Get task details for editing if task_id is provided
$editTask = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editTask = getTaskById($conn, $_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Farm Task Scheduler</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Task
                </button>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Filter Tasks</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tasks Table -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Scheduled Tasks (<?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tasks)): ?>
                        <div class="alert alert-info">No tasks scheduled for this period.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Crop & Location</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($task['task_name']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($task['crop_name']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($task['field_or_location']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill" style="background-color: <?= $task['color_code'] ?>">
                                                    <?php if (!empty($task['icon'])): ?>
                                                    <i class="bi bi-<?= $task['icon'] ?> me-1"></i>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($task['type_name']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($task['scheduled_date'])) ?></td>
                                            <td>
                                                <?php if ($task['completion_status']): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($task['notes'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                            data-bs-toggle="popover" 
                                                            data-bs-content="<?= htmlspecialchars($task['notes']) ?>">
                                                        <i class="bi bi-info-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?edit=<?= $task['task_id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteTaskModal" 
                                                            data-task-id="<?= $task['task_id'] ?>"
                                                            data-task-name="<?= htmlspecialchars($task['task_name']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php if (!$task['completion_status']): ?>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="action" value="edit">
                                                            <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                                                            <input type="hidden" name="task_name" value="<?= htmlspecialchars($task['task_name']) ?>">
                                                            <input type="hidden" name="scheduled_date" value="<?= $task['scheduled_date'] ?>">
                                                            <input type="hidden" name="notes" value="<?= htmlspecialchars($task['notes']) ?>">
                                                            <input type="hidden" name="completion_status" value="1">
                                                            <button type="submit" class="btn btn-outline-success">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="addTaskForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="cycle_id" class="form-label">Crop & Location</label>
                        <select class="form-select" id="cycle_id" name="cycle_id" required>
                            <option value="">-- Select Crop Cycle --</option>
                            <?php foreach ($cycles as $cycle): ?>
                                <option value="<?= $cycle['cycle_id'] ?>">
                                    <?= htmlspecialchars($cycle['crop_name']) ?> (<?= htmlspecialchars($cycle['field_or_location']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="task_type_id" class="form-label">Task Type</label>
                        <select class="form-select" id="task_type_id" name="task_type_id" required>
                            <option value="">-- Select Task Type --</option>
                            <?php foreach ($taskTypes as $type): ?>
                                <option value="<?= $type['task_type_id'] ?>" data-color="<?= $type['color_code'] ?>">
                                    <?= htmlspecialchars($type['type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="task_name" class="form-label">Task Name</label>
                        <input type="text" class="form-control" id="task_name" name="task_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="scheduled_date" class="form-label">Scheduled Date</label>
                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addTaskForm" class="btn btn-primary">Add Task</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<?php if ($editTask): ?>
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="editTaskForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="task_id" value="<?= $editTask['task_id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Crop & Location</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($editTask['crop_name']) ?> (<?= htmlspecialchars($editTask['field_or_location']) ?>)" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Task Type</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($editTask['type_name']) ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_task_name" class="form-label">Task Name</label>
                        <input type="text" class="form-control" id="edit_task_name" name="task_name" value="<?= htmlspecialchars($editTask['task_name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_scheduled_date" class="form-label">Scheduled Date</label>
                        <input type="date" class="form-control" id="edit_scheduled_date" name="scheduled_date" value="<?= $editTask['scheduled_date'] ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"><?= htmlspecialchars($editTask['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="completion_status" name="completion_status" <?= $editTask['completion_status'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="completion_status">Mark as Completed</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <a href="?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" form="editTaskForm" class="btn btn-primary">Update Task</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editTaskModal = new bootstrap.Modal(document.getElementById('editTaskModal'));
        editTaskModal.show();
    });
</script>
<?php endif; ?>

<!-- Delete Task Modal -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteTaskModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the task "<span id="deleteTaskName"></span>"?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" id="deleteTaskForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="task_id" id="deleteTaskId">
                    <button type="submit" class="btn btn-danger">Delete Task</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            trigger: 'hover',
            placement: 'top'
        });
    });
    
    // Setup delete task modal
    var deleteTaskModal = document.getElementById('deleteTaskModal');
    if (deleteTaskModal) {
        deleteTaskModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var taskId = button.getAttribute('data-task-id');
            var taskName = button.getAttribute('data-task-name');
            
            document.getElementById('deleteTaskId').value = taskId;
            document.getElementById('deleteTaskName').textContent = taskName;
        });
    }
    
    // Highlight select option based on task type color
    var taskTypeSelect = document.getElementById('task_type_id');
    if (taskTypeSelect) {
        taskTypeSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var color = selectedOption.getAttribute('data-color');
            if (color) {
                this.style.backgroundColor = color;
                this.style.color = getContrastColor(color);
            } else {
                this.style.backgroundColor = '';
                this.style.color = '';
            }
        });
    }
    
    // Get contrasting color (black or white) based on background
    function getContrastColor(hexcolor) {
        if (!hexcolor) return '#000000';
        hexcolor = hexcolor.replace('#', '');
        var r = parseInt(hexcolor.substr(0,2),16);
        var g = parseInt(hexcolor.substr(2,2),16);
        var b = parseInt(hexcolor.substr(4,2),16);
        var yiq = ((r*299)+(g*587)+(b*114))/1000;
        return (yiq >= 128) ? '#000000' : '#ffffff';
    }
});
</script>

</body>
</html>
<?php
include 'views/footer.php';?>
<?php $conn->close(); ?>