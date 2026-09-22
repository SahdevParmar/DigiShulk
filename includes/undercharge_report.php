<?php
/**
 * DigiShulk — Undercharge Report
 *
 * Admin-only. Surfaces transactions where total_amount < suggested_amount.
 * Optionally exports the current view as CSV via ?export=csv.
 *
 * Depends on Sprint 1 schema (transactions.suggested_amount, rate_per_sqft).
 */

session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: logout.php');
    exit();
}

require_once 'db_connect.php';

/* =========================================================
   FILTERS
   ========================================================= */

$date_from     = isset($_GET['date_from'])     ? trim($_GET['date_from'])     : '';
$date_to       = isset($_GET['date_to'])       ? trim($_GET['date_to'])       : '';
$inspector_id  = isset($_GET['inspector_id'])  ? (int) $_GET['inspector_id']  : 0;
$stall_type    = isset($_GET['stall_type'])    ? trim($_GET['stall_type'])    : '';
$min_diff      = isset($_GET['min_diff'])      ? (float) $_GET['min_diff']    : 0.01;
$sort          = isset($_GET['sort'])          ? $_GET['sort']                : 'diff_desc';
$export        = isset($_GET['export'])        ? $_GET['export']              : '';

// Validate dates.
$dateRe = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($dateRe, $date_from)) { $date_from = ''; }
if (!preg_match($dateRe, $date_to))   { $date_to   = ''; }

// Default: current month.
if ($date_from === '' && $date_to === '') {
    $date_from = date('Y-m-01');
    $date_to   = date('Y-m-d');
}

// Whitelist sort options.
$sortMap = [
    'diff_desc' => 'shortfall DESC, t.created_at DESC',
    'diff_asc'  => 'shortfall ASC, t.created_at DESC',
    'date_desc' => 't.created_at DESC',
    'date_asc'  => 't.created_at ASC',
    'amount_desc' => 't.total_amount DESC',
];
$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : $sortMap['diff_desc'];

/* =========================================================
   BUILD QUERY
   ========================================================= */

$sql = "
    SELECT
        t.transaction_id,
        t.receipt_number,
        t.created_at,
        t.shop_name,
        t.shopkeeper_phone,
        t.stall_type,
        t.area_sqft,
        t.rate_per_sqft,
        t.suggested_amount,
        t.total_amount,
        t.payment_mode,
        t.status,
        (t.suggested_amount - t.total_amount) AS shortfall,
        t.inspector_id,
        u.username        AS inspector_username,
        u.full_name       AS inspector_name
    FROM transactions t
    LEFT JOIN users u ON t.inspector_id = u.user_id
    WHERE t.suggested_amount IS NOT NULL
      AND t.total_amount < t.suggested_amount
      AND (t.suggested_amount - t.total_amount) >= ?
";

$params = [$min_diff];
$types  = 'd';

