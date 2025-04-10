<?php
include '../config/db.php';

$sql = "SELECT * FROM expenses ORDER BY expense_date DESC";
$result = $conn->query($sql);

echo "<h2>Expense Records</h2>";
echo "<table border='1'>
<tr>
    <th>ID</th>
    <th>Reason</th>
    <th>Amount</th>
    <th>Expense Date</th>
    <th>Status</th>
    <th>Vendor</th>
    <th>Notes</th>
    <th>Actions</th>
</tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['expense_id']}</td>
            <td>{$row['expense_reason']}</td>
            <td>{$row['amount']}</td>
            <td>{$row['expense_date']}</td>
            <td>{$row['payment_status']}</td>
            <td>{$row['vendor_name']}</td>
            <td>{$row['notes']}</td>
            <td>
                <a href='edit_expense.php?expense_id={$row['expense_id']}'>Edit</a> |
                <a href='delete_expense.php?expense_id={$row['expense_id']}'>Delete</a>
            </td>
          </tr>";
}

echo "</table>";
$conn->close();
?>
