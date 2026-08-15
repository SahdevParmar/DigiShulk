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

// Pending transactions needing attention
$pendingTxns = 0;
$pendingAmount = 0;
$res = $conn->query("
SELECT COUNT(*) as count, COALESCE(SUM(total_amount),0) as total
FROM transactions
WHERE status='pending'
");
if($row = $res->fetch_assoc()){
    $pendingTxns = $row['count'];
    $pendingAmount = $row['total'];
}

// Inactive inspectors (not active in 24h)
$inactiveInspectors = 0;
$res = $conn->query("
SELECT COUNT(*) total
FROM users
WHERE role='inspector'
AND last_active < DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
if($row = $res->fetch_assoc()){
    $inactiveInspectors = $row['total'];
}

// Recent transactions for table
$result = $conn->query(
    "SELECT t.*, u.username
     FROM transactions t
     JOIN users u ON t.inspector_id = u.user_id
     ORDER BY t.created_at DESC
     LIMIT 10"
);

?>

<div class="page">

    <!-- Page Header -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0;">Admin Dashboard</h1>
            <p class="subtitle" style="margin-top: var(--space-1);">Today's operations overview</p>
        </div>
        <div style="display: flex; gap: var(--space-2);">
            <a href="history.php" class="btn btn-secondary">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                Export Report
            </a>
            <a href="history.php" class="btn btn-primary">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                View History
            </a>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="stat-grid">
        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Today's Collection</div>
                    <div class="stat-value">₹<?php echo number_format($todayCollection,2); ?></div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Today's Collections</div>
                    <div class="stat-value"><?php echo $todayTransactions; ?></div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Online Inspectors</div>
                    <div class="stat-value"><?php echo $onlineInspectors; ?></div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Today's Seizures</div>
                    <div class="stat-value"><?php echo $todaySeizures; ?></div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Attention Required Panel -->
    <?php if ($pendingTxns > 0 || $inactiveInspectors > 0): ?>
    <div class="card card-accent-warning" style="margin-top: var(--space-6);">
        <div class="card-body">
            <div style="display: flex; align-items: flex-start; gap: var(--space-3);">
                <div class="stat-icon stat-icon-warning" style="flex-shrink: 0; margin-top: 2px;">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: var(--text-lg); font-weight: 600; color: var(--color-text); margin: 0 0 var(--space-2);">Needs Attention</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-4);">
                        <?php if ($pendingTxns > 0): ?>
                        <div>
                            <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--color-warning);"><?= $pendingTxns ?></div>
                            <div style="font-size: var(--text-sm); color: var(--color-text-muted);">Pending Transactions</div>
                            <div style="font-size: var(--text-xs); color: var(--color-text-subtle);">₹<?= number_format($pendingAmount, 2) ?> awaiting confirmation</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($inactiveInspectors > 0): ?>
                        <div>
                            <div style="font-size: var(--text-2xl); font-weight: 700; color: var(--color-warning);"><?= $inactiveInspectors ?></div>
                            <div style="font-size: var(--text-sm); color: var(--color-text-muted);">Inactive Inspectors</div>
                            <div style="font-size: var(--text-xs); color: var(--color-text-subtle);">No activity in 24 hours</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: var(--space-4); display: flex; gap: var(--space-2); flex-wrap: wrap;">
                        <?php if ($pendingTxns > 0): ?>
                        <a href="history.php?status=pending" class="btn btn-warning btn-sm">
                            <i class="fa-solid fa-clock" aria-hidden="true"></i>
                            View Pending
                        </a>
                        <?php endif; ?>
                        <?php if ($inactiveInspectors > 0): ?>
                        <a href="add_inspector.php" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                            Manage Inspectors
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Live Activity -->
    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl);">Live Activity</h2>
                    <p class="card-subtitle">Recent successful collections</p>
                </div>
                <span class="badge badge-success" id="liveStatus">
                    <i class="fa-solid fa-circle" aria-hidden="true"></i>
                    Live
                </span>
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <div id="liveActivity" style="max-height: 300px; overflow-y: auto;">
                <div class="skeleton skeleton-card">
                    <div class="skeleton-title"></div>
                    <div class="skeleton-text"></div>
                    <div class="skeleton-text short"></div>
                </div>
                <div class="skeleton skeleton-card">
                    <div class="skeleton-title"></div>
                    <div class="skeleton-text"></div>
                    <div class="skeleton-text short"></div>
                </div>
                <div class="skeleton skeleton-card">
                    <div class="skeleton-title"></div>
                    <div class="skeleton-text"></div>
                    <div class="skeleton-text short"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Collections Table -->
    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl);">Recent Collections</h2>
                    <p class="card-subtitle">Latest 10 transactions across all inspectors</p>
                </div>
                <a href="history.php" class="btn btn-sm btn-ghost">View All</a>
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Inspector</th>
                            <th>Shop Name</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th style="width: 100px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total=0;
                        if ($result->num_rows > 0):
                            while($row=$result->fetch_assoc()):
                                $stampClass = $row['status']=='paid' ? 'success' : ($row['status']=='pending' ? 'warning' : 'danger');
                                $stampIcon = $row['status']=='paid' ? 'check' : ($row['status']=='pending' ? 'clock' : 'xmark');
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: var(--space-2);">
                                    <div class="avatar avatar-sm" style="background: var(--color-primary-light); color: var(--color-primary);">
                                        <?= strtoupper(substr(htmlspecialchars($row['username']), 0, 1)) ?>
                                    </div>
                                    <strong><?=htmlspecialchars($row['username'])?></strong>
                                </div>
                            </td>
                            <td><?=htmlspecialchars($row['shop_name'])?></td>
                            <td>
                                <span style="font-weight: 600; color: var(--color-success);">₹<?php echo number_format($row['total_amount'],2); ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $stampClass ?> badge-dot">
                                    <i class="fa-solid fa-<?= $stampIcon ?>" aria-hidden="true"></i>
                                    <?=ucfirst($row['status'])?>
                                </span>
                            </td>
                            <td><?=htmlspecialchars($row['created_at'])?></td>
                            <td>
                                <a href="receipt.php?id=<?php echo $row['transaction_id']; ?>" class="table-action-btn" style="padding: var(--space-1) var(--space-2); font-size: var(--text-xs);">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php
                                if($row['status']=='paid'){
                                    $total+=$row['total_amount'];
                                }
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: var(--space-8); color: var(--color-text-muted);">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-3);">
                                    <i class="fa-solid fa-table-list" style="font-size: 2rem; color: var(--color-text-subtle);" aria-hidden="true"></i>
                                    <p>No transactions found</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total > 0): ?>
        <div class="card-footer" style="justify-content: flex-start; background: var(--color-surface-muted);">
            <span style="font-size: var(--text-base); font-weight: 600; color: var(--color-text);">
                Total Collection (Paid): <strong style="color: var(--color-success);">₹<?php echo number_format($total,2);?></strong>
            </span>
        </div>
        <?php endif; ?>
    </div>

