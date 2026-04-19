<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// 1. Handle Add Expense
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_expense') {
    $pID = $_POST['plantID'];
    $expName = $_POST['expenseName'];
    $expType = $_POST['expenseType'];
    $desc = $_POST['description'];
    
    // Format date properly
    $rawDate = $_POST['expenseDate'];
    $dateParts = explode('/', $rawDate);
    $expDate = (count($dateParts) == 3) ? $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0] : $rawDate;

    if ($expType == 'Inventory Item') {
        $invID = $_POST['inventoryID'];
        $qty = $_POST['quantityUsed'];
        $unit = $_POST['unitType'];
        
        // Check stock AND retrieve the Unit Price
        $invCheck = $conn->query("SELECT Quantity, UnitPrice FROM Inventory WHERE InventoryID = '$invID' AND UserID = $uID")->fetch_assoc();
        
        if ($invCheck && $invCheck['Quantity'] >= $qty) {
            
            $autoCalculatedAmount = $qty * $invCheck['UnitPrice'];
            
            $sql = "INSERT INTO Expense (UserID, PlantID, ExpenseName, ExpenseType, InventoryID, QuantityUsed, UnitType, Amount, Description, Date) 
                    VALUES ($uID, $pID, '$expName', '$expType', '$invID', '$qty', '$unit', '$autoCalculatedAmount', '$desc', '$expDate')";
            
            $updateInv = "UPDATE Inventory SET Quantity = Quantity - $qty WHERE InventoryID = '$invID' AND UserID = $uID";
            
            if ($conn->query($sql) === TRUE && $conn->query($updateInv) === TRUE) {
                $message = "<div class='alert success'>Expense auto-calculated and recorded! $qty $unit deducted from inventory.</div>";
            }
        } else {
            $message = "<div class='alert error'>Insufficient inventory! You don't have enough of this item.</div>";
        }
    } else {
        // Type is 'Other' - Grab the manual amount input
        $amount = $_POST['amount'];
        $sql = "INSERT INTO Expense (UserID, PlantID, ExpenseName, ExpenseType, Amount, Description, Date) 
                VALUES ($uID, $pID, '$expName', '$expType', '$amount', '$desc', '$expDate')";
        if ($conn->query($sql) === TRUE) {
            $message = "<div class='alert success'>Manual expense recorded successfully!</div>";
        }
    }
}

// ==========================================
// 2. Handle Delete Expense
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_expense') {
    $eID = $_POST['expenseID'];
    
    $expData = $conn->query("SELECT ExpenseType, InventoryID, QuantityUsed FROM Expense WHERE ExpenseID = '$eID' AND UserID = $uID")->fetch_assoc();
    
    // Refund inventory if necessary
    if ($expData && $expData['ExpenseType'] == 'Inventory Item' && $expData['InventoryID'] != NULL) {
        $refundInvID = $expData['InventoryID'];
        $refundQty = $expData['QuantityUsed'];
        $conn->query("UPDATE Inventory SET Quantity = Quantity + $refundQty WHERE InventoryID = '$refundInvID' AND UserID = $uID");
    }
    
    $conn->query("DELETE FROM Expense WHERE ExpenseID = '$eID' AND UserID = $uID");
    $message = "<div class='alert success'>Expense deleted successfully. Inventory refunded if applicable.</div>";
}

