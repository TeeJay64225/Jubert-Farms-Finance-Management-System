<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Authentication check
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

// Function to format currency
function formatCurrency($amount) {
    return 'GHS ' . number_format($amount, 2);
}

// Database helper function to prevent SQL injection
function executeQuery($conn, $query, $params = []) {
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';  // integer
            } elseif (is_float($param)) {
                $types .= 'd';  // double
            } elseif (is_string($param)) {
                $types .= 's';  // string
            } else {
                $types .= 'b';  // blob
            }
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Handle theme toggle
$theme = isset($_COOKIE['dashboard_theme']) ? $_COOKIE['dashboard_theme'] : 'light';

if (isset($_POST['toggle_theme'])) {
    $theme = ($theme === 'light') ? 'dark' : 'light';
    setcookie('dashboard_theme', $theme, time() + (86400 * 30), "/"); // 30 days
    
    // Log the theme change
    $userId = $_SESSION['user_id'];
    $action = "Changed dashboard theme to $theme mode";
    executeQuery($conn, "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)", [$userId, $action]);
    
    // Redirect to avoid form resubmission
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Fetch dashboard data
class DashboardData {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getTotalEmployees() {
        $result = executeQuery($this->conn, "SELECT COUNT(*) as total FROM employees");
        return ($row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;
    }
    
    public function getActiveEmployees() {
        $result = executeQuery($this->conn, "SELECT COUNT(*) as active FROM employees WHERE status = 'Active'");
        return ($row = mysqli_fetch_assoc($result)) ? $row['active'] : 0;
    }
    
    public function getTotalPayroll() {
        $result = executeQuery($this->conn, "SELECT SUM(amount) as total FROM payroll WHERE MONTH(payment_date) = MONTH(CURRENT_DATE())");
        return ($row = mysqli_fetch_assoc($result)) ? ($row['total'] ?: 0) : 0;
    }
    
    public function getAverageSalary() {
        $result = executeQuery($this->conn, "SELECT AVG(salary) as avg FROM employees");
        return ($row = mysqli_fetch_assoc($result)) ? ($row['avg'] ?: 0) : 0;
    }
    
    public function getPositionData() {
        return executeQuery($this->conn, "SELECT position, COUNT(*) as count, AVG(salary) as avg_salary FROM employees GROUP BY position");
    }
    
    public function getLatestEmployees() {
        return executeQuery($this->conn, "SELECT id, first_name, last_name, position, photo, status FROM employees ORDER BY created_at DESC LIMIT 5");
    }
    
    public function getEmploymentTypeCounts() {
        $types = ['Fulltime', 'By-Day'];
        $counts = [];
        
        foreach ($types as $type) {
            $result = executeQuery($this->conn, "SELECT COUNT(*) as count FROM employees WHERE employment_type = ?", [$type]);
            $row = mysqli_fetch_assoc($result);
            $counts[] = $row['count'];
        }
        
        return [
            'types' => $types,
            'counts' => $counts
        ];
    }
    
    public function getPayrollTrend() {
        $result = executeQuery($this->conn, 
            "SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount) as total 
             FROM payroll 
             WHERE payment_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) 
             GROUP BY DATE_FORMAT(payment_date, '%Y-%m') 
             ORDER BY payment_date"
        );
        
        $months = [];
        $amounts = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $months[] = $row['month'];
            $amounts[] = $row['total'];
        }
        
        // If we have less than 6 months of data, pad with estimates
        if (count($months) < 6) {
            $currentMonth = date('n'); // Current month as a number
            for ($i = 0; $i < 6 - count($months); $i++) {
                $monthNum = $currentMonth - count($months) - $i;
                if ($monthNum <= 0) {
                    $monthNum += 12;
                    $year = date('Y') - 1;
                } else {
                    $year = date('Y');
                }
                array_unshift($months, date('M Y', mktime(0, 0, 0, $monthNum, 1, $year)));
                array_unshift($amounts, rand(80000, 120000)); // Simulated data
            }
        }
        
        return [
            'months' => $months,
            'amounts' => $amounts
        ];
    }
}

