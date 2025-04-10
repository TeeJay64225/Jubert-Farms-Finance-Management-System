<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include '../config/db.php'; // Adjust the path


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $role = trim($_POST["role"]);
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Username already taken!";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user into database
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);

        if ($stmt->execute()) {
            header("Location: login.php?registered=1");
            exit();
        } else {
            $error = "Error registering user.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
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
            
            <h3 class="text-center mb-4">Register</h3>
            
            <?php if (isset($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username:</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password:</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role:</label>
                    <select name="role" class="form-select" required>
                       <!-- <option value="Employee">Employee</option> for Later Update-->
                        <option value="Manager">Manager</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            
            <p class="mt-3 text-center">
                Already have an account? <a class="link" href="login.php">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>