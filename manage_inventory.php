<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// 1. Handle Add New Inventory Item
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_item') {
    $itemName = $conn->real_escape_string($_POST['itemName']);
    $itemType = $conn->real_escape_string($_POST['itemType']);
    $qty = $_POST['quantity'];
    $unit = $_POST['unitType'];
    $price = $_POST['unitPrice'];

    $sql = "INSERT INTO Inventory (UserID, ItemName, ItemType, Quantity, UnitType, UnitPrice) 
            VALUES ($uID, '$itemName', '$itemType', '$qty', '$unit', '$price')";
            
    if ($conn->query($sql) === TRUE) {
        $message = "<div class='alert success'>$itemName added to supplies successfully!</div>";
    } else {
        $message = "<div class='alert error'>Error adding item.</div>";
    }
}

// ==========================================
// 2. Handle Quick Restock
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'add_stock') {
    $iID = $_POST['inventoryID'];
    $addQty = $_POST['addQuantity'];
    
    $conn->query("UPDATE Inventory SET Quantity = Quantity + $addQty WHERE InventoryID = '$iID' AND UserID = $uID");
    $message = "<div class='alert success'>Stock replenished successfully!</div>";
}

// ==========================================
// 3. Handle Delete Item
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_item') {
    $iID = $_POST['inventoryID'];
    
    // Safety check: Don't delete if it's currently planted in the field
    $checkPlanting = $conn->query("SELECT 1 FROM Planting WHERE InventoryID = '$iID' AND UserID = $uID LIMIT 1");
    if($checkPlanting->num_rows > 0) {
         $message = "<div class='alert error'>Cannot delete: This seed is currently actively planted in a field.</div>";
    } else {
        $conn->query("DELETE FROM Inventory WHERE InventoryID = '$iID' AND UserID = $uID");
        $message = "<div class='alert success'>Item permanently removed from inventory.</div>";
    }
}

// Fetch Inventory Data
$inventory = $conn->query("SELECT * FROM Inventory WHERE UserID = $uID ORDER BY ItemType ASC, ItemName ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Inventory</title>
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
        
        .main-content { flex-grow: 1; padding: 40px 60px; overflow-y: auto; }
        .card { background-color: #07160d; padding: 25px; border-radius: 16px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.05); }
        h1, h2 { font-weight: 500; }
        
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
        
        button.restock-btn { background: rgba(102, 178, 255, 0.1); color: #66b2ff; border: 1px solid #66b2ff; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; transition: 0.3s; font-weight: bold;}
        button.restock-btn:hover { background: #66b2ff; color: #0b2214; }
        
        button.delete-btn { background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; transition: 0.3s; }
        button.delete-btn:hover { background: #ff4d4d; color: #ffffff; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: left; font-size: 14px; vertical-align: middle;}
        th { color: #9aa39e; font-weight: 500; }
        
        .type-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .type-seed { background: rgba(197, 255, 50, 0.1); color: #c5ff32; }
        .type-fertilizer { background: rgba(102, 178, 255, 0.1); color: #66b2ff; }
        .type-other { background: rgba(255, 173, 51, 0.1); color: #ffad33; }
        
        .low-stock { color: #ff4d4d; font-weight: bold; }
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
                <h1 style="font-weight: 500; font-size: 28px;">Farm Supplies Inventory</h1>
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
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Register New Supply</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_item">
                
                <div class="input-group">
                    <input type="text" name="itemName" placeholder="Item Name (e.g., Tomato Seeds, Urea Fertilizer)" required style="flex: 2;">
                    <select name="itemType" required style="flex: 1;">
                        <option value="" disabled selected>Select Category...</option>
                        <option value="Seed">Seed / Plant</option>
                        <option value="Fertilizer">Fertilizer / Chemical</option>
                        <option value="Tool">Tool / Equipment</option>
                        <option value="Other">Other Supply</option>
                    </select>
                </div>

                <div class="input-group">
                    <input type="number" step="0.01" name="quantity" placeholder="Initial Quantity" required style="flex: 1;">
                    <select name="unitType" required style="flex: 1;">
                        <option value="" disabled selected>Unit of Measurement...</option>
                        <option value="kg">kg</option>
                        <option value="Liters">Liters</option>
                        <option value="Packets">Packets</option>
                        <option value="Sacks">Sacks</option>
                        <option value="Pieces">Pieces</option>
                    </select>
                    <input type="number" step="0.01" name="unitPrice" placeholder="Price Per Unit (৳)" required style="flex: 1;">
                </div>
                
                <button type="submit" class="submit-btn">Add to Inventory</button>
            </form>
        </div>

        <div class="card">
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Current Stock Levels</h2>
            <table>
                <thead>
                    <tr>
                        <th>Item Details</th>
                        <th>Category</th>
                        <th>Stock Available</th>
                        <th>Unit Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($inventory->num_rows > 0):
                        while($i = $inventory->fetch_assoc()): 
                            
                            // Styling Badges
                            $badgeClass = 'type-other';
                            if($i['ItemType'] == 'Seed') $badgeClass = 'type-seed';
                            if($i['ItemType'] == 'Fertilizer') $badgeClass = 'type-fertilizer';
                            
                            // Low Stock Warning
                            $stockClass = ($i['Quantity'] <= 5) ? 'low-stock' : '';
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($i['ItemName']); ?></strong></td>
                            <td><span class="type-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($i['ItemType']); ?></span></td>
                            <td class="<?php echo $stockClass; ?>"><?php echo $i['Quantity'] . ' ' . $i['UnitType']; ?></td>
                            <td style="color:#9aa39e;">৳<?php echo number_format($i['UnitPrice'], 2); ?></td>
                            <td>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <form method="POST" style="display:flex; gap:5px;">
                                        <input type="hidden" name="action" value="add_stock">
                                        <input type="hidden" name="inventoryID" value="<?php echo $i['InventoryID']; ?>">
                                        <input type="number" step="0.01" name="addQuantity" placeholder="+Qty" required style="width: 70px; padding: 6px; margin: 0;">
                                        <button type="submit" class="restock-btn">Restock</button>
                                    </form>
                                    
                                    <form method="POST" onsubmit="return confirm('Permanently delete this item from your supplies?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="inventoryID" value="<?php echo $i['InventoryID']; ?>">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <tr><td colspan="5" style="text-align:center; color:#9aa39e; padding: 20px;">Your supply inventory is empty.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>