<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';

// Get financial summary data
$sql = "
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM sales WHERE payment_status = 'Paid') AS total_sales,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE payment_status = 'Paid') AS total_expenses,
        (SELECT COALESCE(SUM(amount), 0) FROM sales WHERE payment_status = 'Not Paid') AS total_receivables,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE payment_status = 'Not Paid') AS total_payables
";
$result = $conn->query($sql);
$finance = $result->fetch_assoc();

$total_sales = $finance['total_sales'];
$total_expenses = $finance['total_expenses'];
$net_profit = $total_sales - $total_expenses;
$total_receivables = $finance['total_receivables'];
$total_payables = $finance['total_payables'];

// Get monthly data for charts (last 6 months)
$months = [];
$sales_data = [];
$expenses_data = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i month"));
    $months[] = $month;
    
    $month_num = date('m', strtotime("-$i month"));
    $year = date('Y', strtotime("-$i month"));
    
    // Get monthly sales
    $sql = "SELECT COALESCE(SUM(amount), 0) AS total 
            FROM sales 
            WHERE MONTH(sale_date) = '$month_num' 
            AND YEAR(sale_date) = '$year'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $sales_data[] = $row['total'];
    
    // Get monthly expenses
    $sql = "SELECT COALESCE(SUM(amount), 0) AS total 
            FROM expenses 
            WHERE MONTH(expense_date) = '$month_num' 
            AND YEAR(expense_date) = '$year'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $expenses_data[] = $row['total'];
}

// Get top products/crops
$sql = "SELECT product_name, SUM(amount) as total 
        FROM sales 
        GROUP BY product_name 
        ORDER BY total DESC 
        LIMIT 5";
$result = $conn->query($sql);
$top_products = [];
while ($row = $result->fetch_assoc()) {
    $top_products[] = $row;
}

// Get recent transactions
// Get recent transactions
$sql = "SELECT 'Sale' as type, s.product_name as description, s.amount, s.sale_date as transaction_date, s.payment_status
        FROM sales s
        UNION ALL
        SELECT 'Expense' as type, e.expense_reason as description, e.amount, e.expense_date as transaction_date, e.payment_status
        FROM expenses e
        ORDER BY transaction_date DESC
        LIMIT 5";
$result = $conn->query($sql);
$recent_transactions = [];
while ($row = $result->fetch_assoc()) {
    $recent_transactions[] = $row;
}

$conn->close();