if ($date_from !== '') {
    $sql .= " AND DATE(t.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $sql .= " AND DATE(t.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}
if ($inspector_id > 0) {
    $sql .= " AND t.inspector_id = ?";
    $params[] = $inspector_id;
    $types .= 'i';
}
if ($stall_type !== '') {
    $sql .= " AND t.stall_type = ?";
    $params[] = $stall_type;
    $types .= 's';
}

$sql .= " ORDER BY " . $orderBy;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Query prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Load into memory so we can both render and export from one pass.
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

/* =========================================================
   SUMMARY STATS
   ========================================================= */

$totalShortfall = 0.0;
$totalSuggested = 0.0;
$totalCharged   = 0.0;
$byInspector    = [];
$byStall        = [];

foreach ($rows as $r) {
    $gap = (float) $r['shortfall'];
    $totalShortfall += $gap;
    $totalSuggested += (float) $r['suggested_amount'];
    $totalCharged   += (float) $r['total_amount'];

    $inspKey = $r['inspector_id'];
    if (!isset($byInspector[$inspKey])) {
        $byInspector[$inspKey] = [
            'name'  => $r['inspector_name'] ?? $r['inspector_username'] ?? ('#' . $inspKey),
            'count' => 0,
            'gap'   => 0.0,
        ];
    }
    $byInspector[$inspKey]['count']++;
    $byInspector[$inspKey]['gap'] += $gap;

    $stallKey = $r['stall_type'] !== '' ? $r['stall_type'] : '—';
    if (!isset($byStall[$stallKey])) {
        $byStall[$stallKey] = ['count' => 0, 'gap' => 0.0];
    }
    $byStall[$stallKey]['count']++;
    $byStall[$stallKey]['gap'] += $gap;
}

// Sort inspectors by total gap descending.
uasort($byInspector, function ($a, $b) {
    if ($a['gap'] === $b['gap']) { return 0; }
    return ($a['gap'] < $b['gap']) ? 1 : -1;
});

// Sort stall types by total gap descending.
uasort($byStall, function ($a, $b) {
    if ($a['gap'] === $b['gap']) { return 0; }
    return ($a['gap'] < $b['gap']) ? 1 : -1;
});

/* =========================================================
   CSV EXPORT
   ========================================================= */

if ($export === 'csv') {
    $filename = 'undercharge_report_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM so Excel opens it correctly.
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Receipt #',
        'Date',
        'Shop',
        'Phone',
        'Stall Type',
        'Area (sqft)',
        'Rate/sqft',
        'Suggested',
        'Charged',
        'Shortfall',
        'Payment Mode',
        'Status',
        'Inspector',
    ]);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['receipt_number'],
            date('Y-m-d H:i', strtotime($r['created_at'])),
            $r['shop_name'],
            $r['shopkeeper_phone'],
            $r['stall_type'],
            $r['area_sqft'],
            $r['rate_per_sqft'],
            number_format((float) $r['suggested_amount'], 2, '.', ''),
            number_format((float) $r['total_amount'], 2, '.', ''),
            number_format((float) $r['shortfall'], 2, '.', ''),
            $r['payment_mode'],
            $r['status'],
            $r['inspector_name'] ?? $r['inspector_username'] ?? '',
        ]);
    }

    fclose($out);
    exit();
}

/* =========================================================
   INSPECTOR LIST FOR FILTER DROPDOWN
   ========================================================= */

$inspectors = [];
$res = $conn->query(
    "SELECT user_id, username, full_name FROM users WHERE role = 'inspector' ORDER BY username ASC"
);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $inspectors[] = $r;
    }
}

/* =========================================================
   STALL TYPES FOR FILTER DROPDOWN
   ========================================================= */

$stallTypes = [];
$res = $conn->query(
    "SELECT DISTINCT stall_type FROM transactions
     WHERE suggested_amount IS NOT NULL
       AND total_amount < suggested_amount
       AND stall_type IS NOT NULL
       AND stall_type <> ''
     ORDER BY stall_type ASC"
);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $stallTypes[] = $r['stall_type'];
    }
}

// Track rows with no suggestion — for a small notice.
$noSuggestionCount = 0;
$res = $conn->query(
    "SELECT COUNT(*) AS c FROM transactions
     WHERE suggested_amount IS NULL
       AND DATE(created_at) BETWEEN '" . $conn->real_escape_string($date_from) . "'
                                AND '" . $conn->real_escape_string($date_to) . "'"
);
if ($res && ($r = $res->fetch_assoc())) {
    $noSuggestionCount = (int) $r['c'];
}

include 'header.php';
?>

