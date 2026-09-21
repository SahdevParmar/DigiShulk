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
   Pending Collections
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
   Yesterday
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
   Week over week
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
        'date'  => $date,
        'day'   => $dayName,
        'total' => (float) ($row['total'] ?? 0),
        'count' => (int)   ($row['count'] ?? 0),
    ];
}

/* 7-day average (excluding today) for the pace indicator */
$pastDays = array_slice($trendData, 0, 6);
$pastSum = 0.0;
foreach ($pastDays as $d) { $pastSum += $d['total']; }
$sevenDayAvg = $pastSum > 0 ? ($pastSum / 6) : 0;
$pacePct     = $sevenDayAvg > 0
    ? round(($stats['total'] / $sevenDayAvg) * 100)
    : 0;
$paceLabel   = '—';
$paceClass   = 'neutral';
if ($sevenDayAvg > 0) {
    if ($pacePct >= 120)      { $paceLabel = 'Ahead of pace';  $paceClass = 'positive'; }
    elseif ($pacePct >= 80)   { $paceLabel = 'On pace';        $paceClass = 'positive'; }
    elseif ($pacePct >= 40)   { $paceLabel = 'Below pace';     $paceClass = 'warning'; }
    else                      { $paceLabel = 'Way below pace'; $paceClass = 'negative'; }
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
    $totalTodayTxns += (int) $row['count'];
    if($row['status'] === 'paid') $paidTodayTxns = (int) $row['count'];
}

/* -----------------------------
   Today's Seizures
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
        $todaySeizures = (int) $row['total'];
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

/* -----------------------------
   Greeting
------------------------------*/
$hour = (int) date('G');
if     ($hour < 5)  { $greeting = 'Late night';    $greetIcon = 'moon'; }
elseif ($hour < 12) { $greeting = 'Good morning';  $greetIcon = 'sun'; }
elseif ($hour < 17) { $greeting = 'Good afternoon';$greetIcon = 'cloud-sun'; }
elseif ($hour < 21) { $greeting = 'Good evening';  $greetIcon = 'cloud-moon'; }
else                { $greeting = 'Working late';  $greetIcon = 'moon'; }

