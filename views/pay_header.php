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
            
            <!-- Harvest Dropdown -->
            <div class="nav-item d-inline-block">
                <a href="#" class="nav-btn"><i class="fas fa-file-alt"></i> Harvest <i class="fas fa-caret-down"></i></a>
                <div class="dropdown-content">
                    <a href="#"><i class="fas fa-seedling"></i> Crop Harvest</a>
                    <a href="#"><i class="fas fa-chart-line"></i> Harvest Analysis</a>
                </div>
            </div>
            
            <a href="#" class="nav-btn"><i class="fas fa-users"></i> Clients</a>
            <a href="#" class="nav-btn"><i class="fas fa-user"></i> User</a>
            <a href="#" class="nav-btn"><i class="fas fa-file-alt"></i> Reports</a>
        </div>
        
        <!-- User Info and Logout -->
        <div>
            <span class="text-white me-3"><i class="fas fa-user-circle"></i> <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span>
            <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>