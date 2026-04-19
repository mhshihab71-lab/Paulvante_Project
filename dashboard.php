<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];
$userName = $_SESSION['Name'];

// ==========================================
// 1. FINANCIAL METRICS
// ==========================================
$revenue = $conn->query("SELECT SUM(Price) AS Total FROM Sales WHERE UserID = $uID")->fetch_assoc()['Total'] ?? 0;
$manualExpenses = $conn->query("SELECT SUM(Amount) AS Total FROM Expense WHERE UserID = $uID")->fetch_assoc()['Total'] ?? 0;
$seedExpenses = $conn->query("SELECT SUM(p.QuantityUsed * i.UnitPrice) AS Total FROM Planting p JOIN Inventory i ON p.InventoryID = i.InventoryID WHERE p.UserID = $uID")->fetch_assoc()['Total'] ?? 0;
$expenses = $manualExpenses + $seedExpenses;
$netProfit = $revenue - $expenses;
$profitColor = ($netProfit >= 0) ? "#c5ff32" : "#ff4d4d"; 
$profitPrefix = ($netProfit >= 0) ? "+" : "";

// ==========================================
// 2. OPERATIONAL METRICS
// ==========================================
$activeInField = $conn->query("SELECT COUNT(*) AS total FROM Planting WHERE UserID = $uID AND IsHarvested = 0")->fetch_assoc()['total'] ?? 0;
$readyToHarvest = $conn->query("SELECT COUNT(*) AS total FROM Planting WHERE UserID = $uID AND IsHarvested = 0 AND ExpectedHarvestDate <= NOW()")->fetch_assoc()['total'] ?? 0;
$warehouseStock = $conn->query("SELECT COUNT(*) AS total FROM (SELECT h.YieldQuantity, (SELECT COALESCE(SUM(QuantitySold), 0) FROM Sales WHERE PlantID = h.PlantID AND UserID = $uID) as TotalSold FROM Harvest h WHERE h.UserID = $uID) as WarehouseData WHERE (YieldQuantity - TotalSold) > 0")->fetch_assoc()['total'] ?? 0;

