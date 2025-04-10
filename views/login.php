<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include your database connection
require_once '../config/db.php'; // Make sure the path is correct

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Now $conn is already available from db.php
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();
        
        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            
            // Debugging: Check role output
            // echo "User Role: " . $role;
            // exit();
            
            // Redirect based on user role
            switch (strtolower($role)) { 
                case 'admin':
                    header("Location: ../admin/select_section.php"); // Redirect to the correct folder
                    exit();
                case 'manager':
                    header("Location: ../manager/manager_dashboard.php");
                    exit();
                case 'employee':
                    header("Location: ../employee/employee_dashboard.php");
                    exit();
                default:
                    header("Location: ../index.php");
                    exit();
            }            
            
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
    
    $stmt->close();
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url('../assets/green.JPG');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(22, 20, 20, 0.76);  /* Glassmorphism effect */
            backdrop-filter: blur(10px);
            z-index: -1;
        }
        
        .logo-container {
            text-align: center;
            margin: 0 auto 20px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: black;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        
        .logo {
            width: 100%;
            height: auto;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
        }
        .btn {
            background-color: #042b0c;
            border-color: #042b0c;
        }
        
        .btn:hover {
            background-color: #4CAF50;
            border-color: #4CAF50;
        }
        .link{
            color:#042b0c;
            text-decoration:none; 
        }
    </style>
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card p-4 shadow-lg" style="width: 350px;">
            <div class="logo-container">
                <img src="../assets/logo2.JPG" alt="Farm Logo" class="logo">
            </div>
            
            <h3 class="text-center mb-4">Login</h3>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class='alert alert-success'>Registration successful! You can log in now.</div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class='alert alert-danger'><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username:</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            
            <p class="mt-3 text-center">
                Don't have an account? <a class="link" href="../views/register.php">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>