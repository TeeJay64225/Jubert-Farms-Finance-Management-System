<link rel="icon" type="image/svg+xml" href="assets/fab.svg">
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
    
    /* Completely updated dropdown system */
    .custom-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .dropdown-content {
        display: none;
        position: absolute;
        background-color: white;
        min-width: 200px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1000;
        border-radius: 4px;
        margin-top: 5px;
        left: 0;
        padding: 5px 0;
    }
    
    .dropdown-content a {
        color: var(--dark-color);
        padding: 10px 16px;
        text-decoration: none;
        display: block;
        text-align: left;
        transition: background-color 0.3s;
        white-space: nowrap;
        font-size: 0.9rem;
    }
    
    .dropdown-content a:hover {
        background-color: var(--light-color);
    }
    
    /* Show class for JavaScript toggle */
    .show {
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
        cursor: pointer;
    }
    
    .nav-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    /* Dropdown nav items */
    .dropdown-content a {
        color: var(--dark-color);
        border: none;
        width: 100%;
        text-align: left;
        padding: 10px 16px;
        margin: 0;
        border-radius: 0;
        display: flex;
        align-items: center;
    }
    
    .dropdown-content a:hover {
        background-color: var(--light-color);
        color: var(--dark-color);
    }
    
    .nav-btn i {
        margin-right: 5px;
    }

    /* Toggle button active style */
    .nav-btn.active {
        background-color: rgba(255, 255, 255, 0.3);
    }

    /* Make navbar responsive */
    @media (max-width: 992px) {
        .nav-container {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .menu-container {
            margin-top: 10px;
            width: 100%;
            overflow-x: auto;
            white-space: nowrap;
            padding-bottom: 5px;
        }
        
        .custom-dropdown {
            position: static;
        }
        
        .dropdown-content {
            position: absolute;
            width: 250px;
        }
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
        <div class="menu-container d-flex flex-wrap">
            <a href="admin/dashboard.php" class="nav-btn"><i class="fas fa-chart-line"></i> Dashboard</a>

            <!-- Sales Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('salesDropdown')">
                    <i class="fas fa-receipt"></i> Sales <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="salesDropdown" class="dropdown-content">
                    <a href="sales.php"><i class="fas fa-dollar-sign"></i> Sales</a>
                    <a href="slip.php"><i class="fas fa-file-invoice"></i> Slip</a>
                </div>
            </div>
            
            <!-- Expenses Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('expensesDropdown')">
                    <i class="fas fa-receipt"></i> Expenses <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="expensesDropdown" class="dropdown-content">
                    <a href="expenses.php"><i class="fas fa-list"></i> All Expenses</a>
                    <a href="expense_categories.php"><i class="fas fa-tags"></i> Categories</a>
                </div>
            </div>
            
            <!-- Assets Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('assetsDropdown')">
                    <i class="fas fa-file-alt"></i> Assets <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="assetsDropdown" class="dropdown-content">
                    <a href="assets.php"><i class="fas fa-clipboard-list"></i> All Assets</a>
                    <a href="asset_categories.php"><i class="fas fa-tags"></i> Categories</a>
                    <a href="asset_report.php"><i class="fas fa-chart-pie"></i> Reports</a>
                </div>
            </div>
            
            <!-- Harvest Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('harvestDropdown')">
                    <i class="fas fa-seedling"></i> Harvest <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="harvestDropdown" class="dropdown-content">
                    <a href="harvest_crop.php"><i class="fas fa-seedling"></i> Crop Harvest</a>
                    <a href="harvest_crop_analysis.php"><i class="fas fa-chart-line"></i> Harvest Analysis</a>
                    <a href="crop_manag.php"><i class="fas fa-leaf"></i> Crop Management</a>
                    <a href="harvest_records.php"><i class="fas fa-clipboard-check"></i> Harvest Records</a>
                    <a href="farm_calendar.php"><i class="fas fa-calendar-alt"></i> Farm Calendar</a>
                    <a href="task.php"><i class="fas fa-tasks"></i> Tasks</a>
                   <!-- <a href="crop_events.php"><i class="fas fa-tasks"></i> Events for Crop</a> -->
                </div>
            </div>

            <!-- Stakeholders Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('stakeholdersDropdown')">
                    <i class="fas fa-users"></i> Stakeholders <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="stakeholdersDropdown" class="dropdown-content">
                    <a href="clients.php"><i class="fas fa-user-tie"></i> Clients</a>
                    <a href="user.php"><i class="fas fa-user-shield"></i> Users</a>
                </div>
            </div>

            <!-- Labor Dropdown -->
            <div class="custom-dropdown d-inline-block">
                <button class="nav-btn" onclick="toggleDropdown('laborDropdown')">
                    <i class="fas fa-people-carry"></i> Labor <i class="fas fa-caret-down ms-1"></i>
                </button>
                <div id="laborDropdown" class="dropdown-content">
                    <a href="labor_management.php"><i class="fas fa-users-cog"></i> Labor Management</a>
                    <a href="labor_reports.php"><i class="fas fa-file-contract"></i> Labor Reports</a>
                    <a href="labor_tracking.php"><i class="fas fa-user-clock"></i> Labor Tracking</a>
                   
                </div>
            </div>

        <!-- supply Dropdown -->
<div class="custom-dropdown d-inline-block">
    <button class="nav-btn" onclick="toggleDropdown('supplyDropdown')">
    <i class="fas fa-user-clock"></i> Supply <i class="fas fa-caret-down ms-1"></i>
    </button>
    <div id="supplyDropdown" class="dropdown-content">
        <a href="add_chem.php"><i class="fas fa-flask"></i> Add Chemical Supply</a>
        <a href="chem_supply.php"><i class="fas fa-boxes"></i> Chemical Supply</a>  
    </div>
</div>
            
            <a href="report.php" class="nav-btn"><i class="fas fa-file-alt"></i> Reports</a>
            <a href="audit_logs.php" class="nav-btn"><i class="fas fa-shield-alt"></i> Audit</a>
        </div>
        
        <!-- User Info and Logout -->
         
        <div>
            <a href="logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>

<!-- JavaScript for dropdown functionality -->
<script>
// Store the currently open dropdown ID
let currentOpenDropdown = null;

// Function to toggle a specific dropdown
function toggleDropdown(dropdownId) {
    // Close any open dropdown first
    if (currentOpenDropdown && currentOpenDropdown !== dropdownId) {
        document.getElementById(currentOpenDropdown).classList.remove('show');
        document.querySelector(`[onclick="toggleDropdown('${currentOpenDropdown}')"]`).classList.remove('active');
    }
    
    const dropdown = document.getElementById(dropdownId);
    const button = document.querySelector(`[onclick="toggleDropdown('${dropdownId}')"]`);
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
    button.classList.toggle('active');
    
    // Update currently open dropdown reference
    if (dropdown.classList.contains('show')) {
        currentOpenDropdown = dropdownId;
    } else {
        currentOpenDropdown = null;
    }
}

// Close the dropdown when clicking anywhere else on the page
window.onclick = function(event) {
    if (!event.target.matches('.nav-btn') && !event.target.closest('.dropdown-content')) {
        const dropdowns = document.getElementsByClassName("dropdown-content");
        const buttons = document.querySelectorAll('.nav-btn');
        
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
        
        for (let i = 0; i < buttons.length; i++) {
            buttons[i].classList.remove('active');
        }
        
        currentOpenDropdown = null;
    }
}
</script>