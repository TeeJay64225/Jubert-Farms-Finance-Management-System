
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../views/login.php");
    exit();
}

include '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Finance Dashboard - Access Selection</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        
        /* Navbar styles */
        .navbar {
            background-color: #2c3e50;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
        }
        
        .user-info {
            margin-right: 20px;
            color: #ecf0f1;
        }
        
        .logout-btn {
            background-color: white;
            color: #2c3e50;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
        }
        
        .logout-btn i {
            margin-right: 5px;
        }
        
        /* Main content */
        .container {
            max-width: 1000px;
            margin: 60px auto;
            padding: 0 20px;
        }
        
        .welcome-text {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .welcome-text h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .welcome-text p {
            font-size: 1.2rem;
            color: #7f8c8d;
        }
        
        /* Card grid */
        .card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            text-align: center;
            height: 100%;
        }
        
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .card-body {
            padding: 30px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        
        .card-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .payroll-icon {
            color: #27ae60;
        }
        
        .finance-icon {
            color: #2980b9;
        }
        
        .card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .card p {
            color: #7f8c8d;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .card-btn {
            margin-top: auto;
            display: inline-block;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .card-btn i {
            margin-left: 8px;
        }
        
        .btn-payroll {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-payroll:hover {
            background-color: #219653;
        }
        
        .btn-finance {
            background-color: #2980b9;
            color: white;
        }
        
        .btn-finance:hover {
            background-color: #2471a3;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-leaf"></i> Farm Finance Dashboard
        </div>
        <div class="navbar-right">
            <span class="user-info">
                <i class="fas fa-user-circle"></i> <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?>
            </span>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-text">
            <h2>Welcome, <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?>!</h2>
            <p>Please select which system you would like to access:</p>
        </div>
        
        <div class="card-grid">
            <!-- Payroll Management Card -->
            <div class="card">
                <div class="card-body">
                    <i class="fas fa-money-bill-wave card-icon payroll-icon"></i>
                    <h3>Payroll Management</h3>
                    <p>Process employee payments, manage salaries, view payroll reports and handle tax deductions.</p>
                    <a href="../admin/payroll.php" class="card-btn btn-payroll">Access Payroll <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            
            <!-- Expenditure/Sales Management Card -->
            <div class="card">
                <div class="card-body">
                    <i class="fas fa-chart-line card-icon finance-icon"></i>
                    <h3>Expenditure & Sales</h3>
                    <p>Track farm expenses, manage sales records, generate financial reports and monitor inventory.</p>
                    <a href="../admin/dashboard.php" class="card-btn btn-finance">Access Finance <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>