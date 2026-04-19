<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// 1. Handle Recording a Sale
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'record_sale') {
    $pID = $_POST['plantID'];
    $qtySold = $_POST['quantitySold'];
    $unit = $_POST['unitType'];
    $buyer = $_POST['buyer'];
    $unitPrice = $_POST['unitPrice'];
    $totalPrice = $qtySold * $unitPrice;
    
    $rawDate = $_POST['saleDate'];
    $dateParts = explode('/', $rawDate);
    $sDate = (count($dateParts) == 3) ? $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0] : $rawDate;

    // Smart Stock Check: Calculate Available Stock from the Harvest Ledger dynamically
    $stockCheck = $conn->query("
        SELECT h.YieldQuantity, p.LandID,
               (SELECT COALESCE(SUM(QuantitySold), 0) FROM Sales WHERE PlantID = '$pID' AND UserID = $uID) as TotalSold
        FROM Harvest h
        JOIN Planting p ON h.PlantID = p.PlantID
        WHERE h.PlantID = '$pID' AND h.UserID = $uID
    ")->fetch_assoc();
    
    $availableStock = $stockCheck['YieldQuantity'] - $stockCheck['TotalSold'];

    if ($stockCheck && $availableStock >= $qtySold) {
        $landID = $stockCheck['LandID'];
        
        $insertSale = "INSERT INTO Sales (UserID, LandID, PlantID, BuyerName, QuantitySold, UnitType, UnitPrice, Price, Date) 
                       VALUES ($uID, $landID, '$pID', '$buyer', '$qtySold', '$unit', '$unitPrice', '$totalPrice', '$sDate')";
                       
        // NOTE: We no longer need to update the Harvest table! The warehouse math handles it.
        if ($conn->query($insertSale) === TRUE) {
            $message = "<div class='alert success'>Sale auto-calculated and recorded!</div>";
        }
    } else {
        $message = "<div class='alert error'>Insufficient warehouse stock! You only have " . $availableStock . " left.</div>";
    }
}

// ==========================================
// 2. Handle Deleting a Sale
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_sale') {
    $sID = $_POST['saleID'];
    // Because available stock is calculated dynamically, we just delete the sale! No refund logic needed.
    $conn->query("DELETE FROM Sales WHERE SaleID = '$sID' AND UserID = $uID");
    $message = "<div class='alert success'>Sale deleted. Stock automatically recalculated.</div>";
}

// ==========================================
// Fetch Data
// ==========================================

