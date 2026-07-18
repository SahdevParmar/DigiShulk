
<?php
session_start();
include 'db_connect.php';
if(!isset($_SESSION['role'])|| $_SESSION['role']!='admin'){
    header("Location: logout.php");
    exit();
}
include 'header.php';

/* ---------- Dashboard Statistics ---------- */

// Today's Collection
$todayCollection = 0;

$res = $conn->query("
SELECT SUM(total_amount) total
FROM transactions
WHERE DATE(created_at)=CURDATE()
AND status='paid'
");

if($row = $res->fetch_assoc()){
    $todayCollection = $row['total'] ?? 0;
}


// Today's Transactions
$todayTransactions = 0;

$res = $conn->query("
SELECT COUNT(*) total
FROM transactions
WHERE DATE(created_at)=CURDATE()
");

if($row = $res->fetch_assoc()){
    $todayTransactions = $row['total'];
}


// Online Inspectors
$onlineInspectors = 0;

$res = $conn->query("
SELECT COUNT(*) total
FROM users
WHERE role='inspector'
AND last_active >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
");

if($row = $res->fetch_assoc()){
    $onlineInspectors = $row['total'];
}


// Today's Seizures
$todaySeizures = 0;

if($conn->query("SHOW TABLES LIKE 'rmc_seizures'")->num_rows){

    $res = $conn->query("
    SELECT COUNT(*) total
    FROM rmc_seizures
    WHERE seizure_date = CURDATE()
    ");

    if($row = $res->fetch_assoc()){
        $todaySeizures = $row['total'];
    }

}

// Navigation and records are searched through the shared Spotlight Search (Ctrl + K).
$result = $conn->query(
    "SELECT t.*, u.username
     FROM transactions t
     JOIN users u ON t.inspector_id = u.user_id
     ORDER BY t.created_at DESC"
);

?>

<div class="dashboard-grid">

    <div class="dashboard-card">
        <h3>💰 Today's Collection</h3>
        <h1>₹<?php echo number_format($todayCollection,2); ?></h1>
    </div>

    <div class="dashboard-card">
        <h3>🟢 Online Inspectors</h3>
        <h1><?php echo $onlineInspectors; ?></h1>
    </div>

    <div class="dashboard-card">
        <h3>🧾 Today's Collections</h3>
        <h1><?php echo $todayTransactions; ?></h1>
    </div>

    <div class="dashboard-card">
        <h3>🚨 Today's Seizures</h3>
        <h1><?php echo $todaySeizures; ?></h1>
    </div>

</div>

<br>
<div class="dashboard-card">

    <h2>📡 Live Activity</h2>

    <div id="liveActivity">

        Loading...

    </div>

</div>

<br>

<div class="table-card">

<table class="modern-table">
    <tr>
        <th>Inspector</th>
        <th>Shop Name</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Time</th>
        <th>Receipt</th>
    </tr>
    <?php 
    $total=0;
    while($row=$result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['username']);?></td>
            <td><?php echo htmlspecialchars($row['shop_name']);?></td>
            <td>

                <span class="amount">

                    ₹<?php echo number_format($row['total_amount'],2); ?>

                </span>

            </td>
            <?php $stampClass = $row['status']=='paid' ? 'stamp-paid' : 'stamp-pending'; ?>
            <td><span class="stamp <?php echo $stampClass; ?>"><?php echo $row['status']; ?></span></td>
            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            <td>

            <a class="view-btn" href="receipt.php?id=<?php echo $row['transaction_id']; ?>">👁 View</a>

            </td>
            <?php
            if($row['status']=='paid'){
                 $total+=$row['total_amount'];
            }
            ?>

        </tr>
        <?php endwhile;?>
    </table>

</div>
<br>
<div class="total-collection">Total Collection for Selection: ₹<?php echo number_format($total,2);?></div>
<script>
function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value ?? '';
    return element.innerHTML;
}

function loadActivity() {

    fetch("api/live_activity.php")
        .then(r => r.json())
        .then(data => {

            let html = "";

            data.forEach(item => {
                item = {
                    ...item,
                    username: escapeHtml(item.username),
                    total_amount: escapeHtml(item.total_amount),
                    shop_name: escapeHtml(item.shop_name),
                    created_at: escapeHtml(item.created_at)
                };

                html += `
                <div class="activity-item activity-success">

                    <div class="activity-icon">💰</div>

                    <div class="activity-content">

                        <div class="activity-title">
                            <strong>${item.username}</strong> collected ₹${item.total_amount}
                        </div>

                        <div class="activity-subtitle">
                            ${item.shop_name}
                        </div>

                        <div class="activity-time">
                            ${item.created_at}
                        </div>

                    </div>

                </div>
                `;

            });

            document.getElementById("liveActivity").innerHTML = html;

        })
        .catch(err => {
            console.error(err);
            document.getElementById("liveActivity").innerHTML =
                "<p>Unable to load activity.</p>";
        });

}

loadActivity();

setInterval(loadActivity, 5000);

</script>
