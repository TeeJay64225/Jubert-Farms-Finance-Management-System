<?php
// audit_logs.php - Display and export user activity logs
require('config/db.php'); // adjust path if needed
require('FPDF186/fpdf.php'); // path to FPDF library
session_start();

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: login.php');
    exit;
}

// Define filters and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = ($page - 1) * $limit;

// Define filters
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';

// Build query based on filters
$query = "SELECT a.id, a.user_id, a.action, a.timestamp, u.username 
          FROM audit_logs a
          LEFT JOIN users u ON a.user_id = u.id
          WHERE 1=1";

$params = [];

if ($user_filter > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(a.timestamp) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.timestamp) <= ?";
    $params[] = $date_to;
}

if (!empty($action_filter)) {
    $query .= " AND a.action LIKE ?";
    $params[] = "%$action_filter%";
}

// Count total rows for pagination
$count_query = str_replace("SELECT a.id, a.user_id, a.action, a.timestamp, u.username", "SELECT COUNT(*) as total", $query);
$stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_rows = $row['total'];
$total_pages = ceil($total_rows / $limit);

// Get actual data with limit and offset
$query .= " ORDER BY a.timestamp DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$logs = [];

while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Get list of users for the filter dropdown
$users_query = "SELECT id, username FROM users ORDER BY username";
$users_result = $conn->query($users_query);
$users = [];

while ($user = $users_result->fetch_assoc()) {
    $users[] = $user;
}