$dayChange  = $yesterdayTotal > 0 ? round((($stats['total'] - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;
$weekChange = $lastWeekTotal  > 0 ? round((($thisWeekTotal - $lastWeekTotal)  / $lastWeekTotal)  * 100, 1) : 0;
$hasCollectionToday = ((float) $stats['total']) > 0;
?>

<style>
/* ============================================================
   Inspector Dashboard — scoped styles
   All animations respect prefers-reduced-motion.
   ============================================================ */

.dash-page {
    --anim-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --anim-dur: 0.55s;
}

/* --- Entrance animations --- */
@keyframes dashRise {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes dashPop {
    0%   { opacity: 0; transform: scale(0.92); }
    60%  { opacity: 1; transform: scale(1.02); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes dashPulse {
    0%, 100% { transform: scale(1);    opacity: 1; }
    50%      { transform: scale(1.18); opacity: 0.85; }
}
@keyframes dashShimmer {
    0%   { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes dashFloat {
    0%, 100% { transform: translateY(0)    rotate(0deg); }
    50%      { transform: translateY(-6px) rotate(-3deg); }
}
@keyframes dashSparkle {
    0%   { transform: scale(0.6); opacity: 0.9; }
    100% { transform: scale(1.6); opacity: 0; }
}

.dash-reveal {
    opacity: 0;
    animation: dashRise var(--anim-dur) var(--anim-ease) forwards;
    animation-delay: var(--delay, 0ms);
}
.dash-pop {
    opacity: 0;
    animation: dashPop 0.6s var(--anim-ease) forwards;
    animation-delay: var(--delay, 0ms);
}

@media (prefers-reduced-motion: reduce) {
    .dash-reveal, .dash-pop {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}

/* --- Hero card --- */
.dash-hero {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(120% 120% at 100% 0%,
            rgba(59, 130, 246, 0.18) 0%,
            rgba(59, 130, 246, 0.04) 45%,
            transparent 70%),
        linear-gradient(135deg,
            var(--color-surface) 0%,
            var(--color-surface) 100%);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: box-shadow 0.3s var(--anim-ease), transform 0.3s var(--anim-ease);
}
.dash-hero::after {
    content: '';
    position: absolute;
    inset: -40% -40% auto auto;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.22) 0%, transparent 65%);
    pointer-events: none;
    filter: blur(4px);
}
.dash-hero:hover {
    box-shadow: 0 12px 32px -12px rgba(59, 130, 246, 0.35);
}

.dash-hero-icon {
    animation: dashFloat 4s ease-in-out infinite;
}

.dash-hero-amount {
    letter-spacing: -0.02em;
    background: linear-gradient(120deg, var(--color-text), var(--color-text) 60%, var(--color-primary) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.dash-hero-amount.has-value::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    margin-right: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.18);
    animation: dashPulse 2s ease-in-out infinite;
    vertical-align: middle;
    -webkit-text-fill-color: initial;
}

/* --- Pace pill --- */
.dash-pace {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: var(--text-xs);
    font-weight: 600;
    letter-spacing: 0.01em;
}
.dash-pace.positive { background: rgba(34, 197, 94, 0.12);  color: #15803d; }
.dash-pace.warning  { background: rgba(234, 179, 8, 0.14);  color: #a16207; }
.dash-pace.negative { background: rgba(239, 68, 68, 0.12);  color: #b91c1c; }
.dash-pace.neutral  { background: var(--color-surface-muted); color: var(--color-text-muted); }

/* --- Quick stat cards --- */
.dash-stat {
    position: relative;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-4);
    text-align: center;
    transition: transform 0.25s var(--anim-ease),
                box-shadow 0.25s var(--anim-ease),
                border-color 0.25s var(--anim-ease);
    cursor: default;
}
.dash-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px -14px rgba(15, 23, 42, 0.25);
    border-color: rgba(59, 130, 246, 0.35);
}
.dash-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    margin-bottom: var(--space-2);
    font-size: 0.95rem;
}
.dash-stat-icon.blue   { background: rgba(59, 130, 246, 0.12); color: #2563eb; }
.dash-stat-icon.amber  { background: rgba(234, 179, 8, 0.14);  color: #ca8a04; }
.dash-stat-icon.red    { background: rgba(239, 68, 68, 0.12);  color: #dc2626; }

/* --- Buttons --- */
.dash-cta {
    position: relative;
    overflow: hidden;
    transition: transform 0.25s var(--anim-ease), box-shadow 0.25s var(--anim-ease);
    box-shadow: 0 8px 24px -14px rgba(37, 99, 235, 0.6);
}
.dash-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 32px -14px rgba(37, 99, 235, 0.7);
}
.dash-cta:active { transform: translateY(0); }
.dash-cta::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg,
        transparent 30%,
        rgba(255, 255, 255, 0.28) 45%,
        transparent 60%);
    background-size: 200% 100%;
    background-position: -200% 0;
    animation: dashShimmer 3.2s linear infinite;
    pointer-events: none;
}

.dash-action {
    transition: transform 0.2s var(--anim-ease),
                box-shadow 0.2s var(--anim-ease),
                background 0.2s var(--anim-ease);
}
.dash-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.28);
}
.dash-action i {
    transition: transform 0.25s var(--anim-ease);
}
.dash-action:hover i {
    transform: scale(1.18) rotate(-4deg);
}

/* --- Card lift for the chart & recent table --- */
.dash-card {
    transition: box-shadow 0.3s var(--anim-ease);
}
.dash-card:hover {
    box-shadow: 0 12px 32px -20px rgba(15, 23, 42, 0.28);
}

/* --- Recent row hover --- */
.dash-recent-row {
    transition: background 0.2s var(--anim-ease), transform 0.2s var(--anim-ease);
}
.dash-recent-row:hover {
    background: var(--color-surface-muted);
    transform: translateX(2px);
}

/* --- Sparkle --- */
.dash-sparkle {
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: radial-gradient(circle, #fbbf24 0%, rgba(251, 191, 36, 0) 70%);
    pointer-events: none;
    animation: dashSparkle 0.9s var(--anim-ease) forwards;
}
</style>

<div class="page dash-page">

    <!-- Page Header -->
    <div class="dash-reveal" style="--delay: 0ms; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: var(--space-3);">
                <i class="fa-solid fa-<?= $greetIcon ?>" style="color: var(--color-primary);" aria-hidden="true"></i>
                <?= $greeting ?>, <?= $inspectorName ?> 👋
            </h1>
            <p class="subtitle" style="margin-top: var(--space-1);">Today is <?= date('l, F j, Y') ?></p>
        </div>
    </div>

    <!-- Today's Collection Hero -->
    <div class="dash-hero dash-reveal" style="--delay: 80ms; margin-bottom: var(--space-6);">
        <div class="card-body" style="padding: var(--space-6); position: relative; z-index: 1;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4);">
                <div>
                    <div class="stat-label" style="font-size: var(--text-sm); color: var(--color-text-muted); margin-bottom: var(--space-1);">
                        Today's Collection
                    </div>
                    <div id="heroAmount"
                         class="dash-hero-amount <?= $hasCollectionToday ? 'has-value' : '' ?>"
                         data-target="<?= number_format((float) $stats['total'], 2, '.', '') ?>"
                         style="font-size: var(--text-4xl); font-weight: 700; color: var(--color-text);">
                        ₹0.00
                    </div>

                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-3);">
                        <?php if ($yesterdayTotal > 0): ?>
                            <div class="stat-change stat-change-<?= $dayChange >= 0 ? 'positive' : 'negative' ?>" style="font-size: var(--text-sm);">
                                <i class="fa-solid fa-<?= $dayChange >= 0 ? 'arrow-up' : 'arrow-down' ?>" aria-hidden="true"></i>
                                <?= $dayChange >= 0 ? '+' : '' ?><?= $dayChange ?>% vs yesterday
                            </div>
                        <?php endif; ?>

                        <?php if ($sevenDayAvg > 0): ?>
                            <span class="dash-pace <?= $paceClass ?>">
                                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                                <?= $paceLabel ?>
                                <span style="opacity: 0.7;">·</span>
                                <?= $pacePct ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stat-icon stat-icon-primary dash-hero-icon" style="width: 72px; height: 72px; font-size: 2.2rem;">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                </div>
            </div>

            <!-- Mini breakdown -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-top: var(--space-5); padding-top: var(--space-4); border-top: 1px dashed var(--color-border);">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div class="dash-stat-icon blue" style="margin: 0;"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i></div>
                    <div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Cash</div>
                        <div style="font-weight: 600;">₹<?= number_format((float) $stats['cash_total'], 2) ?></div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div class="dash-stat-icon blue" style="margin: 0;"><i class="fa-solid fa-qrcode" aria-hidden="true"></i></div>
                    <div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-muted);">UPI</div>
                        <div style="font-weight: 600;">₹<?= number_format((float) $stats['upi_total'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Primary CTA -->
    <div class="dash-pop" style="--delay: 160ms; margin-bottom: var(--space-6);">
        <a href="spot_tax.php" class="btn btn-primary btn-block btn-lg dash-cta"
           style="padding: var(--space-5); font-size: var(--text-lg); position: relative;">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            New Spot Collection
        </a>
    </div>

    <!-- Quick Stats Row -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-3); margin-bottom: var(--space-6);">

        <div class="dash-stat dash-pop" style="--delay: 220ms;">
            <div class="dash-stat-icon blue" style="margin: 0 auto var(--space-2);">
                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
            </div>
            <div class="stat-value" style="font-size: var(--text-2xl);"><?= $totalTodayTxns ?></div>
            <div class="stat-label" style="font-size: var(--text-xs);">Collections</div>
        </div>

        <div class="dash-stat dash-pop" style="--delay: 280ms;">
            <div class="dash-stat-icon amber" style="margin: 0 auto var(--space-2);">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
            </div>
            <div class="stat-value" style="font-size: var(--text-2xl); color: var(--color-warning);"><?= $pending['count'] ?></div>
            <div class="stat-label" style="font-size: var(--text-xs);">Pending</div>
        </div>

        <div class="dash-stat dash-pop" style="--delay: 340ms;">
            <div class="dash-stat-icon red" style="margin: 0 auto var(--space-2);">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <div class="stat-value" style="font-size: var(--text-2xl); color: var(--color-warning);"><?= $todaySeizures ?></div>
            <div class="stat-label" style="font-size: var(--text-xs);">Seizures</div>
        </div>

    </div>

    <!-- Quick Actions -->
    <div class="dash-reveal" style="--delay: 400ms; display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-6);">
        <a href="spot_tax.php" class="btn btn-primary dash-action" style="flex: 1; min-width: 140px;">
            <i class="fa-solid fa-receipt" aria-hidden="true"></i>
            New Spot Tax
        </a>
        <a href="seizure_form.php" class="btn btn-secondary dash-action" style="flex: 1; min-width: 140px;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            New Seizure
        </a>
        <a href="history.php" class="btn btn-ghost dash-action" style="flex: 1; min-width: 140px;">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            History
        </a>
    </div>

    <!-- 7-Day Trend -->
    <div class="card dash-card dash-reveal" style="--delay: 460ms; margin-bottom: var(--space-6);">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl); display: flex; align-items: center; gap: var(--space-2);">
                        <i class="fa-solid fa-chart-line" style="color: var(--color-primary);" aria-hidden="true"></i>
                        7-Day Trend
                    </h2>
                    <p class="card-subtitle">Your daily paid collections</p>
                </div>
                <?php if ($sevenDayAvg > 0): ?>
                    <span class="dash-pace neutral">
                        Avg: ₹<?= number_format($sevenDayAvg, 0) ?>/day
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body" style="padding-top: var(--space-2);">
            <canvas id="trendChart" height="200" style="width: 100%; max-height: 280px;"></canvas>
        </div>
    </div>

    <!-- Recent Collections -->
    <div class="card dash-card dash-reveal" style="--delay: 520ms;">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl); display: flex; align-items: center; gap: var(--space-2);">
                        <i class="fa-solid fa-clock-rotate-left" style="color: var(--color-primary);" aria-hidden="true"></i>
                        Recent Collections
                    </h2>
                    <p class="card-subtitle">Your latest 5 transactions</p>
                </div>
                <a href="history.php" class="btn btn-sm btn-ghost dash-action">View All</a>
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
                            <?php $rowIdx = 0; while($row=$recent->fetch_assoc()): $rowIdx++; ?>
                                <tr class="dash-recent-row dash-reveal" style="--delay: <?= 560 + ($rowIdx * 50) ?>ms;">
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
/* ============================================================
   1. Animated number counter for the hero amount
   ============================================================ */
(function () {
    var el = document.getElementById('heroAmount');
    if (!el) return;

    var target = parseFloat(el.getAttribute('data-target')) || 0;
    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function format(n) {
        return '₹' + n.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    if (reduced || target === 0) {
        el.textContent = format(target);
        return;
    }

    var start    = null;
    var duration = 1400;
    var startVal = 0;

    function easeOutQuart(t) {
        return 1 - Math.pow(1 - t, 4);
    }

    function tick(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / duration, 1);
        var eased = easeOutQuart(p);
        el.textContent = format(startVal + (target - startVal) * eased);

        if (p < 1) {
            requestAnimationFrame(tick);
        } else {
            el.textContent = format(target);
            // Celebratory sparkle if there was collection today
            if (target > 0) sparkleBurst(el);
        }
    }

    requestAnimationFrame(tick);
})();

/* ============================================================
   2. Sparkle burst — small visual reward when amount lands
   ============================================================ */
function sparkleBurst(anchor) {
    if (window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var rect = anchor.getBoundingClientRect();
    var host = anchor.parentElement;
    if (!host) return;

    for (var i = 0; i < 6; i++) {
        (function (idx) {
            setTimeout(function () {
                var s = document.createElement('span');
                s.className = 'dash-sparkle';
                s.style.left = (rect.width * (0.15 + Math.random() * 0.7)) + 'px';
                s.style.top  = (rect.height * (0.2  + Math.random() * 0.6)) + 'px';
                host.style.position = 'relative';
                host.appendChild(s);
                setTimeout(function () { s.remove(); }, 950);
            }, idx * 70);
        })(i);
    }
}

/* ============================================================
   3. 7-Day Trend chart
   ============================================================ */
(function() {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;

    const trendData = <?php echo json_encode($trendData); ?>;
    const labels = trendData.map(d => d.day);
    const amounts = trendData.map(d => d.total);
    const counts = trendData.map(d => d.count);

    const hasData = amounts.some(a => a > 0);

    if (!hasData) {
        ctx.parentElement.innerHTML = '<div class="empty-state" style="padding: var(--space-8); margin: 0; border: none; border-radius: 0; background: transparent;"><div class="empty-state-icon" style="width: 48px; height: 48px; font-size: 1.5rem; margin-bottom: var(--space-3);"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i></div><p class="empty-state-title" style="font-size: var(--text-base);">No collection trends recorded yet</p><p class="empty-state-message" style="font-size: var(--text-sm);">Start collecting to see trends</p></div>';
        return;
    }

    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(37, 99, 235, 0.55)');
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0.06)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Collections (₹)',
                data: amounts,
                backgroundColor: gradient,
                hoverBackgroundColor: 'rgba(37, 99, 235, 0.85)',
                borderColor: 'rgb(37, 99, 235)',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 40,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 900,
                easing: 'easeOutQuart'
            },
            interaction: { intersect: false, mode: 'index' },
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
                    ticks: { color: '#94a3b8', font: { size: 11 } }
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