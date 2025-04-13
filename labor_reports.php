<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent the "headers already sent" error
ob_start();

include 'config/db.php';
require_once 'views/header.php';



// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$category_filter = isset($_GET['category_id']) ? $_GET['category_id'] : '';

// Fetch labor categories for filter dropdown
$categories_sql = "SELECT * FROM labor_categories ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_sql);
$categories = [];

if (mysqli_num_rows($categories_result) > 0) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row;
    }
}

// Build the WHERE clause for filtering
$where_clause = "WHERE lr.labor_date BETWEEN '$start_date' AND '$end_date'";
if (!empty($category_filter)) {
    $where_clause .= " AND lr.category_id = $category_filter";
}

// Fetch filtered labor records
$records_sql = "SELECT lr.labor_date, lc.category_name as category_name, lr.worker_count, 
                lr.fee_per_head, lr.total_cost, lr.notes 
                FROM labor_records lr
                JOIN labor_categories lc ON lr.category_id = lc.category_id
                $where_clause
                ORDER BY lr.labor_date ASC, lc.category_name ASC";


$records_result = mysqli_query($conn, $records_sql);
$records = [];
$total_workers = 0;
$total_cost = 0;

if ($records_result && mysqli_num_rows($records_result) > 0) {
    while ($row = mysqli_fetch_assoc($records_result)) {
        $records[] = $row;
        $total_workers += $row['worker_count'];
        $total_cost += $row['total_cost'];
    }
}

// Get labor costs by category for pie chart
$category_data_sql = "SELECT lc.category_name as category_name, SUM(lr.total_cost) as total_cost
                      FROM labor_records lr
                      JOIN labor_categories lc ON lr.category_id = lc.category_id
                      $where_clause
                      GROUP BY lr.category_id
                      ORDER BY total_cost DESC";

$category_data_result = mysqli_query($conn, $category_data_sql);
$category_data = [];
$category_labels = [];
$category_values = [];

if ($category_data_result && mysqli_num_rows($category_data_result) > 0) {
    while ($row = mysqli_fetch_assoc($category_data_result)) {
        $category_data[] = $row;
        $category_labels[] = $row['category_name'];
        $category_values[] = $row['total_cost'];
    }
}

// Get labor costs by date for line chart
$date_data_sql = "SELECT lr.labor_date, SUM(lr.total_cost) as daily_cost
                   FROM labor_records lr
                   $where_clause
                   GROUP BY lr.labor_date
                   ORDER BY lr.labor_date ASC";

$date_data_result = mysqli_query($conn, $date_data_sql);
$date_labels = [];
$date_values = [];

