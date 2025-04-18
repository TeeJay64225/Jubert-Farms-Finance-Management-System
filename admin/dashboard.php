<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';

function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}
log_action($conn, $_SESSION['user_id'], "Viewed admin dashboard");

/**
 * Get dashboard summary data for the admin panel
 * @return array Associative array containing all dashboard data
 */
function getDashboardData() {
    global $conn;
    $dashboardData = [];
    log_action($conn, $_SESSION['user_id'], "Viewed admin dashboard");
    //paid total_sales
    //not paid total_receivables
    // Total Revenue (Paid + Not Paid)
    $query = "SELECT 
    SUM(CASE WHEN payment_status = 'Paid' THEN amount ELSE 0 END) as total_sales,
    SUM(CASE WHEN payment_status = 'Not Paid' THEN amount ELSE 0 END) as total_receivables
FROM sales";

$result = $conn->query($query);

if ($result) {
    $row = $result->fetch_assoc();
    $total_sales = $row['total_sales'] ?? 0;
    $total_receivables = $row['total_receivables'] ?? 0;
    $total_revenue = $total_sales + $total_receivables;

    $dashboardData['finance']['total_sales'] = $total_sales;
    $dashboardData['finance']['total_receivables'] = $total_receivables;
    $dashboardData['finance']['total_revenue'] = $total_revenue;
}




    // Employee Statistics
    $query = "SELECT 
                COUNT(*) as total_employees,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_employees,
                SUM(CASE WHEN employment_type = 'Fulltime' THEN 1 ELSE 0 END) as fulltime_employees,
                SUM(CASE WHEN employment_type = 'By-Day' THEN 1 ELSE 0 END) as byday_employees
              FROM employees";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['employees'] = $result->fetch_assoc();
    }
    
    // Financial Overview
    $query = "SELECT 
                (SELECT SUM(amount) FROM sales WHERE YEAR(sale_date) = YEAR(CURRENT_DATE())) as yearly_sales,
                (SELECT SUM(amount) FROM sales WHERE MONTH(sale_date) = MONTH(CURRENT_DATE()) AND YEAR(sale_date) = YEAR(CURRENT_DATE())) as monthly_sales,
                (SELECT SUM(amount) FROM expenses WHERE YEAR(expense_date) = YEAR(CURRENT_DATE())) as yearly_expenses,
                (SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())) as monthly_expenses,
                (SELECT SUM(total_amount) FROM invoices WHERE payment_status = 'Unpaid') as outstanding_invoices,
                (SELECT SUM(amount) FROM payroll WHERE YEAR(payment_date) = YEAR(CURRENT_DATE())) as yearly_payroll,
                (SELECT COALESCE(SUM(amount), 0) FROM sales WHERE payment_status = 'Paid') AS total_sales,
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE payment_status = 'Paid') AS total_expenses,
                (SELECT COALESCE(SUM(amount), 0) FROM sales WHERE payment_status = 'Not Paid') AS total_receivables,
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE payment_status = 'Not Paid') AS total_payables
              FROM dual";
    $result = $conn->query($query);
    if ($result) {
        
        $dashboardData['finance'] = $result->fetch_assoc();
        
        // Calculate profit
        $dashboardData['finance']['yearly_profit'] = $dashboardData['finance']['yearly_sales'] - 
                                                    $dashboardData['finance']['yearly_expenses'] - 
                                                    $dashboardData['finance']['yearly_payroll'];
        $dashboardData['finance']['monthly_profit'] = $dashboardData['finance']['monthly_sales'] - 
                                                     $dashboardData['finance']['monthly_expenses'];
        $dashboardData['finance']['net_profit'] = $dashboardData['finance']['total_sales'] - 
                                                 $dashboardData['finance']['total_expenses'];
        
        // Calculate metrics
        $dashboardData['finance']['profit_margin'] = ($dashboardData['finance']['total_sales'] > 0) ? 
                                                    ($dashboardData['finance']['net_profit'] / $dashboardData['finance']['total_sales']) * 100 : 0;
        $dashboardData['finance']['debt_ratio'] = ($dashboardData['finance']['total_receivables'] > 0) ? 
                                                 ($dashboardData['finance']['total_payables'] / $dashboardData['finance']['total_receivables']) * 100 : 0;
                                                 $dashboardData['finance']['total_sales'] = $total_sales;
                                                 $dashboardData['finance']['total_receivables'] = $total_receivables;
                                                 $dashboardData['finance']['total_revenue'] = $total_revenue;
    }
    
    // Crop Statistics
    $query = "SELECT 
                COUNT(*) as total_crops,
                (SELECT COUNT(*) FROM crop_cycles WHERE status = 'In Progress') as active_cycles,
                (SELECT COUNT(*) FROM farm_tasks WHERE completion_status = 0 AND scheduled_date >= CURRENT_DATE()) as pending_tasks,
                (SELECT COUNT(*) FROM harvest_records WHERE MONTH(harvest_date) = MONTH(CURRENT_DATE()) AND YEAR(harvest_date) = YEAR(CURRENT_DATE())) as harvests_this_month
              FROM crops";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['crops'] = $result->fetch_assoc();
    }

    // Crop Cycle Status Counts
