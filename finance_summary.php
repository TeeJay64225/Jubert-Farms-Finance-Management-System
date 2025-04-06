<?php
include 'config/db.php';

// Get Total Sales
$sql_sales = "SELECT SUM(amount) AS total_sales FROM sales WHERE payment_status = 'Paid'";
$result_sales = $conn->query($sql_sales);
$row_sales = $result_sales->fetch_assoc();
$total_sales = $row_sales['total_sales'] ?? 0;

// Get Total Expenses
$sql_expenses = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE payment_status = 'Paid'";
$result_expenses = $conn->query($sql_expenses);
$row_expenses = $result_expenses->fetch_assoc();
$total_expenses = $row_expenses['total_expenses'] ?? 0;

// Calculate Net Profit
$net_profit = $total_sales - $total_expenses;

// Get Total Receivables (Clients who haven't paid)
$sql_receivables = "SELECT SUM(amount) AS total_receivables FROM sales WHERE payment_status = 'Not Paid'";
$result_receivables = $conn->query($sql_receivables);
$row_receivables = $result_receivables->fetch_assoc();
$total_receivables = $row_receivables['total_receivables'] ?? 0;

// Get Total Payables (Debts you owe)
$sql_payables = "SELECT SUM(amount) AS total_payables FROM expenses WHERE payment_status = 'Not Paid'";
$result_payables = $conn->query($sql_payables);
$row_payables = $result_payables->fetch_assoc();
$total_payables = $row_payables['total_payables'] ?? 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Financial Summary</title>
</head>
<body>
    <h2>Financial Summary</h2>
    <table border="1">
        <tr>
            <th>Total Sales</th>
            <td><?php echo number_format($total_sales, 2); ?> GHS</td>
        </tr>
        <tr>
            <th>Total Expenses</th>
            <td><?php echo number_format($total_expenses, 2); ?> GHS</td>
        </tr>
        <tr>
            <th>Net Profit</th>
            <td><?php echo number_format($net_profit, 2); ?> GHS</td>
        </tr>
        <tr>
            <th>Total Receivables (Clients Owe You)</th>
            <td><?php echo number_format($total_receivables, 2); ?> GHS</td>
        </tr>
        <tr>
            <th>Total Payables (You Owe Others)</th>
            <td><?php echo number_format($total_payables, 2); ?> GHS</td>
        </tr>
    </table>
</body>
</html>