</div>

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
            const container = document.getElementById("liveActivity");
            if (!data.length) {
                container.innerHTML = `
                    <div class="empty-state" style="padding: var(--space-8); margin: 0; border: none; border-radius: 0; background: transparent;">
                        <div class="empty-state-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                            <i class="fa-solid fa-satellite-dish" aria-hidden="true"></i>
                        </div>
                        <p class="empty-state-title" style="font-size: var(--text-base);">No recent activity</p>
                        <p class="empty-state-message" style="font-size: var(--text-sm);">Collections will appear here in real-time</p>
                    </div>
                `;
                return;
            }

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
                <div style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-5); border-bottom: 1px solid var(--color-border); transition: background var(--motion-fast);"
                     onmouseover="this.style.background='var(--color-surface-muted)'"
                     onmouseout="this.style.background=''">
                    <div class="stat-icon stat-icon-success" style="width: 40px; height: 40px; font-size: 1.125rem;">
                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: var(--text-sm); color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <strong>${item.username}</strong> collected ₹${item.total_amount}
                        </div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${item.shop_name}
                        </div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-subtle); margin-top: 2px;">
                            ${item.created_at}
                        </div>
                    </div>
                </div>
                `;
            });

            container.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            document.getElementById("liveActivity").innerHTML = `
                <div class="alert alert-danger" style="margin: var(--space-4); border-radius: 0; border-left: none; border-right: none; border-top: none;">
                    <i class="fa-solid fa-triangle-exclamation alert-icon" aria-hidden="true"></i>
                    <div class="alert-content">
                        <p class="alert-message">Unable to load activity. <button class="btn btn-ghost btn-sm" onclick="loadActivity()">Retry</button></p>
                    </div>
                </div>
            `;
        });
}

loadActivity();
setInterval(loadActivity, 5000);
</script>

<?php include 'footer.php'; ?>