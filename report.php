<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: views/login.php");
    exit();
}
include 'config/db.php';

$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

function log_action($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

// Log the report viewing
$month_name = date('F', mktime(0, 0, 0, $month, 1));
log_action($conn, $_SESSION['user_id'], "Viewed profit report for $month_name $year");

// Fetch filtered Sales & Expenses
$sql_sales = "SELECT SUM(amount) AS total_sales FROM sales WHERE YEAR(sale_date) = $year AND MONTH(sale_date) = $month";
$sql_expenses = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month";

// Get category breakdown for expenses
$sql_expense_categories = "SELECT expense_reason as category, SUM(amount) as total 
                         FROM expenses 
                         WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month 
                         GROUP BY expense_reason";

// Get product breakdown for sales
$sql_product_sales = "SELECT product_name as product, SUM(amount) as total 
                     FROM sales 
                     WHERE YEAR(sale_date) = $year AND MONTH(sale_date) = $month 
                     GROUP BY product_name";

$result_sales = $conn->query($sql_sales);
$result_expenses = $conn->query($sql_expenses);
$result_expense_categories = $conn->query($sql_expense_categories);
$result_product_sales = $conn->query($sql_product_sales);

$total_sales = $result_sales->fetch_assoc()['total_sales'] ?? 0;
$total_expenses = $result_expenses->fetch_assoc()['total_expenses'] ?? 0;
$net_profit = $total_sales - $total_expenses;
$profit_margin = ($total_sales > 0) ? (($net_profit / $total_sales) * 100) : 0;

// Format month name again if needed (already defined above)
$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Include header
include 'views/header.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Finance Report - <?php echo $month_name . ' ' . $year; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #2c6e49;
            --secondary-color: #4c956c;
            --accent-color: #fefee3;
            --light-color: #e9f5db;
            --dark-color: #1b4332;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 2rem;
        }
        
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.sales {
            border-top: 4px solid #4CAF50;
        }
        
        .stat-card.expenses {
            border-top: 4px solid #F44336;
        }
        
        .stat-card.profit {
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card.profit-margin {
            border-top: 4px solid #2196F3;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .btn-export {
            background-color: var(--primary-color);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-export:hover {
            background-color: var(--dark-color);
            color: white;
        }
        
        .title-icon {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="bi bi-file-earmark-bar-graph title-icon"></i>Financial Report</h1>
                <span class="fs-4"><?php echo $month_name . ' ' . $year; ?></span>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filter Form -->
        <div class="filter-form">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Select Month:</label>
                    <select name="month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++) { ?>
                            <option value="<?php echo $m; ?>" <?php if ($m == $month) echo 'selected'; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Select Year:</label>
                    <select name="year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y'); $y++) { ?>
                            <option value="<?php echo $y; ?>" <?php if ($y == $year) echo 'selected'; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <button type="submit" class="btn w-100" style="background-color: var(--secondary-color); color: white;">
                        <i class="bi bi-filter"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-4">
                <div class="stat-card sales">
                    <h5>Total Sales</h5>
                    <div class="stat-value">GHS <?php echo number_format($total_sales, 2); ?></div>
                    <small class="text-muted">Revenue generated this month</small>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card expenses">
                    <h5>Total Expenses</h5>
                    <div class="stat-value">GHS <?php echo number_format($total_expenses, 2); ?></div>
                    <small class="text-muted">Costs incurred this month</small>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card profit">
                    <h5>Net Profit</h5>
                    <div class="stat-value" style="color: <?php echo $net_profit >= 0 ? '#4CAF50' : '#F44336'; ?>">
                        GHS <?php echo number_format($net_profit, 2); ?>
                    </div>
                    <small class="text-muted">Sales minus expenses</small>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card profit-margin">
                    <h5>Profit Margin</h5>
                    <div class="stat-value" style="color: <?php echo $profit_margin >= 0 ? '#4CAF50' : '#F44336'; ?>">
                        <?php echo number_format($profit_margin, 1); ?>%
                    </div>
                    <small class="text-muted">Percentage of revenue</small>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="chart-container">
                    <h4>Income vs. Expenses</h4>
                    <canvas id="financialSummaryChart"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="chart-container">
                    <h4>Revenue Breakdown</h4>
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Tables -->
        <div class="row">
            <div class="col-md-12">
                <div class="chart-container">
                    <h4>Financial Summary</h4>
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Amount (GHS)</th>
                                <th class="text-end">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-success">
                                <td>Total Sales</td>
                                <td class="text-end"><?php echo number_format($total_sales, 2); ?></td>
                                <td class="text-end">100%</td>
                            </tr>
                            <tr class="table-danger">
                                <td>Total Expenses</td>
                                <td class="text-end"><?php echo number_format($total_expenses, 2); ?></td>
                                <td class="text-end"><?php echo ($total_sales > 0) ? number_format(($total_expenses / $total_sales) * 100, 1) : 0; ?>%</td>
                            </tr>
                            <tr class="<?php echo $net_profit >= 0 ? 'table-primary' : 'table-warning'; ?>">
                                <td><strong>Net Profit</strong></td>
                                <td class="text-end"><strong><?php echo number_format($net_profit, 2); ?></strong></td>
                                <td class="text-end"><strong><?php echo number_format($profit_margin, 1); ?>%</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Export Button -->
                    <div class="text-end mt-4">
                        <a href="export_report.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn-export">
                            <i class="bi bi-download"></i> Download Full Report
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Financial Summary Chart
        var ctxSummary = document.getElementById('financialSummaryChart').getContext('2d');
        var financialSummaryChart = new Chart(ctxSummary, {
            type: 'bar',
            data: {
                labels: ['Sales', 'Expenses', 'Net Profit'],
                datasets: [{
                    label: 'Amount (GHS)',
                    data: [<?php echo $total_sales; ?>, <?php echo $total_expenses; ?>, <?php echo $net_profit; ?>],
                    backgroundColor: [
                        'rgba(76, 175, 80, 0.6)',
                        'rgba(244, 67, 54, 0.6)',
                        'rgba(44, 110, 73, 0.6)'
                    ],
                    borderColor: [
                        'rgba(76, 175, 80, 1)',
                        'rgba(244, 67, 54, 1)',
                        'rgba(44, 110, 73, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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

        // Get product sales data from PHP
        var productLabels = [];
        var productData = [];
        
        <?php
        if ($result_product_sales && $result_product_sales->num_rows > 0) {
            while($row = $result_product_sales->fetch_assoc()) {
                echo "productLabels.push('" . $row['product'] . "');\n";
                echo "productData.push(" . $row['total'] . ");\n";
            }
        } else {
            echo "productLabels = ['No Data'];\n";
            echo "productData = [0];\n";
        }
        ?>

        // Sales Breakdown Chart
        var ctxSales = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(ctxSales, {
            type: 'pie',
            data: {
                labels: productLabels,
                datasets: [{
                    data: productData,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(76, 149, 108, 0.6)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(76, 149, 108, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.raw;
                                var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                var percentage = Math.round((value / total) * 100);
                                return label + ': GHS ' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    
    <!-- Add Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</body>
</html>
<?php
include 'views/footer.php';?>