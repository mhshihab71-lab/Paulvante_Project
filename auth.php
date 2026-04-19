<?php
// Start a session to remember logged-in users
session_start();

// Include your database connection
include 'db_connect.php';

$message = '';
$messageType = '';

// Check if a form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ==========================================
    // 1. REGISTRATION LOGIC
    // ==========================================
    if (isset($_POST['action']) && $_POST['action'] == 'register') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password']; 
        $phone = $_POST['phone'];
        $type = $_POST['type']; // Must be 'Admin' or 'Manager'

        // Prepare the SQL to match the new Users table structure
        $sql = "INSERT INTO Users (Name, Email, Password, Phone, Type) 
                VALUES ('$name', '$email', '$password', '$phone', '$type')";
        
        if ($conn->query($sql) === TRUE) {
            $message = "Account created successfully! You can now log in.";
            $messageType = "success";
        } else {
            $message = "Error: " . $conn->error;
            $messageType = "error";
        }
    } 
    
    // ==========================================
    // 2. LOGIN LOGIC
    // ==========================================
    elseif (isset($_POST['action']) && $_POST['action'] == 'login') {
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Search the database for this email
        $sql = "SELECT * FROM Users WHERE Email='$email'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Simple password check (Note: use password_verify in real apps)
            if ($password == $row['Password']) {
                // Success! Save user data to the session
                $_SESSION['UserID'] = $row['UserID'];
                $_SESSION['Name'] = $row['Name'];
                $_SESSION['Type'] = $row['Type'];
                
                // Teleport to dashboard
                header("Location: dashboard.php");
                exit();
                
            } else {
                $message = "Incorrect password. Please try again.";
                $messageType = "error";
            }
        } else {
            $message = "No account found with that email address.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paulvante - Login & Register</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            background: linear-gradient(rgba(11, 34, 20, 0.8), rgba(11, 34, 20, 0.9)), 
                        url('https://images.unsplash.com/photo-1625246333195-78d9c38ad449?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover no-repeat;
            color: #ffffff; display: flex; justify-content: center; align-items: center; height: 100vh;
        }
        .auth-container {
            background: rgba(7, 22, 13, 0.6); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            padding: 50px 40px; border-radius: 24px; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            width: 100%; max-width: 420px; border: 1px solid rgba(197, 255, 50, 0.15);
        }
        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h2 { font-size: 28px; font-weight: 600; margin-bottom: 8px; }
        input, select {
            width: 100%; padding: 15px 20px; margin-bottom: 16px; border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.05);
            color: #ffffff; font-size: 15px;
        }
        input:focus, select:focus { outline: none; border-color: #c5ff32; background: rgba(255, 255, 255, 0.1); }
        select option { background-color: #0b2214; color: white; }
        button {
            width: 100%; padding: 15px; background-color: #c5ff32; color: #0b2214; border: none;
            border-radius: 12px; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.3s;
        }
        button:hover { background-color: #b0e620; }
        .toggle-text { text-align: center; margin-top: 25px; font-size: 14px; color: #9aa39e; }
        .toggle-text span { color: #c5ff32; font-weight: 600; cursor: pointer; }
        .alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .alert.success { background: rgba(197, 255, 50, 0.2); color: #c5ff32; border: 1px solid #c5ff32; }
        .alert.error { background: rgba(255, 77, 77, 0.2); color: #ff4d4d; border: 1px solid #ff4d4d; }
        .hidden { display: none; }
    </style>
</head>
<body>

    <div class="auth-container">
        <?php if($message != ''): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div id="loginSection">
            <div class="auth-header"><h2>Welcome Back</h2><p>Sign in to Paulvante</p></div>
            <form method="POST" action="auth.php">
                <input type="hidden" name="action" value="login">
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Log In</button>
            </form>
            <div class="toggle-text">Don't have an account? <span onclick="toggleForms()">Register here</span></div>
        </div>

        <div id="registerSection" class="hidden">
            <div class="auth-header"><h2>Create Account</h2><p>Join the farm management system</p></div>
            <form method="POST" action="auth.php">
                <input type="hidden" name="action" value="register">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="text" name="phone" placeholder="Phone Number" required>
                <select name="type" required>
                    <option value="" disabled selected>Select Your Role</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
                <button type="submit">Create Account</button>
            </form>
            <div class="toggle-text">Already have an account? <span onclick="toggleForms()">Log in here</span></div>
        </div>
    </div>

    <script>
        function toggleForms() {
            const login = document.getElementById('loginSection');
            const register = document.getElementById('registerSection');
            login.classList.toggle('hidden');
            register.classList.toggle('hidden');
        }
    </script>
</body>
</html>