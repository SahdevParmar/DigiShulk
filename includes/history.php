<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'inspector'], true)) {
    header('Location: logout.php');
    exit();
}

include 'db_connect.php';
include 'header.php';

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$payment_mode = $_GET['payment_mode'] ?? '';

$sql = "SELECT * FROM transactions WHERE 1=1";
$params = [];
$types = "";

if ($_SESSION['role'] === 'inspector') {
    $sql .= " AND inspector_id = ?";
    $params[] = (int) $_SESSION['user_id'];
    $types .= "i";
}

if(!empty($date_from)){ $sql .= " AND date(created_at)>= ?"; $params[]=$date_from; $types.="s"; }
if(!empty($date_to)){ $sql .= " AND date(created_at)<=?"; $params[]=$date_to; $types.="s"; }
if(!empty($status)){ $sql.=" AND status=?"; $params[]=$status; $types.="s"; }
if(!empty($payment_mode)){ $sql.=" AND payment_mode=?"; $params[]=$payment_mode; $types.="s"; }

$stmt = $conn->prepare($sql);
if(!empty($params)){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
?>
<div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
    <div class="card" style="max-width: 100%; margin-bottom: 25px;">
        <h2><?php echo __('filters_title'); ?></h2>
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div>
                <label><?php echo __('from'); ?></label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div>
                <label><?php echo __('to'); ?></label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div>
                <label><?php echo __('status'); ?></label>
                <select name="status">
                    <option value=""><?php echo __('all'); ?></option>
                    <option value="paid" <?php if($status=='paid') echo 'selected'; ?>><?php echo __('paid'); ?></option>
                    <option value="pending" <?php if($status=='pending') echo 'selected'; ?>><?php echo __('pending'); ?></option>
                </select>
            </div>
            <div>
                <label><?php echo __('payment_mode'); ?></label>
                <select name="payment_mode">
                    <option value=""><?php echo __('all'); ?></option>
                    <option value="cash" <?php if($payment_mode=='cash') echo 'selected';?>><?php echo __('cash'); ?></option>
                    <option value="upi" <?php if($payment_mode=='upi') echo 'selected';?>><?php echo __('upi'); ?></option>
                </select>
            </div>
            <button type="submit" style="background:#2ecc71; height: 42px; padding: 0 25px;">
                <?php echo __('btn_apply'); ?>
            </button>
        </form>
    </div>

    <table border="1" style="width:100%; background: var(--panel); border-collapse: collapse; border-radius: 8px; overflow: hidden;">
        <thead>
            <tr style="background: var(--bg-soft); color: var(--text-light);">
                <th><?php echo __('th_shop'); ?></th>
                <th><?php echo __('th_amount'); ?></th>
                <th><?php echo __('th_status'); ?></th>
                <th><?php echo __('payment_mode'); ?></th>
                <th><?php echo __('th_time'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr style="color: var(--ink); text-align: center;">
                    <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
                    <td>₹<?php echo $row['total_amount']; ?></td>
                    <td><span class="badge badge-<?php echo htmlspecialchars($row['status']); ?>"><?php echo __($row['status']); ?></span></td>
                    <td><?php echo __($row['payment_mode']); ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