$query = "SELECT 
SUM(status = 'Planned') as planned_cycles,
SUM(status = 'In Progress') as in_progress_cycles,
SUM(status = 'Completed') as completed_cycles,
SUM(status = 'Failed') as failed_cycles
FROM crop_cycles";

$result = $conn->query($query);
if ($result) {
$dashboardData['crop_cycles'] = $result->fetch_assoc();
}

    
    // Asset Overview
    $query = "SELECT 
                COUNT(*) as total_assets,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_assets,
                (SELECT COUNT(*) FROM asset_transactions WHERE MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())) as recent_transactions
              FROM assets";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['assets'] = $result->fetch_assoc();
    }
    
    // Recent Sales (Last 5)
    $query = "SELECT s.sale_id, s.invoice_no, s.product_name, s.amount, s.sale_date, c.full_name as client_name 
              FROM sales s 
              LEFT JOIN clients c ON s.client_id = c.client_id 
              ORDER BY s.sale_date DESC 
              LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['recent_sales'] = [];
        while ($row = $result->fetch_assoc()) {
            $dashboardData['recent_sales'][] = $row;
        }
    }
    
    // Recent Expenses (Last 5)
    $query = "SELECT e.expense_id, e.expense_reason, e.amount, e.expense_date, ec.category_name 
              FROM expenses e 
              LEFT JOIN expense_categories ec ON e.category_id = ec.category_id 
              ORDER BY e.expense_date DESC 
              LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['recent_expenses'] = [];
        while ($row = $result->fetch_assoc()) {
            $dashboardData['recent_expenses'][] = $row;
        }
    }
    
    // Recent Transactions (Combined Sales and Expenses)
    $query = "SELECT 'Sale' as type, s.product_name as description, s.amount, s.sale_date as transaction_date, s.payment_status
              FROM sales s
              UNION ALL
              SELECT 'Expense' as type, e.expense_reason as description, e.amount, e.expense_date as transaction_date, e.payment_status
              FROM expenses e
              ORDER BY transaction_date DESC
              LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['recent_transactions'] = [];
        while ($row = $result->fetch_assoc()) {
            $dashboardData['recent_transactions'][] = $row;
        }
    }
    
    // Upcoming Tasks
    $query = "SELECT ft.task_id, ft.task_name, ft.scheduled_date, tt.type_name, 
              c.crop_name, tt.color_code
              FROM farm_tasks ft 
              JOIN task_types tt ON ft.task_type_id = tt.task_type_id
              JOIN crop_cycles cc ON ft.cycle_id = cc.cycle_id
              JOIN crops c ON cc.crop_id = c.crop_id
              WHERE ft.completion_status = 0 
              AND ft.scheduled_date >= CURRENT_DATE()
              ORDER BY ft.scheduled_date ASC
              LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['upcoming_tasks'] = [];
        while ($row = $result->fetch_assoc()) {
            $dashboardData['upcoming_tasks'][] = $row;
        }
    }
    
    // Labor Statistics
    $query = "SELECT 
                (SELECT SUM(total_cost) FROM labor_records WHERE MONTH(labor_date) = MONTH(CURRENT_DATE()) AND YEAR(labor_date) = YEAR(CURRENT_DATE())) as monthly_labor_cost,
                (SELECT COUNT(*) FROM labor WHERE payment_status = 'Not Paid') as unpaid_labor_count,
                (SELECT SUM(total_amount) FROM labor WHERE payment_status = 'Not Paid') as unpaid_labor_amount
              FROM dual";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['labor'] = $result->fetch_assoc();
    }
    
    // Monthly Sales for Chart (last 6 months)
    $dashboardData['chart_data'] = [
        'months' => [],
        'sales_data' => [],
        'expenses_data' => []
    ];
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M', strtotime("-$i month"));
        $dashboardData['chart_data']['months'][] = $month;
        
        $month_num = date('m', strtotime("-$i month"));
        $year = date('Y', strtotime("-$i month"));
        
        // Get monthly sales
        $sql = "SELECT COALESCE(SUM(amount), 0) AS total 
                FROM sales 
                WHERE MONTH(sale_date) = '$month_num' 
                AND YEAR(sale_date) = '$year'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $dashboardData['chart_data']['sales_data'][] = $row['total'];
        
        // Get monthly expenses
        $sql = "SELECT COALESCE(SUM(amount), 0) AS total 
                FROM expenses 
                WHERE MONTH(expense_date) = '$month_num' 
                AND YEAR(expense_date) = '$year'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $dashboardData['chart_data']['expenses_data'][] = $row['total'];
    }
    
    // Monthly Sales for Annual Chart
    $query = "SELECT 
                MONTH(sale_date) as month, 
                SUM(amount) as total 
              FROM sales 
              WHERE YEAR(sale_date) = YEAR(CURRENT_DATE()) 
              GROUP BY MONTH(sale_date) 
              ORDER BY MONTH(sale_date)";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['monthly_sales_chart'] = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        while ($row = $result->fetch_assoc()) {
            $month_idx = (int)$row['month'] - 1;
            $dashboardData['monthly_sales_chart'][] = [
                'month' => $months[$month_idx],
                'total' => $row['total']
            ];
        }
    }
    
    // Monthly Expenses for Annual Chart
    $query = "SELECT 
                MONTH(expense_date) as month, 
                SUM(amount) as total 
              FROM expenses 
              WHERE YEAR(expense_date) = YEAR(CURRENT_DATE()) 
              GROUP BY MONTH(expense_date) 
              ORDER BY MONTH(expense_date)";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['monthly_expenses_chart'] = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        while ($row = $result->fetch_assoc()) {
            $month_idx = (int)$row['month'] - 1;
            $dashboardData['monthly_expenses_chart'][] = [
                'month' => $months[$month_idx],
                'total' => $row['total']
            ];
        }
    }
    
    // Expense Categories Breakdown
    $query = "SELECT 
                ec.category_name, 
                SUM(e.amount) as total 
              FROM expenses e 
              JOIN expense_categories ec ON e.category_id = ec.category_id 
              WHERE YEAR(e.expense_date) = YEAR(CURRENT_DATE()) 
              GROUP BY e.category_id 
              ORDER BY total DESC";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['expense_categories'] = [];
        while ($row = $result->fetch_assoc()) {
            $dashboardData['expense_categories'][] = $row;
        }
    }
    
    // Top Products/Crops
    $query = "SELECT product_name, SUM(amount) as total 
            FROM sales 
            GROUP BY product_name 
            ORDER BY total DESC 
            LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['top_products'] = [];
        while ($row = $result->fetch_assoc()) {
            $dashboardData['top_products'][] = $row;
        }
    }
    
    // Unpaid Invoices
    $query = "SELECT 
                i.invoice_id, 
                i.invoice_no, 
                c.full_name as client_name, 
                i.total_amount, 
                i.due_date 
              FROM invoices i 
              JOIN clients c ON i.client_id = c.client_id 
              WHERE i.payment_status IN ('Unpaid', 'Partial') 
              ORDER BY i.due_date ASC 
              LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['unpaid_invoices'] = [];
        while ($row = $result->fetch_assoc()) {
            $dashboardData['unpaid_invoices'][] = $row;
        }
    }
    
    // System Information
    $query = "SELECT 
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM audit_logs WHERE DATE(timestamp) = CURRENT_DATE()) as todays_actions,
                (SELECT COUNT(*) FROM clients) as total_clients
              FROM dual";
    $result = $conn->query($query);
    if ($result) {
        $dashboardData['system'] = $result->fetch_assoc();
    }
    
    return $dashboardData;
}