// ==========================================
// Fetch Data for UI
// ==========================================
// NEW FEATURE: Fetch ALL plantings (Active and Harvested), ordered so active ones are at the top
$allPlantings = $conn->query("
    SELECT p.PlantID, p.IsHarvested, l.LandName, i.ItemName 
    FROM Planting p 
    JOIN Land l ON p.LandID = l.LandID 
    JOIN Inventory i ON p.InventoryID = i.InventoryID 
    WHERE p.UserID = $uID 
    ORDER BY p.IsHarvested ASC, p.PlantingDate DESC
");

$selectedPlantID = isset($_GET['plant_id']) ? $_GET['plant_id'] : null;
$seedCost = 0;
$totalExpense = 0;

if ($selectedPlantID) {
    $inventoryItems = $conn->query("SELECT * FROM Inventory WHERE UserID = $uID AND Quantity > 0");
    
    // Fetch the Initial Seed Cost automatically from Planting Ops
    $seedData = $conn->query("
        SELECT p.QuantityUsed, i.ItemName, i.UnitPrice, i.UnitType, p.PlantingDate 
        FROM Planting p 
        JOIN Inventory i ON p.InventoryID = i.InventoryID 
        WHERE p.PlantID = '$selectedPlantID' AND p.UserID = $uID
    ")->fetch_assoc();

    if ($seedData) {
        $seedCost = $seedData['QuantityUsed'] * $seedData['UnitPrice'];
    }

    // Fetch Manual Expenses
    $expenses = $conn->query("
        SELECT e.*, i.ItemName 
        FROM Expense e 
        LEFT JOIN Inventory i ON e.InventoryID = i.InventoryID 
        WHERE e.PlantID = '$selectedPlantID' AND e.UserID = $uID 
        ORDER BY e.Date DESC
    ");
    
    $manualExpensesTotal = $conn->query("SELECT SUM(Amount) as Total FROM Expense WHERE PlantID = '$selectedPlantID' AND UserID = $uID")->fetch_assoc()['Total'] ?? 0;
    $totalExpense = $manualExpensesTotal + $seedCost;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Financials</title>
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
        h1, h2 { font-weight: 500; }
        
        input, select, textarea { width: 100%; padding: 12px; margin-bottom: 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background-color: #111111; color: #ffffff; font-size: 14px; resize: none; }
        select option { background-color: #111111; color: #ffffff; padding: 10px; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #c5ff32; }

        .input-group { display: flex; gap: 10px; margin-bottom: 12px; }
        .input-group input, .input-group select { margin-bottom: 0; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;}
        .success { border: 1px solid #c5ff32; color: #c5ff32; background: rgba(197, 255, 50, 0.1); }
        .error { border: 1px solid #ff4d4d; color: #ff4d4d; background: rgba(255, 77, 77, 0.1); }
        
        button.submit-btn, button.select-btn { background: #c5ff32; color: #0b2214; padding: 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; font-size: 15px;}
        button.submit-btn:hover, button.select-btn:hover { background: #a8e022; }
        button.delete-btn { background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; transition: 0.3s; }
        button.delete-btn:hover { background: #ff4d4d; color: #ffffff; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: left; font-size: 14px; }
        th { color: #9aa39e; font-weight: 500; }
        
        .total-box { background: rgba(255, 77, 77, 0.1); border: 1px solid #ff4d4d; padding: 15px; border-radius: 8px; color: #ff4d4d; display: inline-block; font-size: 18px; font-weight: bold; margin-bottom: 20px;}
        
        .bottom-nav { margin-top: auto; display: flex; flex-direction: column; gap: 15px; padding-bottom: 10px; }
        .action-btn { background-color: transparent; padding: 10px 20px; border-radius: 8px; cursor: pointer; width: 80%; margin-left: 10%; transition: 0.3s; font-weight: 600; text-align: center; text-decoration: none; display: block; font-size: 14px;}
        .settings-btn { color: #9aa39e; border: 1px solid #9aa39e; }
        .settings-btn:hover { background-color: #9aa39e; color: #0b2214; }
        .logout-btn { color: #ff4d4d; border: 1px solid #ff4d4d; }
        .logout-btn:hover { background-color: #ff4d4d; color: #ffffff; }
        
        #inventory-fields { display: none; margin-top: -12px;}
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
        <h1 style="font-weight: 500; font-size: 28px;">Expense Management</h1>
        
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
            <h2 style="font-size: 16px; color: #9aa39e; margin-bottom: 15px;">1. Select a Crop Cycle</h2>
            <form method="GET" style="display: flex; gap: 10px;">
                <select name="plant_id" required style="margin-bottom: 0;">
                    <option value="" disabled <?php if(!$selectedPlantID) echo 'selected'; ?>>Select a Planting...</option>
                    <?php 
                    if($allPlantings->num_rows > 0) {
                        while($p = $allPlantings->fetch_assoc()) {
                            $selected = ($selectedPlantID == $p['PlantID']) ? 'selected' : '';
                            // NEW FEATURE: Add [SOLD / HARVESTED] tag
                            $statusTag = ($p['IsHarvested'] == 1) ? ' [SOLD / HARVESTED]' : '';
                            
                            echo "<option value='".$p['PlantID']."' $selected>".$p['LandName']." - ".$p['ItemName'].$statusTag."</option>";
                        }
                    } else {
                        echo "<option value='' disabled>No plantings found. Start one first!</option>";
                    }
                    ?>
                </select>
                <button type="submit" class="select-btn" style="width: auto; padding: 0 30px;">Load Expenses</button>
            </form>
        </div>

        <?php if($selectedPlantID): ?>
            
            <div class="card">
                <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Add New Expense</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_expense">
                    <input type="hidden" name="plantID" value="<?php echo $selectedPlantID; ?>">
                    
                    <div class="input-group">
                        <input type="text" name="expenseName" placeholder="Expense Name (e.g. Storage Fee, Transport)" required style="flex: 2;">
                        
                        <select id="expenseType" name="expenseType" onchange="toggleExpenseFields()" required style="flex: 1;">
                            <option value="" disabled selected>Expense Type</option>
                            <option value="Inventory Item">Inventory Item</option>
                            <option value="Other">Other (Labor, Transport, etc.)</option>
                        </select>
                    </div>

                    <div id="inventory-fields">
                        <select id="inventoryID" name="inventoryID">
                            <option value="" disabled selected>Select Item from Inventory</option>
                            <?php 
                            if ($inventoryItems && $inventoryItems->num_rows > 0) {
                                while($i = $inventoryItems->fetch_assoc()) {
                                    echo "<option value='".$i['InventoryID']."'>".$i['ItemName']." (Available Stock: ".$i['Quantity'].")</option>";
                                }
                            } else {
                                echo "<option value='' disabled>No usable items left in inventory.</option>";
                            }
                            ?>
                        </select>
                        <div class="input-group">
                            <input type="number" step="0.01" id="quantityUsed" name="quantityUsed" placeholder="Quantity Used" style="flex: 2;">
                            <select id="unitType" name="unitType" style="flex: 1;">
                                <option value="" disabled selected>Unit</option>
                                <option value="kg">kg</option>
                                <option value="Liters">Liters</option>
                                <option value="Packets">Packets</option>
                                <option value="Sacks">Sacks</option>
                                <option value="Pieces">Pieces</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <input type="number" step="0.01" id="manualAmount" name="amount" placeholder="Manual Expense Cost (৳)" required style="flex: 1;">
                        <input type="text" name="expenseDate" placeholder="Date (dd/mm/yyyy)" pattern="\d{2}/\d{2}/\d{4}" required style="flex: 1;">
                    </div>
                    
                    <textarea name="description" placeholder="Description / Notes (Optional)" rows="2"></textarea>
                    
                    <button type="submit" class="submit-btn">Record Expense</button>
                </form>
            </div>

            <div class="card">
                <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Expense History for this Crop</h2>
                
                <div class="total-box">
                    Total Expense: ৳<?php echo number_format($totalExpense, 2); ?>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Expense Name</th>
                            <th>Type / Item Used</th>
                            <th>Cost</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1; ?>
                        
                        <?php if($seedData && $seedCost > 0): ?>
                            <tr style="background: rgba(197, 255, 50, 0.05);">
                                <td style="color:#c5ff32; font-weight:bold;"><?php echo $serial++; ?></td>
                                <td><strong>Initial Seed Cost</strong><br><span style="font-size:11px; color:#c5ff32;">Auto-logged from Planting Ops</span></td>
                                <td><?php echo htmlspecialchars($seedData['ItemName']); ?> (<?php echo $seedData['QuantityUsed'] . ' ' . $seedData['UnitType']; ?>)</td>
                                <td style="color:#ff4d4d; font-weight:500;">-৳<?php echo number_format($seedCost, 2); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($seedData['PlantingDate'])); ?></td>
                                <td><span style="font-size:11px; color:#9aa39e;">Locked (Delete planting to reverse)</span></td>
                            </tr>
                        <?php endif; ?>

                        <?php 
                        while($e = $expenses->fetch_assoc()): 
                            $eDate = date('d/m/Y', strtotime($e['Date']));
                            
                            $details = $e['ExpenseType'];
                            if ($e['ExpenseType'] == 'Inventory Item' && $e['ItemName']) {
                                $details = $e['ItemName'] . " (" . $e['QuantityUsed'] . " " . $e['UnitType'] . ")";
                            }
                        ?>
                            <tr>
                                <td style="color:#9aa39e; font-weight:bold;"><?php echo $serial++; ?></td>
                                <td><strong><?php echo htmlspecialchars($e['ExpenseName']); ?></strong><br><span style="font-size:11px; color:#9aa39e;"><?php echo htmlspecialchars($e['Description']); ?></span></td>
                                <td><?php echo htmlspecialchars($details); ?></td>
                                <td style="color:#ff4d4d; font-weight:500;">-৳<?php echo number_format($e['Amount'], 2); ?></td>
                                <td><?php echo $eDate; ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this expense? Inventory items will be refunded.');">
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="expenseID" value="<?php echo $e['ExpenseID']; ?>">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

    <script>
        function toggleExpenseFields() {
            const type = document.getElementById('expenseType').value;
            const invFields = document.getElementById('inventory-fields');
            const invSelect = document.getElementById('inventoryID');
            const qtyInput = document.getElementById('quantityUsed');
            const unitSelect = document.getElementById('unitType');
            const manualAmount = document.getElementById('manualAmount');

            if (type === 'Inventory Item') {
                invFields.style.display = 'block';
                invSelect.setAttribute('required', 'true');
                qtyInput.setAttribute('required', 'true');
                unitSelect.setAttribute('required', 'true');
                
                manualAmount.style.display = 'none';
                manualAmount.removeAttribute('required');
            } else {
                invFields.style.display = 'none';
                invSelect.removeAttribute('required');
                qtyInput.removeAttribute('required');
                unitSelect.removeAttribute('required');
                invSelect.value = '';
                qtyInput.value = '';
                unitSelect.value = '';
                
                manualAmount.style.display = 'block';
                manualAmount.setAttribute('required', 'true');
            }
        }
    </script>
</body>
</html>