// ==========================================
// 3. ACTIONABLE ALERTS
// ==========================================
$urgentTasks = $conn->query("
    SELECT t.TaskName, l.LandName 
    FROM Tasks t 
    LEFT JOIN Planting p ON t.PlantID = p.PlantID 
    LEFT JOIN Land l ON p.LandID = l.LandID 
    WHERE t.UserID = $uID AND t.Status = 'Pending' AND t.DueDate <= CURDATE() 
    ORDER BY t.DueDate ASC LIMIT 5
");

$upcomingHarvests = $conn->query("SELECT p.ExpectedHarvestDate, l.LandName, i.ItemName FROM Planting p JOIN Land l ON p.LandID = l.LandID JOIN Inventory i ON p.InventoryID = i.InventoryID WHERE p.UserID = $uID AND p.IsHarvested = 0 ORDER BY p.ExpectedHarvestDate ASC LIMIT 5");
$recentExpenses = $conn->query("SELECT ExpenseName, Amount, Date FROM Expense WHERE UserID = $uID ORDER BY Date DESC LIMIT 5");
$recentSales = $conn->query("SELECT s.BuyerName, s.Price, s.Date, i.ItemName FROM Sales s LEFT JOIN Planting p ON s.PlantID = p.PlantID LEFT JOIN Inventory i ON p.InventoryID = i.InventoryID WHERE s.UserID = $uID ORDER BY s.Date DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Farm Dashboard</title>
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
        .section-title { font-size: 16px; text-transform: uppercase; letter-spacing: 1px; color: #9aa39e; margin-bottom: 15px; margin-top: 10px; font-weight: 600; }
        
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .grid-2x2 { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 20px; 
    margin-bottom: 30px; 
    align-items: stretch; /* Forces boxes in the same row to match height */
}

.grid-2x2 .card {
    min-height: 140px; /* Forces all 4 boxes to have the same minimum size even if empty */
}
        
        .card { background-color: #07160d; padding: 25px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.05); position: relative; overflow: hidden; }
        .card h3 { font-size: 14px; color: #9aa39e; margin-bottom: 10px; font-weight: 500; }
        .card .val { font-size: 32px; font-weight: 700; }
        
        a.clickable-card { text-decoration: none; display: block; color: inherit; transition: all 0.3s ease; }
        a.clickable-card:hover { border-color: #c5ff32; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(197, 255, 50, 0.08); cursor: pointer; }
        div.static-card { transition: all 0.3s ease; }
        div.static-card:hover { border-color: #c5ff32; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(197, 255, 50, 0.08); }

        .list-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .list-table td { padding: 12px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.05); font-size: 13px; }
        .list-table tr:last-child td { border-bottom: none; padding-bottom: 0; }
        .empty-state { color: #9aa39e; font-size: 13px; font-style: italic; margin-top: 10px; text-align: center; padding: 20px 0;}

        /* Upgraded Weather Widget CSS */
        .weather-widget { background: linear-gradient(135deg, rgba(102, 178, 255, 0.1) 0%, rgba(7, 22, 13, 1) 100%); border-color: rgba(102, 178, 255, 0.2); margin-bottom: 20px; padding: 20px 30px;}
        .weather-current { display: flex; align-items: center; justify-content: space-between; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .weather-icon { font-size: 48px; line-height: 1; margin-right: 20px; }
        .weather-temp { font-size: 36px; font-weight: 700; color: #ffffff; }
        .weather-desc { font-size: 16px; color: #66b2ff; font-weight: 500; }
        .weather-left { display: flex; align-items: center; }
        .weather-right { text-align: right; color: #9aa39e; font-size: 13px; }
        
        /* 3-Day Forecast Grid */
        .forecast-grid { display: flex; justify-content: space-between; margin-top: 15px; }
        .forecast-day { text-align: center; flex: 1; border-right: 1px solid rgba(255,255,255,0.05); }
        .forecast-day:last-child { border-right: none; }
        .fc-date { font-size: 12px; color: #9aa39e; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
        .fc-icon { font-size: 24px; margin-bottom: 5px; }
        .fc-temps { font-size: 13px; font-weight: bold; }
        .fc-max { color: #ffffff; }
        .fc-min { color: #9aa39e; font-weight: normal; margin-left: 5px;}
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
        <h1 style="font-weight: 500; font-size: 28px;">Welcome Back!</h1>
        
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

        <h2 class="section-title">Financial Health</h2>
        <div class="grid-3">
            <a href="manage_sales.php" class="card clickable-card">
                <h3>Total Revenue</h3>
                <div class="val" style="color: #66b2ff;">৳<?php echo number_format($revenue, 2); ?></div>
            </a>
            <a href="manage_expense.php" class="card clickable-card">
                <h3>Total Crop Expenses</h3>
                <div class="val" style="color: #ff4d4d;">-৳<?php echo number_format($expenses, 2); ?></div>
            </a>
            <a href="profit_report.php" class="card clickable-card" style="background: rgba(197, 255, 50, 0.02);">
                <h3>Net Profit / Loss (View Report)</h3>
                <div class="val" style="color: <?php echo $profitColor; ?>;"><?php echo $profitPrefix; ?>৳<?php echo number_format($netProfit, 2); ?></div>
            </a>
        </div>

        <h2 class="section-title">Farm Operations</h2>
        <div class="grid-3">
            <a href="manage_planting.php" class="card clickable-card">
                <h3>Growing in Field</h3>
                <div class="val" style="color: #ffffff;"><?php echo $activeInField; ?> <span style="font-size: 14px; font-weight: normal; color:#9aa39e;">Active Plots</span></div>
            </a>
            <a href="manage_harvest.php" class="card clickable-card">
                <h3>Ready to Harvest</h3>
                <div class="val" style="color: #ffad33;"><?php echo $readyToHarvest; ?> <span style="font-size: 14px; font-weight: normal; color:#9aa39e;">Needs Action</span></div>
            </a>
            <a href="manage_sales.php" class="card clickable-card">
                <h3>Warehouse Stock</h3>
                <div class="val" style="color: #c5ff32;"><?php echo $warehouseStock; ?> <span style="font-size: 14px; font-weight: normal; color:#9aa39e;">Ready to Sell</span></div>
            </a>
        </div>

        <h2 class="section-title">Field Intelligence & Alerts</h2>
        
        <div class="card static-card weather-widget" id="weatherBox">
            <div class="weather-current">
                <div class="weather-left">
                    <div class="weather-icon" id="wIcon">⏳</div>
                    <div>
                        <div class="weather-temp" id="wTemp">--°C</div>
                        <div class="weather-desc" id="wDesc">Loading Live Weather...</div>
                    </div>
                </div>
                <div class="weather-right">
                    <div><strong>Farm Location:</strong> Dhaka, BD</div>
                    <div id="wWind" style="margin-top: 5px;">Wind: -- km/h</div>
                </div>
            </div>
            
            <div class="forecast-grid" id="forecastGrid">
                <div style="text-align:center; width:100%; color:#9aa39e; font-size:12px;">Fetching forecast data...</div>
            </div>
        </div>

        <div class="grid-2x2">
            <a href="manage_tasks.php" class="card clickable-card">
                <h3 style="color: #c5ff32; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;">Tasks Due Today/Overdue</h3>
                <?php if ($urgentTasks->num_rows > 0): ?>
                    <table class="list-table">
                        <?php while($t = $urgentTasks->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['TaskName']); ?></strong></td>
                                <td style="text-align:right; color:#9aa39e;"><?php echo htmlspecialchars($t['LandName']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No urgent tasks today!</div>
                <?php endif; ?>
            </a>

            <a href="manage_harvest.php" class="card clickable-card">
                <h3 style="color: #ffad33; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;">Upcoming Harvests</h3>
                <?php if ($upcomingHarvests->num_rows > 0): ?>
                    <table class="list-table">
                        <?php while($h = $upcomingHarvests->fetch_assoc()): 
                            $dateStr = date('d/m/Y', strtotime($h['ExpectedHarvestDate']));
                            $color = (strtotime($h['ExpectedHarvestDate']) <= time()) ? '#ff4d4d' : '#ffffff';
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($h['ItemName']); ?></strong><br><span style="color:#9aa39e; font-size:11px;"><?php echo htmlspecialchars($h['LandName']); ?></span></td>
                                <td style="text-align:right; color:<?php echo $color; ?>;"><?php echo $dateStr; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No crops currently growing.</div>
                <?php endif; ?>
            </a>

            <a href="manage_expense.php" class="card clickable-card">
                <h3 style="color: #ff4d4d; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;">Recent Expenses</h3>
                <?php if ($recentExpenses->num_rows > 0): ?>
                    <table class="list-table">
                        <?php while($e = $recentExpenses->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($e['ExpenseName']); ?></strong><br><span style="color:#9aa39e; font-size:11px;"><?php echo date('d/m/Y', strtotime($e['Date'])); ?></span></td>
                                <td style="text-align:right; font-weight: bold; color: #ff4d4d;">-৳<?php echo number_format($e['Amount'], 0); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No recent expenses recorded.</div>
                <?php endif; ?>
            </a>

            <a href="manage_sales.php" class="card clickable-card">
                <h3 style="color: #66b2ff; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;">Recent Sales</h3>
                <?php if ($recentSales->num_rows > 0): ?>
                    <table class="list-table">
                        <?php while($s = $recentSales->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['BuyerName']); ?></strong><br><span style="color:#9aa39e; font-size:11px;"><?php echo htmlspecialchars($s['ItemName'] ?? 'Unknown Crop'); ?></span></td>
                                <td style="text-align:right; color: #c5ff32;">+৳<?php echo number_format($s['Price'], 0); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No sales recorded yet.</div>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <script>
        // Helper function to map weather codes to icons/text
        function getWeatherDetails(code) {
            let text = 'Unknown';
            let icon = '🌡️';
            if (code === 0) { text = 'Clear Sky'; icon = '☀️'; }
            else if (code >= 1 && code <= 3) { text = 'Partly Cloudy'; icon = '⛅'; }
            else if (code === 45 || code === 48) { text = 'Foggy'; icon = '🌫️'; }
            else if (code >= 51 && code <= 55) { text = 'Drizzle'; icon = '🌧️'; }
            else if (code >= 61 && code <= 65) { text = 'Rain'; icon = '🌧️'; }
            else if (code >= 71 && code <= 75) { text = 'Snow'; icon = '❄️'; }
            else if (code >= 95) { text = 'Thunderstorm'; icon = '🌩️'; }
            return { text, icon };
        }

        async function fetchWeather() {
            try {
                // Fetch current weather AND daily forecast for 4 days (Today + next 3)
                const response = await fetch('https://api.open-meteo.com/v1/forecast?latitude=23.8103&longitude=90.4125&current_weather=true&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=auto');
                const data = await response.json();
                
                // --- 1. Update Current Weather ---
                const current = data.current_weather;
                const details = getWeatherDetails(current.weathercode);
                
                document.getElementById('wTemp').innerText = Math.round(current.temperature) + '°C';
                document.getElementById('wIcon').innerText = details.icon;
                document.getElementById('wDesc').innerText = details.text;
                document.getElementById('wWind').innerText = 'Wind: ' + current.windspeed + ' km/h';

                // --- 2. Build 3-Day Forecast ---
                const daily = data.daily;
                let forecastHTML = '';
                
                // Loop through index 1, 2, and 3 (Tomorrow, Day After, Next Day)
                for(let i = 1; i <= 3; i++) {
                    const dateObj = new Date(daily.time[i]);
                    // Get short day name (e.g., "Mon", "Tue")
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
                    
                    const fcDetails = getWeatherDetails(daily.weathercode[i]);
                    const maxTemp = Math.round(daily.temperature_2m_max[i]);
                    const minTemp = Math.round(daily.temperature_2m_min[i]);

                    forecastHTML += `
                        <div class="forecast-day">
                            <div class="fc-date">${dayName}</div>
                            <div class="fc-icon" title="${fcDetails.text}">${fcDetails.icon}</div>
                            <div class="fc-temps">
                                <span class="fc-max">${maxTemp}°</span><span class="fc-min">${minTemp}°</span>
                            </div>
                        </div>
                    `;
                }

                // Inject into the grid
                document.getElementById('forecastGrid').innerHTML = forecastHTML;
                
            } catch (error) {
                document.getElementById('wDesc').innerText = 'Weather data unavailable';
                document.getElementById('wIcon').innerText = '⚠️';
                document.getElementById('forecastGrid').innerHTML = '<div style="text-align:center; width:100%; color:#ff4d4d; font-size:12px;">Failed to load forecast</div>';
            }
        }
        
        // Fetch weather immediately on load
        fetchWeather();
    </script>
</body>
</html>