// Get all dashboard data
$dashboardData = getDashboardData();


// Close the database connection
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Finance Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="../assets/fab.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <style>

    </style>
</head>
<body>
   <!-- Add this CSS to your existing style section -->
<style>
    /* Toggle Button Styles */
    .nav-toggle-container {
        display: flex;
        align-items: center;
    }
    
    .toggle-btn {
        color: white;
        background-color: rgba(255, 255, 255, 0.1);
        border: 1px solid white;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: all 0.3s;
        cursor: pointer;
        font-size: 0.9rem;
    }
    
    .toggle-btn:hover {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
    }
    
    .toggle-btn.dashboard-active {
        background-color: rgba(255, 255, 255, 0.15);
        border-color: var(--accent-color);
    }
    
    .toggle-btn.payroll-active {
        background-color: rgba(255, 255, 255, 0.15);
        border-color: var(--accent-color);
    }
    
    .toggle-label i {
        margin-right: 5px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 992px) {
        .nav-toggle-container {
            margin-top: 10px;
            align-self: flex-start;
        }
    }
</style>
<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand d-flex align-items-center">
            <div class="logo-container-nav me-2">
                <img src="../assets/logo2.JPG" alt="Farm Logo" class="logo-nav">
            </div>
            Jubert Farms Finance 
        </span>
        
        <!-- Main Navigation Links -->
        <div class="menu-container d-flex flex-wrap">
            <a href="../admin/dashboard.php" class="nav-btn"><i class="fas fa-chart-line"></i> Dashboard</a>

            <!-- Sales Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('salesDropdown')">
                    <i class="fas fa-receipt"></i> Sales <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="salesDropdown" class="dropdown-content">
                    <a href="../sales.php"><i class="fas fa-dollar-sign"></i> Sales</a>
                    <a href="../slip.php"><i class="fas fa-file-invoice"></i> Slip</a>
                </div>
            </div>
            
            <!-- Expenses Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('expensesDropdown')">
                    <i class="fas fa-receipt"></i> Expenses <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="expensesDropdown" class="dropdown-content">
                    <a href="../expenses.php"><i class="fas fa-list"></i> All Expenses</a>
                    <a href="../expense_categories.php"><i class="fas fa-tags"></i> Categories</a>
                </div>
            </div>
            
            <!-- Assets Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('assetsDropdown')">
                    <i class="fas fa-file-alt"></i> Assets <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="assetsDropdown" class="dropdown-content">
                    <a href="../assets.php"><i class="fas fa-clipboard-list"></i> All Assets</a>
                    <a href="../asset_categories.php"><i class="fas fa-tags"></i> Categories</a>
                    <a href="../asset_report.php"><i class="fas fa-chart-pie"></i> Reports</a>
                </div>
            </div>
            
            <!-- Harvest Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('harvestDropdown')">
                    <i class="fas fa-seedling"></i> Harvest <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="harvestDropdown" class="dropdown-content">
                    <a href="../harvest_crop.php"><i class="fas fa-seedling"></i> Crop Harvest</a>
                    <a href="../harvest_crop_analysis.php"><i class="fas fa-chart-line"></i> Harvest Analysis</a>
                    <a href="../crop_manag.php"><i class="fas fa-leaf"></i> Crop Management</a>
                    <a href="../harvest_records.php"><i class="fas fa-clipboard-check"></i> Harvest Records</a>
                    <a href="../farm_calendar.php"><i class="fas fa-calendar-alt"></i> Farm Calendar</a>
                    <a href="../task.php"><i class="fas fa-tasks"></i> Tasks</a>
                </div>
            </div>

            <!-- Stakeholders Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('stakeholdersDropdown')">
                    <i class="fas fa-users"></i> Stakeholders <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="stakeholdersDropdown" class="dropdown-content">
                    <a href="../clients.php"><i class="fas fa-user-tie"></i> Clients</a>
                    <a href="../user.php"><i class="fas fa-user-shield"></i> Users</a>
                </div>
            </div>

            <!-- Labor Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('laborDropdown')">
                    <i class="fas fa-people-carry"></i> Labor <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="laborDropdown" class="dropdown-content">
                    <a href="../labor_management.php"><i class="fas fa-users-cog"></i> Labor Management</a>
                    <a href="../labor_reports.php"><i class="fas fa-file-contract"></i> Labor Reports</a>
                    <a href="../labor_tracking.php"><i class="fas fa-user-clock"></i> Labor Tracking</a>
                </div>
            </div>
            <a href="../add_chem.php" class="nav-btn"><i class="fas fa-user-clock"></i> Add Chemical Supply</a>
            <a href="../chem_supply.php" class="nav-btn"><i class="fas fa-user-clock"></i> Chemical Supply</a>
            <a href="../audit_logs.php" class="nav-btn"><i class="fas fa-shield-alt"></i> Audit </a>
            <a href="../report.php" class="nav-btn"><i class="fas fa-file-alt"></i> Reports</a>
        </div>
        
        <!-- User Info and Logout -->
         <!-- Add this to your navbar, typically near the user info and logout section -->
        <!-- Add this to your navbar, typically near the user info and logout section -->
        <div class="nav-toggle-container me-2">
    <div class="toggle-switch">
        <?php 
        // Get current page filename
        $current_page = basename($_SERVER['PHP_SELF']);
        $is_dashboard = ($current_page == '../admin/dashboard.php');
        ?>
        <a href="<?php echo $is_dashboard ? '../admin/dashboard.php' : '../admin/payroll_dashboard.php'; ?>" 
           class="toggle-btn <?php echo $is_dashboard ? 'dashboard-active' : 'payroll-active'; ?>">
            <span class="toggle-label"><i class="fas <?php echo $is_dashboard ? 'fa-chart-line' : 'fa-money-bill-wave'; ?>"></i> 
            <?php echo $is_dashboard ? 'Switch to Finance' : 'Switch to Payroll'; ?></span>
        </a>
    </div>
        <div>
            <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
     
