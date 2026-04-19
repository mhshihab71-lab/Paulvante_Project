<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];

// ==========================================
// Fetch Master Profit Data
// ==========================================
// This query calculates Seed Cost, Manual Expenses, and Sales for every planting cycle.
$profitData = $conn->query("
    SELECT 
        p.PlantID, 
        l.LandName, 
        i.ItemName, 
        p.PlantingDate,
        (p.QuantityUsed * i.UnitPrice) AS SeedCost,
        COALESCE((SELECT SUM(Amount) FROM Expense WHERE PlantID = p.PlantID AND UserID = $uID), 0) AS ManualExpenses,
        COALESCE((SELECT SUM(Price) FROM Sales WHERE PlantID = p.PlantID AND UserID = $uID), 0) AS TotalSales
    FROM Planting p
    JOIN Land l ON p.LandID = l.LandID
    JOIN Inventory i ON p.InventoryID = i.InventoryID
    WHERE p.UserID = $uID
    ORDER BY p.PlantingDate DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Profit Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #0b2214; color: #ffffff; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background-color: #07160d; border-right: 1px solid rgba(197, 255, 50, 0.1); display: flex; flex-direction: column; padding: 30px 0; height: 100vh; }
        .logo { font-size: 24px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; text-align: center; margin-bottom: 40px; color: #ffffff; }
        .nav-menu { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
        .nav-item { padding: 15px 30px; font-size: 14px; color: #9aa39e; text-decoration: none; border-left: 4px solid transparent; transition: 0.3s; display: block; }
        .nav-item:hover { background-color: rgba(197, 255, 50, 0.05); color: #ffffff; }
        .nav-item.active { background-color: rgba(197, 255, 50, 0.1); color: #c5ff32; border-left: 4px solid #c5ff32; font-weight: 600; }
        
        .bottom-nav { margin-top: auto; display: flex; flex-direction: column; gap: 15px; padding-bottom: 10px; }
        .action-btn { background-color: transparent; padding: 10px 20px; border-radius: 8px; cursor: pointer; width: 80%; margin-left: 10%; transition: 0.3s; font-weight: 600; text-align: center; text-decoration: none; display: block; font-size: 14px;}
        .settings-btn { color: #9aa39e; border: 1px solid #9aa39e; }
        .settings-btn:hover { background-color: #9aa39e; color: #0b2214; }
        .logout-btn { color: #ff4d4d; border: 1px solid #ff4d4d; }
        .logout-btn:hover { background-color: #ff4d4d; color: #ffffff; }

        .main-content { flex-grow: 1; padding: 40px 60px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px;}
        
        .card { background-color: #07160d; padding: 30px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.05); }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: left; font-size: 14px; }
        th { color: #9aa39e; font-weight: 500; }
        
        .back-btn { background: rgba(255,255,255,0.05); color: #ffffff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; transition: 0.3s; border: 1px solid rgba(255,255,255,0.1);}
        .back-btn:hover { background: rgba(255,255,255,0.1); border-color: #c5ff32; }
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
        <div class="header">
            <div>
                <h1 style="font-weight: 500; font-size: 28px;">Crop Profitability Report</h1>
                <p style="color: #9aa39e; margin-top: 5px;">Detailed financial breakdown per planting cycle.</p>
            </div>
            <div>
                <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            </div>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Crop Details</th>
                        <th>Planted On</th>
                        <th>Total Expenses</th>
                        <th>Total Revenue</th>
                        <th>Net Profit / Loss</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial = 1;
                    while($row = $profitData->fetch_assoc()): 
                        // Math
                        $totalExpense = $row['SeedCost'] + $row['ManualExpenses'];
                        $totalSales = $row['TotalSales'];
                        $netProfit = $totalSales - $totalExpense;
                        
                        // Styling
                        $profitColor = ($netProfit >= 0) ? "#c5ff32" : "#ff4d4d";
                        $profitPrefix = ($netProfit >= 0) ? "+" : "";
                    ?>
                        <tr>
                            <td style="color:#9aa39e; font-weight:bold;"><?php echo $serial++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['ItemName']); ?></strong><br><span style="font-size:11px; color:#9aa39e;">Plot: <?php echo htmlspecialchars($row['LandName']); ?></span></td>
                            <td><?php echo date('d/m/Y', strtotime($row['PlantingDate'])); ?></td>
                            <td style="color:#ff4d4d;">-৳<?php echo number_format($totalExpense, 2); ?></td>
                            <td style="color:#66b2ff;">+৳<?php echo number_format($totalSales, 2); ?></td>
                            <td style="color:<?php echo $profitColor; ?>; font-weight:bold; font-size:16px;">
                                <?php echo $profitPrefix; ?>৳<?php echo number_format($netProfit, 2); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if($profitData->num_rows == 0): ?>
                        <tr><td colspan="6" style="text-align:center; color:#9aa39e; padding:30px;">No crops have been planted yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>