// Initialize dashboard data
$dashboard = new DashboardData($conn);
$totalEmployees = $dashboard->getTotalEmployees();
$activeEmployees = $dashboard->getActiveEmployees();
$totalPayroll = $dashboard->getTotalPayroll();
$avgSalary = $dashboard->getAverageSalary();
$positionData = $dashboard->getPositionData();
$latestEmployees = $dashboard->getLatestEmployees();
$employmentData = $dashboard->getEmploymentTypeCounts();
$payrollTrend = $dashboard->getPayrollTrend();

// Prepare data for charts
$positions = [];
$counts = [];
$avgSalaries = [];

if ($positionData) {
    while ($row = mysqli_fetch_assoc($positionData)) {
        $positions[] = $row['position'];
        $counts[] = $row['count'];
        $avgSalaries[] = round($row['avg_salary'], 2);
    }
}

// Prepare JSON for JavaScript
$chartData = [
    'positions' => $positions,
    'counts' => $counts,
    'avgSalaries' => $avgSalaries,
    'employmentTypes' => $employmentData['types'],
    'employmentCounts' => $employmentData['counts'],
    'payrollMonths' => $payrollTrend['months'],
    'payrollAmounts' => $payrollTrend['amounts']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/payroll_dashboard.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <!-- Add theme class to body -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const theme = "<?php echo $theme; ?>";
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
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
        <!-- Logo and Brand on the left -->
        <span class="navbar-brand d-flex align-items-center">
            <div class="logo-container-nav me-2">
                <img src="../assets/logo2.JPG" alt="Farm Logo" class="logo-nav">
            </div>
            Jubert Farms Finance 
        </span>
        
        <!-- Main Navigation Links - Centered -->
        <div class="nav-links-center">
            <a href="../admin/payroll_dashboard.php" class="nav-btn"><i class="fas fa-chart-line"></i> Dashboard</a>

    
            <a href="../admin/payroll.php" class="nav-btn"><i class="fas fa-dollar-sign"></i> Payroll</a>
            <a href="../admin/employee_management.php" class="nav-btn"><i class="fas fa-dollar-sign"></i> employee management</a>
            <a href="../admin/payment_account_management.php" class="nav-btn active"><i class="fas fa-university"></i> Payment Accounts</a>
        </div>
        
        <!-- User Info and Logout on the right -->
 
        <div>
            <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
            <!-- Add this to your navbar, typically near the user info and logout section -->
<div class="nav-toggle-container me-2">
    <div class="toggle-switch">
        <?php 
        // Get current page filename
        $current_page = basename($_SERVER['PHP_SELF']);
        $is_dashboard = ($current_page == '../admin/dashboard.php');
        ?>
        <a href="<?php echo $is_dashboard ? '../admin/payroll_dashboard.php' : '../admin/dashboard.php'; ?>" 
           class="toggle-btn <?php echo $is_dashboard ? 'dashboard-active' : 'payroll-active'; ?>">
            <span class="toggle-label"><i class="fas <?php echo $is_dashboard ? 'fa-chart-line' : 'fa-money-bill-wave'; ?>"></i> 
            <?php echo $is_dashboard ? 'Switch to Payroll' : 'Switch to Finance'; ?></span>
        </a>
    </div>
</div>
</nav>

<br>
<br>

<div class="container-fluid px-4">
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="stat-label">Total Employees</h6>
                        <div class="stat-value"><?php echo $totalEmployees; ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background-color: rgba(13, 110, 253, 0.1);">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="stat-label">Active Employees</h6>
                        <div class="stat-value"><?php echo $activeEmployees; ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background-color: rgba(40, 167, 69, 0.1);">
                        <i class="fas fa-user-check fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="stat-label">Monthly Payroll</h6>
                        <div class="stat-value"><?php echo formatCurrency($totalPayroll); ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background-color: rgba(220, 53, 69, 0.1);">
                        <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="stat-label">Average Salary</h6>
                        <div class="stat-value"><?php echo formatCurrency($avgSalary); ?></div>
                    </div>
                    <div class="rounded-circle p-3" style="background-color: rgba(255, 193, 7, 0.1);">
                        <i class="fas fa-chart-line fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Employee Profiles Row -->
    <div class="row">
        <!-- Salary Distribution Chart -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h5 class="mb-3">Salary Distribution by Position</h5>
                <div class="chart-container">
                    <canvas id="salaryDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Employee Status Chart -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h5 class="mb-3">Employment Type Distribution</h5>
                <div class="chart-container">
                    <canvas id="employmentTypeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Employees -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h5 class="mb-3">Recent Employees</h5>
                <?php if ($latestEmployees && mysqli_num_rows($latestEmployees) > 0): ?>
                    <?php while ($employee = mysqli_fetch_assoc($latestEmployees)): ?>
                        <div class="profile-card">
                            <?php 
                            $photoPath = '../uploads/' . $employee['photo'];
                            if ($employee['photo'] && file_exists($photoPath)): 
                            ?>
                                <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Profile" class="profile-image">
                            <?php else: ?>
                                <div class="profile-image d-flex align-items-center justify-content-center" style="background-color: var(--highlight);">
                                    <span style="color: white; font-size: 1.5rem;">
                                        <?php echo htmlspecialchars(strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1))); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($employee['position']); ?></small>
                            </div>
                            <span class="status-badge status-<?php echo htmlspecialchars($employee['status']); ?>">
                                <?php echo htmlspecialchars($employee['status']); ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center">No employees found</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Payroll Trend -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h5 class="mb-3">Monthly Payroll Trend</h5>
                <div class="chart-container">
                    <canvas id="payrollTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart Initialization -->