if ($date_data_result && mysqli_num_rows($date_data_result) > 0) {
    while ($row = mysqli_fetch_assoc($date_data_result)) {
        $date_labels[] = date('M d', strtotime($row['labor_date']));
        $date_values[] = $row['daily_cost'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labor Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/labor-reports.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>


</head>
<body>

<div class="container-fluid px-4">
    <h1 class="mt-4">Labor Cost Reports</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="labor_management.php">Labor Management</a></li>
        <li class="breadcrumb-item active">Labor Reports</li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filter Options
        </div>
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
   <label for="category_id" class="form-label">Labor Category</label>
   <select class="form-select" id="category_id" name="category_id">
       <option value="">All Categories</option>
       <?php foreach ($categories as $category): ?>
           <option value="<?php echo $category['id']; ?>" <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
               <?php echo htmlspecialchars($category['name']); ?>
           </option>
       <?php endforeach; ?>
   </select>
</div>
<div class="col-md-2 d-flex align-items-end">
   <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
</div>
           </form>
       </div>
   </div>
   
   <div class="row">
       <div class="col-xl-4">
           <div class="card mb-4">
               <div class="card-header">
                   <i class="fas fa-chart-pie me-1"></i>
                   Labor Cost by Category
               </div>
               <div class="card-body">
                   <canvas id="laborCategoryChart" width="100%" height="100"></canvas>
               </div>
           </div>
       </div>
       <div class="col-xl-8">
           <div class="card mb-4">
               <div class="card-header">
                   <i class="fas fa-chart-line me-1"></i>
                   Daily Labor Cost
               </div>
               <div class="card-body">
                   <canvas id="laborCostChart" width="100%" height="50"></canvas>
               </div>
           </div>
       </div>
   </div>
   
   <div class="row mb-4">
       <div class="col-xl-4 col-md-6">
           <div class="card bg-primary text-white mb-4">
               <div class="card-body">
                   <div class="d-flex justify-content-between">
                       <div>
                           <h5 class="mb-0">Total Labor Cost</h5>
                           <small>For selected period</small>
                       </div>
                       <div class="text-end">
                           <h3 class="mb-0">GHS <?php echo number_format($total_cost, 2); ?></h3>
                       </div>
                   </div>
               </div>
           </div>
       </div>
       <div class="col-xl-4 col-md-6">
           <div class="card bg-success text-white mb-4">
               <div class="card-body">
                   <div class="d-flex justify-content-between">
                       <div>
                           <h5 class="mb-0">Total Workers Employed</h5>
                           <small>For selected period</small>
                       </div>
                       <div class="text-end">
                           <h3 class="mb-0"><?php echo number_format($total_workers); ?></h3>
                       </div>
                   </div>
               </div>
           </div>
       </div>
       <div class="col-xl-4 col-md-6">
           <div class="card bg-info text-white mb-4">
               <div class="card-body">
                   <div class="d-flex justify-content-between">
                       <div>
                           <h5 class="mb-0">Avg. Cost Per Worker</h5>
                           <small>For selected period</small>
                       </div>
                       <div class="text-end">
                           <h3 class="mb-0">GHS <?php echo ($total_workers > 0) ? number_format($total_cost / $total_workers, 2) : '0.00'; ?></h3>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </div>
   
   <div class="card mb-4">
       <div class="card-header">
           <div class="d-flex justify-content-between align-items-center">
               <div><i class="fas fa-table me-1"></i> Labor Records</div>
               <button class="btn btn-sm btn-success" id="exportBtn">
                   <i class="fas fa-file-excel me-1"></i> Export to Excel
               </button>
           </div>
       </div>
       <div class="card-body">
           <table id="laborReportTable" class="table table-striped table-bordered">
               <thead>
                   <tr>
                       <th>Date</th>
                       <th>Category</th>
                       <th>Workers</th>
                       <th>Fee/Head</th>
                       <th>Total Cost</th>
                       <th>Notes</th>
                   </tr>
               </thead>
               <tbody>
                   <?php foreach ($records as $record): ?>
                   <tr>
                       <td><?php echo date('Y-m-d', strtotime($record['labor_date'])); ?></td>
                       <td><?php echo htmlspecialchars($record['category_name']); ?></td>
                       <td><?php echo $record['worker_count']; ?></td>
                       <td>GHS <?php echo number_format($record['fee_per_head'], 2); ?></td>
                       <td>GHS <?php echo number_format($record['total_cost'], 2); ?></td>
                       <td><?php echo htmlspecialchars($record['notes']); ?></td>
                   </tr>
                   <?php endforeach; ?>
               </tbody>
               <tfoot>
                   <tr class="table-primary">
                       <th colspan="2">Totals</th>
                       <th><?php echo $total_workers; ?></th>
                       <th>-</th>
                       <th>GHS <?php echo number_format($total_cost, 2); ?></th>
                       <th>-</th>
                   </tr>
               </tfoot>
           </table>
       </div>
   </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
   $(document).ready(function() {
       // Initialize DataTable
       $('#laborReportTable').DataTable({
           dom: 'Bfrtip',
           buttons: [
               'copy', 'excel', 'pdf', 'print'
           ]
       });
       
       // Category Pie Chart
       const categoryLabels = <?php echo json_encode($category_labels); ?>;
       const categoryData = <?php echo json_encode($category_values); ?>;
       
       new Chart(document.getElementById("laborCategoryChart"), {
           type: 'pie',
           data: {
               labels: categoryLabels,
               datasets: [{
                   data: categoryData,
                   backgroundColor: [
                       '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                       '#5a5c69', '#6f42c1', '#20c9a6', '#fd7e14', '#6c757d'
                   ]
               }]
           },
           options: {
               responsive: true,
               plugins: {
                   legend: {
                       position: 'bottom'
                   },
                   tooltip: {
                       callbacks: {
                           label: function(context) {
                               const label = context.label || '';
                               const value = context.raw || 0;
                               return label + ': GHS ' + value.toFixed(2);
                           }
                       }
                   }
               }
           }
       });
       
       // Daily Labor Cost Line Chart
       const dateLabels = <?php echo json_encode($date_labels); ?>;
       const costData = <?php echo json_encode($date_values); ?>;
       
       new Chart(document.getElementById("laborCostChart"), {
           type: 'line',
           data: {
               labels: dateLabels,
               datasets: [{
                   label: 'Daily Labor Cost (GHS)',
                   data: costData,
                   fill: false,
                   borderColor: '#4e73df',
                   tension: 0.1,
                   pointBackgroundColor: '#4e73df',
                   pointRadius: 3
               }]
           },
           options: {
               responsive: true,
               scales: {
                   y: {
                       beginAtZero: true,
                       ticks: {
                           callback: function(value) {
                               return 'GHS ' + value;
                           }
                       }
                   }
               }
           }
       });
       
       // Export to Excel functionality
       $('#exportBtn').click(function() {
           const table = document.getElementById('laborReportTable');
           const ws = XLSX.utils.table_to_sheet(table);
           
           // Format the Excel sheet
           const range = XLSX.utils.decode_range(ws['!ref']);
           for (let C = range.s.c; C <= range.e.c; ++C) {
               const address = XLSX.utils.encode_col(C) + '1';
               if (!ws[address]) continue;
               ws[address].s = { font: { bold: true }, fill: { fgColor: { rgb: "EFEFEF" } } };
           }
           
           const wb = XLSX.utils.book_new();
           XLSX.utils.book_append_sheet(wb, ws, 'Labor Report');
           
           // Generate filename with date range
           const filename = `Labor_Report_${$('#start_date').val()}_to_${$('#end_date').val()}.xlsx`;
           XLSX.writeFile(wb, filename);
       });
   });
</script>

<?php
include 'views/footer.php';
?>