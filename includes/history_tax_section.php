<?php
/**
 * history_tax_section.php
 * Requires: $conn, $is_admin, hist_render_pagination() from history.php
 */

// --- Pagination (clamped) ---
$limit  = 10;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// --- Filters ---
$date_from    = $_GET['date_from']    ?? '';
$date_to      = $_GET['date_to']      ?? '';
$status       = $_GET['status']       ?? '';
$payment_mode = $_GET['payment_mode'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $date_from = ''; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   { $date_to   = ''; }

// --- Base SQL (fixed: JOIN users so inspector name is available) ---
$base_sql = "FROM transactions t LEFT JOIN users u ON t.inspector_id = u.user_id WHERE 1=1";
$params   = [];
$types    = "";

if (!$is_admin) {
    $base_sql .= " AND t.inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
if ($date_from !== '')          { $base_sql .= " AND DATE(t.created_at) >= ?"; $params[] = $date_from;    $types .= "s"; }
if ($date_to   !== '')          { $base_sql .= " AND DATE(t.created_at) <= ?"; $params[] = $date_to;      $types .= "s"; }
if ($is_admin && $status !== '') { $base_sql .= " AND t.status = ?";           $params[] = $status;       $types .= "s"; }
if ($payment_mode !== '')       { $base_sql .= " AND t.payment_mode = ?";      $params[] = $payment_mode; $types .= "s"; }

// --- Count ---
$count_stmt = $conn->prepare("SELECT COUNT(*) AS total " . $base_sql);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_records = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_pages   = max(1, (int) ceil($total_records / $limit));

if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// --- Sum ---
$sum_stmt = $conn->prepare("SELECT COALESCE(SUM(t.total_amount),0) AS total " . $base_sql);
if (!empty($params)) { $sum_stmt->bind_param($types, ...$params); }
$sum_stmt->execute();
$sum_amount = (float) ($sum_stmt->get_result()->fetch_assoc()['total'] ?? 0);

// --- Data ---
$data_sql    = "SELECT t.*, u.username, u.full_name AS inspector_name " . $base_sql . " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
$data_params = array_merge($params, [$limit, $offset]);
$data_types  = $types . "ii";

$stmt = $conn->prepare($data_sql);
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$result = $stmt->get_result();

// --- Export URL (server-rendered fallback) ---
$exportParams = $_GET;
unset($exportParams['page']);
$exportQuery  = http_build_query($exportParams);
?>

<!-- Summary strip -->
<div class="history-summary">
    <div class="history-stat">
        <div class="history-stat-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></div>
        <div class="history-stat-label">Records</div>
        <div class="history-stat-value hist-counter"
             data-target="<?= $total_records ?>">0</div>
    </div>

    <div class="history-stat">
        <div class="history-stat-icon"><i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i></div>
        <div class="history-stat-label">Total Amount</div>
        <div class="history-stat-value hist-counter-currency"
             data-target="<?= number_format($sum_amount, 2, '.', '') ?>">₹0.00</div>
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
<form id="filterForm" method="GET" class="history-filters">
    <input type="hidden" name="view" value="tax">

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

    <?php if ($is_admin): ?>
    <div class="form-field">
        <label class="form-label" for="status"><?php echo __('status'); ?></label>
        <select name="status" id="status" class="form-select">
            <option value=""><?php echo __('all'); ?></option>
            <option value="paid"    <?= $status === 'paid'    ? 'selected' : '' ?>><?php echo __('paid'); ?></option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?php echo __('pending'); ?></option>
        </select>
    </div>
    <?php endif; ?>

    <div class="form-field">
        <label class="form-label" for="payment_mode"><?php echo __('payment_mode'); ?></label>
        <select name="payment_mode" id="payment_mode" class="form-select">
            <option value=""><?php echo __('all'); ?></option>
            <option value="cash" <?= $payment_mode === 'cash' ? 'selected' : '' ?>><?php echo __('cash'); ?></option>
            <option value="upi"  <?= $payment_mode === 'upi'  ? 'selected' : '' ?>><?php echo __('upi'); ?></option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <?php echo __('btn_apply'); ?>
    </button>

    <div class="history-actions">
        <a href="export_tax_excel.php?<?= htmlspecialchars($exportQuery) ?>"
           id="exportExcelBtn"
           class="history-export-btn history-export-btn-csv">
            <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
            CSV
        </a>
        <a href="export_tax_pdf.php?<?= htmlspecialchars($exportQuery) ?>"
           id="exportPdfBtn"
           class="history-export-btn history-export-btn-pdf">
            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
            PDF
        </a>
    </div>
</form>

<!-- Results table -->
<?php if ($result->num_rows > 0): ?>
    <div class="table-wrapper">
        <table class="table responsive-table hist-table">
            <thead>
                <tr>
                    <th><?php echo __('th_shop'); ?></th>
                    <th><?php echo __('th_amount'); ?></th>
                    <?php if ($is_admin): ?><th><?php echo __('th_status'); ?></th><?php endif; ?>
                    <th><?php echo __('payment_mode'); ?></th>
                    <th><?php echo __('th_time'); ?></th>
                    <th style="width: 90px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 0; while ($row = $result->fetch_assoc()): $i++; ?>
                    <tr class="hist-row" style="--d: <?= min($i * 35, 500) ?>ms;">
                        <td data-label="<?php echo __('th_shop'); ?>">
                            <strong><?= htmlspecialchars($row['shop_name']) ?></strong>
                            <?php if ($is_admin && !empty($row['inspector_name'])): ?>
                                <br><small style="color: var(--color-text-muted);">
                                    <i class="fa-solid fa-user" style="opacity: 0.55;" aria-hidden="true"></i>
                                    <?= htmlspecialchars($row['inspector_name']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo __('th_amount'); ?>">
                            <span style="font-weight: 600; color: var(--color-success);">
                                ₹<?= number_format((float) $row['total_amount'], 2) ?>
                            </span>
                        </td>
                        <?php if ($is_admin): ?>
                        <td data-label="<?php echo __('th_status'); ?>">
                            <?php
                            $statusClass = $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger');
                            $statusIcon  = $row['status'] === 'paid' ? 'check'   : ($row['status'] === 'pending' ? 'clock'   : 'xmark');
                            ?>
                            <span class="badge badge-<?= $statusClass ?> badge-dot">
                                <i class="fa-solid fa-<?= $statusIcon ?>" aria-hidden="true"></i>
                                <?= __($row['status']) ?>
                            </span>
                        </td>
                        <?php endif; ?>
                        <td data-label="<?php echo __('payment_mode'); ?>">
                            <span class="badge badge-<?= $row['payment_mode'] === 'upi' ? 'primary' : 'neutral' ?>">
                                <?= __($row['payment_mode']) ?>
                            </span>
                        </td>
                        <td data-label="<?php echo __('th_time'); ?>">
                            <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                        </td>
                        <td>
                            <a href="payment.php?id=<?= (int) $row['transaction_id'] ?><?= $row['status'] === 'paid' ? '&paid=1' : '' ?>"
                               class="table-action-btn">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                View
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?= hist_render_pagination($page, $total_pages) ?>

<?php else: ?>
    <div class="card">
        <div class="history-empty">
            <div class="history-empty-icon">
                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
            </div>
            <p class="history-empty-title">No records found</p>
            <p class="history-empty-msg">
                Try adjusting your filters or date range.
            </p>
            <a href="history.php?view=tax" class="btn btn-secondary" style="margin-top: 8px;">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                Clear Filters
            </a>
        </div>
    </div>
<?php endif; ?>

<script>
/* Keep export URLs in sync as the user changes filters (nice UX). */
(function () {
    var form     = document.getElementById('filterForm');
    var excelBtn = document.getElementById('exportExcelBtn');
    var pdfBtn   = document.getElementById('exportPdfBtn');
    if (!form || !excelBtn || !pdfBtn) return;

    function updateExportLinks() {
        var params = new URLSearchParams(new FormData(form)).toString();
        excelBtn.href = 'export_tax_excel.php?' + params;
        pdfBtn.href   = 'export_tax_pdf.php?'   + params;
    }

    form.addEventListener('change', updateExportLinks);
})();
</script>