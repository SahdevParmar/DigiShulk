<?php
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$payment_mode = $_GET['payment_mode'] ?? '';

$sql = "SELECT * FROM transactions WHERE 1=1";
$params = [];
$types = "";

if(!$is_admin){
    $sql .= " AND inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

if(!empty($date_from)){ $sql .= " AND date(created_at)>= ?"; $params[]=$date_from; $types.="s"; }
if(!empty($date_to)){ $sql .= " AND date(created_at)<=?"; $params[]=$date_to; $types.="s"; }
if($is_admin && !empty($status)){ $sql.=" AND status=?"; $params[]=$status; $types.="s"; } // admin-only filter
if(!empty($payment_mode)){ $sql.=" AND payment_mode=?"; $params[]=$payment_mode; $types.="s"; }
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="card" style="max-width: 100%; margin-bottom: 25px;">
    <h2><?php echo __('filters_title'); ?></h2>
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
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
</div>

<table>
    <tr>
        <th><?php echo __('th_shop'); ?></th>
        <th><?php echo __('th_amount'); ?></th>
        <?php if($is_admin): ?><th><?php echo __('th_status'); ?></th><?php endif; ?>
        <th><?php echo __('payment_mode'); ?></th>
        <th><?php echo __('th_time'); ?></th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
            <td>₹<?php echo number_format($row['total_amount'],2); ?></td>
            <?php if($is_admin): ?>
            <td><span class="stamp <?php echo $row['status']=='paid'?'stamp-paid':'stamp-pending'; ?>"><?php echo __($row['status']); ?></span></td>
            <?php endif; ?>
            <td><?php echo __($row['payment_mode']); ?></td>
            <td><?php echo $row['created_at']; ?></td>
        </tr>
    <?php endwhile; ?>
</table>