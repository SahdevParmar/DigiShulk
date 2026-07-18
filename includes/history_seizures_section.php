<?php
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$zone_filter = $_GET['zone_filter'] ?? '';

$sql = "SELECT s.*, u.username FROM seizure_sessions s JOIN users u ON s.inspector_id = u.user_id WHERE 1=1";
$params = [];
$types = "";

if(!$is_admin){
    $sql .= " AND s.inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
if(!empty($date_from)){ $sql .= " AND s.seizure_date >= ?"; $params[] = $date_from; $types .= "s"; }
if(!empty($date_to)){ $sql .= " AND s.seizure_date <= ?"; $params[] = $date_to; $types .= "s"; }
if(!empty($zone_filter)){ $sql .= " AND s.zone = ?"; $params[] = $zone_filter; $types .= "s"; }

$sql .= " ORDER BY s.seizure_date DESC, s.session_id DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$sessions = $stmt->get_result();
?>

<div class="card" style="max-width: 100%; margin-bottom: 25px;">
    <h2><?php echo __('filters_title'); ?></h2>
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
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
</div>

<?php while($session = $sessions->fetch_assoc()): ?>
    <div class="card" style="max-width:100%; margin-bottom:20px;">
        <h3>
            <?php echo htmlspecialchars($session['team_leader_name']); ?> 
            — Zone <?php echo htmlspecialchars($session['zone']); ?> 
            — Team <?php echo htmlspecialchars($session['team_number']); ?>
        </h3>
        <p style="color:var(--muted);">
            Inspector: <?php echo htmlspecialchars($session['username']); ?> | 
            Date: <?php echo $session['seizure_date']; ?>
        </p>
        <?php
        $items = $conn->prepare("SELECT * FROM seizure_items WHERE session_id = ?");
        $items->bind_param("i", $session['session_id']);
        $items->execute();
        $items_result = $items->get_result();
        ?>
        <table style="margin-top:10px;">
            <tr><th>Item</th><th>Qty</th><th>Owner</th><th>Location</th><th>Godown No.</th></tr>
            <?php while($item = $items_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['item_details']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo htmlspecialchars($item['owner_merchant_name']); ?></td>
                <td><?php echo htmlspecialchars($item['seizure_location']); ?></td>
                <td><?php echo htmlspecialchars($item['godown_register_no']); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
<?php endwhile; ?>

<?php if($sessions->num_rows === 0): ?>
    <p style="text-align:center; color:var(--muted); padding:20px;">No seizure records match these filters.</p>
<?php endif; ?>