// Export to PDF if requested
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // Create a new PDF class that extends FPDF to add headers and footers
    class ActivityLogsPDF extends FPDF {
        function Header() {
            // Logo
            // $this->Image('../assets/img/logo.png', 10, 6, 30);
            
            // Title
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, 'User Activity Logs', 0, 1, 'C');
            
            // Generated date
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
            $this->Ln(10);
            
            // Column headers
            $this->SetFont('Arial', 'B', 11);
            $this->SetFillColor(200, 220, 255);
            $this->Cell(15, 7, 'ID', 1, 0, 'C', true);
            $this->Cell(30, 7, 'Username', 1, 0, 'C', true);
            $this->Cell(95, 7, 'Action', 1, 0, 'C', true);
            $this->Cell(50, 7, 'Timestamp', 1, 1, 'C', true);
        }
        
        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    
    // Create PDF document
    $pdf = new ActivityLogsPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);
    
    // Add filter information if any filters were applied
    if ($user_filter > 0 || !empty($date_from) || !empty($date_to) || !empty($action_filter)) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, 'Applied Filters:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        
        if ($user_filter > 0) {
            foreach ($users as $user) {
                if ($user['id'] == $user_filter) {
                    $pdf->Cell(0, 6, 'User: ' . $user['username'], 0, 1);
                }
            }
        }
        
        if (!empty($date_from)) {
            $pdf->Cell(0, 6, 'From Date: ' . $date_from, 0, 1);
        }
        
        if (!empty($date_to)) {
            $pdf->Cell(0, 6, 'To Date: ' . $date_to, 0, 1);
        }
        
        if (!empty($action_filter)) {
            $pdf->Cell(0, 6, 'Action contains: ' . $action_filter, 0, 1);
        }
        
        $pdf->Ln(5);
    }
    
    // Reset query without pagination for PDF export
    $export_query = str_replace(" LIMIT ? OFFSET ?", "", $query);
    $export_params = array_slice($params, 0, -2); // Remove limit and offset params
    
    $stmt = $conn->prepare($export_query);
    
    if (!empty($export_params)) {
        $types = str_repeat('s', count($export_params));
        $stmt->bind_param($types, ...$export_params);
    }
    
    $stmt->execute();
    $export_result = $stmt->get_result();
    
    // Add data rows
    while ($row = $export_result->fetch_assoc()) {
        $pdf->Cell(15, 6, $row['id'], 1, 0, 'C');
        $pdf->Cell(30, 6, $row['username'], 1, 0, 'L');
        
        // Handle long action text with MultiCell
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell(95, 6, $row['action'], 1, 'L');
        $pdf->SetXY($x + 95, $y);
        
        $pdf->Cell(50, 6, $row['timestamp'], 1, 1, 'C');
    }
    
    // Output PDF
    $pdf->Output('User_Activity_Logs_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// Include header
include_once('views/header.php');
?>

<div class="container-fluid">
    <h1 class="mt-4">User Activity Logs</h1>
    <p class="mb-4">View and export user activity history.</p>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Filter Activity Logs</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">User</label>
                    <select name="user_id" id="user_id" class="form-control">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= ($user_filter == $user['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
                </div>
                <div class="col-md-3">
                    <label for="action" class="form-label">Action Contains</label>
                    <input type="text" class="form-control" id="action" name="action" value="<?= htmlspecialchars($action_filter) ?>">
                </div>
                <div class="col-md-2">
                    <label for="limit" class="form-label">Rows Per Page</label>
                    <select name="limit" id="limit" class="form-control">
                        <option value="25" <?= ($limit == 25) ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= ($limit == 50) ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= ($limit == 100) ? 'selected' : '' ?>>100</option>
                        <option value="250" <?= ($limit == 250) ? 'selected' : '' ?>>250</option>
                    </select>
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="audit_logs.php" class="btn btn-secondary">Reset</a>
                    <?php if (count($logs) > 0): ?>
                        <a href="<?= $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') ?>export=pdf" 
                           class="btn btn-danger float-end">
                            <i class="fas fa-file-pdf"></i> Export to PDF
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Activity Logs</h6>
        </div>
        <div class="card-body">
            <?php if (count($logs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th width="40%">Action</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= $log['id'] ?></td>
                                    <td><?= htmlspecialchars($log['username']) ?></td>
                                    <td><?= htmlspecialchars($log['action']) ?></td>
                                    <td><?= $log['timestamp'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?= $user_filter ? '&user_id='.$user_filter : '' ?><?= $date_from ? '&date_from='.$date_from : '' ?><?= $date_to ? '&date_to='.$date_to : '' ?><?= $action_filter ? '&action='.$action_filter : '' ?>&limit=<?= $limit ?>">First</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?><?= $user_filter ? '&user_id='.$user_filter : '' ?><?= $date_from ? '&date_from='.$date_from : '' ?><?= $date_to ? '&date_to='.$date_to : '' ?><?= $action_filter ? '&action='.$action_filter : '' ?>&limit=<?= $limit ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate range of page numbers to show
                        $range = 2; // Show 2 pages before and after current page
                        $start_page = max(1, $page - $range);
                        $end_page = min($total_pages, $page + $range);
                        
                        // Always show first page
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1' . 
                                 ($user_filter ? '&user_id='.$user_filter : '') . 
                                 ($date_from ? '&date_from='.$date_from : '') . 
                                 ($date_to ? '&date_to='.$date_to : '') . 
                                 ($action_filter ? '&action='.$action_filter : '') . 
                                 '&limit='.$limit.'">1</a></li>';
                            
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                        }
                        
                        // Page links
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                            echo '<a class="page-link" href="?page=' . $i . 
                                 ($user_filter ? '&user_id='.$user_filter : '') . 
                                 ($date_from ? '&date_from='.$date_from : '') . 
                                 ($date_to ? '&date_to='.$date_to : '') . 
                                 ($action_filter ? '&action='.$action_filter : '') . 
                                 '&limit='.$limit.'">' . $i . '</a>';
                            echo '</li>';
                        }
                        
                        // Always show last page
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                            
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . 
                                 ($user_filter ? '&user_id='.$user_filter : '') . 
                                 ($date_from ? '&date_from='.$date_from : '') . 
                                 ($date_to ? '&date_to='.$date_to : '') . 
                                 ($action_filter ? '&action='.$action_filter : '') . 
                                 '&limit='.$limit.'">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?><?= $user_filter ? '&user_id='.$user_filter : '' ?><?= $date_from ? '&date_from='.$date_from : '' ?><?= $date_to ? '&date_to='.$date_to : '' ?><?= $action_filter ? '&action='.$action_filter : '' ?>&limit=<?= $limit ?>">Next</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?= $user_filter ? '&user_id='.$user_filter : '' ?><?= $date_from ? '&date_from='.$date_from : '' ?><?= $date_to ? '&date_to='.$date_to : '' ?><?= $action_filter ? '&action='.$action_filter : '' ?>&limit=<?= $limit ?>">Last</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="mt-3 text-center">
                    <p>Showing <?= count($logs) ?> of <?= $total_rows ?> records</p>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No activity logs found matching your criteria.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set min/max date constraints
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    dateFrom.addEventListener('change', function() {
        dateTo.min = this.value;
    });
    
    dateTo.addEventListener('change', function() {
        dateFrom.max = this.value;
    });
    
    // Initialize existing values
    if (dateFrom.value) {
        dateTo.min = dateFrom.value;
    }
    
    if (dateTo.value) {
        dateFrom.max = dateTo.value;
    }
});
</script>

<?php
// Include footer
include_once('views/footer.php');
?>