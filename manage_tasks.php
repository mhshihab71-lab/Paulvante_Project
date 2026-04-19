<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) { header("Location: auth.php"); exit(); }
$uID = $_SESSION['UserID'];
$message = '';

// ==========================================
// 1. Handle Add Task
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_task') {
    $pID = $_POST['plantID'];
    $taskName = $conn->real_escape_string($_POST['taskName']);
    $desc = $conn->real_escape_string($_POST['description']);
    
    // Format date properly
    $rawDate = $_POST['dueDate'];
    $dateParts = explode('/', $rawDate);
    $dueDate = (count($dateParts) == 3) ? $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0] : $rawDate;

    // SMART LOGIC: If 'other', insert NULL into the database instead of a PlantID
    $plantID_sql = ($pID === 'other') ? "NULL" : "'$pID'";

    $sql = "INSERT INTO Tasks (UserID, PlantID, TaskName, Description, DueDate) 
            VALUES ($uID, $plantID_sql, '$taskName', '$desc', '$dueDate')";
            
    if ($conn->query($sql) === TRUE) {
        $message = "<div class='alert success'>Task scheduled successfully!</div>";
    } else {
        $message = "<div class='alert error'>Error scheduling task.</div>";
    }
}

// ==========================================
// 2. Handle Mark as Done
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'mark_done') {
    $tID = $_POST['taskID'];
    $conn->query("UPDATE Tasks SET Status = 'Completed' WHERE TaskID = '$tID' AND UserID = $uID");
    $message = "<div class='alert success'>Task marked as completed! Great job.</div>";
}

// ==========================================
// 3. Handle Delete Task
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_task') {
    $tID = $_POST['taskID'];
    $conn->query("DELETE FROM Tasks WHERE TaskID = '$tID' AND UserID = $uID");
    $message = "<div class='alert success'>Task deleted from schedule.</div>";
}

