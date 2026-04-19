<?php
session_start();
include 'db_connect.php';

// Security check
if (!isset($_SESSION['UserID'])) { 
    header("Location: auth.php"); 
    exit(); 
}

$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// 1. Handle Adding New Land
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_land') {
    $landName = $_POST['landName']; 
    $sizeValue = $_POST['size']; 
    $unit = $_POST['unit']; 
    $location = $_POST['location']; 
    $soilType = $_POST['soilType'];
    
    // Combine the number and the unit (e.g., "5 Katha")
    $fullSize = $sizeValue . ' ' . $unit;

    $sql = "INSERT INTO Land (UserID, LandName, Size, Location, SoilType) 
            VALUES ($uID, '$landName', '$fullSize', '$location', '$soilType')";
            
    if ($conn->query($sql) === TRUE) { 
        $message = "<div class='alert success'>Land plot registered successfully!</div>"; 
    } else {
        $message = "<div class='alert error'>Error registering land.</div>";
    }
}

// ==========================================
// 2. Handle Deleting Land
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_land') {
    $deleteID = $_POST['deleteID'];

    // SAFETY CHECK: Ensure this land is NOT being used in Planting, Expenses, or Sales
    $checkPlanting = $conn->query("SELECT 1 FROM Planting WHERE LandID = '$deleteID' LIMIT 1");
    $checkExpense = $conn->query("SELECT 1 FROM Expense WHERE LandID = '$deleteID' LIMIT 1");
    $checkSales = $conn->query("SELECT 1 FROM Sales WHERE LandID = '$deleteID' LIMIT 1");

    if ($checkPlanting->num_rows > 0 || $checkExpense->num_rows > 0 || $checkSales->num_rows > 0) {
        $message = "<div class='alert error'>Cannot Delete: This plot is currently in use (has active plantings, expenses, or sales).</div>";
    } else {
        // Safe to delete! Ensure they only delete THEIR OWN land.
        $deleteSql = "DELETE FROM Land WHERE LandID = '$deleteID' AND UserID = '$uID'";
        if ($conn->query($deleteSql) === TRUE) {
            $message = "<div class='alert success'>Land plot deleted successfully.</div>";
        }
    }
}

// Fetch only the logged-in user's lands
$landRecords = $conn->query("SELECT * FROM Land WHERE UserID = $uID");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Manage Land</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #0b2214; color: #ffffff; height: 100vh; overflow: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: 260px; background-color: #07160d; border-right: 1px solid rgba(197, 255, 50, 0.1); display: flex; flex-direction: column; padding: 30px 0; height: 100vh; }
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
        .nav-menu { display: flex; flex-direction: column; gap: 5px; }
        .nav-item { padding: 15px 30px; font-size: 14px; color: #9aa39e; text-decoration: none; border-left: 4px solid transparent; display: block; transition: 0.3s; }
        .nav-item:hover { background-color: rgba(197, 255, 50, 0.05); color: #ffffff; }
        .nav-item.active { background-color: rgba(197, 255, 50, 0.1); color: #c5ff32; border-left: 4px solid #c5ff32; font-weight: 600; }
        .logout-btn { margin-top: auto; background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 10px; border-radius: 8px; cursor: pointer; width: 80%; margin-left: 10%; transition: 0.3s; }
        .logout-btn:hover { background-color: #ff4d4d; color: #ffffff; }

        /* Main Content Styling */
        .main-content { flex-grow: 1; padding: 40px 60px; overflow-y: auto; }
        h1 { margin-bottom: 20px; font-weight: 500; }
        .card { background-color: #07160d; padding: 25px; border-radius: 16px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* Alerts */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; text-align: center; }
        .alert.success { background: rgba(197, 255, 50, 0.1); border: 1px solid #c5ff32; color: #c5ff32; }
        .alert.error { background: rgba(255, 77, 77, 0.1); border: 1px solid #ff4d4d; color: #ff4d4d; }

        /* Form Inputs */
        input, select { width: 100%; padding: 12px; margin-bottom: 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.05); color: #fff; font-size: 14px; transition: 0.3s;}
        input:focus, select:focus { outline: none; border-color: #c5ff32; }
        select option { background-color: #0b2214; color: white; }
        
        /* Layout for side-by-side size and unit */
        .input-group { display: flex; gap: 10px; margin-bottom: 12px; }
        .input-group input, .input-group select { margin-bottom: 0; }
        
        button.submit-btn { background: #c5ff32; color: #0b2214; padding: 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; font-size: 15px; }
        button.submit-btn:hover { background: #b0e620; }
        
        button.delete-btn { background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 13px; transition: 0.3s; }
        button.delete-btn:hover { background: #ff4d4d; color: #ffffff; }

        /* Table Styling */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: left; font-size: 14px; }
        th { color: #9aa39e; font-weight: 500; }
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

        <div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px;">
    <div>
        <h1 style="font-weight: 500; font-size: 28px;">Land Management</h1>
    
    </div>
    <div style="text-align: right;">
        <p style="font-weight: 700; font-size: 16px; color: #ffffff; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
            <?php echo date('l'); ?>
        </p>
        <p style="font-size: 14px; color: #c5ff32; margin-top: 4px; margin-bottom: 0;">
            <?php echo date('d F Y'); ?>
        </p>
    </div>
</div>
        
        <?php echo $message; ?>

        <div class="card">
            <form method="POST">
                <input type="hidden" name="action" value="add_land">
                
                <input type="text" name="landName" placeholder="Plot Name (e.g. North Field)" required>
                
                <div class="input-group">
                    <input type="number" step="0.01" name="size" placeholder="Enter Size Number" required style="flex: 2;">
                    <select name="unit" required style="flex: 1;">
                        <option value="Shotok">Shotangsho / Shotok</option>
                        <option value="Katha">Katha</option>
                        <option value="Bigha">Bigha</option>
                        <option value="Acre">Acre</option>
                    </select>
                </div>

                <input type="text" name="location" placeholder="Geographic Location / Area" required>
                
                <select name="soilType" required>
                    <option value="" disabled selected>Select Soil Type</option>
                    <option value="Doash (Loamy)">Doash (Loamy)</option>
                    <option value="Bele (Sandy)">Bele (Sandy)</option>
                    <option value="Entel (Clayey)">Entel (Clayey)</option>
                    <option value="Poli (Silty)">Poli (Silty)</option>
                    <option value="Kada (Clay)">Kada (Clay)</option>
                    <option value="Peat Soil">Peat Soil</option>
                    <option value="Others">Others</option>
                </select>

                <button type="submit" class="submit-btn">Register Land Plot</button>
            </form>
        </div>

        <div class="card">
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px; font-weight: 500;">Your Registered Lands</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plot Name</th>
                        <th>Size</th>
                        <th>Location</th>
                        <th>Soil Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial = 1; // Start counting from 1
                    while($row = $landRecords->fetch_assoc()): 
                    ?>
                        <tr>
                            <td style="color: #9aa39e; font-weight: bold;"><?php echo $serial++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['LandName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['Size']); ?></td>
                            <td><?php echo htmlspecialchars($row['Location']); ?></td>
                            <td><?php echo htmlspecialchars($row['SoilType']); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this plot?');">
                                    <input type="hidden" name="action" value="delete_land">
                                    <input type="hidden" name="deleteID" value="<?php echo $row['LandID']; ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if($landRecords->num_rows == 0): ?>
                        <tr><td colspan="6" style="text-align:center; color:#9aa39e;">No land plots registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>