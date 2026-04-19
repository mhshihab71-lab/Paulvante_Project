<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// 1. Handle New Planting (Reduces Inventory)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_planting') {
    $lID = $_POST['landID'];
    $iID = $_POST['inventoryID'];
    $qtyUsed = $_POST['quantityUsed'];
    $qtyUnit = $_POST['unitType'];
    
    $pDateRaw = $_POST['plantingDate'];
    $hDateRaw = $_POST['harvestDate'];
    
    $pParts = explode('/', $pDateRaw);
    $hParts = explode('/', $hDateRaw);
    
    $pDate = $pParts[2] . '-' . $pParts[1] . '-' . $pParts[0];
    $hDate = $hParts[2] . '-' . $hParts[1] . '-' . $hParts[0] . ' 23:59:59';

    $invCheck = $conn->query("SELECT Quantity FROM Inventory WHERE InventoryID = '$iID' AND UserID = $uID")->fetch_assoc();
    
    if ($invCheck['Quantity'] >= $qtyUsed) {
        $sql = "INSERT INTO Planting (UserID, LandID, InventoryID, QuantityUsed, PlantingDate, ExpectedHarvestDate) 
                VALUES ($uID, $lID, $iID, '$qtyUsed', '$pDate', '$hDate')";
        
        $updateInv = "UPDATE Inventory SET Quantity = Quantity - $qtyUsed WHERE InventoryID = '$iID' AND UserID = $uID";
        
        if ($conn->query($sql) === TRUE && $conn->query($updateInv) === TRUE) {
            $message = "<div class='alert success'>Planting started! $qtyUsed $qtyUnit deducted from inventory.</div>";
        }
    } else {
        $message = "<div class='alert error'>Insufficient inventory! You only have " . $invCheck['Quantity'] . " left.</div>";
    }
}

// ==========================================
// 2. Handle Delete (Restocks Inventory)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_planting') {
    $pID = $_POST['plantID'];
    
    $getPlantingInfo = $conn->query("SELECT InventoryID, QuantityUsed FROM Planting WHERE PlantID = '$pID' AND UserID = $uID")->fetch_assoc();
    
    if ($getPlantingInfo) {
        $restockInvID = $getPlantingInfo['InventoryID'];
        $restockQty = $getPlantingInfo['QuantityUsed'];
        
        $conn->query("UPDATE Inventory SET Quantity = Quantity + $restockQty WHERE InventoryID = '$restockInvID' AND UserID = $uID");
        $conn->query("DELETE FROM Planting WHERE PlantID = '$pID' AND UserID = $uID");
        
        $message = "<div class='alert success'>Planting deleted. $restockQty units returned to inventory and land is free.</div>";
    }
}