<div class="page">

    <!-- Header -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0;">Undercharge Report</h1>
            <p class="subtitle" style="margin-top: var(--space-1);">
                Transactions charged below the rates table · <?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?>
            </p>
        </div>
        <div style="display: flex; gap: var(--space-2);">
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"
               class="btn btn-secondary">
                <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                Export CSV
            </a>
            <a href="admin_dashboard.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Dashboard
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-body">
            <form method="GET" action="" style="display: grid; gap: var(--space-4);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--space-4);">

                    <div class="form-field">
                        <label class="form-label" for="date_from">From</label>
                        <input type="date" id="date_from" name="date_from" class="form-input"
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="date_to">To</label>
                        <input type="date" id="date_to" name="date_to" class="form-input"
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="inspector_id">Inspector</label>
                        <select id="inspector_id" name="inspector_id" class="form-select">
                            <option value="0">— All —</option>
                            <?php foreach ($inspectors as $insp): ?>
                                <option value="<?= (int) $insp['user_id'] ?>"
                                    <?= $inspector_id === (int) $insp['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($insp['full_name'] ?: $insp['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="stall_type">Stall Type</label>
                        <select id="stall_type" name="stall_type" class="form-select">
                            <option value="">— All —</option>
                            <?php foreach ($stallTypes as $st): ?>
                                <option value="<?= htmlspecialchars($st) ?>"
                                    <?= $stall_type === $st ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="min_diff">Min. shortfall (₹)</label>
                        <input type="number" id="min_diff" name="min_diff" class="form-input"
                               step="0.01" min="0" value="<?= htmlspecialchars((string) $min_diff) ?>">
                        <p class="form-help">Hide gaps smaller than this</p>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="sort">Sort by</label>
                        <select id="sort" name="sort" class="form-select">
                            <option value="diff_desc"   <?= $sort === 'diff_desc'   ? 'selected' : '' ?>>Shortfall (high → low)</option>
                            <option value="diff_asc"    <?= $sort === 'diff_asc'    ? 'selected' : '' ?>>Shortfall (low → high)</option>
                            <option value="date_desc"   <?= $sort === 'date_desc'   ? 'selected' : '' ?>>Newest first</option>
                            <option value="date_asc"    <?= $sort === 'date_asc'    ? 'selected' : '' ?>>Oldest first</option>
                            <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>Charged amount (high → low)</option>
                        </select>
                    </div>

                </div>

                <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        Apply Filters
                    </button>
                    <a href="undercharge_report.php" class="btn btn-ghost">
                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stat Cards -->
    <div class="stat-grid" style="margin-bottom: var(--space-6);">

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Total Shortfall</div>
                    <div class="stat-value" style="color: var(--color-danger);">
                        ₹<?= number_format($totalShortfall, 2) ?>
                    </div>
                    <div style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 2px;">
                        Across <?= count($rows) ?> transaction<?= count($rows) === 1 ? '' : 's' ?>
                    </div>
                </div>
                <div class="stat-icon stat-icon-danger">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Total Suggested</div>
                    <div class="stat-value">₹<?= number_format($totalSuggested, 2) ?></div>
                    <div style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 2px;">
                        Per rates table
                    </div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Actually Charged</div>
                    <div class="stat-value" style="color: var(--color-success);">₹<?= number_format($totalCharged, 2) ?></div>
                    <div style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 2px;">
                        <?php
                        $recoveryPct = $totalSuggested > 0
                            ? round(($totalCharged / $totalSuggested) * 100, 1)
                            : 0;
                        ?>
                        <?= $recoveryPct ?>% recovery rate
                    </div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Unique Inspectors</div>
                    <div class="stat-value"><?= count($byInspector) ?></div>
                    <div style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 2px;">
                        With at least one undercharge
                    </div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </div>
            </div>
        </div>

    </div>

    <?php if ($noSuggestionCount > 0): ?>
    <div class="alert alert-info" style="margin-bottom: var(--space-6);">
        <i class="fa-solid fa-circle-info alert-icon" aria-hidden="true"></i>
        <div class="alert-content">
            <p class="alert-message" style="margin:0;">
                <?= $noSuggestionCount ?> transaction<?= $noSuggestionCount === 1 ? '' : 's' ?>
                in this date range have no suggested amount
                (stall type not in the <code>rates</code> table). Those are excluded from this report.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- By Inspector -->
    <?php if (!empty($byInspector)): ?>
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 class="card-title" style="font-size: var(--text-xl);">Shortfall by Inspector</h2>
            <p class="card-subtitle">Sorted by total gap</p>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Inspector</th>
                            <th style="text-align:right;">Undercharges</th>
                            <th style="text-align:right;">Total Shortfall</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byInspector as $insp): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($insp['name']) ?></strong></td>
                            <td style="text-align:right;"><?= (int) $insp['count'] ?></td>
                            <td style="text-align:right; font-weight: 600; color: var(--color-danger);">
                                ₹<?= number_format($insp['gap'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- By Stall Type -->
    <?php if (!empty($byStall)): ?>
    <div class="card" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h2 class="card-title" style="font-size: var(--text-xl);">Shortfall by Stall Type</h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Stall Type</th>
                            <th style="text-align:right;">Undercharges</th>
                            <th style="text-align:right;">Total Shortfall</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byStall as $st => $info): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($st) ?></strong></td>
                            <td style="text-align:right;"><?= (int) $info['count'] ?></td>
                            <td style="text-align:right; font-weight: 600; color: var(--color-danger);">
                                ₹<?= number_format($info['gap'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transaction Detail Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title" style="font-size: var(--text-xl);">All Undercharges</h2>
            <p class="card-subtitle"><?= count($rows) ?> transaction<?= count($rows) === 1 ? '' : 's' ?> matching the current filters</p>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table responsive-table">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Date</th>
                            <th>Shop</th>
                            <th>Stall</th>
                            <th style="text-align:right;">Size</th>
                            <th style="text-align:right;">Suggested</th>
                            <th style="text-align:right;">Charged</th>
                            <th style="text-align:right;">Gap</th>
                            <th>Inspector</th>
                            <th style="width:100px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: var(--space-8);">
                                <div class="empty-state" style="margin: 0; border: none; border-radius: 0; background: transparent;">
                                    <div class="empty-state-icon"><i class="fa-solid fa-check-circle" aria-hidden="true"></i></div>
                                    <p class="empty-state-title">No undercharges found</p>
                                    <p class="empty-state-message">Every transaction in this period was charged at or above the suggested amount.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="Receipt">
                                <span style="font-family: var(--font-mono); font-size: var(--text-xs);">
                                    <?= htmlspecialchars($r['receipt_number']) ?>
                                </span>
                            </td>
                            <td data-label="Date"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td data-label="Shop"><strong><?= htmlspecialchars($r['shop_name']) ?></strong></td>
                            <td data-label="Stall"><?= htmlspecialchars($r['stall_type']) ?></td>
                            <td data-label="Size" style="text-align:right;"><?= number_format((float) $r['area_sqft'], 2) ?></td>
                            <td data-label="Suggested" style="text-align:right;">
                                ₹<?= number_format((float) $r['suggested_amount'], 2) ?>
                                <div style="font-size: var(--text-xs); color: var(--color-text-muted);">
                                    @ ₹<?= number_format((float) $r['rate_per_sqft'], 2) ?>/sqft
                                </div>
                            </td>
                            <td data-label="Charged" style="text-align:right;">
                                <strong style="color: var(--color-success);">₹<?= number_format((float) $r['total_amount'], 2) ?></strong>
                            </td>
                            <td data-label="Gap" style="text-align:right;">
                                <span style="font-weight: 700; color: var(--color-danger);">
                                    −₹<?= number_format((float) $r['shortfall'], 2) ?>
                                </span>
                            </td>
                            <td data-label="Inspector">
                                <?= htmlspecialchars($r['inspector_name'] ?: $r['inspector_username'] ?: '—') ?>
                            </td>
                            <td>
                                <a href="transaction_detail.php?id=<?= (int) $r['transaction_id'] ?>" class="table-action-btn">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>