</div>
</nav>

<!-- JavaScript for dropdown functionality -->
<script>
// Store the currently open dropdown ID
let currentOpenDropdown = null;

// Function to toggle a specific dropdown
function toggleDropdown(dropdownId) {
    // Close any open dropdown first
    if (currentOpenDropdown && currentOpenDropdown !== dropdownId) {
        document.getElementById(currentOpenDropdown).classList.remove('show');
        document.querySelector(`[onclick="toggleDropdown('${currentOpenDropdown}')"]`).classList.remove('active');
    }
    
    const dropdown = document.getElementById(dropdownId);
    const button = document.querySelector(`[onclick="toggleDropdown('${dropdownId}')"]`);
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
    button.classList.toggle('active');
    
    // Update currently open dropdown reference
    if (dropdown.classList.contains('show')) {
        currentOpenDropdown = dropdownId;
    } else {
        currentOpenDropdown = null;
    }
}

// Close the dropdown when clicking anywhere else on the page
window.onclick = function(event) {
    if (!event.target.matches('.nav-btn') && !event.target.closest('.dropdown-content')) {
        const dropdowns = document.getElementsByClassName("dropdown-content");
        const buttons = document.querySelectorAll('.nav-btn');
        
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
        
        for (let i = 0; i < buttons.length; i++) {
            buttons[i].classList.remove('active');
        }
        
        currentOpenDropdown = null;
    }
}
</script>

    <div class="container-fluid py-4">
        <!-- Date and Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h4><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h4>
                <p class="text-muted">Welcome back! Here's your farm financial summary as of <?php echo date('F d, Y'); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary"><i class="fas fa-calendar-alt"></i> This Month</button>
                    <button type="button" class="btn btn-outline-secondary"><i class="fas fa-chart-line"></i> Compare</button>
                    <button type="button" class="btn btn-outline-secondary"><i class="fas fa-download"></i> Export</button>
                </div>
            </div>
        </div>
        
        <!-- Quick Action Buttons -->
        <div class="quick-actions">
            <a href="../sales.php" class="quick-action-btn">
                <i class="fas fa-coins"></i>
                Add Sale
            </a>
            <a href="../expenses.php" class="quick-action-btn">
                <i class="fas fa-file-invoice-dollar"></i>
                Add Expense
            </a>
            <a href="../clients.php" class="quick-action-btn">
                <i class="fas fa-chart-pie"></i>
                Reports
            </a>
            <a href="../clients.php" class="quick-action-btn">
            <i class="fas fa-users"></i>
                Clients
            </a>
            <a href="../user.php" class="quick-action-btn">
            <i class="fas fa-user"></i> 
            Users
        </a>
        </div>

        <!-- Financial Summary Cards -->
        <div class="row">
            <div class="col-md-4 col-lg-2-4">
                <div class="dashboard-card sales-card">
                    <div class="card-header bg-transparent border-0">
                        Total Sales
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="card-body">
                    <div class="metric-value"> <?php echo number_format($dashboardData['finance']['total_sales'] ?? 0.00, 2); ?>GHS</div>
                        <div class="metric-label">
                            <?php 
                                $prev_month_sales = isset($sales_data[4]) ? $sales_data[4] : 1;
                                $current_month_sales = isset($sales_data[5]) ? $sales_data[5] : 0;
                                $sales_growth = ($prev_month_sales > 0) ? (($current_month_sales - $prev_month_sales) / $prev_month_sales) * 100 : 0;
                                $growth_icon = ($sales_growth >= 0) ? 'fa-arrow-up' : 'fa-arrow-down';
                                $growth_color = ($sales_growth >= 0) ? 'text-success' : 'text-danger';
                            ?>
                            <span class="<?php echo $growth_color; ?>">
                                <i class="fas <?php echo $growth_icon; ?>"></i> 
                                <?php echo abs(number_format($sales_growth, 1)); ?>%
                            </span> from last month
                        </div>
                    </div>
                </div>
            </div>


            <div class="col-md-4 col-lg-2-4">
    <div class="dashboard-card revenue-card">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
            <span>Total Revenue</span>
            <i class="fas fa-wallet"></i>
        </div>
        <div class="card-body">
            <div class="metric-value h4 font-weight-bold">
                <?php echo number_format($dashboardData['finance']['total_revenue'] ?? 0.00, 2); ?> GHS
            </div>
            <div class="metric-label small mt-2">
                <span class="text-success d-block mb-1">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo number_format($dashboardData['finance']['total_sales'] ?? 0.00, 2); ?> GHS Paid
                </span>
                <span class="text-danger d-block">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo number_format($dashboardData['finance']['total_receivables'] ?? 0.00, 2); ?> GHS Not Paid
                </span>
            </div>
        </div>
    </div>
