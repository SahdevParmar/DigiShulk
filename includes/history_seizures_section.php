<?php
// --- Pagination Logic ---
$limit = 5; // Sessions per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Filter Logic ---
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$zone_filter = $_GET['zone_filter'] ?? '';

$base_sql = "FROM seizure_sessions s JOIN users u ON s.inspector_id = u.user_id WHERE 1=1";
$params = [];
$types = "";

if(!$is_admin){
    $base_sql .= " AND s.inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
if(!empty($date_from)){ $base_sql .= " AND s.seizure_date >= ?"; $params[] = $date_from; $types .= "s"; }
if(!empty($date_to)){ $base_sql .= " AND s.seizure_date <= ?"; $params[] = $date_to; $types .= "s"; }
if(!empty($zone_filter)){ $base_sql .= " AND s.zone = ?"; $params[] = $zone_filter; $types .= "s"; }

// --- Get Total Records for Pagination ---
$count_stmt = $conn->prepare("SELECT COUNT(*) as total " . $base_sql);
if(!empty($params)){ $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);


// --- Get Records for Current Page ---
$data_sql = "SELECT s.*, u.full_name as inspector_name " . $base_sql . " ORDER BY s.seizure_date DESC, s.session_id DESC LIMIT ? OFFSET ?";
$data_params = array_merge($params, [$limit, $offset]);
$data_types = $types . "ii";

$stmt = $conn->prepare($data_sql);
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$sessions = $stmt->get_result();
?>

<div class="card" style="max-width: 100%; margin-bottom: 25px;">
    <h2><?php echo __('filters_title'); ?></h2>
    <form id="seizureFilterForm" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="view" value="seizures">
        <div>
            <label><?php echo __('from'); ?></label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div>
            <label><?php echo __('to'); ?></label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <div>
            <label><?php echo __('zone'); ?></label>
            <input type="text" name="zone_filter" placeholder="e.g. Central" value="<?php echo htmlspecialchars($zone_filter); ?>">
        </div>
        <button type="submit"><?php echo __('btn_apply'); ?></button>
    </form>
    <hr style="margin: 20px 0;">
    <div class="export-buttons">
        <a href="#" id="exportSeizureExcelBtn" class="view-btn" style="background: #107c41; color: white;"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export to Excel</a>
        <a href="#" id="exportSeizurePdfBtn" class="view-btn" style="background: #ef4444; color: white;"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export to PDF</a>
    </div>
</div>

<?php while($session = $sessions->fetch_assoc()): ?>
    <div class="card responsive-table-container" style="max-width:100%; margin-bottom:20px;">
        <h3>
            <?php echo htmlspecialchars($session['team_leader_name']); ?>
            — Zone <?php echo htmlspecialchars($session['zone']); ?>
            — Team <?php echo htmlspecialchars($session['team_number']); ?>
        </h3>
        <p style="color:var(--muted);">
            Inspector: <?php echo htmlspecialchars($session['inspector_name'] ?? $session['username']); ?> |
            Date: <?php echo $session['seizure_date']; ?>
        </p>
        <?php
        $items_sql = "SELECT * FROM seizure_items WHERE session_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $session['session_id']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        ?>
        <table class="modern-table responsive-table" style="margin-top:10px;">
            <thead>
                <tr><th>Item</th><th>Qty</th><th>Owner</th><th>Location</th><th>Godown No.</th></tr>
            </thead>
            <tbody>
                <?php while($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td data-label="Item"><?php echo htmlspecialchars($item['item_details']); ?></td>
                    <td data-label="Qty"><?php echo $item['quantity']; ?></td>
                    <td data-label="Owner"><?php echo htmlspecialchars($item['owner_merchant_name']); ?></td>
                    <td data-label="Location"><?php echo htmlspecialchars($item['seizure_location']); ?></td>
                    <td data-label="Godown No."><?php echo htmlspecialchars($item['godown_register_no']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php endwhile; ?>

<?php if($sessions->num_rows === 0): ?>
    <p style="text-align:center; color:var(--muted); padding:20px;">No seizure records match these filters.</p>
<?php endif; ?>

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
    const form = document.getElementById('seizureFilterForm');
    const excelBtn = document.getElementById('exportSeizureExcelBtn');
    const pdfBtn = document.getElementById('exportSeizurePdfBtn');

    function updateExportLinks() {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        excelBtn.href = 'export_seizures_excel.php?' + params;
        pdfBtn.href = 'export_seizures_pdf.php?' + params;
    }

    updateExportLinks();
    form.addEventListener('change', updateExportLinks);
    form.addEventListener('submit', updateExportLinks);
});
</script>
