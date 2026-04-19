<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// 1. Handle Harvest
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'harvest_crop') {
    $pID = $_POST['plantID'];
    $qty = $_POST['yieldQuantity'];
    $unit = $_POST['yieldUnit'];
    
    $rawDate = $_POST['harvestDate'];
    $dateParts = explode('/', $rawDate);
    $hDate = (count($dateParts) == 3) ? $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0] : $rawDate;

    // Insert into the NEW Harvest table
    $insertHarvest = "INSERT INTO Harvest (UserID, PlantID, YieldQuantity, YieldUnit, Date) VALUES ($uID, '$pID', '$qty', '$unit', '$hDate')";
    
    // Just flag the planting as complete
    $updatePlant = "UPDATE Planting SET IsHarvested = 1 WHERE PlantID = '$pID' AND UserID = $uID";
    
    if ($conn->query($insertHarvest) === TRUE && $conn->query($updatePlant) === TRUE) {
        $message = "<div class='alert success'>Crop Harvested! $qty $unit securely logged. Go to Sales to sell it.</div>";
    } else {
        $message = "<div class='alert error'>Error recording harvest.</div>";
    }
}

// ==========================================
// 2. Handle Delete Harvest (Undo)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_harvest') {
    $pID = $_POST['plantID'];
    
    // Safety Check: Make sure they haven't sold any of this yet
    $salesCheck = $conn->query("SELECT 1 FROM Sales WHERE PlantID = '$pID' AND UserID = $uID LIMIT 1");
    
    if ($salesCheck->num_rows > 0) {
        $message = "<div class='alert error'>Cannot undo harvest: You have already recorded sales for this crop. Delete the sales first.</div>";
    } else {
        // Delete the Harvest record and un-flag the planting
        $conn->query("DELETE FROM Harvest WHERE PlantID = '$pID' AND UserID = $uID");
        $conn->query("UPDATE Planting SET IsHarvested = 0 WHERE PlantID = '$pID' AND UserID = $uID");
        $message = "<div class='alert success'>Harvest undone. The crop is back in the field.</div>";
    }
}

// ==========================================
// Fetch Data
// ==========================================
// Crops growing in the field
$readyToHarvest = $conn->query("
    SELECT p.*, l.LandName, i.ItemName 
    FROM Planting p 
    JOIN Land l ON p.LandID = l.LandID 
    JOIN Inventory i ON p.InventoryID = i.InventoryID 
    WHERE p.UserID = $uID AND p.IsHarvested = 0
");

// Past Harvests (Now joining the Harvest table with Planting)
$pastHarvests = $conn->query("
    SELECT h.*, l.LandName, i.ItemName 
    FROM Harvest h 
    JOIN Planting p ON h.PlantID = p.PlantID 
    JOIN Land l ON p.LandID = l.LandID 
    JOIN Inventory i ON p.InventoryID = i.InventoryID 
    WHERE h.UserID = $uID 
    ORDER BY h.Date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Harvest Ops</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #0b2214; color: #ffffff; height: 100vh; overflow: hidden; }
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
        .nav-item.active { background-color: rgba(197, 255, 50, 0.1); color: #c5ff32; border-left: 4px solid #c5ff32; font-weight: 600; }
        .main-content { flex-grow: 1; padding: 40px 60px; overflow-y: auto; }
        .card { background-color: #07160d; padding: 25px; border-radius: 16px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.05); }
        input, select { width: 100%; padding: 12px; margin-bottom: 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background-color: #111111; color: #ffffff; font-size: 14px; }
        select option { background-color: #111111; color: #ffffff; }
        input:focus, select:focus { outline: none; border-color: #c5ff32; }
        .input-group { display: flex; gap: 10px; margin-bottom: 12px; }
        .input-group input, .input-group select { margin-bottom: 0; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;}
        .success { border: 1px solid #c5ff32; color: #c5ff32; background: rgba(197, 255, 50, 0.1); }
        .error { border: 1px solid #ff4d4d; color: #ff4d4d; background: rgba(255, 77, 77, 0.1); }
        button.submit-btn { background: #c5ff32; color: #0b2214; padding: 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; font-size: 15px;}
        button.submit-btn:hover { background: #a8e022; }
        button.delete-btn { background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; transition: 0.3s; }
        button.delete-btn:hover { background: #ff4d4d; color: #ffffff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: left; font-size: 14px; }
        th { color: #9aa39e; font-weight: 500; }
        .bottom-nav { margin-top: auto; display: flex; flex-direction: column; gap: 15px; padding-bottom: 10px; }
        .action-btn { background-color: transparent; padding: 10px 20px; border-radius: 8px; cursor: pointer; width: 80%; margin-left: 10%; transition: 0.3s; font-weight: 600; text-align: center; text-decoration: none; display: block; font-size: 14px;}
        .settings-btn { color: #c5ff32; border: 1px solid #c5ff32; }
        .settings-btn:hover { background-color: #c5ff32; color: #0b2214; }
        .logout-btn { color: #ff4d4d; border: 1px solid #ff4d4d; }
        .logout-btn:hover { background-color: #ff4d4d; color: #ffffff; }
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
        <h1 style="font-weight: 500; font-size: 28px;">Harvest Management</h1>
    
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
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Record a Harvest</h2>
            <form method="POST">
                <input type="hidden" name="action" value="harvest_crop">
                
                <select name="plantID" required>
                    <option value="" disabled selected>Select Crop in Field...</option>
                    <?php 
                    if($readyToHarvest->num_rows > 0) {
                        while($r = $readyToHarvest->fetch_assoc()) {
                            echo "<option value='".$r['PlantID']."'>".$r['LandName']." - ".$r['ItemName']." (Planted: ".date('d/m/Y', strtotime($r['PlantingDate'])).")</option>";
                        }
                    } else {
                        echo "<option value='' disabled>No crops currently in the field.</option>";
                    }
                    ?>
                </select>

                <div class="input-group">
                    <input type="number" step="0.01" name="yieldQuantity" placeholder="Total Yield Quantity" required style="flex: 2;">
                    <select name="yieldUnit" required style="flex: 1;">
                        <option value="" disabled selected>Unit</option>
                        <option value="kg">kg</option>
                        <option value="Tons">Tons</option>
                        <option value="Sacks">Sacks</option>
                        <option value="Pieces">Pieces</option>
                    </select>
                </div>

                <input type="text" name="harvestDate" placeholder="Harvest Date (dd/mm/yyyy)" pattern="\d{2}/\d{2}/\d{4}" required>
                
                <button type="submit" class="submit-btn">Record Harvest</button>
            </form>
        </div>

        <div class="card">
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Past Harvests</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plot Source</th>
                        <th>Crop Detail</th>
                        <th>Total Yield</th>
                        <th>Harvest Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial = 1;
                    while($h = $pastHarvests->fetch_assoc()): 
                    ?>
                        <tr>
                            <td style="color:#9aa39e; font-weight:bold;"><?php echo $serial++; ?></td>
                            <td><strong><?php echo htmlspecialchars($h['LandName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['ItemName']); ?></td>
                            <td style="color:#c5ff32; font-weight:500;"><?php echo $h['YieldQuantity'] . ' ' . $h['YieldUnit']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($h['Date'])); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Undo harvest? The crop will return to the field.');">
                                    <input type="hidden" name="action" value="delete_harvest">
                                    <input type="hidden" name="plantID" value="<?php echo $h['PlantID']; ?>">
                                    <button type="submit" class="delete-btn">Undo</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if($pastHarvests->num_rows == 0): ?>
                        <tr><td colspan="6" style="text-align:center; color:#9aa39e;">No harvests recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>