// ==========================================
// Queries for Dropdowns and Tables
// ==========================================
$lands = $conn->query("
    SELECT * FROM Land 
    WHERE UserID = $uID 
    AND LandID NOT IN (
        SELECT LandID FROM Planting WHERE UserID = $uID AND IsHarvested = 0
    )
");

$seeds = $conn->query("SELECT * FROM Inventory WHERE UserID = $uID AND ItemType = 'Seed' AND Quantity > 0");

$plantings = $conn->query("
    SELECT p.*, l.LandName, i.ItemName 
    FROM Planting p 
    JOIN Land l ON p.LandID = l.LandID 
    JOIN Inventory i ON p.InventoryID = i.InventoryID 
    WHERE p.UserID = $uID 
    AND p.IsHarvested = 0 
    ORDER BY p.PlantingDate DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Planting</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #0b2214; color: #ffffff; height: 100vh; overflow: hidden; }
        
        /* Universal Sidebar CSS */
        .sidebar { width: 260px; min-width: 260px; flex-shrink: 0; background-color: #07160d; border-right: 1px solid rgba(197, 255, 50, 0.1); display: flex; flex-direction: column; padding: 30px 0; height: 100vh; }
        .logo { font-size: 24px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; text-align: center; margin-bottom: 40px; color: #ffffff; display: block; white-space: nowrap; }
        .nav-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
        .nav-item { padding: 15px 30px; font-size: 14px; color: #9aa39e; text-decoration: none; border-left: 4px solid transparent; display: block; transition: 0.3s; }
        .nav-item:hover { background-color: rgba(197, 255, 50, 0.05); color: #ffffff; }
        .nav-item.active { background-color: rgba(197, 255, 50, 0.1); color: #c5ff32; border-left: 4px solid #c5ff32; font-weight: 600; }
        .bottom-nav { margin-top: auto; display: flex; flex-direction: column; gap: 15px; padding-bottom: 10px; }
        
        .main-content { flex-grow: 1; padding: 40px 60px; overflow-y: auto; }
        .card { background-color: #07160d; padding: 25px; border-radius: 16px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.05); }
        
        input, select { width: 100%; padding: 12px; margin-bottom: 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background-color: #111111; color: #ffffff; font-size: 14px; }
        select option { background-color: #111111; color: #ffffff; padding: 10px; }
        input:focus, select:focus { outline: none; border-color: #c5ff32; }

        .input-group { display: flex; gap: 10px; margin-bottom: 12px; }
        .input-group input, .input-group select { margin-bottom: 0; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;}
        .success { border: 1px solid #c5ff32; color: #c5ff32; background: rgba(197, 255, 50, 0.1); }
        .error { border: 1px solid #ff4d4d; color: #ff4d4d; background: rgba(255, 77, 77, 0.1); }
        
        button.submit-btn { background: #c5ff32; color: #0b2214; padding: 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; font-size: 15px;}
        button.submit-btn:hover { background: #a8e022; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: left; font-size: 14px; }
        th { color: #9aa39e; font-weight: 500;}
        
        .action-btns { display: flex; gap: 10px; }
        .delete-btn { background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; transition: 0.3s; }
        .delete-btn:hover { background: #ff4d4d; color: #ffffff; }
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
                <h1 style="font-weight: 500; font-size: 28px;">Planting</h1>
            
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

        <div class="card">
            <form method="POST">
                <input type="hidden" name="action" value="add_planting">
                
                <select name="landID" required>
                    <option value="" disabled selected>Select Available Free Land</option>
                    <?php 
                    if($lands->num_rows > 0) {
                        while($l = $lands->fetch_assoc()) {
                            echo "<option value='".$l['LandID']."'>".$l['LandName']."</option>";
                        }
                    } else {
                        echo "<option value='' disabled>No free land available. Please harvest existing crops first.</option>";
                    }
                    ?>
                </select>
                
                <div class="input-group">
                    <select name="inventoryID" required style="flex: 2;">
                        <option value="" disabled selected>Select Seed from Inventory</option>
                        <?php while($s = $seeds->fetch_assoc()) echo "<option value='".$s['InventoryID']."'>".$s['ItemName']." (Available: ".$s['Quantity'].")</option>"; ?>
                    </select>
                    <input type="number" step="0.01" name="quantityUsed" placeholder="Qty Used" required style="flex: 1;">
                    <select name="unitType" required style="flex: 1;">
                        <option value="" disabled selected>Unit</option>
                        <option value="kg">kg</option>
                        <option value="Packets">Packets</option>
                        <option value="Sacks">Sacks</option>
                        <option value="Pieces">Pieces</option>
                    </select>
                </div>

                <div class="input-group">
                    <input type="text" name="plantingDate" placeholder="Planting Date (dd/mm/yyyy)" pattern="\d{2}/\d{2}/\d{4}" required>
                    <input type="text" name="harvestDate" placeholder="Expected Harvest Date (dd/mm/yyyy)" pattern="\d{2}/\d{2}/\d{4}" required>
                </div>
                
                <button type="submit" class="submit-btn">Start Planting</button>
            </form>
        </div>

        <div class="card">
            <h2 style="color:#c5ff32; margin-bottom:15px; font-size:18px; font-weight:500;">Planted Crops (Currently in Field)</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plot Name</th>
                        <th>Crop Seed</th>
                        <th>Planted On</th>
                        <th>Expected Harvest</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial = 1;
                    while($p = $plantings->fetch_assoc()): 
                        $pDate = date('d/m/Y', strtotime($p['PlantingDate']));
                        $hDate = date('d/m/Y', strtotime($p['ExpectedHarvestDate']));
                    ?>
                        <tr>
                            <td style="color:#9aa39e; font-weight:bold;"><?php echo $serial++; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['LandName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['ItemName']); ?></td>
                            <td><?php echo $pDate; ?></td>
                            <td><?php echo $hDate; ?></td>
                            <td class="action-btns">
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this planting? The seeds will be returned to inventory and the land will be freed.');">
                                    <input type="hidden" name="action" value="delete_planting">
                                    <input type="hidden" name="plantID" value="<?php echo $p['PlantID']; ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if($plantings->num_rows == 0): ?>
                        <tr><td colspan="6" style="text-align:center; color:#9aa39e;">No crops are currently planted.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>