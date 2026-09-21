<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: logout.php");
    exit();
}

include 'header.php';

/* ---------- Dashboard Statistics ---------- */

// Today's Collection (paid only)
$todayCollection = 0.0;
$res = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM transactions
    WHERE DATE(created_at) = CURDATE()
      AND status = 'paid'
");
if ($row = $res->fetch_assoc()) {
    $todayCollection = (float) $row['total'];
}

// Today's Transactions (all statuses)
$todayTransactions = 0;
$res = $conn->query("
    SELECT COUNT(*) AS total FROM transactions WHERE DATE(created_at) = CURDATE()
");
if ($row = $res->fetch_assoc()) {
    $todayTransactions = (int) $row['total'];
}

// Online inspectors (active in last 60s — allows a little jitter)
$onlineInspectors = 0;
$res = $conn->query("
    SELECT COUNT(*) AS total FROM users
    WHERE role = 'inspector'
      AND last_active >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
");
if ($row = $res->fetch_assoc()) {
    $onlineInspectors = (int) $row['total'];
}

// Total inspectors (for the "x of y" display)
$totalInspectors = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'inspector'");
if ($r = $res->fetch_assoc()) { $totalInspectors = (int) $r['c']; }

// Today's Seizures — FIXED: table is seizure_sessions, not rmc_seizures
$todaySeizures = 0;
if ($conn->query("SHOW TABLES LIKE 'seizure_sessions'")->num_rows) {
    $res = $conn->query("
        SELECT COUNT(*) AS total FROM seizure_sessions
        WHERE seizure_date = CURDATE()
    ");
    if ($row = $res->fetch_assoc()) {
        $todaySeizures = (int) $row['total'];
    }
}

// Pending transactions
$pendingTxns = 0;
$pendingAmount = 0.0;
$res = $conn->query("
    SELECT COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS total
    FROM transactions WHERE status = 'pending'
");
if ($row = $res->fetch_assoc()) {
    $pendingTxns   = (int) $row['count'];
    $pendingAmount = (float) $row['total'];
}

// Inactive inspectors — FIXED: exclude inspectors who never logged in
$inactiveInspectors = 0;
$res = $conn->query("
    SELECT COUNT(*) AS total FROM users
    WHERE role = 'inspector'
      AND last_active IS NOT NULL
      AND last_active < DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
if ($row = $res->fetch_assoc()) {
    $inactiveInspectors = (int) $row['total'];
}

// Undercharges this month (Sprint 1 feature)
$underchargeCount  = 0;
$underchargeAmount = 0.0;
$res = $conn->query("
    SELECT COUNT(*) AS c,
           COALESCE(SUM(suggested_amount - total_amount), 0) AS gap
    FROM transactions
    WHERE suggested_amount IS NOT NULL
      AND total_amount < suggested_amount
      AND DATE(created_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
if ($res && ($row = $res->fetch_assoc())) {
    $underchargeCount  = (int)   $row['c'];
    $underchargeAmount = (float) $row['gap'];
}

/* ---------- Insight Calculations ---------- */

// Yesterday
$yesterdayCollection = 0.0;
$res = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total FROM transactions
    WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
      AND status = 'paid'
");
if ($row = $res->fetch_assoc()) {
    $yesterdayCollection = (float) $row['total'];
}

// Week over week
$thisWeekCollection = 0.0;
$lastWeekCollection = 0.0;
$res = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total FROM transactions
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
      AND status = 'paid'
");
if ($row = $res->fetch_assoc()) { $thisWeekCollection = (float) $row['total']; }

$res = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total FROM transactions
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1)
      AND status = 'paid'
");
if ($row = $res->fetch_assoc()) { $lastWeekCollection = (float) $row['total']; }

$weekChange = $lastWeekCollection > 0
    ? round((($thisWeekCollection - $lastWeekCollection) / $lastWeekCollection) * 100, 1)
    : 0;

$dayChange = $yesterdayCollection > 0
    ? round((($todayCollection - $yesterdayCollection) / $yesterdayCollection) * 100, 1)
    : 0;

// Last 7 days trend
$trendData = [];
$trendSum  = 0.0;
for ($i = 6; $i >= 0; $i--) {
    $date    = date('Y-m-d', strtotime("-$i days"));
    $dayName = date('D', strtotime($date));
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) AS total, COUNT(*) AS count
        FROM transactions
        WHERE DATE(created_at) = ? AND status = 'paid'
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $trendData[] = [
        'date'  => $date,
        'day'   => $dayName,
        'total' => (float) $row['total'],
        'count' => (int)   $row['count'],
    ];
    $trendSum += (float) $row['total'];
}
$sevenDayAvg = $trendSum > 0 ? ($trendSum / 7) : 0;

// Today's pace vs 7-day average
$pacePct   = $sevenDayAvg > 0 ? round(($todayCollection / $sevenDayAvg) * 100) : 0;
$paceLabel = '—';
$paceClass = 'neutral';
if ($sevenDayAvg > 0) {
    if     ($pacePct >= 120) { $paceLabel = 'Ahead of pace';  $paceClass = 'positive'; }
    elseif ($pacePct >= 80)  { $paceLabel = 'On pace';        $paceClass = 'positive'; }
    elseif ($pacePct >= 40)  { $paceLabel = 'Below pace';     $paceClass = 'warning';  }
    else                     { $paceLabel = 'Way below pace'; $paceClass = 'negative'; }
}

// Top 5 inspectors this week
$topInspectors = [];
$res = $conn->query("
    SELECT u.username, u.full_name,
           COALESCE(SUM(t.total_amount), 0) AS total,
           COUNT(t.transaction_id) AS count
    FROM transactions t
    JOIN users u ON t.inspector_id = u.user_id
    WHERE YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)
      AND t.status = 'paid'
    GROUP BY t.inspector_id
    ORDER BY total DESC
    LIMIT 5
");
while ($row = $res->fetch_assoc()) { $topInspectors[] = $row; }

// Payment mode breakdown today
$cashToday = 0.0;
$upiToday  = 0.0;
$res = $conn->query("
    SELECT payment_mode, COALESCE(SUM(total_amount), 0) AS total
    FROM transactions
    WHERE DATE(created_at) = CURDATE() AND status = 'paid'
    GROUP BY payment_mode
");
while ($row = $res->fetch_assoc()) {
    if ($row['payment_mode'] === 'cash') { $cashToday = (float) $row['total']; }
    if ($row['payment_mode'] === 'upi')  { $upiToday  = (float) $row['total']; }
}
$cashPct = $todayCollection > 0 ? round(($cashToday / $todayCollection) * 100) : 0;
$upiPct  = $todayCollection > 0 ? round(($upiToday  / $todayCollection) * 100) : 0;

// Collection rate
$totalTodayTxns = 0;
$paidTodayTxns  = 0;
$res = $conn->query("
    SELECT status, COUNT(*) AS count FROM transactions
    WHERE DATE(created_at) = CURDATE()
    GROUP BY status
");
while ($row = $res->fetch_assoc()) {
    $totalTodayTxns += (int) $row['count'];
    if ($row['status'] === 'paid') { $paidTodayTxns = (int) $row['count']; }
}
$collectionRate = $totalTodayTxns > 0 ? round(($paidTodayTxns / $totalTodayTxns) * 100) : 100;

// Recent transactions
$result = $conn->query("
    SELECT t.*, u.username
    FROM transactions t
    JOIN users u ON t.inspector_id = u.user_id
    ORDER BY t.created_at DESC
    LIMIT 10
");

// Time-of-day greeting
$hour = (int) date('G');
if     ($hour < 5)  { $greeting = 'Late night';    $greetIcon = 'moon'; }
elseif ($hour < 12) { $greeting = 'Good morning';  $greetIcon = 'sun'; }
elseif ($hour < 17) { $greeting = 'Good afternoon';$greetIcon = 'cloud-sun'; }
elseif ($hour < 21) { $greeting = 'Good evening';  $greetIcon = 'cloud-moon'; }
else                { $greeting = 'Working late';  $greetIcon = 'moon'; }

$hasCollectionToday = $todayCollection > 0;
?>

<style>
/* ================================================================
   Admin Dashboard — scoped styles (prefix: .adash-)
   All animations respect prefers-reduced-motion.
   ================================================================ */

.adash {
    --ad-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --ad-accent: #3b82f6;
    --ad-accent-2: #6366f1;
    --ad-accent-soft: rgba(59, 130, 246, 0.14);
    --ad-success: #22c55e;
    --ad-danger: #ef4444;
    --ad-warn: #eab308;
}

/* ---------- Animations ---------- */
@keyframes adRise {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes adPop {
    0%   { opacity: 0; transform: scale(0.9); }
    60%  { opacity: 1; transform: scale(1.02); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes adPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); }
    70%      { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
}
@keyframes adFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50%      { transform: translateY(-6px) rotate(-3deg); }
}
@keyframes adShimmer {
    0%   { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes adSparkle {
    0%   { transform: scale(0.6); opacity: 0.9; }
    100% { transform: scale(1.6); opacity: 0; }
}
@keyframes adBlink {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.35; }
}
@keyframes adSlideInRight {
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
}

.ad-rise { opacity: 0; animation: adRise 0.55s var(--ad-ease) forwards; animation-delay: var(--d, 0ms); }
.ad-pop  { opacity: 0; animation: adPop 0.5s var(--ad-ease) forwards; animation-delay: var(--d, 0ms); }

@media (prefers-reduced-motion: reduce) {
    .ad-rise, .ad-pop, .ad-hero-icon, .ad-live-dot {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}

/* ---------- Hero ---------- */
.ad-hero {
    position: relative;
    overflow: hidden;
    padding: 28px;
    border-radius: 20px;
    background:
        radial-gradient(120% 140% at 100% 0%, rgba(59, 130, 246, 0.20), transparent 55%),
        radial-gradient(100% 140% at 0% 100%, rgba(168, 85, 247, 0.15), transparent 55%),
        var(--color-surface);
    border: 1px solid var(--color-border);
    color: var(--color-text);
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    transition: box-shadow 0.35s var(--ad-ease), transform 0.35s var(--ad-ease);
    margin-bottom: 22px;
}
.ad-hero:hover {
    box-shadow: 0 20px 44px -20px rgba(59, 130, 246, 0.45);
}
.ad-hero::after {
    content: '';
    position: absolute;
    inset: -40% -40% auto auto;
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.20), transparent 65%);
    filter: blur(6px);
    pointer-events: none;
    animation: adFloat 8s ease-in-out infinite;
}
.ad-hero-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    position: relative;
    z-index: 1;
}
.ad-hero-title {
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--color-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ad-hero-title i { color: var(--ad-accent); }
.ad-hero-sub {
    font-size: 0.85rem;
    color: var(--color-text-muted);
    margin: 4px 0 0;
}
.ad-hero-icon {
    width: 76px;
    height: 76px;
    border-radius: 22px;
    background: var(--ad-accent-soft);
    color: #93c5fd;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    animation: adFloat 4s ease-in-out infinite;
}
.ad-hero-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 22px;
    position: relative;
    z-index: 1;
}
@media (max-width: 640px) { .ad-hero-grid { grid-template-columns: 1fr; } }

.ad-hero-metric-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}
.ad-hero-metric-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--color-text);
    letter-spacing: -0.03em;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.ad-hero-metric-value.has-value::before {
    content: '';
    display: inline-block;
    width: 9px;
    height: 9px;
    margin-right: 8px;
    border-radius: 50%;
    background: var(--ad-success);
    vertical-align: middle;
    animation: adPulse 2.2s ease-in-out infinite;
}
.ad-hero-metric-sub {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin-top: 6px;
}

.ad-pace {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}
.ad-pace.positive { background: rgba(34, 197, 94, 0.13); color: #4ade80; }
.ad-pace.warning  { background: rgba(234, 179, 8, 0.14); color: #facc15; }
.ad-pace.negative { background: rgba(239, 68, 68, 0.13); color: #f87171; }
.ad-pace.neutral  { background: var(--color-surface-muted); color: var(--color-text-muted); }

/* Cash/UPI mini breakdown */
.ad-hero-modes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px dashed var(--color-border);
    position: relative;
    z-index: 1;
}
.ad-mode-chip {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--color-border);
    transition: transform 0.25s var(--ad-ease), border-color 0.25s var(--ad-ease);
}
.ad-mode-chip:hover {
    transform: translateY(-2px);
    border-color: rgba(59, 130, 246, 0.4);
}
.ad-mode-icon {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.ad-mode-icon.cash { background: rgba(34, 197, 94, 0.14);  color: #4ade80; }
.ad-mode-icon.upi  { background: rgba(59, 130, 246, 0.14); color: #60a5fa; }
.ad-mode-body { min-width: 0; }
.ad-mode-label { font-size: 0.7rem; color: var(--color-text-muted); font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
.ad-mode-amount { font-size: 1rem; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums; }
.ad-mode-pct { font-size: 0.7rem; color: var(--color-text-muted); }

/* ---------- Stat cards ---------- */
.ad-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}
.ad-stat {
    position: relative;
    padding: 20px;
    border-radius: 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    transition:
        transform 0.25s var(--ad-ease),
        box-shadow 0.25s var(--ad-ease),
        border-color 0.25s var(--ad-ease);
    overflow: hidden;
}
.ad-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 38px -20px rgba(0,0,0,0.55);
    border-color: rgba(59, 130, 246, 0.35);
}
.ad-stat::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--ad-accent), var(--ad-accent-2));
    opacity: 0;
    transition: opacity 0.3s var(--ad-ease);
}
.ad-stat:hover::before { opacity: 1; }

.ad-stat-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.ad-stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}
.ad-stat-value {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--color-text);
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.ad-stat-sub {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    margin-top: 4px;
}
.ad-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
    transition: transform 0.3s var(--ad-ease);
}
.ad-stat:hover .ad-stat-icon { transform: scale(1.1) rotate(-4deg); }
.ad-stat-icon.blue   { background: rgba(59, 130, 246, 0.14); color: #60a5fa; }
.ad-stat-icon.green  { background: rgba(34, 197, 94, 0.14);  color: #4ade80; }
.ad-stat-icon.amber  { background: rgba(234, 179, 8, 0.14);  color: #facc15; }
.ad-stat-icon.red    { background: rgba(239, 68, 68, 0.14);  color: #f87171; }

.ad-change {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    margin-top: 8px;
}
.ad-change.positive { background: rgba(34, 197, 94, 0.13); color: #4ade80; }
.ad-change.negative { background: rgba(239, 68, 68, 0.13); color: #f87171; }

/* Progress bar (for collection rate, cash/upi split) */
.ad-bar {
    width: 100%;
    height: 6px;
    background: var(--color-surface-muted);
    border-radius: 999px;
    overflow: hidden;
    margin-top: 12px;
    position: relative;
}
.ad-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--ad-accent), var(--ad-accent-2));
    border-radius: 999px;
    transition: width 1s var(--ad-ease);
    position: relative;
}
.ad-bar-fill::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,0.35) 45%, transparent 60%);
    background-size: 200% 100%;
    animation: adShimmer 2.6s linear infinite;
}
@media (prefers-reduced-motion: reduce) {
    .ad-bar-fill::after { animation: none; }
}
.ad-bar-fill.green { background: linear-gradient(90deg, #22c55e, #4ade80); }
.ad-bar-fill.amber { background: linear-gradient(90deg, #eab308, #facc15); }
.ad-bar-fill.red   { background: linear-gradient(90deg, #ef4444, #f87171); }

/* ---------- Cards ---------- */
.ad-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 18px;
    overflow: hidden;
    margin-bottom: 22px;
    transition: box-shadow 0.3s var(--ad-ease);
}
.ad-card:hover {
    box-shadow: 0 18px 42px -22px rgba(0,0,0,0.45);
}
.ad-card-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent);
}
.ad-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    color: var(--color-text);
    display: flex;
    align-items: center;
    gap: 10px;
}
.ad-card-title i { color: var(--ad-accent); }
.ad-card-sub {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    margin: 3px 0 0;
}

/* ---------- Live badge ---------- */
.ad-live-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(34, 197, 94, 0.13);
    color: #4ade80;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.ad-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
    animation: adPulse 1.8s ease-in-out infinite;
}
.ad-live-badge.stale {
    background: rgba(234, 179, 8, 0.13);
    color: #facc15;
}
.ad-live-badge.stale .ad-live-dot { background: #eab308; animation: adBlink 1.5s ease-in-out infinite; }

/* ---------- Live activity feed ---------- */
.ad-activity {
    max-height: 340px;
    overflow-y: auto;
}
.ad-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 22px;
    border-bottom: 1px solid var(--color-border);
    transition: background 0.2s var(--ad-ease), transform 0.2s var(--ad-ease);
    animation: adSlideInRight 0.4s var(--ad-ease) both;
}
.ad-activity-item:hover {
    background: var(--color-surface-muted);
    transform: translateX(3px);
}
.ad-activity-item:last-child { border-bottom: none; }
.ad-activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    background: rgba(34, 197, 94, 0.14);
    color: #4ade80;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.ad-activity-body { flex: 1; min-width: 0; }
.ad-activity-line {
    font-size: 0.88rem;
    color: var(--color-text);
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ad-activity-line strong { font-weight: 700; color: #60a5fa; }
.ad-activity-shop {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 2px;
}
.ad-activity-time {
    font-size: 0.72rem;
    color: var(--color-text-subtle, var(--color-text-muted));
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* ---------- Table hover ---------- */
.ad-table tbody tr {
    transition: background 0.2s var(--ad-ease), transform 0.2s var(--ad-ease);
}
.ad-table tbody tr:hover {
    background: var(--color-surface-muted);
    transform: translateX(2px);
}

/* ---------- Rank badge ---------- */
.ad-rank {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.85rem;
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
    border: 1px solid var(--color-border);
    flex-shrink: 0;
}
.ad-rank.gold   { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #fff; border-color: transparent; }
.ad-rank.silver { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: #fff; border-color: transparent; }
.ad-rank.bronze { background: linear-gradient(135deg, #d97706, #92400e); color: #fff; border-color: transparent; }

/* ---------- Needs Attention ---------- */
.ad-alert-panel {
    position: relative;
    border-radius: 18px;
    padding: 22px;
    background:
        radial-gradient(120% 100% at 100% 0%, rgba(234, 179, 8, 0.10), transparent 60%),
        var(--color-surface);
    border: 1px solid rgba(234, 179, 8, 0.30);
    transition: box-shadow 0.3s var(--ad-ease);
    margin-bottom: 22px;
}
.ad-alert-panel:hover {
    box-shadow: 0 16px 40px -22px rgba(234, 179, 8, 0.45);
}
.ad-alert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-top: 16px;
}
.ad-alert-card {
    padding: 14px 16px;
    border-radius: 12px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--color-border);
}
.ad-alert-num {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--ad-warn);
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.ad-alert-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--color-text);
    margin-top: 4px;
}
.ad-alert-note {
    font-size: 0.72rem;
    color: var(--color-text-muted);
    margin-top: 2px;
}

/* ---------- Sparkle ---------- */
.ad-sparkle {
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: radial-gradient(circle, #fbbf24 0%, rgba(251, 191, 36, 0) 70%);
    pointer-events: none;
    animation: adSparkle 0.9s var(--ad-ease) forwards;
}

/* ---------- Skeleton ---------- */
.ad-skel {
    padding: 16px 22px;
    border-bottom: 1px solid var(--color-border);
}
.ad-skel-bar {
    height: 10px;
    background: linear-gradient(90deg, var(--color-surface-muted) 0%, rgba(255,255,255,0.06) 50%, var(--color-surface-muted) 100%);
    background-size: 200% 100%;
    animation: adShimmer 1.6s linear infinite;
    border-radius: 999px;
    margin-bottom: 10px;
}
.ad-skel-bar.short { width: 40%; }

/* ---------- Buttons ---------- */
.ad-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    font-family: inherit;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s var(--ad-ease);
    text-decoration: none;
    position: relative;
    overflow: hidden;
}
.ad-btn-primary {
    background: linear-gradient(135deg, var(--ad-accent), var(--ad-accent-2));
    color: #fff;
    box-shadow: 0 10px 24px -12px rgba(59, 130, 246, 0.7);
}
.ad-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 30px -12px rgba(59, 130, 246, 0.85); }
.ad-btn-primary::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,0.28) 50%, transparent 65%);
    background-size: 200% 100%;
    animation: adShimmer 3.6s linear infinite;
    pointer-events: none;
}
.ad-btn-secondary {
    background: var(--color-surface-muted);
    color: var(--color-text);
    border-color: var(--color-border);
}
.ad-btn-secondary:hover { background: var(--color-surface); border-color: var(--ad-accent); transform: translateY(-1px); }
.ad-btn-warn {
    background: rgba(234, 179, 8, 0.14);
    color: #facc15;
    border-color: rgba(234, 179, 8, 0.35);
}
.ad-btn-warn:hover { background: rgba(234, 179, 8, 0.22); transform: translateY(-1px); }
.ad-btn i { transition: transform 0.25s var(--ad-ease); }
.ad-btn:hover i { transform: scale(1.15); }
</style>

<div class="page adash">

    <!-- ============ HERO ============ -->
    <section class="ad-hero ad-rise" style="--d: 0ms;">
        <div class="ad-hero-head">
            <div>
                <h1 class="ad-hero-title">
                    <i class="fa-solid fa-<?= $greetIcon ?>" aria-hidden="true"></i>
                    <?= $greeting ?>, Admin
                </h1>
                <p class="ad-hero-sub">
                    <i class="fa-solid fa-calendar-day" style="opacity: 0.6;" aria-hidden="true"></i>
                    <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="ad-hero-icon" aria-hidden="true">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
        </div>

        <div class="ad-hero-grid">
            <div>
                <div class="ad-hero-metric-label">Today's Collection</div>
                <div id="heroAmount" class="ad-hero-metric-value <?= $hasCollectionToday ? 'has-value' : '' ?>"
                     data-target="<?= number_format($todayCollection, 2, '.', '') ?>">
                    ₹0.00
                </div>
                <div class="ad-hero-metric-sub">
                    <?php if ($yesterdayCollection > 0): ?>
                        <span class="ad-change <?= $dayChange >= 0 ? 'positive' : 'negative' ?>" style="margin: 0 8px 0 0;">
                            <i class="fa-solid fa-arrow-<?= $dayChange >= 0 ? 'up' : 'down' ?>" aria-hidden="true"></i>
                            <?= $dayChange >= 0 ? '+' : '' ?><?= $dayChange ?>% vs yesterday
                        </span>
                    <?php endif; ?>
                    <?php if ($sevenDayAvg > 0): ?>
                        <span class="ad-pace <?= $paceClass ?>">
                            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                            <?= $paceLabel ?> · <?= $pacePct ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="ad-hero-metric-label">Transactions Today</div>
                <div class="ad-hero-metric-value" data-counter="<?= $todayTransactions ?>">0</div>
                <div class="ad-hero-metric-sub">
                    <i class="fa-solid fa-check-circle" style="color: #4ade80;" aria-hidden="true"></i>
                    <?= $paidTodayTxns ?> paid · <?= $totalTodayTxns - $paidTodayTxns ?> pending
                </div>
            </div>
        </div>

        <div class="ad-hero-modes">
            <div class="ad-mode-chip">
                <div class="ad-mode-icon cash"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i></div>
                <div class="ad-mode-body">
                    <div class="ad-mode-label">Cash</div>
                    <div class="ad-mode-amount">₹<?= number_format($cashToday, 2) ?></div>
                    <div class="ad-mode-pct"><?= $cashPct ?>% of today</div>
                </div>
            </div>
            <div class="ad-mode-chip">
                <div class="ad-mode-icon upi"><i class="fa-solid fa-qrcode" aria-hidden="true"></i></div>
                <div class="ad-mode-body">
                    <div class="ad-mode-label">UPI</div>
                    <div class="ad-mode-amount">₹<?= number_format($upiToday, 2) ?></div>
                    <div class="ad-mode-pct"><?= $upiPct ?>% of today</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ STAT GRID ============ -->
    <div class="ad-stat-grid">

        <div class="ad-stat ad-pop" style="--d: 120ms;">
            <div class="ad-stat-head">
                <div>
                    <div class="ad-stat-label">This Week</div>
                    <div class="ad-stat-value">₹<?= number_format($thisWeekCollection, 2) ?></div>
                    <div class="ad-change <?= $weekChange >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fa-solid fa-arrow-<?= $weekChange >= 0 ? 'up' : 'down' ?>" aria-hidden="true"></i>
                        <?= $weekChange >= 0 ? '+' : '' ?><?= $weekChange ?>% vs last week
                    </div>
                </div>
                <div class="ad-stat-icon blue"><i class="fa-solid fa-calendar-week" aria-hidden="true"></i></div>
            </div>
        </div>

        <div class="ad-stat ad-pop" style="--d: 180ms;">
            <div class="ad-stat-head">
                <div>
                    <div class="ad-stat-label">Online Inspectors</div>
                    <div class="ad-stat-value">
                        <span data-counter="<?= $onlineInspectors ?>">0</span>
                        <span style="font-size: 1rem; color: var(--color-text-muted); font-weight: 600;">/ <?= $totalInspectors ?></span>
                    </div>
                    <div class="ad-stat-sub">Active in last minute</div>
                </div>
                <div class="ad-stat-icon green"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
            </div>
        </div>

        <div class="ad-stat ad-pop" style="--d: 240ms;">
            <div class="ad-stat-head">
                <div style="flex: 1;">
                    <div class="ad-stat-label">Collection Rate</div>
                    <div class="ad-stat-value"><?= $collectionRate ?>%</div>
                    <div class="ad-stat-sub"><?= $paidTodayTxns ?> of <?= $totalTodayTxns ?> paid</div>
                    <div class="ad-bar">
                        <div class="ad-bar-fill <?= $collectionRate >= 80 ? 'green' : ($collectionRate >= 50 ? 'amber' : 'red') ?>"
                             style="width: <?= $collectionRate ?>%;"></div>
                    </div>
                </div>
                <div class="ad-stat-icon <?= $collectionRate >= 80 ? 'green' : ($collectionRate >= 50 ? 'amber' : 'red') ?>">
                    <i class="fa-solid fa-<?= $collectionRate >= 80 ? 'check-circle' : ($collectionRate >= 50 ? 'clock' : 'xmark-circle') ?>" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="ad-stat ad-pop" style="--d: 300ms;">
            <div class="ad-stat-head">
                <div>
                    <div class="ad-stat-label">Seizures Today</div>
                    <div class="ad-stat-value" data-counter="<?= $todaySeizures ?>">0</div>
                    <div class="ad-stat-sub">Total sessions logged</div>
                </div>
                <div class="ad-stat-icon amber"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
            </div>
        </div>

    </div>

    <!-- ============ 7-DAY TREND ============ -->
    <section class="ad-card ad-rise" style="--d: 360ms;">
        <div class="ad-card-head">
            <div>
                <h2 class="ad-card-title">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    7-Day Collection Trend
                </h2>
                <p class="ad-card-sub">Daily paid collections across all inspectors</p>
            </div>
            <?php if ($sevenDayAvg > 0): ?>
                <span class="ad-pace neutral">
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    Avg: ₹<?= number_format($sevenDayAvg, 0) ?>/day
                </span>
            <?php endif; ?>
        </div>
        <div class="ad-card-body" style="padding: 16px 22px 8px;">
            <canvas id="trendChart" height="200" style="width: 100%; max-height: 300px;"></canvas>
        </div>
    </section>

    <!-- ============ TOP INSPECTORS + PAYMENT MODES ============ -->
    <?php if (!empty($topInspectors)): ?>
    <section class="ad-card ad-rise" style="--d: 440ms;">
        <div class="ad-card-head">
            <div>
                <h2 class="ad-card-title">
                    <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                    Top Inspectors This Week
                </h2>
                <p class="ad-card-sub">Ranked by total paid collection</p>
            </div>
            <a href="add_inspector.php" class="ad-btn ad-btn-secondary" style="padding: 7px 14px; font-size: 0.78rem;">
                <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                Manage
            </a>
        </div>
        <div class="table-wrapper">
            <table class="table ad-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Inspector</th>
                        <th style="text-align: right;">Collections</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: right;">Avg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topInspectors as $index => $inspector):
                        $rankClass = $index === 0 ? 'gold' : ($index === 1 ? 'silver' : ($index === 2 ? 'bronze' : ''));
                        $avg = $inspector['count'] > 0 ? ((float) $inspector['total'] / (int) $inspector['count']) : 0;
                    ?>
                    <tr class="ad-rise" style="--d: <?= 500 + ($index * 50) ?>ms;">
                        <td>
                            <span class="ad-rank <?= $rankClass ?>"><?= $index + 1 ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($inspector['full_name'] ?? $inspector['username']) ?></strong>
                            <br><small style="color: var(--color-text-muted);">@<?= htmlspecialchars($inspector['username']) ?></small>
                        </td>
                        <td style="text-align: right;"><?= (int) $inspector['count'] ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--color-success);">
                            ₹<?= number_format((float) $inspector['total'], 2) ?>
                        </td>
                        <td style="text-align: right; color: var(--color-text-muted);">
                            ₹<?= number_format($avg, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ NEEDS ATTENTION ============ -->
    <?php if ($pendingTxns > 0 || $inactiveInspectors > 0 || $underchargeCount > 0): ?>
    <section class="ad-alert-panel ad-rise" style="--d: 520ms;">
        <div style="display: flex; align-items: flex-start; gap: 14px;">
            <div class="ad-stat-icon amber" style="flex-shrink: 0;">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <div style="flex: 1;">
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--color-text); margin: 0 0 4px;">
                    Needs Attention
                </h3>
                <p style="font-size: 0.82rem; color: var(--color-text-muted); margin: 0;">
                    Items that may need follow-up
                </p>

                <div class="ad-alert-grid">
                    <?php if ($pendingTxns > 0): ?>
                    <div class="ad-alert-card">
                        <div class="ad-alert-num"><?= $pendingTxns ?></div>
                        <div class="ad-alert-label">Pending Transactions</div>
                        <div class="ad-alert-note">₹<?= number_format($pendingAmount, 2) ?> awaiting confirmation</div>
                    </div>
                    <?php endif; ?>

                    <?php if ($inactiveInspectors > 0): ?>
                    <div class="ad-alert-card">
                        <div class="ad-alert-num"><?= $inactiveInspectors ?></div>
                        <div class="ad-alert-label">Inactive Inspectors</div>
                        <div class="ad-alert-note">No activity in 24 hours</div>
                    </div>
                    <?php endif; ?>

                    <?php if ($underchargeCount > 0): ?>
                    <div class="ad-alert-card">
                        <div class="ad-alert-num"><?= $underchargeCount ?></div>
                        <div class="ad-alert-label">Undercharges This Month</div>
                        <div class="ad-alert-note">₹<?= number_format($underchargeAmount, 2) ?> below suggested</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if ($pendingTxns > 0): ?>
                    <a href="history.php?status=pending" class="ad-btn ad-btn-secondary" style="font-size: 0.8rem; padding: 8px 14px;">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        View Pending
                    </a>
                    <?php endif; ?>
                    <?php if ($underchargeCount > 0): ?>
                    <a href="undercharge_report.php" class="ad-btn ad-btn-warn" style="font-size: 0.8rem; padding: 8px 14px;">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        View Undercharges
                    </a>
                    <?php endif; ?>
                    <?php if ($inactiveInspectors > 0): ?>
                    <a href="add_inspector.php" class="ad-btn ad-btn-secondary" style="font-size: 0.8rem; padding: 8px 14px;">
                        <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                        Manage Inspectors
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ LIVE ACTIVITY ============ -->
    <section class="ad-card ad-rise" style="--d: 580ms;">
        <div class="ad-card-head">
            <div>
                <h2 class="ad-card-title">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    Live Activity
                </h2>
                <p class="ad-card-sub">Latest paid collections, refreshing every 5s</p>
            </div>
            <span class="ad-live-badge" id="liveBadge">
                <span class="ad-live-dot" aria-hidden="true"></span>
                Live
            </span>
        </div>
        <div class="ad-activity" id="liveActivity">
            <div class="ad-skel"><div class="ad-skel-bar"></div><div class="ad-skel-bar short"></div></div>
            <div class="ad-skel"><div class="ad-skel-bar"></div><div class="ad-skel-bar short"></div></div>
            <div class="ad-skel"><div class="ad-skel-bar"></div><div class="ad-skel-bar short"></div></div>
        </div>
    </section>

    <!-- ============ RECENT COLLECTIONS ============ -->
    <section class="ad-card ad-rise" style="--d: 640ms;">
        <div class="ad-card-head">
            <div>
                <h2 class="ad-card-title">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    Recent Collections
                </h2>
                <p class="ad-card-sub">Latest 10 transactions across all inspectors</p>
            </div>
            <a href="history.php" class="ad-btn ad-btn-secondary" style="padding: 7px 14px; font-size: 0.78rem;">
                View All
            </a>
        </div>

        <div class="table-wrapper">
            <table class="table responsive-table ad-table">
                <thead>
                    <tr>
                        <th>Inspector</th>
                        <th>Shop</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total = 0;
                    if ($result->num_rows > 0):
                        $ri = 0;
                        while ($row = $result->fetch_assoc()):
                            $ri++;
                            $stampClass = $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger');
                            $stampIcon  = $row['status'] === 'paid' ? 'check'   : ($row['status'] === 'pending' ? 'clock'   : 'xmark');
                    ?>
                    <tr class="ad-rise" style="--d: <?= 700 + ($ri * 30) ?>ms;">
                        <td data-label="Inspector">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="avatar avatar-sm" style="background: var(--ad-accent-soft); color: #60a5fa;">
                                    <?= strtoupper(substr(htmlspecialchars($row['username']), 0, 1)) ?>
                                </div>
                                <strong><?= htmlspecialchars($row['username']) ?></strong>
                            </div>
                        </td>
                        <td data-label="Shop"><?= htmlspecialchars($row['shop_name']) ?></td>
                        <td data-label="Amount">
                            <span style="font-weight: 700; color: var(--color-success);">
                                ₹<?= number_format((float) $row['total_amount'], 2) ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <span class="badge badge-<?= $stampClass ?> badge-dot">
                                <i class="fa-solid fa-<?= $stampIcon ?>" aria-hidden="true"></i>
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                        <td data-label="Time"><?= date('d M, H:i', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="transaction_detail.php?id=<?= (int) $row['transaction_id'] ?>" class="table-action-btn">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                View
                            </a>
                        </td>
                    </tr>
                    <?php
                            if ($row['status'] === 'paid') { $total += (float) $row['total_amount']; }
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px; color: var(--color-text-muted);">
                                <i class="fa-solid fa-table-list" style="font-size: 2rem; opacity: 0.4;" aria-hidden="true"></i>
                                <p style="margin: 0;">No transactions found</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > 0): ?>
        <div style="padding: 14px 22px; background: var(--color-surface-muted); border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="font-size: 0.85rem; color: var(--color-text-muted);">Total (paid) across these rows</span>
            <strong style="color: var(--color-success); font-size: 1.1rem;">₹<?= number_format($total, 2) ?></strong>
        </div>
        <?php endif; ?>
    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* ============================================================
   Admin Dashboard — all runtime behavior
   ============================================================ */
(function () {
    'use strict';

    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ============================================================
       1. Animated counters (hero + stat cards)
       ============================================================ */
    function easeOutQuart(t) { return 1 - Math.pow(1 - t, 4); }

    function animateCurrency(el) {
        var target = parseFloat(el.getAttribute('data-target')) || 0;
        if (reduced) {
            el.textContent = '₹' + target.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return;
        }
        var start = null, duration = 1400;
        function tick(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / duration, 1);
            var v = target * easeOutQuart(p);
            el.textContent = '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (p < 1) requestAnimationFrame(tick);
            else {
                el.textContent = '₹' + target.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (target > 0) sparkleBurst(el);
            }
        }
        requestAnimationFrame(tick);
    }

    function animateCount(el) {
        var target = parseInt(el.getAttribute('data-counter'), 10) || 0;
        if (reduced || target === 0) { el.textContent = target; return; }
        var start = null, duration = 900;
        function tick(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / duration, 1);
            el.textContent = Math.floor(target * easeOutQuart(p));
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = target;
        }
        requestAnimationFrame(tick);
    }

    document.querySelectorAll('[data-target]').forEach(animateCurrency);
    document.querySelectorAll('[data-counter]').forEach(animateCount);

    /* ============================================================
       2. Sparkle burst on hero amount
       ============================================================ */
    function sparkleBurst(anchor) {
        if (reduced) return;
        var rect = anchor.getBoundingClientRect();
        var host = anchor.parentElement;
        if (!host) return;
        host.style.position = 'relative';
        for (var i = 0; i < 6; i++) {
            (function (idx) {
                setTimeout(function () {
                    var s = document.createElement('span');
                    s.className = 'ad-sparkle';
                    s.style.left = (rect.width * (0.15 + Math.random() * 0.7)) + 'px';
                    s.style.top  = (rect.height * (0.2  + Math.random() * 0.6)) + 'px';
                    host.appendChild(s);
                    setTimeout(function () { s.remove(); }, 950);
                }, idx * 80);
            })(i);
        }
    }

    /* ============================================================
       3. 7-Day Trend Chart — fixed duplicate const declarations
       ============================================================ */
    (function () {
        var ctx = document.getElementById('trendChart');
        if (!ctx || typeof Chart === 'undefined') return;

        var trendData = <?= json_encode($trendData, JSON_UNESCAPED_UNICODE) ?>;
        var labels = trendData.map(function (d) { return d.day; });
        var amounts = trendData.map(function (d) { return d.total; });
        var counts  = trendData.map(function (d) { return d.count; });

        var hasData = amounts.some(function (a) { return a > 0; });

        if (!hasData) {
            ctx.parentElement.innerHTML =
                '<div style="padding: 48px 24px; text-align: center;">' +
                    '<div style="width: 60px; height: 60px; border-radius: 18px; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: rgba(59,130,246,0.14); color: #60a5fa;">' +
                        '<i class="fa-solid fa-chart-bar" aria-hidden="true"></i>' +
                    '</div>' +
                    '<p style="font-weight: 700; color: var(--color-text); margin: 0 0 4px;">No collection trends yet</p>' +
                    '<p style="color: var(--color-text-muted); font-size: 0.85rem; margin: 0;">Start collecting to see trends here</p>' +
                '</div>';
            return;
        }

        var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.55)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.06)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Collections (₹)',
                    data: amounts,
                    backgroundColor: gradient,
                    hoverBackgroundColor: 'rgba(99, 102, 241, 0.85)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduced ? false : { duration: 900, easing: 'easeOutQuart' },
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#f1f5f9',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 10,
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function (context) {
                                var idx = context.dataIndex;
                                return [
                                    'Amount: ₹' + amounts[idx].toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                                    'Transactions: ' + counts[idx]
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(148, 163, 184, 0.08)', display: false },
                        ticks: { color: '#94a3b8', font: { size: 11, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.08)' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 },
                            callback: function (value) {
                                return '₹' + (value >= 1000 ? (value / 1000).toFixed(1) + 'k' : value);
                            }
                        }
                    }
                }
            }
        });
    })();

    /* ============================================================
       4. Live activity feed — with relative time + stale detection
       ============================================================ */
    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function relativeTime(iso) {
        if (!iso) return '';
        var d = new Date((iso + '').replace(' ', 'T'));
        if (isNaN(d.getTime())) return iso;
        var s = Math.max(0, Math.round((Date.now() - d.getTime()) / 1000));
        if (s < 5)     return 'just now';
        if (s < 60)    return s + 's ago';
        if (s < 3600)  return Math.floor(s / 60) + 'm ago';
        if (s < 86400) return Math.floor(s / 3600) + 'h ago';
        return Math.floor(s / 86400) + 'd ago';
    }

    var liveBadge    = document.getElementById('liveBadge');
    var liveActivity = document.getElementById('liveActivity');
    var liveFailureCount = 0;

    function setLiveStatus(ok) {
        if (!liveBadge) return;
        if (ok) {
            liveBadge.classList.remove('stale');
            liveBadge.innerHTML = '<span class="ad-live-dot" aria-hidden="true"></span> Live';
        } else {
            liveBadge.classList.add('stale');
            liveBadge.innerHTML = '<span class="ad-live-dot" aria-hidden="true"></span> Reconnecting…';
        }
    }

    function loadActivity() {
        fetch('api/live_activity.php', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                liveFailureCount = 0;
                setLiveStatus(true);

                if (!data || !data.length) {
                    liveActivity.innerHTML =
                        '<div style="padding: 48px 24px; text-align: center;">' +
                            '<div style="width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; background: rgba(59,130,246,0.14); color: #60a5fa;">' +
                                '<i class="fa-solid fa-satellite-dish" aria-hidden="true"></i>' +
                            '</div>' +
                            '<p style="font-weight: 700; color: var(--color-text); margin: 0 0 4px;">No recent activity</p>' +
                            '<p style="color: var(--color-text-muted); font-size: 0.85rem; margin: 0;">Collections will appear here in real-time</p>' +
                        '</div>';
                    return;
                }

                var html = '';
                data.forEach(function (item, i) {
                    var user   = escapeHtml(item.username);
                    var amt    = escapeHtml(item.total_amount);
                    var shop   = escapeHtml(item.shop_name);
                    var when   = escapeHtml(relativeTime(item.created_at));
                    html +=
                        '<div class="ad-activity-item" style="animation-delay:' + Math.min(i * 40, 400) + 'ms;">' +
                            '<div class="ad-activity-icon"><i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i></div>' +
                            '<div class="ad-activity-body">' +
                                '<div class="ad-activity-line"><strong>' + user + '</strong> collected ₹' + amt + '</div>' +
                                '<div class="ad-activity-shop">' + shop + '</div>' +
                                '<div class="ad-activity-time"><i class="fa-solid fa-clock" aria-hidden="true"></i> ' + when + '</div>' +
                            '</div>' +
                        '</div>';
                });
                liveActivity.innerHTML = html;
            })
            .catch(function () {
                liveFailureCount++;
                setLiveStatus(false);
                if (liveFailureCount >= 3) {
                    liveActivity.innerHTML =
                        '<div style="padding: 32px 24px; text-align: center; color: var(--color-text-muted);">' +
                            '<i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem; color: #facc15; opacity: 0.7;" aria-hidden="true"></i>' +
                            '<p style="margin: 10px 0 0; font-size: 0.85rem;">Unable to load activity</p>' +
                            '<button type="button" class="ad-btn ad-btn-secondary" style="margin-top: 12px; font-size: 0.78rem; padding: 7px 14px;" onclick="window.__adashRetryActivity && window.__adashRetryActivity()">' +
                                '<i class="fa-solid fa-rotate" aria-hidden="true"></i> Retry' +
                            '</button>' +
                        '</div>';
                }
            });
    }

    window.__adashRetryActivity = function () { liveFailureCount = 0; loadActivity(); };

    loadActivity();
    setInterval(loadActivity, 5000);

})();
</script>

<?php include 'footer.php'; ?>