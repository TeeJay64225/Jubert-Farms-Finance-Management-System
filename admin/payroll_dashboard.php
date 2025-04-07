<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';

// Get summary data for dashboard
$totalEmployees = 0;
$totalPayroll = 0;
$activeEmployees = 0;
$avgSalary = 0;

// Count total employees
$query = "SELECT COUNT(*) as total FROM employees";
$result = mysqli_query($conn, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEmployees = $row['total'];
}

// Count active employees
$query = "SELECT COUNT(*) as active FROM employees WHERE status = 'Active'";
$result = mysqli_query($conn, $query);
if ($row = mysqli_fetch_assoc($result)) {
    $activeEmployees = $row['active'];
}

// Get total payroll amount for current month
$query = "SELECT SUM(amount) as total FROM payroll WHERE MONTH(payment_date) = MONTH(CURRENT_DATE())";
$result = mysqli_query($conn, $query);
if ($row = mysqli_fetch_assoc($result)) {
    $totalPayroll = $row['total'] ?: 0;
}

// Get average salary
$query = "SELECT AVG(salary) as avg FROM employees";
$result = mysqli_query($conn, $query);
if ($row = mysqli_fetch_assoc($result)) {
    $avgSalary = $row['avg'] ?: 0;
}

// Get salary distribution by position
$query = "SELECT position, COUNT(*) as count, AVG(salary) as avg_salary FROM employees GROUP BY position";
$positionData = mysqli_query($conn, $query);

// Get latest employees
$query = "SELECT id, first_name, last_name, position, photo, status FROM employees ORDER BY created_at DESC LIMIT 5";
$latestEmployees = mysqli_query($conn, $query);

// Check for theme preference in cookie or set default
$theme = isset($_COOKIE['dashboard_theme']) ? $_COOKIE['dashboard_theme'] : 'light';