</div>




            <div class="col-md-4 col-lg-2-4">
                <div class="dashboard-card expenses-card">
                    <div class="card-header bg-transparent border-0">
                        Total Expenses
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="card-body">
                    <div class="metric-value"><?php echo number_format($dashboardData['finance']['total_expenses'] ?? 0.00, 2); ?> GHS</div>
                        <div class="metric-label">
                            <?php 
                                $prev_month_expenses = isset($expenses_data[4]) ? $expenses_data[4] : 1;
                                $current_month_expenses = isset($expenses_data[5]) ? $expenses_data[5] : 0;
                                $expenses_growth = ($prev_month_expenses > 0) ? (($current_month_expenses - $prev_month_expenses) / $prev_month_expenses) * 100 : 0;
                                $growth_icon = ($expenses_growth > 0) ? 'fa-arrow-up' : 'fa-arrow-down';
                                $growth_color = ($expenses_growth > 0) ? 'text-danger' : 'text-success';
                            ?>
                            <span class="<?php echo $growth_color; ?>">
                                <i class="fas <?php echo $growth_icon; ?>"></i> 
                                <?php echo abs(number_format($expenses_growth, 1)); ?>%
                            </span> from last month
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-lg-2-4">
                <div class="dashboard-card profit-card">
                    <div class="card-header bg-transparent border-0">
                        Net Profit
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="card-body">
                        <div class="metric-value"><?php echo number_format($dashboardData['finance']['net_profit'] ?? 0.00, 2); ?> GHS
                        </div>
                        <div class="metric-label">
                        <?php
$profit_margin = $dashboardData['finance']['profit_margin'] ?? 0;
?>

<span class="<?php echo ($profit_margin >= 30) ? 'text-success' : (($profit_margin >= 15) ? 'text-warning' : 'text-danger'); ?>">
    <?php echo number_format($profit_margin, 1); ?>% profit margin
