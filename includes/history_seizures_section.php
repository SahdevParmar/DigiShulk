<?php
/**
 * history_seizures_section.php
 * Requires: $conn, $is_admin, hist_render_pagination() from history.php
 */

// --- Pagination (clamped) ---
$limit  = 5;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// --- Filters ---
$date_from   = $_GET['date_from']   ?? '';
$date_to     = $_GET['date_to']     ?? '';
$zone_filter = $_GET['zone_filter'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $date_from = ''; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   { $date_to   = ''; }

// --- Base SQL ---
$base_sql = "FROM seizure_sessions s LEFT JOIN users u ON s.inspector_id = u.user_id WHERE 1=1";
$params   = [];
$types    = "";

if (!$is_admin) {
    $base_sql .= " AND s.inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
if ($date_from !== '')   { $base_sql .= " AND s.seizure_date >= ?"; $params[] = $date_from;   $types .= "s"; }
if ($date_to   !== '')   { $base_sql .= " AND s.seizure_date <= ?"; $params[] = $date_to;     $types .= "s"; }
if ($zone_filter !== '') { $base_sql .= " AND s.zone = ?";          $params[] = $zone_filter; $types .= "s"; }

// --- Count ---
$count_stmt = $conn->prepare("SELECT COUNT(*) AS total " . $base_sql);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_records = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_pages   = max(1, (int) ceil($total_records / $limit));

if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// --- Data ---
$data_sql    = "SELECT s.*, u.full_name AS inspector_name, u.username AS inspector_username "
             . $base_sql
             . " ORDER BY s.seizure_date DESC, s.session_id DESC LIMIT ? OFFSET ?";
$data_params = array_merge($params, [$limit, $offset]);
$data_types  = $types . "ii";

$stmt = $conn->prepare($data_sql);
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$sessions = $stmt->get_result();

// --- Export URL (server-rendered) ---
$exportParams = $_GET;
unset($exportParams['page']);
$exportQuery = http_build_query($exportParams);
?>

<!-- Summary strip -->
<div class="history-summary">
    <div class="history-stat">
        <div class="history-stat-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
        <div class="history-stat-label">Sessions</div>
        <div class="history-stat-value hist-counter"
             data-target="<?= $total_records ?>">0</div>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="history-stat">
        <div class="history-stat-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></div>
        <div class="history-stat-label">Page</div>
        <div class="history-stat-value">
            <?= $page ?> <span style="color: var(--color-text-muted); font-weight: 500;">/ <?= $total_pages ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter bar -->
<form id="seizureFilterForm" method="GET" class="history-filters">
    <input type="hidden" name="view" value="seizures">

    <div class="form-field">
        <label class="form-label" for="date_from"><?php echo __('from'); ?></label>
        <input type="date" name="date_from" id="date_from" class="form-input"
               max="<?= date('Y-m-d') ?>"
               value="<?= htmlspecialchars($date_from) ?>">
    </div>

    <div class="form-field">
        <label class="form-label" for="date_to"><?php echo __('to'); ?></label>
        <input type="date" name="date_to" id="date_to" class="form-input"
               max="<?= date('Y-m-d') ?>"
               value="<?= htmlspecialchars($date_to) ?>">
    </div>

    <div class="form-field">
        <label class="form-label" for="zone_filter"><?php echo __('zone'); ?></label>
        <input type="text" name="zone_filter" id="zone_filter" class="form-input"
               placeholder="e.g. Central"
               value="<?= htmlspecialchars($zone_filter) ?>">
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <?php echo __('btn_apply'); ?>
    </button>

    <div class="history-actions">
        <a href="export_seizures_excel.php?<?= htmlspecialchars($exportQuery) ?>"
           id="exportSeizureExcelBtn"
           class="history-export-btn history-export-btn-csv">
            <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
            CSV
        </a>
        <a href="export_seizures_pdf.php?<?= htmlspecialchars($exportQuery) ?>"
           id="exportSeizurePdfBtn"
           class="history-export-btn history-export-btn-pdf">
            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
            PDF
        </a>
    </div>
</form>

<!-- Results -->
<?php if ($sessions->num_rows > 0): ?>
    <?php $si = 0; while ($session = $sessions->fetch_assoc()): $si++; ?>
        <?php
        // Fetch items for this session
        $items_stmt = $conn->prepare("SELECT * FROM seizure_items WHERE session_id = ?");
        $items_stmt->bind_param('i', $session['session_id']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $item_count   = $items_result->num_rows;
        ?>
        <div class="seizure-session hist-rise" style="--d: <?= min($si * 60, 400) ?>ms;">
            <div class="seizure-head">
                <div>
                    <h3 class="seizure-title">
                        <?= htmlspecialchars($session['team_leader_name']) ?>
                    </h3>
                    <div class="seizure-meta">
                        <span><i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i> Zone <?= htmlspecialchars($session['zone']) ?></span>
                        <span><i class="fa-solid fa-hashtag" aria-hidden="true"></i> Team <?= htmlspecialchars($session['team_number']) ?></span>
                        <span>
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <?= htmlspecialchars($session['inspector_name'] ?? $session['inspector_username'] ?? '—') ?>
                        </span>
                        <span><i class="fa-solid fa-calendar" aria-hidden="true"></i> <?= htmlspecialchars($session['seizure_date']) ?></span>
                    </div>
                </div>
                <span class="seizure-item-count">
                    <?= $item_count ?> item<?= $item_count === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if ($item_count > 0): ?>
                <div class="table-wrapper">
                    <table class="table responsive-table hist-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Owner</th>
                                <th>Location</th>
                                <th>Godown No.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ri = 0; while ($item = $items_result->fetch_assoc()): $ri++; ?>
                            <tr class="hist-row" style="--d: <?= min(($si * 60) + ($ri * 25), 600) ?>ms;">
                                <td data-label="Item"><?= htmlspecialchars($item['item_details']) ?></td>
                                <td data-label="Qty"><?= (int) $item['quantity'] ?></td>
                                <td data-label="Owner"><?= htmlspecialchars($item['owner_merchant_name'] ?? '') ?></td>
                                <td data-label="Location"><?= htmlspecialchars($item['seizure_location'] ?? '') ?></td>
                                <td data-label="Godown No."><?= htmlspecialchars($item['godown_register_no'] ?? '') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding: 22px; text-align: center; color: var(--color-text-muted); font-size: 0.85rem;">
                    <i class="fa-solid fa-inbox" style="opacity: 0.5;" aria-hidden="true"></i>
                    No items recorded for this session
                </div>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>

    <?= hist_render_pagination($page, $total_pages) ?>

<?php else: ?>
    <div class="card">
        <div class="history-empty">
            <div class="history-empty-icon">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <p class="history-empty-title">No seizure records found</p>
            <p class="history-empty-msg">
                Try adjusting your filters or date range.
            </p>
            <a href="history.php?view=seizures" class="btn btn-secondary" style="margin-top: 8px;">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                Clear Filters
            </a>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    var form     = document.getElementById('seizureFilterForm');
    var excelBtn = document.getElementById('exportSeizureExcelBtn');
    var pdfBtn   = document.getElementById('exportSeizurePdfBtn');
    if (!form || !excelBtn || !pdfBtn) return;

    function updateExportLinks() {
        var params = new URLSearchParams(new FormData(form)).toString();
        excelBtn.href = 'export_seizures_excel.php?' + params;
        pdfBtn.href   = 'export_seizures_pdf.php?'   + params;
    }

    form.addEventListener('change', updateExportLinks);
})();
</script>