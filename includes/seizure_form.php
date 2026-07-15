<?php
include 'header.php'; // Injects the database connect, session validation, and lang functions

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_seizure'])) {
    $t_leader = $_POST['team_leader_name'];
    $zone = $_POST['zone'];
    $t_no = $_POST['team_number'];
    $g_no = $_POST['godown_register_no'];
    $details = $_POST['seized_item_details'];
    $qty = intval($_POST['quantity_seized']);
    $owner = $_POST['owner_merchant_name'];
    $loc = $_POST['seizure_location'];
    $s_date = $_POST['seizure_date'];
    
    $stmt = $conn->prepare("INSERT INTO rmc_seizures (inspector_id, team_leader_name, zone, team_number, godown_register_no, seized_item_details, quantity_seized, owner_merchant_name, seizure_location, seizure_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssisss", $_SESSION['user_id'], $t_leader, $zone, $t_no, $g_no, $details, $qty, $owner, $loc, $s_date);
    
    if ($stmt->execute()) {
        $msg = "<div class='stamp stamp-paid' style='margin-bottom:15px; display:block; text-align:center;'>" . __('success_msg') . "</div>";
    }
}
?>

<div style="display: flex; justify-content: center; padding-top: 20px;">
    <div class="card">
        <h1 style="font-size: 1.6rem; text-align: center; color: var(--ink);"><?php echo __('rmc_title'); ?></h1>
        <h2 style="font-size: 1.1rem; text-align: center; color: var(--ink-soft); margin-bottom: 20px; border-bottom: 2px dashed rgba(0,0,0,0.1); padding-bottom: 10px;">
            <?php echo __('dept_title'); ?>
        </h2>
        
        <?php echo $msg; ?>

        <form method="POST" action="">
            <div style="display: flex; gap: 10px;">
                <div style="flex: 2;">
                    <label><?php echo __('team_leader'); ?></label>
                    <input type="text" name="team_leader_name" required>
                </div>
                <div style="flex: 1;">
                    <label><?php echo __('zone'); ?></label>
                    <input type="text" name="zone" placeholder="e.g. Central" required>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label><?php echo __('team_no'); ?></label>
                    <input type="text" name="team_number" required>
                </div>
                <div style="flex: 1;">
                    <label><?php echo __('date'); ?></label>
                    <input type="date" name="seizure_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <label><?php echo __('godown_no'); ?></label>
            <input type="text" name="godown_register_no" required>

            <label><?php echo __('item_details'); ?></label>
            <input type="text" name="seized_item_details" placeholder="e.g. Rekdi / Cabin" required>

            <label><?php echo __('quantity'); ?></label>
            <input type="number" name="quantity_seized" min="1" required>

            <label><?php echo __('owner_name'); ?></label>
            <input type="text" name="owner_merchant_name" required>

            <label><?php echo __('location'); ?></label>
            <input type="text" name="seizure_location" required>

            <button type="submit" name="log_seizure" style="max-width: 100%; margin-top: 20px; width: 100%;">
                <?php echo __('submit'); ?>
            </button>
        </form>
    </div>
</div>
</body>
</html>