</span>

                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-2-4">
                <div class="dashboard-card receivables-card">
                    <div class="card-header bg-transparent border-0">
                        Receivables
                        <i class="fas fa-hand-holding-dollar"></i>
                    </div>
                    <div class="card-body">
                    <div class="metric-value"><?php echo number_format($dashboardData['finance']['total_receivables'] ?? 0, 2); ?> GHS</div>
                        <div class="metric-label">
                            Clients owe us this amount
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-2-4">
                <div class="dashboard-card payables-card">
                    <div class="card-header bg-transparent border-0">
                        Payables
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-body">
                    <div class="metric-value"><?php echo number_format($dashboardData['finance']['total_payables'] ?? 0, 2); ?> GHS</div>
                        <div class="metric-label">
                            We owe others this amount
                        </div>
                    </div>
                </div>


            </div>
        </div>


                  <!-- Crop Management Section -->
                  <div class="row">
    <div class="col-md-12">
        <h4 class="section-title">Crop Management</h4>
    </div>
    
    <!-- Crop Statistics Cards -->
    <div class="col-md-3">
        <div class="dashboard-card crops-card">
            <div class="card-header bg-transparent border-0">
                Total Crops
                <i class="fas fa-seedling"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['crops']['total_crops']; ?></div>
                <div class="metric-label">
                    Varieties being managed
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
    <div class="dashboard-card cycles-card">
        <div class="card-header bg-transparent border-0">
            Planned Cycles
            <i class="fas fa-calendar-plus"></i>
        </div>
        <div class="card-body">
            <div class="metric-value"><?php echo $dashboardData['crop_cycles']['planned_cycles'] ?? 0; ?></div>
            <div class="metric-label text-muted">Planned but not started</div>
        </div>
    </div>
</div>

<div class="col-md-3">
    <div class="dashboard-card cycles-card">
        <div class="card-header bg-transparent border-0">
            In Progress
            <i class="fas fa-sync-alt"></i>
        </div>
        <div class="card-body">
            <div class="metric-value text-info"><?php echo $dashboardData['crop_cycles']['in_progress_cycles'] ?? 0; ?></div>
            <div class="metric-label text-muted">Growing cycles in progress</div>
        </div>
    </div>
</div>

<div class="col-md-3">
    <div class="dashboard-card cycles-card">
        <div class="card-header bg-transparent border-0">
            Completed
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="card-body">
            <div class="metric-value text-success"><?php echo $dashboardData['crop_cycles']['completed_cycles'] ?? 0; ?></div>
            <div class="metric-label text-muted">Successfully harvested</div>
        </div>
    </div>
</div>

<div class="col-md-3">
    <div class="dashboard-card cycles-card">
        <div class="card-header bg-transparent border-0">
            Failed
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="card-body">
            <div class="metric-value text-danger"><?php echo $dashboardData['crop_cycles']['failed_cycles'] ?? 0; ?></div>
            <div class="metric-label text-muted">Cycles that failed</div>
        </div>
    </div>
</div>

    
    <div class="col-md-3">
        <div class="dashboard-card tasks-card">
            <div class="card-header bg-transparent border-0">
                Pending Tasks
                <i class="fas fa-tasks"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['crops']['pending_tasks']; ?></div>
                <div class="metric-label">
                    Farm tasks to be completed
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-card harvest-card">
            <div class="card-header bg-transparent border-0">
                Harvests This Month
                <i class="fas fa-tractor"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['crops']['harvests_this_month']; ?></div>
                <div class="metric-label">
                    Completed harvests
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Labor Management Section -->
<div class="row mt-4">
    <div class="col-md-12">
        <h4 class="section-title">Labor & HR</h4>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-card employees-card">
            <div class="card-header bg-transparent border-0">
                Total Employees
                <i class="fas fa-users"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['employees']['total_employees']; ?></div>
                <div class="metric-label">
                    <span class="text-primary">
                        <?php echo $dashboardData['employees']['active_employees']; ?> active
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-card fulltime-card">
            <div class="card-header bg-transparent border-0">
                Fulltime Staff
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['employees']['fulltime_employees']; ?></div>
                <div class="metric-label">
                    Permanent employees
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-card byday-card">
            <div class="card-header bg-transparent border-0">
                By-Day Workers
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['employees']['byday_employees']; ?></div>
                <div class="metric-label">
                    Temporary workers
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="dashboard-card labor-cost-card">
            <div class="card-header bg-transparent border-0">
                Monthly Labor Cost
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-body">
            <div class="metric-value">
    <?php echo number_format($dashboardData['labor']['monthly_labor_cost'] ?? 0, 2); ?> GHS
</div>
<div class="metric-label">
    <span class="text-danger">
        <?php echo number_format($dashboardData['labor']['unpaid_labor_amount'] ?? 0, 2); ?> GHS unpaid
    </span>
</div>

            </div>
        </div>
    </div>
</div>

