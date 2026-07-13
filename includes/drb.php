<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "merchant_db";

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("<div style='color:red; font-weight:bold; padding:20px;'>MySQL Server Connection Failed: " . $conn->connect_error . "<br>Please ensure MySQL is started in XAMPP.</div>");
}

$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
$conn->select_db($dbname);

$tableQuery = "CREATE TABLE IF NOT EXISTS merchants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspector_name VARCHAR(100) NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    quantity_collected INT NOT NULL,
    road_name VARCHAR(100) NOT NULL,
    area VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableQuery);



$current_today = date('Y-m-d');
$conn->query("DELETE FROM merchants WHERE DATE(created_at) < '$current_today'");


session_start();
$message = "";

if (isset($_SESSION['msg'])) {
    $message = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

$page = isset($_GET['page']) ? $_GET['page'] : 'new_data';

if (isset($_POST['add_merchant'])) {
    $ins_name = $_POST['inspector_name'];
    $item = $_POST['item_name'];
    $qty = intval($_POST['quantity_collected']);
    $road = $_POST['road_name'];
    $area = $_POST['area'];

    $stmt = $conn->prepare("INSERT INTO merchants (inspector_name, item_name, quantity_collected, road_name, area) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $ins_name, $item, $qty, $road, $area);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "<div class='alert success'>Data recorded successfully! Form reset for next entry.</div>";
    } else {
        $_SESSION['msg'] = "<div class='alert error'>Error saving record: " . $conn->error . "</div>";
    }
    $stmt->close();
    
    header("Location: ?page=new_data");
    exit();
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $stmt = $conn->prepare("DELETE FROM merchants WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "<div class='alert success'>Record deleted successfully!</div>";
    } else {
        $_SESSION['msg'] = "<div class='alert error'>Error deleting record: " . $conn->error . "</div>";
    }
    $stmt->close();
    
    header("Location: ?page=" . $page);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspector Administration Panel</title>
    <style>
        * { 
        box-sizing: border-box; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        margin: 0; padding: 0;
     }
        body { 
        background-color: #f4f7f6; 
        color: #333; 
        padding: 20px; 
    }
        .container { 
        max-width: 1100px; 
        margin: 0 auto; 
        background: white; 
        padding: 30px; 
        border-radius: 8px; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
    }
        
        header { 
        text-align: center; 
        margin-bottom: 30px;
        border-bottom: 2px solid #eee; 
        padding-bottom: 20px; 
    }
        h1 { 
        color: #2c3e50; 
        font-size: 28px; 
        margin-bottom: 15px; 
    }
        
        nav { 
        display: flex; 
        justify-content: center; 
        gap: 15px; margin-top: 10px; 
    }
        nav a { 
        text-decoration: none; 
        padding: 12px 20px; 
        background: #e0e0e0; 
        color: #333; 
        border-radius: 5px; 
        font-weight: bold; 
        transition: all 0.3s ease; 
        font-size: 14px; 
    }
        nav a.active { 
        background: #3498db; 
        color: white; 
    }
        nav a:hover:not(.active) { 
        background: #d5d5d5; 
    }

        form { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 20px; 
        margin-top: 15px; 
    }
        .form-group { 
        display: flex; 
        flex-direction: column; 
    }
        .form-group.full-width { 
        grid-column: span 2;
    }
        label { 
        margin-bottom: 8px; 
        font-weight: 600; 
        color: #444; 
        font-size: 14px; 
    }
        input[type="text"], input[type="number"] { 
        padding: 12px; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        font-size: 16px; 
    }
        input:focus { 
        border-color: #3498db; 
        outline: none; 
    }
        
        button.btn { 
        grid-column: span 2; 
        padding: 14px; 
        background: #2ecc71; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        font-size: 16px; 
        font-weight: bold; 
        cursor: pointer; 
        transition: background 0.2s; 
        margin-top: 10px; 
    }
        button.btn:hover { 
        background: #27ae60; 
    }

        table { 
        width: 100%; 
        border-collapse: collapse;
        margin-top: 20px; 
        font-size: 15px; 
    }
        th, td { 
        padding: 12px 15px; 
        text-align: left; 
        border-bottom: 1px solid #ddd; 
    }
        th { 
        background-color: #34495e; 
        color: white; 
        font-weight: 600; 
    }
        tr:hover { 
        background-color: #f9f9f9; 
    }
        
        .btn-delete { 
        background: #e74c3c; 
        color: white; 
        padding: 6px 12px; 
        text-decoration: none; 
        border-radius: 3px; 
        font-size: 13px; 
        font-weight: bold; 
        transition: background 0.2s; 
    }
        .btn-delete:hover { 
        background: #c0392b; 
    }
        .btn-print { 
        background: #f39c12; 
        color: white; 
        padding: 10px 20px; 
        border: none; 
        border-radius: 4px; 
        font-weight: bold; 
        cursor: pointer; 
        display: inline-block; 
        text-decoration: none; 
        font-size: 14px; }
        .btn-print:hover { 
        background: #d35400; 
    }
        .table-header-container { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 10px; 
    }

        .alert { 
        padding: 15px; 
        margin-bottom: 20px; 
        border-radius: 4px; 
        font-weight: bold; 
        text-align: center; 
        font-size: 15px; 
    }
        .success { 
        background-color: #d4edda; 
        color: #155724; 
        border: 1px solid #c3e6cb; 
    }
        .error { 
        background-color: #f8d7da; 
        color: #721c24; 
        border: 1px solid #f5c6cb; 
    }

        @media print {
            body { 
            background: white; 
            padding: 0; margin: 0; 
            color: #000; 
        }
            nav, .btn-print, .btn-delete, form, .alert, header h1 { 
            display: none !important; 
        }
            .container { 
            box-shadow: none; 
            padding: 0; 
            max-width: 100%; 
        }
            table { 
            width: 100%; 
            border: 1px solid #000; 
            margin-top: 0; 
        }
            th { 
            background-color: #000 !important; 
            color: #fff !important; 
            border: 1px solid #000; 
        }
            td { 
            border: 1px solid #000; 
            padding: 8px; 
        }
    }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Daily Report Book</h1>
        <nav>
            <a href="?page=new_data" class="<?php echo $page == 'new_data' ? 'active' : ''; ?>">New Report</a>
            <a href="?page=today_data" class="<?php echo $page == 'today_data' ? 'active' : ''; ?>">Today's Report</a>
            <a href="?page=all_data" class="<?php echo $page == 'all_data' ? 'active' : ''; ?>">All Report</a>
        </nav>
    </header>

    <?php 
    echo $message; 
    ?>

    <?php 
    if ($page == 'new_data'): 
    ?>
        <div style="margin-bottom: 25px;">
            <h2>Record New Merchant Data</h2>
        </div>
        
        <form method="POST" action="?page=new_data">
            <div class="form-group">
                <label for="inspector_name">Merchant Name:</label>
                <input type="text" id="inspector_name" name="inspector_name" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="item_name">Item Description:</label>
                <input type="text" id="item_name" name="item_name" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="quantity_collected">Number of Items Collected:</label>
                <input type="number" id="quantity_collected" name="quantity_collected" min="1" required>
            </div>
            <div class="form-group">
                <label for="road_name">Road Name:</label>
                <input type="text" id="road_name" name="road_name" required autocomplete="off">
            </div>
            <div class="form-group full-width">
                <label for="area">Area / Sector Location Zone:</label>
                <input type="text" id="area" name="area" required autocomplete="off">
            </div>
            <button type="submit" name="add_merchant" class="btn">Submit Report</button>
        </form>

    <?php 
    elseif ($page == 'today_data'): 
    ?>
        <div class="table-header-container">
            <h2>Today's Work Summary (<?php echo date('Y-m-d'); ?>)</h2>
            <button onclick="window.print()" class="btn-print">🖨️ Print Log to Hard Paper</button>
        </div>
        <p style="color: #e67e22; font-size: 13px; margin-bottom: 15px;">⚠️ Note: All records here vanish automatically at midnight.</p>

        <?php
        $result = $conn->query("SELECT * FROM merchants WHERE DATE(created_at) = '$current_today' ORDER BY id DESC");
        ?>

        <table>
            <thead>
                <tr>
                    <th>S.No</th> <th>Merchant Name</th>
                    <th>Item Description</th>
                    <th>Qty</th>
                    <th>Road Location</th>
                    <th>Area Zone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && $result->num_rows > 0): 
                ?>
                    <?php 
                    $sn = 1;
                    ?>
                    <?php 
                    while($row = $result->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?php echo $sn++; ?></td>
                            <td><?php echo htmlspecialchars($row['inspector_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo $row['quantity_collected']; ?></td>
                            <td><?php echo htmlspecialchars($row['road_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['area']); ?></td>
                            <td>
                                <a href="?page=today_data&delete_id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this today record?')">Delete</a>
                            </td>
                        </tr>
                    <?php 
                    endwhile; 
                    ?>
                <?php 
                else: 
                ?>
                    <tr><td colspan="7" style="text-align:center; color: #777;">No records logged yet today. Old data has been auto-cleared.</td></tr>
                <?php 
                endif; 
                ?>
            </tbody>
        </table>

    <?php 
    elseif ($page == 'all_data'): 
    ?>
        <div class="table-header-container">
            <h2>Master System Database Archives (Long-Term Storage)</h2>
        </div>
        <p style="color: #27ae60; font-size: 13px; margin-bottom: 15px;">✓ Data remains safe here until auto-purged at midnight.</p>

        <?php
        $result = $conn->query("SELECT * FROM merchants ORDER BY id DESC");
        ?>

        <table>
            <thead>
                <tr>
                    <th>S.No</th> <th>Merchant Name</th>
                    <th>Item Description</th>
                    <th>Qty</th>
                    <th>Road Location</th>
                    <th>Area Zone</th>
                    <th>Logged Timestamp</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && $result->num_rows > 0): 
                ?>
                    <?php 
                    $sn = 1;
                    ?>
                    <?php  
                    while($row = $result->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?php echo $sn++; ?></td>
                            <td><?php echo htmlspecialchars($row['inspector_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo $row['quantity_collected']; ?></td>
                            <td><?php echo htmlspecialchars($row['road_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['area']); ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td>
                                <a href="?page=all_data&delete_id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('WARNING: Permanently delete this archival data record?')">Delete</a>
                            </td>
                        </tr>
                    <?php 
                    endwhile; 
                    ?>
                <?php 
                else: 
                ?>
                    <tr><td colspan="8" style="text-align:center; color: #777;">Database archive is empty.</td></tr>
                <?php   
                endif; 
                ?>
            </tbody>
        </table>
    <?php 
    endif; 
    ?>

</div>
</body>
</html>
<?php 
$conn->close(); 
?>