<?php
session_start();
include 'db_connect.php';

// Security Check
if (!isset($_SESSION['UserID'])) {
    header("Location: auth.php");
    exit();
}

$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// Handle Profile Update
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $newPassword = $conn->real_escape_string($_POST['new_password']);
    $confirmPassword = $conn->real_escape_string($_POST['confirm_password']);

    // Check if the new email is already used by ANOTHER user
    $emailCheck = $conn->query("SELECT UserID FROM Users WHERE Email = '$email' AND UserID != $uID");
    
    if ($emailCheck->num_rows > 0) {
        $message = "<div class='alert error'>That email is already associated with another account.</div>";
    } else {
        // Did they type a new password?
        if (!empty($newPassword)) {
            if ($newPassword === $confirmPassword) {
                // SAVING AS PLAIN TEXT to match your current login system
                $sql = "UPDATE Users SET Email='$email', Phone='$phone', Password='$newPassword' WHERE UserID=$uID";
            } else {
                $message = "<div class='alert error'>New passwords do not match!</div>";
                $sql = ""; // Prevent execution
            }
        } else {
            // Update without changing the password
            $sql = "UPDATE Users SET Email='$email', Phone='$phone' WHERE UserID=$uID";
        }

        // Execute if there are no errors
        if (!empty($sql)) {
            if ($conn->query($sql) === TRUE) {
                $message = "<div class='alert success'>Account settings updated successfully!</div>";
            } else {
                $message = "<div class='alert error'>Error updating profile: " . $conn->error . "</div>";
            }
        }
    }
}

