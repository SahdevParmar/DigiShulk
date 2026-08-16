<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'inspector') {
    header("Location: logout.php");
    exit();
}

include 'db_connect.php';
include 'header.php';

$inspector = $_SESSION['user_id'];

/* -----------------------------
   Today's Statistics
------------------------------*/

$stmt = $conn->prepare("
SELECT
COALESCE(SUM(total_amount),0) AS total,
COALESCE(SUM(CASE WHEN payment_mode='cash' THEN total_amount ELSE 0 END),0) AS cash_total,
COALESCE(SUM(CASE WHEN payment_mode='upi' THEN total_amount ELSE 0 END),0) AS upi_total
FROM transactions
WHERE inspector_id=?
AND DATE(created_at)=CURDATE()
");

$stmt->bind_param("i",$inspector);
$stmt->execute();
$stats=$stmt->get_result()->fetch_assoc();

/* -----------------------------
   Pending Collections (Need Attention)
------------------------------*/
$stmt = $conn->prepare("
SELECT COUNT(*) as count, COALESCE(SUM(total_amount),0) as total
FROM transactions
WHERE inspector_id=?
AND status='pending'
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();

/* -----------------------------
   Yesterday for comparison
------------------------------*/
$yesterdayTotal = 0;
$stmt = $conn->prepare("
SELECT COALESCE(SUM(total_amount),0) AS total
FROM transactions
WHERE inspector_id=?
AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)
AND status='paid'
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$yesterdayTotal = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* -----------------------------
   This Week vs Last Week
------------------------------*/
$thisWeekTotal = 0;
$lastWeekTotal = 0;
$stmt = $conn->prepare("
SELECT COALESCE(SUM(total_amount),0) AS total
FROM transactions
WHERE inspector_id=?
AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
AND status='paid'
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$thisWeekTotal = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("
SELECT COALESCE(SUM(total_amount),0) AS total
FROM transactions
WHERE inspector_id=?
AND YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1)
AND status='paid'
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$lastWeekTotal = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$weekChange = $lastWeekTotal > 0 ? round((($thisWeekTotal - $lastWeekTotal) / $lastWeekTotal) * 100, 1) : 0;

/* -----------------------------
   Last 7 Days Trend
------------------------------*/
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayName = date('D', strtotime($date));
    $stmt = $conn->prepare("
    SELECT SUM(total_amount) total, COUNT(*) count
    FROM transactions
    WHERE inspector_id=?
    AND DATE(created_at) = ?
    AND status='paid'
    ");
    $stmt->bind_param("is", $inspector, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $trendData[] = [
        'date' => $date,
        'day' => $dayName,
        'total' => $row['total'] ?? 0,
        'count' => $row['count'] ?? 0
    ];
}

/* -----------------------------
   Payment Mode Breakdown Today
------------------------------*/
$cashToday = 0;
$upiToday = 0;
$stmt = $conn->prepare("
SELECT payment_mode, SUM(total_amount) as total, COUNT(*) as count
FROM transactions
WHERE inspector_id=?
AND DATE(created_at)=CURDATE()
AND status='paid'
GROUP BY payment_mode
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    if($row['payment_mode'] === 'cash') $cashToday = $row['total'];
    if($row['payment_mode'] === 'upi') $upiToday = $row['total'];
}

/* -----------------------------
   Collection Rate Today
------------------------------*/
$totalTodayTxns = 0;
$paidTodayTxns = 0;
$stmt = $conn->prepare("
SELECT status, COUNT(*) as count
FROM transactions
WHERE inspector_id=?
AND DATE(created_at)=CURDATE()
GROUP BY status
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $totalTodayTxns += $row['count'];
    if($row['status'] === 'paid') $paidTodayTxns = $row['count'];
}
$collectionRate = $totalTodayTxns > 0 ? round(($paidTodayTxns / $totalTodayTxns) * 100) : 100;

/* -----------------------------
   Top Shops This Week
------------------------------*/
$topShops = [];
$stmt = $conn->prepare("
SELECT shop_name, COALESCE(SUM(total_amount),0) as total, COUNT(*) as count
FROM transactions
WHERE inspector_id=?
AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
AND status='paid'
GROUP BY shop_name
ORDER BY total DESC
LIMIT 5
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $topShops[] = $row;
}

/* -----------------------------
   Recent Collections
------------------------------*/
$stmt=$conn->prepare("
SELECT shop_name,total_amount,payment_mode,created_at,status
FROM transactions
WHERE inspector_id=?
ORDER BY created_at DESC
LIMIT 5
");
$stmt->bind_param("i",$inspector);
$stmt->execute();
$recent=$stmt->get_result();

// Max trend value for chart scaling
$maxTrend = max(array_column($trendData, 'total'));
$maxTrend = max($maxTrend, 1);

$dayChange = $yesterdayTotal > 0 ? round((($stats['total'] - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;
?>

<div class="page">

    <!-- Page Header -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0;">Dashboard</h1>
            <p class="subtitle" style="margin-top: var(--space-1);">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Inspector'); ?> · <?php echo date('l, F j, Y'); ?></p>
        </div>
    </div>

    <!-- Key Metrics with Insights -->
    <div class="stat-grid">
        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Today's Collection</div>
                    <div class="stat-value">₹<?=number_format($stats['total'],2)?></div>
                    <?php if ($yesterdayTotal > 0): ?>
                        <div class="stat-change stat-change-<?= $dayChange >= 0 ? 'positive' : 'negative' ?>" style="margin-top: var(--space-1);">
                            <i class="fa-solid fa-<?= $dayChange >= 0 ? 'arrow-up' : 'arrow-down' ?>" aria-hidden="true"></i>
                            <?= $dayChange >= 0 ? '+' : '' ?><?= $dayChange ?>% vs yesterday
                        </div>
                    <?php endif; ?>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">This Week</div>
                    <div class="stat-value">₹<?=number_format($thisWeekTotal,2)?></div>
                    <div class="stat-change stat-change-<?= $weekChange >= 0 ? 'positive' : 'negative' ?>" style="margin-top: var(--space-1);">
                        <i class="fa-solid fa-<?= $weekChange >= 0 ? 'arrow-up' : 'arrow-down' ?>" aria-hidden="true"></i>
                        <?= $weekChange >= 0 ? '+' : '' ?><?= $weekChange ?>% vs last week
                    </div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fa-solid fa-calendar-week" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Collection Rate</div>
                    <div class="stat-value"><?= $collectionRate ?>%</div>
                    <div style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 2px;">
                        <?php echo $paidTodayTxns; ?> of <?php echo $totalTodayTxns; ?> transactions paid
                    </div>
                </div>
                <div class="stat-icon stat-icon-<?= $collectionRate >= 80 ? 'success' : ($collectionRate >= 50 ? 'warning' : 'danger') ?>">
                    <i class="fa-solid fa-<?= $collectionRate >= 80 ? 'check-circle' : ($collectionRate >= 50 ? 'clock' : 'xmark-circle') ?>" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <?php if ($pending['count'] > 0): ?>
        <div class="stat-card card-accent-warning">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Pending Collections</div>
                    <div class="stat-value"><?= $pending['count'] ?></div>
                    <div class="stat-change stat-change-warning" style="margin-top: var(--space-1);">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        ₹<?= number_format($pending['total'], 2) ?> awaiting confirmation
                    </div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 7-Day Trend Chart -->
    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl);">7-Day Collection Trend</h2>
                    <p class="card-subtitle">Your daily paid collections</p>
                </div>
            </div>
        </div>
        <div class="card-body" style="padding-top: var(--space-2);">
            <canvas id="trendChart" height="200" style="width: 100%; max-height: 300px;"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        const ctx = document.getElementById('trendChart');
        if (!ctx) return;
        
        const trendData = <?php echo json_encode($trendData); ?>;
        const labels = trendData.map(d => d.day);
        const amounts = trendData.map(d => d.total);
        const counts = trendData.map(d => d.count);
        
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.4)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.05)');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Collections (₹)',
                    data: amounts,
                    backgroundColor: gradient,
                    borderColor: 'rgb(37, 99, 235)',
                    borderWidth: 1,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 40,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        titleColor: '#ffffff',
                        bodyColor: '#f3f4f6',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                return [
                                    'Amount: ₹' + amounts[idx].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                                    'Transactions: ' + counts[idx]
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e5e7eb',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 11 },
                            callback: function(value) {
                                return '₹' + (value >= 1000 ? (value/1000).toFixed(1) + 'k' : value);
                            }
                        }
                    }
                }
            }
        });
    })();
    </script>

    <!-- Payment Mode Breakdown -->
    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <h2 class="card-title" style="font-size: var(--text-xl);">Today's Payment Modes</h2>
        </div>
        <div class="card-body" style="padding: var(--space-4) var(--space-6);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--space-4);">
                <div class="stat-card" style="text-align: center; padding: var(--space-5);">
                    <div class="stat-icon stat-icon-success" style="margin: 0 auto var(--space-3); width: 56px; height: 56px; font-size: 1.5rem;">
                        <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">Cash</div>
                    <div class="stat-value">₹<?php echo number_format($cashToday,2); ?></div>
                    <?php $cashPct = $stats['total'] > 0 ? round(($cashToday / $stats['total']) * 100) : 0; ?>
                    <div style="font-size: var(--text-sm); color: var(--color-text-muted); margin-top: var(--space-1);"><?php echo $cashPct; ?>% of total</div>
                </div>
                <div class="stat-card" style="text-align: center; padding: var(--space-5);">
                    <div class="stat-icon stat-icon-primary" style="margin: 0 auto var(--space-3); width: 56px; height: 56px; font-size: 1.5rem;">
                        <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">UPI</div>
                    <div class="stat-value">₹<?php echo number_format($upiToday,2); ?></div>
                    <?php $upiPct = $stats['total'] > 0 ? round(($upiToday / $stats['total']) * 100) : 0; ?>
                    <div style="font-size: var(--text-sm); color: var(--color-text-muted); margin-top: var(--space-1);"><?php echo $upiPct; ?>% of total</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Shops This Week -->
    <?php if (!empty($topShops)): ?>
    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl);">Top Shops This Week</h2>
                    <p class="card-subtitle">By collection amount</p>
                </div>
                <a href="history.php" class="btn btn-sm btn-ghost">View All</a>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th>Collections</th>
                            <th>Total Amount</th>
                            <th>Avg/Collection</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topShops as $index => $shop): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: var(--space-2);">
                                    <div class="avatar avatar-sm" style="background: var(--color-success-light); color: var(--color-success);">
                                        <?= $index + 1 ?>
                                    </div>
                                    <strong><?php echo htmlspecialchars($shop['shop_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo $shop['count']; ?></td>
                            <td style="font-weight: 600; color: var(--color-success);">₹<?php echo number_format($shop['total'],2); ?></td>
                            <td>₹<?php echo number_format($shop['count'] > 0 ? $shop['total'] / $shop['count'] : 0, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Primary Actions -->
    <div style="margin-top: var(--space-6); margin-bottom: var(--space-6);">
        <div style="display: flex; flex-wrap: wrap; gap: var(--space-3);">
            <a href="spot_tax.php" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                New Spot Tax
            </a>
            <a href="seizure_form.php" class="btn btn-secondary btn-lg">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                New Seizure Report
            </a>
            <a href="history.php" class="btn btn-ghost btn-lg">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                View History
            </a>
        </div>
    </div>

    <!-- Recent Collections -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl);">Recent Collections</h2>
                    <p class="card-subtitle">Your latest 5 transactions</p>
                </div>
                <a href="history.php" class="btn btn-sm btn-ghost">View All</a>
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th>Amount</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent->num_rows > 0): ?>
                            <?php while($row=$recent->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?=htmlspecialchars($row['shop_name'])?></strong>
                                    </td>
                                    <td>₹<?=number_format($row['total_amount'],2)?></td>
                                    <td>
                                        <span class="badge badge-<?= $row['payment_mode'] === 'upi' ? 'primary' : 'neutral' ?>">
                                            <?=strtoupper($row['payment_mode'])?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <i class="fa-solid fa-<?= $row['status'] === 'paid' ? 'check' : ($row['status'] === 'pending' ? 'clock' : 'xmark') ?>" aria-hidden="true"></i>
                                            <?=ucfirst($row['status'])?>
                                        </span>
                                    </td>
                                    <td><?=date("d M Y, h:i A", strtotime($row['created_at']))?></td>
                                    <td>
                                        <a href="payment.php?id=<?= $row['transaction_id'] ?><?= $row['status'] === 'paid' ? '&paid=1' : '' ?>" class="table-action-btn" style="padding: var(--space-1) var(--space-2); font-size: var(--text-xs);">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: var(--space-8); color: var(--color-text-muted);">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-3);">
                                        <i class="fa-solid fa-receipt" style="font-size: 2rem; color: var(--color-text-subtle);" aria-hidden="true"></i>
                                        <p>No collections yet</p>
                                        <a href="spot_tax.php" class="btn btn-primary btn-sm">Create First Collection</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>