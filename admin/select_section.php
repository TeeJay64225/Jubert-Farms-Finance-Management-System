
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
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
log_action($conn, $_SESSION['user_id'], 'Visited admin dashboard');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Finance Dashboard - Access Selection</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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
                <img src="../assets/logo2.JPG" alt="Farm Logo" class="logo-nav">
            </div>
            Jubert Farms Finance 
        </span>
        

        <!-- User Info and Logout -->
        
                <div>
            <span class="text-white me-3"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span>
            <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
</nav>
    <!-- Navbar -->
   

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
                    <a href="../admin/payroll_dashboard.php" class="card-btn btn-payroll">Access Payroll <i class="fas fa-arrow-right"></i></a>
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
    <script>
 
    </script>
</body>
</html>