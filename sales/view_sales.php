<?php
include '../config/db.php';

$sql = "SELECT * FROM sales ORDER BY sale_date DESC";
$result = $conn->query($sql);

echo "<h2>Sales Records</h2>";
echo "<table border='1'>
<tr>
    <th>ID</th>
    <th>Product</th>
    <th>Amount</th>
    <th>Sale Date</th>
    <th>Status</th>
    <th>Customer</th>
    <th>Notes</th>
    <th>Actions</th>
</tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['sale_id']}</td>
            <td>{$row['product_name']}</td>
            <td>{$row['amount']}</td>
            <td>{$row['sale_date']}</td>
            <td>{$row['payment_status']}</td>
            <td>{$row['customer_name']}</td>
            <td>{$row['notes']}</td>
            <td>
                <a href='edit_sales.php?sale_id={$row['sale_id']}'>Edit</a> |
                <a href='delete_sales.php?sale_id={$row['sale_id']}'>Delete</a>
            </td>
          </tr>";
}

echo "</table>";
$conn->close();
?>