<!-- Asset Management Section -->
<div class="row mt-4">
    <div class="col-md-12">
        <h4 class="section-title">Assets & Equipment</h4>
    </div>
    
    <div class="col-md-4">
        <div class="dashboard-card assets-card">
            <div class="card-header bg-transparent border-0">
                Farm Assets
                <i class="fas fa-tractor"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['assets']['total_assets']; ?></div>
                <div class="metric-label">
                    <span class="text-success">
                        <?php echo $dashboardData['assets']['active_assets']; ?> active assets
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="dashboard-card asset-transactions-card">
            <div class="card-header bg-transparent border-0">
                Recent Transactions
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo $dashboardData['assets']['recent_transactions']; ?></div>
                <div class="metric-label">
                    Asset transactions this month
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="dashboard-card maintenance-card">
            <div class="card-header bg-transparent border-0">
                Unpaid Invoices
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="card-body">
                <div class="metric-value"><?php echo count($dashboardData['unpaid_invoices']); ?></div>
                <div class="metric-label">
                    <span class="text-warning">
                        Outstanding payments due
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- Charts and Analytics Section -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="dashboard-card">
            <div class="card-header">
                Expense Categories
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="card-body">
                <canvas id="expenseCategoriesChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="dashboard-card">
            <div class="card-header">
                Annual Performance
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="card-body">
                <canvas id="annualPerformanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Unpaid Invoices and Tasks Section -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="dashboard-card">
            <div class="card-header">
                Unpaid Invoices
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="card-body p-0">
                <table class="table dashboard-table mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboardData['unpaid_invoices'] as $invoice): ?>
                        <tr>
                            <td><?php echo $invoice['invoice_no']; ?></td>
                            <td><?php echo $invoice['client_name']; ?></td>
                            <td><?php echo number_format($invoice['total_amount'], 2); ?> GHS</td>
                            <td>
                                <?php 
                                    $due_date = new DateTime($invoice['due_date']);
                                    $today = new DateTime();
                                    $days_diff = $today->diff($due_date)->days;
                                    $past_due = $today > $due_date;
                                    
                                    echo $invoice['due_date'];
                                    if ($past_due) {
                                        echo " <span class='badge bg-danger'>Past due</span>";
                                    } elseif ($days_diff <= 3) {
                                        echo " <span class='badge bg-warning'>Soon</span>";
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="dashboard-card">
            <div class="card-header">
                Upcoming Farm Tasks
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="card-body p-0">
                <table class="table dashboard-table mb-0">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Crop</th>
                            <th>Type</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboardData['upcoming_tasks'] as $task): ?>
                        <tr>
                            <td><?php echo $task['task_name']; ?></td>
                            <td><?php echo $task['crop_name']; ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $task['color_code']; ?>">
                                    <?php echo $task['type_name']; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $task_date = new DateTime($task['scheduled_date']);
                                    $today = new DateTime();
                                    $days_diff = $today->diff($task_date)->days;
                                    
                                    echo $task['scheduled_date'];
                                    if ($days_diff <= 1) {
                                        echo " <span class='badge bg-danger'>Today/Tomorrow</span>";
                                    } elseif ($days_diff <= 3) {
                                        echo " <span class='badge bg-warning'>Soon</span>";
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- System Information Section -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="dashboard-card">
            <div class="card-header">
                System Overview
                <i class="fas fa-server"></i>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="system-metric">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h3><?php echo $dashboardData['system']['total_users']; ?></h3>
                            <p>System Users</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="system-metric">
                            <i class="fas fa-user-tie fa-2x text-success mb-2"></i>
                            <h3><?php echo $dashboardData['system']['total_clients']; ?></h3>
                            <p>Registered Clients</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="system-metric">
                            <i class="fas fa-history fa-2x text-info mb-2"></i>
                            <h3><?php echo $dashboardData['system']['todays_actions']; ?></h3>
                            <p>Actions Today</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        <div class="row">
            <!-- Chart Section -->
            <div class="col-md-8">
                
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header">
                                Top Products/Crops
                                <i class="fas fa-seedling"></i>
                            </div>
                            <div class="card-body p-0">
                                <table class="table dashboard-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-end">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $top_products = $dashboardData['top_products'] ?? []; ?>

<?php foreach ($top_products as $product): ?>
    <tr>
        <td><?php echo $product['product_name']; ?></td>
        <td class="text-end"><?php echo number_format($product['total'], 2); ?> GHS</td>
    </tr>
<?php endforeach; ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header">
                                Recent Transactions
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="card-body p-0">
                                <table class="table dashboard-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $recent_transactions = $dashboardData['recent_transactions'] ?? []; ?>

<?php foreach ($recent_transactions as $tx): ?>
    <tr>
        <td>
            <?php if ($tx['type'] == 'Sale'): ?>
                <span class="tag bg-success text-white">Sale</span>
            <?php else: ?>
                <span class="tag bg-danger text-white">Expense</span>
            <?php endif; ?>
        </td>
        <td><?php echo $tx['description']; ?></td>
        <td class="text-end"><?php echo number_format($tx['amount'], 2); ?> GHS</td>
    </tr>
<?php endforeach; ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Side Widgets -->
            <div class="col-md-4">
                <!-- Financial Health Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        Financial Health
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="card-body">
                        <div class="progress-card">
                            <div class="progress-title">
                                <span>Profit Margin</span>
                                <span><?php echo number_format($profit_margin, 1); ?>%</span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, $profit_margin); ?>%" aria-valuenow="<?php echo $profit_margin; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            
                            <div class="progress-title">
                                <span>Sales Target</span>
                                <span>65%</span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            
                            <?php $debt_ratio = $dashboardData['finance']['debt_ratio'] ?? 0; ?>

