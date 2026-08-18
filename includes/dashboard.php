<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'inspector') {
    header("Location: logout.php");
    exit();
}

include 'db_connect.php';
include 'header.php';

$inspector = $_SESSION['user_id'];
$inspectorName = htmlspecialchars($_SESSION['full_name'] ?? 'Inspector');

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
   Today's Transaction Counts
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

/* -----------------------------
   Today's Seizures Count
------------------------------*/
$todaySeizures = 0;
if($conn->query("SHOW TABLES LIKE 'seizure_sessions'")->num_rows){
    $stmt = $conn->prepare("
    SELECT COUNT(*) total
    FROM seizure_sessions
    WHERE inspector_id=?
    AND seizure_date = CURDATE()
    ");
    $stmt->bind_param("i", $inspector);
    $stmt->execute();
    if($row = $stmt->get_result()->fetch_assoc()){
        $todaySeizures = $row['total'];
    }
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

$dayChange = $yesterdayTotal > 0 ? round((($stats['total'] - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;
$weekChange = $lastWeekTotal > 0 ? round((($thisWeekTotal - $lastWeekTotal) / $lastWeekTotal) * 100, 1) : 0;
?>

<div class="page">

    <!-- Page Header -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0;">Good morning, <?= $inspectorName ?> 👋</h1>
            <p class="subtitle" style="margin-top: var(--space-1);">Today is <?php echo date('l, F j, Y'); ?></p>
        </div>
    </div>

    <!-- Today's Collection Hero -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body" style="padding: var(--space-6);">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4);">
                <div>
                    <div class="stat-label" style="font-size: var(--text-sm); color: var(--color-text-muted); margin-bottom: var(--space-1);">Today's Collection</div>
                    <div class="stat-value" style="font-size: var(--text-4xl); font-weight: 700; color: var(--color-text);">₹<?=number_format($stats['total'],2)?></div>
                    <?php if ($yesterdayTotal > 0): ?>
                        <div class="stat-change stat-change-<?= $dayChange >= 0 ? 'positive' : 'negative' ?>" style="margin-top: var(--space-2); font-size: var(--text-sm);">
                            <i class="fa-solid fa-<?= $dayChange >= 0 ? 'arrow-up' : 'arrow-down' ?>" aria-hidden="true"></i>
                            <?= $dayChange >= 0 ? '+' : '' ?><?= $dayChange ?>% vs yesterday
                        </div>
                    <?php endif; ?>
                </div>
                <div class="stat-icon stat-icon-primary" style="width: 64px; height: 64px; font-size: 2rem;">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Primary Action -->
    <div style="margin-bottom: var(--space-6);">
        <a href="spot_tax.php" class="btn btn-primary btn-block btn-lg" style="padding: var(--space-5); font-size: var(--text-lg);">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            New Spot Collection
        </a>
    </div>

    <!-- Quick Stats Row -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-3); margin-bottom: var(--space-6);">
        <div class="stat-card" style="padding: var(--space-4); text-align: center;">
            <div class="stat-value" style="font-size: var(--text-2xl);"><?= $totalTodayTxns ?></div>
            <div class="stat-label" style="font-size: var(--text-xs);">Collections Today</div>
        </div>
        <div class="stat-card" style="padding: var(--space-4); text-align: center;">
            <div class="stat-value" style="font-size: var(--text-2xl); color: var(--color-warning);"><?= $pending['count'] ?></div>
            <div class="stat-label" style="font-size: var(--text-xs);">Pending</div>
        </div>
        <div class="stat-card" style="padding: var(--space-4); text-align: center;">
            <div class="stat-value" style="font-size: var(--text-2xl); color: var(--color-warning);"><?= $todaySeizures ?></div>
            <div class="stat-label" style="font-size: var(--text-xs);">Seizures Today</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-6);">
        <a href="spot_tax.php" class="btn btn-primary" style="flex: 1; min-width: 140px;">
            <i class="fa-solid fa-receipt" aria-hidden="true"></i>
            New Spot Tax
        </a>
        <a href="seizure_form.php" class="btn btn-secondary" style="flex: 1; min-width: 140px;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            New Seizure
        </a>
        <a href="history.php" class="btn btn-ghost" style="flex: 1; min-width: 140px;">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            History
        </a>
    </div>

    <!-- 7-Day Trend Chart -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl);">7-Day Trend</h2>
                    <p class="card-subtitle">Your daily paid collections</p>
                </div>
            </div>
        </div>
        <div class="card-body" style="padding-top: var(--space-2);">
            <canvas id="trendChart" height="200" style="width: 100%; max-height: 280px;"></canvas>
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
                <table class="table responsive-table">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th>Amount</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent->num_rows > 0): ?>
                            <?php while($row=$recent->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Shop">
                                        <strong><?=htmlspecialchars($row['shop_name'])?></strong>
                                    </td>
                                    <td data-label="Amount">₹<?=number_format($row['total_amount'],2)?></td>
                                    <td data-label="Mode">
                                        <span class="badge badge-<?= $row['payment_mode'] === 'upi' ? 'primary' : 'neutral' ?>">
                                            <?=strtoupper($row['payment_mode'])?>
                                        </span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge badge-<?= $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <i class="fa-solid fa-<?= $row['status'] === 'paid' ? 'check' : ($row['status'] === 'pending' ? 'clock' : 'xmark') ?>" aria-hidden="true"></i>
                                            <?=ucfirst($row['status'])?>
                                        </span>
                                    </td>
                                    <td data-label="Time"><?=date("d/m/y H:i", strtotime($row['created_at']))?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: var(--space-8); color: var(--color-text-muted);">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        const ctx = document.getElementById('trendChart');
        if (!ctx) return;
        
        const trendData = <?php echo json_encode($trendData); ?>;
        const labels = trendData.map(d => d.day);
        const amounts = trendData.map(d => d.total);
        const counts = trendData.map(d => d.count);
        
        const hasData = amounts.some(a => a > 0);
        
        if (!hasData) {
            // No data - show empty state
            ctx.parentElement.innerHTML = '<div class="empty-state" style="padding: var(--space-8); margin: 0; border: none; border-radius: 0; background: transparent;"><div class="empty-state-icon" style="width: 48px; height: 48px; font-size: 1.5rem; margin-bottom: var(--space-3);"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i></div><p class="empty-state-title" style="font-size: var(--text-base);">No collection trends recorded yet</p><p class="empty-state-message" style="font-size: var(--text-sm);">Start collecting to see trends</p></div>';
            return;
        }
        
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
                        backgroundColor: '#0f172a',
                        titleColor: '#f1f5f9',
                        bodyColor: '#e2e8f0',
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
                        grid: { color: '#1e293b', display: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#1e293b' },
                        ticks: {
                            color: '#94a3b8',
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

<?php include 'footer.php'; ?>