// ==========================================
// Fetch Data
// ==========================================
// Get active plantings for the dropdown
$activePlantings = $conn->query("
    SELECT p.PlantID, l.LandName, i.ItemName 
    FROM Planting p 
    JOIN Land l ON p.LandID = l.LandID 
    JOIN Inventory i ON p.InventoryID = i.InventoryID 
    WHERE p.UserID = $uID AND p.IsHarvested = 0
");

// Fetch Pending Tasks (LEFT JOIN ensures 'Other' tasks still show up!)
// Ordered by DueDate ASC (Nearest to Farthest)
$pendingTasks = $conn->query("
    SELECT t.*, l.LandName, i.ItemName 
    FROM Tasks t 
    LEFT JOIN Planting p ON t.PlantID = p.PlantID 
    LEFT JOIN Land l ON p.LandID = l.LandID 
    LEFT JOIN Inventory i ON p.InventoryID = i.InventoryID 
    WHERE t.UserID = $uID AND t.Status = 'Pending' 
    ORDER BY t.DueDate ASC
");

// Fetch Completed Tasks (Recent first)
$completedTasks = $conn->query("
    SELECT t.*, l.LandName, i.ItemName 
    FROM Tasks t 
    LEFT JOIN Planting p ON t.PlantID = p.PlantID 
    LEFT JOIN Land l ON p.LandID = l.LandID 
    LEFT JOIN Inventory i ON p.InventoryID = i.InventoryID 
    WHERE t.UserID = $uID AND t.Status = 'Completed' 
    ORDER BY t.DueDate DESC LIMIT 15
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paulvante - Task Scheduler</title>
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
        
        input, select, textarea { width: 100%; padding: 12px; margin-bottom: 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background-color: #111111; color: #ffffff; font-size: 14px; resize: none;}
        select option { background-color: #111111; color: #ffffff; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #c5ff32; }
        .input-group { display: flex; gap: 10px; margin-bottom: 12px; }
        .input-group input, .input-group select { margin-bottom: 0; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;}
        .success { border: 1px solid #c5ff32; color: #c5ff32; background: rgba(197, 255, 50, 0.1); }
        .error { border: 1px solid #ff4d4d; color: #ff4d4d; background: rgba(255, 77, 77, 0.1); }
        
        button.submit-btn { background: #c5ff32; color: #0b2214; padding: 14px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; font-size: 15px;}
        button.submit-btn:hover { background: #a8e022; }
        button.done-btn { background: rgba(197, 255, 50, 0.1); color: #c5ff32; border: 1px solid #c5ff32; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; transition: 0.3s; font-weight: bold;}
        button.done-btn:hover { background: #c5ff32; color: #0b2214; }
        button.delete-btn { background: transparent; color: #ff4d4d; border: 1px solid #ff4d4d; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; transition: 0.3s; }
        button.delete-btn:hover { background: #ff4d4d; color: #ffffff; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: left; font-size: 14px; vertical-align: top;}
        th { color: #9aa39e; font-weight: 500; }
        
        .overdue { color: #ff4d4d; font-weight: bold; }
        .today { color: #66b2ff; font-weight: bold; }
        .completed-row { opacity: 0.6; }
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
        <h1 style="font-weight: 500; font-size: 28px;">Task Scheduler</h1>
        
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
            <h2 style="font-size: 18px; color: #c5ff32; margin-bottom: 20px;">Schedule New Task</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_task">
                
                <div class="input-group">
                    <select name="plantID" required style="flex: 1;">
                        <option value="" disabled selected>Select Target...</option>
                        
                        <option value="other" style="font-weight: bold; color: #66b2ff;">Other (General Farm Task)</option>
                        
                        <?php 
                        if($activePlantings->num_rows > 0) {
                            while($p = $activePlantings->fetch_assoc()) {
                                echo "<option value='".$p['PlantID']."'>".$p['LandName']." - ".$p['ItemName']."</option>";
                            }
                        }
                        ?>
                    </select>
                    <input type="text" name="taskName" placeholder="Task Name (e.g. Apply Fertilizer, Fix Fence)" required style="flex: 1.5;">
                    <input type="text" name="dueDate" placeholder="Due Date (dd/mm/yyyy)" pattern="\d{2}/\d{2}/\d{4}" required style="flex: 1;">
                </div>
                
                <textarea name="description" placeholder="Task Description / Instructions (Optional)" rows="2"></textarea>
                
                <button type="submit" class="submit-btn">Schedule Task</button>
            </form>
        </div>

        <div class="card">
            <h2 style="font-size: 18px; color: #ffad33; margin-bottom: 20px;">Pending & Upcoming Tasks</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th> <th>Target Location / Crop</th>
                        <th>Task Details</th>
                        <th>Due Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial = 1; // Initialize counter
                    $currentDate = date('Y-m-d');
                    while($t = $pendingTasks->fetch_assoc()): 
                        $tDate = $t['DueDate'];
                        $formattedDate = date('d/m/Y', strtotime($tDate));
                        
                        // Handle formatting if the task is "Other"
                        $cropName = $t['ItemName'] ? htmlspecialchars($t['ItemName']) : 'General Task';
                        $landName = $t['LandName'] ? htmlspecialchars($t['LandName']) : 'Farm Wide';
                        
                        // Color coding based on urgency
                        $dateClass = '';
                        $dateLabel = $formattedDate;
                        
                        if ($tDate < $currentDate) {
                            $dateClass = 'overdue';
                            $dateLabel .= ' (OVERDUE)';
                        } elseif ($tDate == $currentDate) {
                            $dateClass = 'today';
                            $dateLabel = 'TODAY';
                        }
                    ?>
                        <tr>
                            <td style="color:#9aa39e; font-weight:bold;"><?php echo $serial++; ?></td>
                            <td><strong><?php echo $cropName; ?></strong><br><span style="font-size:11px; color:#9aa39e;"><?php echo $landName; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($t['TaskName']); ?></strong><br><span style="font-size:12px; color:#9aa39e;"><?php echo htmlspecialchars($t['Description']); ?></span></td>
                            <td class="<?php echo $dateClass; ?>"><?php echo $dateLabel; ?></td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="mark_done">
                                        <input type="hidden" name="taskID" value="<?php echo $t['TaskID']; ?>">
                                        <button type="submit" class="done-btn">Mark Done ✓</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this task?');">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="taskID" value="<?php echo $t['TaskID']; ?>">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if($pendingTasks->num_rows == 0): ?>
                        <tr><td colspan="5" style="text-align:center; color:#9aa39e; padding: 20px;">You're all caught up! No pending tasks.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <h2 style="font-size: 16px; color: #9aa39e; margin-left: 25px; margin-bottom: 10px;">Recently Completed</h2>
        <div class="card" style="padding: 15px 25px;">
            <table>
                <tbody>
                    <?php while($c = $completedTasks->fetch_assoc()): 
                        // Handle formatting if the task is "Other"
                        $cropName = $c['ItemName'] ? htmlspecialchars($c['ItemName']) : 'General Task';
                        $landName = $c['LandName'] ? htmlspecialchars($c['LandName']) : 'Farm Wide';
                    ?>
                        <tr class="completed-row">
                            <td width="25%"><strong><?php echo $cropName; ?></strong> <span style="font-size:11px; color:#9aa39e;">(<?php echo $landName; ?>)</span></td>
                            <td width="55%" style="text-decoration: line-through;"><?php echo htmlspecialchars($c['TaskName']); ?></td>
                            <td width="20%" style="color:#c5ff32;">✓ Done</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>