<div class="progress-title">
    <span>Debt Ratio</span>
    <span><?php echo number_format($debt_ratio, 1); ?>%</span>
</div>
<div class="progress">
    <div class="progress-bar bg-warning" role="progressbar"
         style="width: <?php echo min(100, $debt_ratio); ?>%"
         aria-valuenow="<?php echo $debt_ratio; ?>" aria-valuemin="0" aria-valuemax="100">
    </div>
</div>

                        </div>
                    </div>
                </div>
                
                <!-- Weather Widget (Placeholder) -->
              


<!-- JavaScript for Charts -->


<!-- Additional CSS for Dashboard -->
<style>

</style>
        </div>
    </div>
    
   

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>


        // Financial Chart
        const ctx = document.getElementById('financialChart').getContext('2d');
        
        const months = <?php echo json_encode($months); ?>;
        const salesData = <?php echo json_encode($sales_data); ?>;
        const expensesData = <?php echo json_encode($expenses_data); ?>;
        
        // Calculate profit data
        const profitData = salesData.map((sale, index) => sale - expensesData[index]);
        
        const financialChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Sales',
                        data: salesData,
                        backgroundColor: 'rgba(44, 110, 73, 0.2)',
                        borderColor: '#2c6e49',
                        borderWidth: 2,
                        tension: 0.4
                    },
                    {
                        label: 'Expenses',
                        data: expensesData,
                        backgroundColor: 'rgba(220, 53, 69, 0.2)',
                        borderColor: '#dc3545',
                        borderWidth: 2,
                        tension: 0.4
                    },
                    {
                        label: 'Profit',
                        data: profitData,
                        backgroundColor: 'rgba(13, 110, 253, 0.2)',
                        borderColor: '#0d6efd',
                        borderWidth: 2,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-GH', { 
                                        style: 'currency', 
                                        currency: 'GHS',
                                        minimumFractionDigits: 2
                                    }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' GHS';
                            }
                        }
                    }
                }
            }
        });

        
    </script>


<footer class="footer">
        <div class="container">
            <p>Jubert Farms</p>
            <p>Food is Health | Food is Wealth | Food is Life.</p>
            <p>© <?php echo date('Y'); ?> Farm Finance Management System. All rights reserved.</p>
        </div>
    </footer>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Expense Categories Chart
    var expenseCategoriesCtx = document.getElementById('expenseCategoriesChart').getContext('2d');
    var expenseCategories = <?php echo json_encode(array_column($dashboardData['expense_categories'], 'category_name')); ?>;
    var expenseValues = <?php echo json_encode(array_column($dashboardData['expense_categories'], 'total')); ?>;
    
    var expenseCategoriesChart = new Chart(expenseCategoriesCtx, {
        type: 'doughnut',
        data: {
            labels: expenseCategories,
            datasets: [{
                data: expenseValues,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#5a5c69', '#858796', '#6610f2', '#6f42c1', '#20c9a6'
                ],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        var dataset = data.datasets[tooltipItem.datasetIndex];
                        var total = dataset.data.reduce(function(previousValue, currentValue) {
                            return previousValue + currentValue;
                        });
                        var currentValue = dataset.data[tooltipItem.index];
                        var percentage = Math.floor(((currentValue/total) * 100)+0.5);
                        return data.labels[tooltipItem.index] + ': ' + 
                            currentValue.toFixed(2) + ' GHS (' + percentage + '%)';
                    }
                }
            },
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12
                }
            },
            cutoutPercentage: 70
        }
    });
    
    // Annual Performance Chart
    var annualPerformanceCtx = document.getElementById('annualPerformanceChart').getContext('2d');
    var months = <?php echo json_encode(array_column($dashboardData['monthly_sales_chart'], 'month')); ?>;
    var salesData = <?php echo json_encode(array_column($dashboardData['monthly_sales_chart'], 'total')); ?>;
    var expensesData = <?php echo json_encode(array_column($dashboardData['monthly_expenses_chart'], 'total')); ?>;
    
    // Calculate profit data
    var profitData = [];
    for (var i = 0; i < salesData.length; i++) {
        var expense = expensesData[i] || 0;
        profitData.push(salesData[i] - expense);
    }
    
    var annualPerformanceChart = new Chart(annualPerformanceCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Sales',
                    data: salesData,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    tension: 0.3
                },
                {
                    label: 'Expenses',
                    data: expensesData,
                    backgroundColor: 'rgba(231, 74, 59, 0.05)',
                    borderColor: 'rgba(231, 74, 59, 1)',
                    pointBackgroundColor: 'rgba(231, 74, 59, 1)',
                    tension: 0.3
                },
                {
                    label: 'Profit',
                    data: profitData,
                    backgroundColor: 'rgba(28, 200, 138, 0.05)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                    tension: 0.3
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            return value.toLocaleString() + ' GHS';
                        }
                    }
                }]
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        var label = data.datasets[tooltipItem.datasetIndex].label || '';
                        return label + ': ' + tooltipItem.yLabel.toLocaleString() + ' GHS';
                    }
                }
            }
        }
    });
});
</script>
</body>
</html>