// Calculate metrics
$profit_margin = ($total_sales > 0) ? ($net_profit / $total_sales) * 100 : 0;
$debt_ratio = ($total_receivables > 0) ? ($total_payables / $total_receivables) * 100 : 0;



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Finance Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c6e49;
            --secondary-color: #4c956c;
            --accent-color: #fefee3;
            --light-color: #f0f3f5;
            --dark-color: #1a3a1a;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        .dashboard-card {
            border-radius: 12px;
            box-shadow: 0 6px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0,0,0,0.12);
        }
        
        .card-header {
            font-weight: 600;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header i {
            font-size: 1.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            margin-bottom: 20px;
        }
        
        .progress {
            height: 0.8rem;
            border-radius: 0.4rem;
        }
        
        .table-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            background-color: white;
        }
        
        .dashboard-table {
            margin-bottom: 0;
        }
        
        .dashboard-table thead {
            background-color: var(--light-color);
        }
        
        .tag {
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .quick-action-btn {
            flex: 1;
            text-align: center;
            padding: 15px 10px;
            border-radius: 10px;
            background-color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            color: var(--dark-color);
        }
        
        .quick-action-btn:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }
        
        .quick-action-btn i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 8px;
        }
        
        .quick-action-btn a {
           text-decoration: none;
        }
        
        .progress-card {
            padding: 1.5rem;
            border-radius: 12px;
            background-color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .progress-title {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .weather-widget {
            background: linear-gradient(135deg, #4DA0B0, #D39D38);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            height: 100%;
        }
        
        .sales-card {
            background-color: rgba(44, 110, 73, 0.1);
            border-left: 5px solid var(--primary-color);
        }
        
        .expenses-card {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 5px solid #dc3545;
        }
        
        .profit-card {
            background-color: rgba(13, 110, 253, 0.1);
            border-left: 5px solid #0d6efd;
        }
        
        .receivables-card {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 5px solid #ffc107;
        }
        
        .payables-card {
            background-color: rgba(108, 117, 125, 0.1);
            border-left: 5px solid #6c757d;
        }
        
        .footer {
            background-color: var(--light-color);
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-leaf"></i> Farm Finance Dashboard</span>
        <div class="d-flex">
            <!-- Main Navigation Links -->
            <div class="me-4">
            <a href="../employee/employee_dashboard.php" class="btn btn-outline-light me-2"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="../employee/employee_sale.php" class="btn btn-outline-light me-2"><i class="fas fa-dollar-sign"></i> Sales</a>
                <a href="../employee/employee_expense.php" class="btn btn-outline-light me-2"><i class="fas fa-receipt"></i> Expenses</a>
</div>
            <!-- User Info and Logout -->
            <div>
            <span class="text-white me-3"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['employee_name'] ?? 'Employee'; ?></span>
                <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

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
            <a href="sales/create.php" class="quick-action-btn">
                <i class="fas fa-coins"></i>
                Add Sale
            </a>
            <a href="expenses/create.php" class="quick-action-btn">
                <i class="fas fa-file-invoice-dollar"></i>
                Add Expense
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
                        <div class="metric-value"><?php echo number_format($total_sales, 2); ?> GHS</div>
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
                <div class="dashboard-card expenses-card">
                    <div class="card-header bg-transparent border-0">
                        Total Expenses
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="card-body">
                        <div class="metric-value"><?php echo number_format($total_expenses, 2); ?> GHS</div>
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
                        <div class="metric-value"><?php echo number_format($net_profit, 2); ?> GHS</div>
                        <div class="metric-label">
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
                        <div class="metric-value"><?php echo number_format($total_receivables, 2); ?> GHS</div>
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
                        <div class="metric-value"><?php echo number_format($total_payables, 2); ?> GHS</div>
                        <div class="metric-label">
                            We owe others this amount
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Chart Section -->
            <div class="col-md-8">
                <div class="dashboard-card">
                    <div class="card-header">
                        Financial Performance (Last 6 Months)
                        <div>
                            <button class="btn btn-sm btn-outline-secondary">Monthly</button>
                            <button class="btn btn-sm btn-outline-secondary">Quarterly</button>
                            <button class="btn btn-sm btn-outline-secondary">Yearly</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="financialChart"></canvas>
                        </div>
                    </div>
                </div>
                
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
                            
                            <div class="progress-title">
                                <span>Debt Ratio</span>
                                <span><?php echo number_format($debt_ratio, 1); ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo min(100, $debt_ratio); ?>%" aria-valuenow="<?php echo $debt_ratio; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weather Widget (Placeholder) -->
                <div class="dashboard-card">
                    <div class="card-header">
                        Weather Forecast
                        <i class="fas fa-cloud-sun"></i>
                    </div>
                    <div class="card-body p-0">
                        <div class="weather-widget">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-0">28°C</h3>
                                    <p>Partly Cloudy</p>
                                </div>
                                <div>
                                    <i class="fas fa-cloud-sun fa-3x"></i>
                                </div>
                            </div>
                            <hr>
                            <div class="row text-center">
                                <div class="col">
                                    <div>Thu</div>
                                    <i class="fas fa-sun"></i>
                                    <div>29°C</div>
                                </div>
                                <div class="col">
                                    <div>Fri</div>
                                    <i class="fas fa-cloud-rain"></i>
                                    <div>25°C</div>
                                </div>
                                <div class="col">
                                    <div>Sat</div>
                                    <i class="fas fa-cloud"></i>
                                    <div>26°C</div>
                                </div>
                                <div class="col">
                                    <div>Sun</div>
                                    <i class="fas fa-sun"></i>
                                    <div>30°C</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Calendar Reminders -->
                <div class="dashboard-card">
                    <div class="card-header">
                        Upcoming Tasks
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">Pay suppliers</div>
                                    <small class="text-muted">April 05, 2025</small>
                                </div>
                                <span class="badge bg-danger rounded-pill">2 days</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">Harvest Maize</div>
                                    <small class="text-muted">April 10, 2025</small>
                                </div>
                                <span class="badge bg-warning rounded-pill">7 days</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">Team Meeting</div>
                                    <small class="text-muted">April 15, 2025</small>
                                </div>
                                <span class="badge bg-info rounded-pill">12 days</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <p>© <?php echo date('Y'); ?> Farm Finance Management System. All rights reserved.</p>
        </div>
    </footer>

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
</body>
</html>