<script>
// Get chart data from PHP
const chartData = <?php echo json_encode($chartData); ?>;

// Set chart colors based on theme
function getChartColors() {
    const isDark = document.body.classList.contains('dark-mode');
    return {
        text: isDark ? '#f8f9fa' : '#212529',
        gridLines: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
        colors: [
            '#0d6efd', '#20c997', '#fd7e14', '#dc3545', '#6610f2',
            '#6f42c1', '#d63384', '#198754', '#0dcaf0', '#ffc107'
        ]
    };
}

// Helper to update chart themes
function updateChartsTheme() {
    const colors = getChartColors();
    
    Chart.defaults.color = colors.text;
    Chart.defaults.borderColor = colors.gridLines;
    
    // Update all charts
    Object.values(Chart.instances).forEach(chart => {
        chart.update();
    });
}

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    const colors = getChartColors();
    
    // Set global chart defaults
    Chart.defaults.color = colors.text;
    Chart.defaults.borderColor = colors.gridLines;
    
    // Salary Distribution Chart
    const salaryDistributionCtx = document.getElementById('salaryDistributionChart').getContext('2d');
    const salaryDistributionChart = new Chart(salaryDistributionCtx, {
        type: 'bar',
        data: {
            labels: chartData.positions,
            datasets: [{
                label: 'Average Salary',
                data: chartData.avgSalaries,
                backgroundColor: colors.colors,
                borderColor: 'rgba(0,0,0,0.1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'GHS ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Employment Type Chart
    const employmentTypeCtx = document.getElementById('employmentTypeChart').getContext('2d');
    const employmentTypeChart = new Chart(employmentTypeCtx, {
        type: 'doughnut',
        data: {
            labels: chartData.employmentTypes,
            datasets: [{
                data: chartData.employmentCounts,
                backgroundColor: [colors.colors[0], colors.colors[1]],
                borderColor: 'rgba(255, 255, 255, 0.5)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Payroll Trend Chart
    const payrollTrendCtx = document.getElementById('payrollTrendChart').getContext('2d');
    const payrollTrendChart = new Chart(payrollTrendCtx, {
        type: 'line',
        data: {
            labels: chartData.payrollMonths,
            datasets: [{
                label: 'Monthly Payroll',
                data: chartData.payrollAmounts,
                fill: true,
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderColor: '#0d6efd',
                tension: 0.4,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'GHS ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Listen for theme changes
    const themeToggle = document.querySelector('.theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            // Theme will change after page reload, but we can update charts immediately for smoother experience
            document.body.classList.toggle('dark-mode');
            updateChartsTheme();
            setTimeout(() => {
                document.body.classList.toggle('dark-mode'); // Toggle back to match server state
            }, 100);
        });
    }
});
</script>
</body>
</html>