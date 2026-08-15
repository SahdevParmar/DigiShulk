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
<div class="card" style="max-width: 100%; margin-bottom: 25px;">
    <h2><?php echo __('filters_title'); ?></h2>
    <form id="filterForm" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="view" value="tax">
        <div>
            <label><?php echo __('from'); ?></label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div>
            <label><?php echo __('to'); ?></label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <?php if($is_admin): ?>
        <div>
            <label><?php echo __('status'); ?></label>
            <select name="status">
                <option value=""><?php echo __('all'); ?></option>
                <option value="paid" <?php if($status=='paid') echo 'selected'; ?>><?php echo __('paid'); ?></option>
                <option value="pending" <?php if($status=='pending') echo 'selected'; ?>><?php echo __('pending'); ?></option>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label><?php echo __('payment_mode'); ?></label>
            <select name="payment_mode">
                <option value=""><?php echo __('all'); ?></option>
                <option value="cash" <?php if($payment_mode=='cash') echo 'selected';?>><?php echo __('cash'); ?></option>
                <option value="upi" <?php if($payment_mode=='upi') echo 'selected';?>><?php echo __('upi'); ?></option>
            </select>
        </div>
        <button type="submit"><?php echo __('btn_apply'); ?></button>
    </form>
    <hr style="margin: 20px 0;">
    <div class="export-buttons">
        <a href="#" id="exportExcelBtn" class="view-btn" style="background: #107c41; color: white;"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export to Excel</a>
        <a href="#" id="exportPdfBtn" class="view-btn" style="background: #ef4444; color: white;"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export to PDF</a>
    </div>
</div>

<div class="table-card responsive-table-container">
    <table class="modern-table responsive-table">
        <thead>
            <tr>
                <th><?php echo __('th_shop'); ?></th>
                <th><?php echo __('th_amount'); ?></th>
                <?php if($is_admin): ?><th><?php echo __('th_status'); ?></th><?php endif; ?>
                <th><?php echo __('payment_mode'); ?></th>
                <th><?php echo __('th_time'); ?></th>
                <th></th> <!-- Action button column -->
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td data-label="<?php echo __('th_shop'); ?>"><?php echo htmlspecialchars($row['shop_name']); ?></td>
                    <td data-label="<?php echo __('th_amount'); ?>" class="amount">₹<?php echo number_format($row['total_amount'],2); ?></td>
                    <?php if($is_admin): ?>
                    <td data-label="<?php echo __('th_status'); ?>"><span class="stamp <?php echo $row['status']=='paid'?'stamp-paid':'stamp-pending'; ?>"><?php echo __($row['status']); ?></span></td>
                    <?php endif; ?>
                    <td data-label="<?php echo __('payment_mode'); ?>"><?php echo __($row['payment_mode']); ?></td>
                    <td data-label="<?php echo __('th_time'); ?>"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                    <td><a href="payment.php?id=<?php echo $row['transaction_id']; ?>" class="view-btn">View</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Controls -->
<div class="pagination">
    <?php
    $queryParams = $_GET;
    // Previous button
    if ($page > 1) {
        $queryParams['page'] = $page - 1;
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link">&laquo; Previous</a>';
    }

    // Page number links
    for ($i = 1; $i <= $total_pages; $i++) {
        $queryParams['page'] = $i;
        $activeClass = ($i == $page) ? 'active' : '';
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link ' . $activeClass . '">' . $i . '</a>';
    }

    // Next button
    if ($page < $total_pages) {
        $queryParams['page'] = $page + 1;
        echo '<a href="?' . http_build_query($queryParams) . '" class="pagination-link">Next &raquo;</a>';
    }
    ?>
</div>


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
