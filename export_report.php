<?php
include 'config/db.php';

$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Fetch Sales & Expenses
$sql_sales = "SELECT SUM(amount) AS total_sales FROM sales WHERE YEAR(sale_date) = $year AND MONTH(sale_date) = $month";
$sql_expenses = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month";

$result_sales = $conn->query($sql_sales);
$result_expenses = $conn->query($sql_expenses);

$total_sales = $result_sales->fetch_assoc()['total_sales'] ?? 0;
$total_expenses = $result_expenses->fetch_assoc()['total_expenses'] ?? 0;
$net_profit = $total_sales - $total_expenses;

// Set CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="financial_report.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Total Sales', 'Total Expenses', 'Net Profit']);
fputcsv($output, [$total_sales, $total_expenses, $net_profit]);

fclose($output);
exit();
?>
