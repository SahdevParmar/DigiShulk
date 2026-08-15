<?php
// --- Pagination Logic ---
$limit = 10; // Records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Filter Logic ---
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$payment_mode = $_GET['payment_mode'] ?? '';

$base_sql = "FROM transactions WHERE 1=1";
$params = [];
$types = "";

if(!$is_admin){
    $base_sql .= " AND inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
if(!empty($date_from)){ $base_sql .= " AND date(created_at)>= ?"; $params[]=$date_from; $types.="s"; }
if(!empty($date_to)){ $base_sql .= " AND date(created_at)<=?"; $params[]=$date_to; $types.="s"; }
if($is_admin && !empty($status)){ $base_sql.=" AND status=?"; $params[]=$status; $types.="s"; }
if(!empty($payment_mode)){ $base_sql.=" AND payment_mode=?"; $params[]=$payment_mode; $types.="s"; }

// --- Get Total Records for Pagination ---
$count_stmt = $conn->prepare("SELECT COUNT(*) as total " . $base_sql);
if(!empty($params)){ $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// --- Get Records for Current Page ---
$data_sql = "SELECT * " . $base_sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$data_params = array_merge($params, [$limit, $offset]);
$data_types = $types . "ii";

$stmt = $conn->prepare($data_sql);
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Filters & Toolbar -->
<div class="table-toolbar">
    <form id="filterForm" method="GET" class="table-toolbar-filters" style="flex: 1; min-width: 0;">
        <input type="hidden" name="view" value="tax">

        <div class="form-field" style="margin-bottom: 0;">
            <label class="form-label" for="date_from"><?php echo __('from'); ?></label>
            <input type="date" name="date_from" id="date_from" class="form-input" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>

        <div class="form-field" style="margin-bottom: 0;">
            <label class="form-label" for="date_to"><?php echo __('to'); ?></label>
            <input type="date" name="date_to" id="date_to" class="form-input" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>

        <?php if($is_admin): ?>
        <div class="form-field" style="margin-bottom: 0;">
            <label class="form-label" for="status"><?php echo __('status'); ?></label>
            <select name="status" id="status" class="form-select">
                <option value=""><?php echo __('all'); ?></option>
                <option value="paid" <?php if($status=='paid') echo 'selected'; ?>><?php echo __('paid'); ?></option>
                <option value="pending" <?php if($status=='pending') echo 'selected'; ?>><?php echo __('pending'); ?></option>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-field" style="margin-bottom: 0;">
            <label class="form-label" for="payment_mode"><?php echo __('payment_mode'); ?></label>
            <select name="payment_mode" id="payment_mode" class="form-select">
                <option value=""><?php echo __('all'); ?></option>
                <option value="cash" <?php if($payment_mode=='cash') echo 'selected';?>><?php echo __('cash'); ?></option>
                <option value="upi" <?php if($payment_mode=='upi') echo 'selected';?>><?php echo __('upi'); ?></option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="height: fit-content; margin-top: auto;"><?php echo __('btn_apply'); ?></button>
    </form>

    <div class="table-toolbar-actions">
        <a href="#" id="exportExcelBtn" class="btn btn-success">
            <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
            Export to Excel
        </a>
        <a href="#" id="exportPdfBtn" class="btn btn-danger">
            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
            Export to PDF
        </a>
    </div>
</div>

<!-- Results Table -->
<div class="card" style="border: none; box-shadow: none; background: transparent;">
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table responsive-table">
                <thead>
                    <tr>
                        <th><?php echo __('th_shop'); ?></th>
                        <th><?php echo __('th_amount'); ?></th>
                        <?php if($is_admin): ?><th><?php echo __('th_status'); ?></th><?php endif; ?>
                        <th><?php echo __('payment_mode'); ?></th>
                        <th><?php echo __('th_time'); ?></th>
                        <th style="width: 80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="<?php echo __('th_shop'); ?>">
                                    <strong><?php echo htmlspecialchars($row['shop_name']); ?></strong>
                                    <?php if ($is_admin): ?>
                                        <br><small style="color: var(--color-text-muted);"><?php echo htmlspecialchars($row['username'] ?? ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo __('th_amount'); ?>">
                                    <span style="font-weight: 600; color: var(--color-success);">₹<?php echo number_format($row['total_amount'],2); ?></span>
                                </td>
                                <?php if($is_admin): ?>
                                <td data-label="<?php echo __('th_status'); ?>">
                                    <?php
                                    $statusClass = $row['status']=='paid' ? 'success' : ($row['status']=='pending' ? 'warning' : 'danger');
                                    $statusIcon = $row['status']=='paid' ? 'check' : ($row['status']=='pending' ? 'clock' : 'xmark');
                                    ?>
                                    <span class="badge badge-<?= $statusClass ?> badge-dot">
                                        <i class="fa-solid fa-<?= $statusIcon ?>" aria-hidden="true"></i>
                                        <?php echo __($row['status']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td data-label="<?php echo __('payment_mode'); ?>">
                                    <span class="badge badge-<?= $row['payment_mode'] === 'upi' ? 'primary' : 'neutral' ?>">
                                        <?php echo __($row['payment_mode']); ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo __('th_time'); ?>"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="payment.php?id=<?php echo $row['transaction_id']; ?>" class="table-action-btn" style="padding: var(--space-1) var(--space-2); font-size: var(--text-xs);">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $is_admin ? 6 : 5; ?>" style="text-align: center; padding: var(--space-12);">
                                <div class="empty-state" style="margin: 0; border: none; border-radius: 0; padding: var(--space-8); background: transparent;">
                                    <div class="empty-state-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                    </div>
                                    <p class="empty-state-title" style="font-size: var(--text-base);">No records found</p>
                                    <p class="empty-state-message" style="font-size: var(--text-sm);">Try adjusting your filters or date range</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination Controls -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php
    $queryParams = $_GET;
    // Previous button
    if ($page > 1) {
        $queryParams['page'] = $page - 1;
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link" aria-label="Previous page">&laquo; Previous</a>';
    }

    // Page number links
    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);

    if ($start > 1) {
        $queryParams['page'] = 1;
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link">1</a>';
        if ($start > 2) {
            echo '<span class="pagination-ellipsis" aria-hidden="true">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $queryParams['page'] = $i;
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link ' . $activeClass . '"' . ($i == $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            echo '<span class="pagination-ellipsis" aria-hidden="true">...</span>';
        }
        $queryParams['page'] = $total_pages;
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link">' . $total_pages . '</a>';
    }

    // Next button
    if ($page < $total_pages) {
        $queryParams['page'] = $page + 1;
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link" aria-label="Next page">Next &raquo;</a>';
    }
    ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const excelBtn = document.getElementById('exportExcelBtn');
    const pdfBtn = document.getElementById('exportPdfBtn');

    function updateExportLinks() {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        excelBtn.href = 'export_tax_excel.php?' + params;
        pdfBtn.href = 'export_tax_pdf.php?' + params;
    }

    // Update on page load
    updateExportLinks();

    // Update when any filter changes
    form.addEventListener('change', updateExportLinks);
    form.addEventListener('submit', updateExportLinks);
});
</script>