// Warehouse Items: Smart query calculating items with stock remaining
$warehouseItems = $conn->query("
    SELECT * FROM (
        SELECT h.PlantID, h.YieldQuantity, h.YieldUnit, l.LandName, i.ItemName,
               (SELECT COALESCE(SUM(QuantitySold), 0) FROM Sales WHERE PlantID = h.PlantID AND UserID = $uID) as TotalSold
        FROM Harvest h 
        JOIN Planting p ON h.PlantID = p.PlantID 
        JOIN Land l ON p.LandID = l.LandID 
        JOIN Inventory i ON p.InventoryID = i.InventoryID 
        WHERE h.UserID = $uID
    ) as WarehouseData
    WHERE (YieldQuantity - TotalSold) > 0
");

// Sales History
$salesHistory = $conn->query("
    SELECT s.*, l.LandName, i.ItemName 
    FROM Sales s 
    LEFT JOIN Land l ON s.LandID = l.LandID 
    LEFT JOIN Planting p ON s.PlantID = p.PlantID
    LEFT JOIN Inventory i ON p.InventoryID = i.InventoryID
    WHERE s.UserID = $uID 
    ORDER BY s.Date DESC
");

$totalRevenue = $conn->query("SELECT SUM(Price) as Total FROM Sales WHERE UserID = $uID")->fetch_assoc()['Total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Sales & Revenue</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #0b2214; color: #ffffff; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background-color: #07160d; border-right: 1px solid rgba(197, 255, 50, 0.1); display: flex; flex-direction: column; padding: 30px 0; height: 100vh; }
        .logo { font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 40px; }
        .nav-menu { display: flex; flex-direction: column; gap: 5px; }
        .nav-item { padding: 15px 30px; font-size: 14px; color: #9aa39e; text-decoration: none; border-left: 4px solid transparent; display: block; transition: 0.3s; }
        .nav-item.active { background-color: rgba(197, 255, 50, 0.1); color: #c5ff32; border-left: 4px solid #c5ff32; font-weight: 600; }
        .main-content { flex-grow: 1; padding: 40px 60px; overflow-y: auto; }

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
        
        .card { background-color: #07160d; padding: 25px; border-radius: 16px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.05); }
        h1, h2 { font-weight: 500; }
        
        .top-dashboard { display: flex; gap: 20px; align-items: flex-start; margin-bottom: 30px; }
        .warehouse-section { flex: 1.6; margin-bottom: 0; }
        .form-section { flex: 1; margin-bottom: 0; }

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
        
        .total-box { background: rgba(197, 255, 50, 0.1); border: 1px solid #c5ff32; padding: 15px; border-radius: 8px; color: #c5ff32; display: inline-block; font-size: 18px; font-weight: bold; margin-bottom: 20px;}
        
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
        <h1 style="font-weight: 500; font-size: 28px;">Sales & Warehouse Management</h1>
        
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

        <div class="top-dashboard">
            <div class="card warehouse-section">
                <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Warehouse (Ready to Sell)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Crop / Source</th>
                            <th>Total Harvested</th>
                            <th>Left to Sell</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($warehouseItems->num_rows > 0) {
                            $warehouseItems->data_seek(0); 
                            while($w = $warehouseItems->fetch_assoc()): 
                                $leftToSell = $w['YieldQuantity'] - $w['TotalSold'];
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($w['ItemName']); ?></strong><br><span style="font-size:11px; color:#9aa39e;">Plot: <?php echo htmlspecialchars($w['LandName']); ?></span></td>
                                <td><?php echo $w['YieldQuantity'] . ' ' . $w['YieldUnit']; ?></td>
                                <td style="color:#c5ff32; font-weight:bold;"><?php echo $leftToSell . ' ' . $w['YieldUnit']; ?></td>
                            </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='3' style='text-align:center; color:#9aa39e; padding:30px;'>Warehouse is empty.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="card form-section">
                <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Record a Sale</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="record_sale">
                    
                    <select name="plantID" required>
                        <option value="" disabled selected>Select from Warehouse...</option>
                        <?php 
                        if($warehouseItems->num_rows > 0) {
                            $warehouseItems->data_seek(0);
                            while($s = $warehouseItems->fetch_assoc()) {
                                $left = $s['YieldQuantity'] - $s['TotalSold'];
                                echo "<option value='".$s['PlantID']."'>".$s['ItemName']." (".$left." ".$s['YieldUnit']." left)</option>";
                            }
                        } else {
                            echo "<option value='' disabled>No stock available.</option>";
                        }
                        ?>
                    </select>

                    <input type="text" name="buyer" placeholder="Buyer / Market Name" required>
                    
                    <div class="input-group">
                        <input type="number" step="0.01" name="quantitySold" placeholder="Qty Sold" required style="flex: 2;">
                        <select name="unitType" required style="flex: 1;">
                            <option value="kg">kg</option>
                            <option value="Tons">Tons</option>
                            <option value="Sacks">Sacks</option>
                            <option value="Pieces">Pieces</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <input type="number" step="0.01" name="unitPrice" placeholder="Unit Price (৳)" required style="flex: 1;">
                        <input type="text" name="saleDate" placeholder="Date (dd/mm/yyyy)" pattern="\d{2}/\d{2}/\d{4}" required style="flex: 1;">
                    </div>
                    
                    <button type="submit" class="submit-btn">Complete Sale</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Sales History</h2>
            
            <div class="total-box">
                Total Revenue: ৳<?php echo number_format($totalRevenue, 2); ?>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Buyer / Market</th>
                        <th>Crop Source</th>
                        <th>Qty Sold</th>
                        <th>Unit Price</th>
                        <th>Total Revenue</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial = 1;
                    while($s = $salesHistory->fetch_assoc()): 
                        $cropSource = $s['ItemName'] ? $s['ItemName'] . " from " . $s['LandName'] : $s['LandName'];
                        $displayUnitPrice = $s['UnitPrice'] ? number_format($s['UnitPrice'], 2) : "N/A";
                    ?>
                        <tr>
                            <td style="color:#9aa39e; font-weight:bold;"><?php echo $serial++; ?></td>
                            <td><strong><?php echo htmlspecialchars($s['BuyerName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cropSource); ?></td>
                            <td><?php echo $s['QuantitySold'] . ' ' . $s['UnitType']; ?></td>
                            <td style="color:#9aa39e;">৳<?php echo $displayUnitPrice; ?></td>
                            <td style="color:#c5ff32; font-weight:500;">+৳<?php echo number_format($s['Price'], 2); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($s['Date'])); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Undo sale? The quantity will be returned to your warehouse stock.');">
                                    <input type="hidden" name="action" value="delete_sale">
                                    <input type="hidden" name="saleID" value="<?php echo $s['SaleID']; ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if($salesHistory->num_rows == 0): ?>
                        <tr><td colspan="8" style="text-align:center; color:#9aa39e;">No sales recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>