<?php
// view_payslip.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();


include '../config/db.php';

// Check if payroll_id is provided
if (!isset($_GET['payroll_id'])) {
    die("Error: No payroll ID provided");
}

$payroll_id = $_GET['payroll_id'];

// Simply redirect to the payslip generator with display=true
header("Location: ../payslip_generator.php?payroll_id=$payroll_id&display=true");
exit;
?>