// ==========================================
// Fetch Current User Data
// ==========================================
$userQuery = $conn->query("SELECT Name, Email, Phone FROM Users WHERE UserID = $uID");
$userData = $userQuery->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Account Settings</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #0b2214; color: #ffffff; height: 100vh; overflow: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: 260px; background-color: #07160d; border-right: 1px solid rgba(197, 255, 50, 0.1); display: flex; flex-direction: column; padding: 30px 0; height: 100vh; }
        .logo { font-size: 24px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; text-align: center; margin-bottom: 40px; color: #ffffff; }
        .nav-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
        .nav-item { padding: 15px 30px; font-size: 14px; color: #9aa39e; text-decoration: none; border-left: 4px solid transparent; transition: 0.3s; display: block; }
        .nav-item:hover { background-color: rgba(197, 255, 50, 0.05); color: #ffffff; }

        .logo { 
    font-size: 24px; 
    font-weight: 700; 
    letter-spacing: 1.5px; /* Ensures consistent width */
    text-transform: uppercase; 
    text-align: center; 
    margin-bottom: 40px; 
    color: #ffffff; 
    display: block; 
    white-space: nowrap; /* Prevents the text from wrapping to two lines */
}
        
        /* NEW: Bottom Action Buttons */
        .bottom-nav { margin-top: auto; display: flex; flex-direction: column; gap: 15px; padding-bottom: 10px; }
        .action-btn { background-color: transparent; padding: 10px 20px; border-radius: 8px; cursor: pointer; width: 80%; margin-left: 10%; transition: 0.3s; font-weight: 600; text-align: center; text-decoration: none; display: block; font-size: 14px;}
        
        .settings-btn { color: #c5ff32; border: 1px solid #c5ff32; }
        .settings-btn:hover { background-color: #c5ff32; color: #0b2214; }
        
        .logout-btn { color: #ff4d4d; border: 1px solid #ff4d4d; }
        .logout-btn:hover { background-color: #ff4d4d; color: #ffffff; }

        /* Content Area */
        .main-content { flex-grow: 1; padding: 40px 60px; overflow-y: auto; }
        .card { background-color: #07160d; padding: 30px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.05); max-width: 600px; margin-top: 20px;}
        h1, h2 { font-weight: 500; }
        
        /* Form Elements */
        label { display: block; color: #9aa39e; font-size: 13px; margin-bottom: 8px; margin-top: 15px; }
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background-color: #111111; color: #ffffff; font-size: 14px; transition: 0.3s; }
        input:focus { outline: none; border-color: #c5ff32; }
        
        /* Disabled input styling for locked name */
        input:disabled { background-color: rgba(255, 255, 255, 0.02); color: #666; border-color: rgba(255,255,255,0.05); cursor: not-allowed; }
        
        .input-group { display: flex; gap: 15px; }
        .input-group > div { flex: 1; }

        .divider { height: 1px; background-color: rgba(255, 255, 255, 0.05); margin: 25px 0; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;}
        .success { border: 1px solid #c5ff32; color: #c5ff32; background: rgba(197, 255, 50, 0.1); }
        .error { border: 1px solid #ff4d4d; color: #ff4d4d; background: rgba(255, 77, 77, 0.1); }
        
        button.submit-btn { background: #c5ff32; color: #0b2214; padding: 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; font-size: 15px; margin-top: 20px;}
        button.submit-btn:hover { background: #a8e022; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">PAULVANTE</div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard Overview</a>
            <a href="manage_tasks.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_tasks.php' ? 'active' : ''; ?>">Task Scheduler</a>
            <a href="manage_land.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_land.php' ? 'active' : ''; ?>">Manage Land</a>
            <a href="manage_inventory.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_inventory.php' ? 'active' : ''; ?>">Inventory</a>
            <a href="manage_planting.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_planting.php' ? 'active' : ''; ?>">Planting</a>
            <a href="manage_expense.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_expense.php' ? 'active' : ''; ?>">Expenses</a>
            <a href="manage_harvest.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_harvest.php' ? 'active' : ''; ?>">Harvest</a>
            <a href="manage_sales.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_sales.php' ? 'active' : ''; ?>">Warehouse & Sales</a>
        </div>
        
        <div class="bottom-nav" style="margin-top: auto; display: flex; flex-direction: column; gap: 15px; padding-bottom: 10px;">
            <a href="account_settings.php" style="background-color: transparent; color: #9aa39e; border: 1px solid #9aa39e; padding: 10px 20px; border-radius: 8px; width: 80%; margin-left: 10%; text-align: center; text-decoration: none; font-size: 14px; font-weight: 600; display: block; transition: 0.3s;" onmouseover="this.style.backgroundColor='#9aa39e'; this.style.color='#0b2214';" onmouseout="this.style.backgroundColor='transparent'; this.style.color='#9aa39e';">Account Settings</a>
            <button onclick="window.location.href='logout.php'" style="background-color: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 10px 20px; border-radius: 8px; cursor: pointer; width: 80%; margin-left: 10%; transition: 0.3s; font-weight: 600; font-size: 14px;" onmouseover="this.style.backgroundColor='#ff4d4d'; this.style.color='#ffffff';" onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ff4d4d';">Log Out</button>
        </div>
    </div>

    <div class="main-content">
        <h1>Account Settings</h1>
        
        <div class="card">
            <?php echo $message; ?>
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 5px;">Personal Information</h2>
            <p style="font-size: 13px; color: #9aa39e; margin-bottom: 20px;">Update your contact details and secure your account.</p>

            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <label>Full Name</label>
                <input type="text" value="<?php echo htmlspecialchars($userData['Name']); ?>" disabled title="Your name cannot be changed here.">

                <div class="input-group">
                    <div>
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['Email']); ?>" required>
                    </div>
                    <div>
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($userData['Phone']); ?>" required>
                    </div>
                </div>

                <div class="divider"></div>

                <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 5px;">Change Password</h2>
                <p style="font-size: 13px; color: #9aa39e; margin-bottom: 10px;">Leave these fields blank if you do not want to change your password.</p>

                <div class="input-group">
                    <div>
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password">
                    </div>
                    <div>
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Repeat new password">
                    </div>
                </div>

                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>