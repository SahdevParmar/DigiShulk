<?php
// ==========================================
// 1. SELF-CONTAINED DATABASE INITIALIZATION
// ==========================================
$db_file = __DIR__ . '/reports.db';
try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        merchant_name TEXT NOT NULL,
        item_count INTEGER NOT NULL,
        road_name TEXT NOT NULL,
        area_name TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Database Initialization Failed: " . $e->getMessage());
}

// Get the current filename dynamically to prevent 404 redirect errors
$current_file = basename($_SERVER['PHP_SELF']);

// ==========================================
// 2. CONTROLLER LOGIC (ACTIONS)
// ==========================================

// Handle Form Submission (Create New Entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $merchant = $_POST['merchant_name'];
    $item_count = (int)$_POST['item_count'];
    $road = $_POST['road_name'];
    $area = $_POST['area_name'];
    $current_time = date('Y-m-d H:i:s');

    $stmt = $db->prepare("INSERT INTO reports (merchant_name, item_count, road_name, area_name, created_at) VALUES (:merchant, :item_count, :road, :area, :created_at)");
    $stmt->execute([
        ':merchant' => $merchant,
        ':item_count' => $item_count,
        ':road' => $road,
        ':area' => $area,
        ':created_at' => $current_time
    ]);

    // Dynamic redirect to current file name
    header("Location: " . $current_file . "?view=todayPage");
    exit();
}

// Handle Manual Record Deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $redirect_view = isset($_GET['from']) ? $_GET['from'] : 'mainMenu';
    
    $stmt = $db->prepare("DELETE FROM reports WHERE id = :id");
    $stmt->execute([':id' => $delete_id]);
    
    header("Location: " . $current_file . "?view=" . $redirect_view);
    exit();
}

// Get Today's Date Boundaries
$today_start = date('Y-m-d 00:00:00');
$today_end   = date('Y-m-d 23:59:59');

$today_stmt = $db->prepare("SELECT * FROM reports WHERE datetime(created_at, 'localtime') BETWEEN :start AND :end ORDER BY id DESC");
$today_stmt->execute([':start' => $today_start, ':end' => $today_end]);
$today_reports = $today_stmt->fetchAll(PDO::FETCH_ASSOC);

$all_stmt = $db->query("SELECT * FROM reports ORDER BY id DESC");
$all_reports = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Report Book</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background-color: #ffffff;
            padding: 40px 35px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
            width: 100%;
            max-width: 600px;
            margin: 20px;
            box-sizing: border-box;
        }
        h1, h2 { color: #2c3e50; margin: 0 0 25px 0; font-weight: 700; }
        h1 { font-size: 2.2rem; }
        h2 { font-size: 1.8rem; }
        p { color: #7f8c8d; font-size: 1rem; line-height: 1.5; margin-bottom: 25px; }
        .form-group { text-align: left; margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #34495e; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        button, .btn {
            display: block; width: 100%; padding: 14px; margin: 16px 0; font-size: 1.1rem; font-weight: 600;
            text-transform: capitalize; color: #ffffff; background-color: #3498db; border: none; border-radius: 8px;
            cursor: pointer; transition: all 0.25s ease; box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2); text-decoration: none; text-align: center; box-sizing: border-box;
        }
        button:hover, .btn:hover { background-color: #2980b9; transform: translateY(-2px); }
        .btn-back { background-color: #95a5a6; box-shadow: 0 4px 6px rgba(149, 165, 166, 0.2); }
        .btn-back:hover { background-color: #7f8c8d; }
        .btn-print { background-color: #2ecc71; box-shadow: 0 4px 6px rgba(46, 204, 113, 0.2); }
        .btn-print:hover { background-color: #27ae60; }
        .table-responsive { max-height: 350px; overflow-y: auto; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f8fafc; color: #334155; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .btn-delete { background-color: #e74c3c; color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.8rem; text-decoration: none; font-weight: bold; }
        .page { display: none; }
        .page.active { display: block; }
        @media print {
            body { background: none; background-color: #fff; }
            .container { box-shadow: none; padding: 0; max-width: 100%; margin: 0; }
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .btn-delete, .btn-back, .btn-print, button, th:last-child, td:last-child { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div id="mainMenu" class="page active">
            <h1>Daily Report Book</h1>
            <button type="button" onclick="showPage('newPage')">new report</button>     
            <button type="button" onclick="showPage('todayPage')">today report</button>   
            <button type="button" onclick="showPage('oldPage')">old report</button>
        </div>

        <div id="newPage" class="page">
            <h2>New Report Entry</h2>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Merchant Name</label>
                    <input type="text" name="merchant_name" required placeholder="Enter merchant name">
                </div>
                <div class="form-group">
                    <label>How Many Items</label>
                    <input type="number" name="item_count" min="1" required placeholder="Total items quantity">
                </div>
                <div class="form-group">
                    <label>Road Name</label>
                    <input type="text" name="road_name" required placeholder="Enter road name">
                </div>
                <div class="form-group">
                    <label>Area</label>
                    <input type="text" name="area_name" required placeholder="Enter area region">
                </div>

                <button type="submit">Submit Entry</button>
            </form>
            <button type="button" class="btn-back" onclick="showPage('mainMenu')">Back to Menu</button>
        </div>

        <div id="todayPage" class="page">
            <div id="printArea">
                <h2>Today's Operational Report</h2>
                <p style="margin-bottom:10px;">Data clears out of this view automatically at midnight.</p>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Merchant</th>
                                <th>Qty</th>
                                <th>Road</th>
                                <th>Area</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($today_reports) > 0): ?>
                                <?php foreach($today_reports as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['merchant_name']) ?></td>
                                        <td><?= htmlspecialchars($row['item_count']) ?></td>
                                        <td><?= htmlspecialchars($row['road_name']) ?></td>
                                        <td><?= htmlspecialchars($row['area_name']) ?></td>
                                        <td><a href="?delete_id=<?= $row['id'] ?>&from=todayPage" class="btn-delete" onclick="return confirm('Delete record?')">Delete</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center; color:#94a3b8;">No entries captured yet today.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <button type="button" class="btn-print" onclick="window.print()">Print Hard Copy</button>
            <button type="button" class="btn-back" onclick="showPage('mainMenu')">Back to Menu</button>
        </div>

        <div id="oldPage" class="page">
            <h2>All-Time Historical Logs</h2>
            <p>Permanent archive log history records.</p>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Merchant</th>
                            <th>Qty</th>
                            <th>Road</th>
                            <th>Area</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_reports) > 0): ?>
                            <?php foreach($all_reports as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['merchant_name']) ?></td>
                                    <td><?= htmlspecialchars($row['item_count']) ?></td>
                                    <td><?= htmlspecialchars($row['road_name']) ?></td>
                                    <td><?= htmlspecialchars($row['area_name']) ?></td>
                                    <td><a href="?delete_id=<?= $row['id'] ?>&from=oldPage" class="btn-delete" onclick="return confirm('Delete completely?')">Delete</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8;">No data records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn-back" onclick="showPage('mainMenu')">Back to Menu</button>
        </div>

    </div>

    <script>
        function showPage(pageId) {
            const pages = document.querySelectorAll('.page');
            pages.forEach(page => page.classList.remove('active'));
            document.getElementById(pageId).classList.add('active');
            window.history.replaceState(null, null, '?view=' + pageId);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const viewParam = urlParams.get('view');
            if (viewParam && document.getElementById(viewParam)) {
                showPage(viewParam);
            }
        });
    </script>
</body>
</html>