// Handle theme toggle
if (isset($_POST['toggle_theme'])) {
    $theme = ($theme === 'light') ? 'dark' : 'light';
    setcookie('dashboard_theme', $theme, time() + (86400 * 30), "/"); // 30 days
    
    // Log the theme change
    $userId = $_SESSION['user_id'];
    $action = "Changed dashboard theme to $theme mode";
    mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$userId', '$action')");
    
    // Redirect to avoid form resubmission
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Function to format currency
function formatCurrency($amount) {
    return 'GHS ' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #212529;
            --border-color: #dee2e6;
            --highlight: #0d6efd;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .dark-mode {
            --bg-color: #212529;
            --card-bg: #343a40;
            --text-color: #f8f9fa;
            --border-color: #495057;
            --highlight: #0d6efd;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }
        
        .dashboard-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--highlight);
        }
        
        .stat-label {
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .profile-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }
        
        .profile-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid var(--highlight);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .status-Active {
            background-color: #28a745;
            color: white;
        }
        
        .status-Terminated {
            background-color: #dc3545;
            color: white;
        }
        
        .status-Suspended {
            background-color: #ffc107;
            color: black;
        }
        
        /* Navbar styling */
        .navbar {
            background-color: var(--card-bg) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Theme toggle button */
        .theme-toggle {
            cursor: pointer;
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .theme-toggle:hover {
            transform: rotate(30deg);
        }
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="<?php echo $theme === 'dark' ? 'dark-mode' : ''; ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
<style>
    :root {
        --primary-color: #2c6e49;
        --secondary-color: #4c956c;
        --accent-color: #fefee3;
        --light-color: #f0f3f5;
        --dark-color: #1a3a1a;
    }
    
    .navbar {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        padding: 0.8rem 1.5rem;
    }
    
    .navbar-brand {
        font-weight: 700;
        font-size: 1.4rem;
        display: flex;
        align-items: center;
    }
    
    .navbar-brand i {
        margin-right: 10px;
        font-size: 1.8rem;
    }
    
    .footer {
        background-color: var(--light-color);
        padding: 1.5rem;
        margin-top: 2rem;
        text-align: center;
        font-size: 0.9rem;
    }

    /* Logo container styles */
    .logo-container-nav {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: black;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
    }
    
    .logo-nav {
        width: 100%;
        height: auto;
    }
    
    .logo-container {
        text-align: center;
        margin: 0 auto 20px;
        width: 150px;
        height: 120px;
        border-radius: 50%;
        background-color: black;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
    }
    
    /* Dropdown menu styles */
    .nav-item {
        position: relative;
        display: inline-block;
    }
    
    .dropdown-content {
        display: none;
        position: absolute;
        background-color: white;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1;
        border-radius: 4px;
        margin-top: 5px;
    }
    
    .dropdown-content a {
        color: var(--dark-color);
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        text-align: left;
        transition: background-color 0.3s;
    }
    
    .dropdown-content a:hover {
        background-color: var(--light-color);
    }
    
    .nav-item:hover .dropdown-content {
        display: block;
    }
    
    /* Main nav buttons */
    .nav-btn {
        color: white;
        background-color: transparent;
        border: 1px solid white;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        margin-right: 0.5rem;
        transition: all 0.3s;
    }
    
    .nav-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .nav-btn i {
        margin-right: 5px;
    }
</style>

<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand d-flex align-items-center">
            <div class="logo-container-nav me-2">
                <img src="assets/logo2.JPG" alt="Farm Logo" class="logo-nav">
            </div>
            Jubert Farms Finance 
        </span>
        
        <!-- Main Navigation Links -->
        <div class="me-4">
    
            <a href="../admin/payroll_dashboard.php" class="nav-btn"><i class="fas fa-chart-line"></i> Dashboard</a>

            <div class="nav-item d-inline-block">
                <a href="#" class="nav-btn"><i class="fas fa-receipt"></i> Payroll <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
            <a href="../admin/payroll.php" class="nav-btn"><i class="fas fa-dollar-sign"></i> Sales</a>
            <a href="../admin/send_letter_email.php" class="nav-btn"><i class="fas fa-dollar-sign"></i> Slip</a>

            </div>
            </div>
            <!-- Expenses Dropdown -->
            <div class="nav-item d-inline-block">
                <a href="#" class="nav-btn"><i class="fas fa-receipt"></i> Expenses <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="#"><i class="fas fa-list"></i> All Expenses</a>
                    <a href="#"><i class="fas fa-tags"></i> Categories</a>

                </div>
            </div>
            
            <!-- Assets Dropdown -->
            <div class="nav-item d-inline-block">
                <a href="#" class="nav-btn"><i class="fas fa-file-alt"></i> Assets <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="#"><i class="fas fa-clipboard-list"></i> All Assets</a>
                    <a href="#"><i class="fas fa-tags"></i> Categories</a>
                    <a href="asset_report.php"><i class="fas fa-chart-pie"></i> Reports</a>
                </div>
            </div>
            
         
            <a href="#" class="nav-btn"><i class="fas fa-users"></i> Clients</a>
            <a href="#" class="nav-btn"><i class="fas fa-user"></i> User</a>
            <a href="#" class="nav-btn"><i class="fas fa-file-alt"></i> Reports</a>
        </div>
        <form method="post" class="me-3">
                    <button type="submit" name="toggle_theme" class="btn btn-link p-0 theme-toggle" title="Toggle Dark/Light Mode">
                        <?php if ($theme === 'light'): ?>
                            <i class="fas fa-moon" style="color: var(--text-color);"></i>
                        <?php else: ?>
                            <i class="fas fa-sun" style="color: var(--text-color);"></i>
                        <?php endif; ?>
                    </button>
                </form>
        <!-- User Info and Logout -->
        <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
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
                                <?php if ($employee['photo'] && file_exists('../uploads/' . $employee['photo'])): ?>
                                    <img src="../uploads/<?php echo $employee['photo']; ?>" alt="Profile" class="profile-image">
                                <?php else: ?>
                                    <div class="profile-image d-flex align-items-center justify-content-center" style="background-color: var(--highlight);">
                                        <span style="color: white; font-size: 1.5rem;"><?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($employee['position']); ?></small>
                                </div>
                                <span class="status-badge status-<?php echo $employee['status']; ?>">
                                    <?php echo $employee['status']; ?>
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
    
    // Get position data from PHP
    <?php
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
    ?>
    
    // Position and salary data
    const positions = <?php echo json_encode($positions); ?>;
    const counts = <?php echo json_encode($counts); ?>;
    const avgSalaries = <?php echo json_encode($avgSalaries); ?>;
    
    // Get employment types
    const employmentTypes = ['Fulltime', 'By-Day'];
    // Simulate data for employment types (in a real application, fetch this from the database)
    const employmentCounts = [
        <?php 
            $query = "SELECT COUNT(*) as count FROM employees WHERE employment_type = 'Fulltime'";
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_assoc($result);
            echo $row['count'] . ', ';
            
            $query = "SELECT COUNT(*) as count FROM employees WHERE employment_type = 'By-Day'";
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_assoc($result);
            echo $row['count'];
        ?>
    ];
    
    // Simulate past 6 months of payroll data (in a real application, fetch this from the database)
    const payrollMonths = [];
    const payrollAmounts = [];
    
    <?php
    // Get 6 months of payroll data
    $query = "SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount) as total 
              FROM payroll 
              WHERE payment_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) 
              GROUP BY DATE_FORMAT(payment_date, '%Y-%m') 
              ORDER BY payment_date";
    $result = mysqli_query($conn, $query);
    
    $months = [];
    $amounts = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $months[] = $row['month'];
            $amounts[] = $row['total'];
        }
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
    
    echo "const payrollMonths = " . json_encode($months) . ";\n";
    echo "const payrollAmounts = " . json_encode($amounts) . ";\n";
    ?>
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        const colors = getChartColors();
        
        // Salary Distribution Chart
        const salaryDistributionCtx = document.getElementById('salaryDistributionChart').getContext('2d');
        const salaryDistributionChart = new Chart(salaryDistributionCtx, {
            type: 'bar',
            data: {
                labels: positions,
                datasets: [{
                    label: 'Average Salary',
                    data: avgSalaries,
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
                                return '$' + value.toLocaleString();
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
                labels: employmentTypes,
                datasets: [{
                    data: employmentCounts,
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
                labels: payrollMonths,
                datasets: [{
                    label: 'Monthly Payroll',
                    data: payrollAmounts,
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
                                return '$' + value.toLocaleString();
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