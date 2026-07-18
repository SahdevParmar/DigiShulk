<?php
include 'header.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_seizure'])) {
    $t_leader = $_POST['team_leader_name'];
    $zone = $_POST['zone'];
    $t_no = $_POST['team_number'];
    $s_date = $_POST['seizure_date'];

    // These arrive as arrays — one entry per item row the inspector added
    $godown_nos = $_POST['godown_register_no'] ?? [];
    $items      = $_POST['item_details'] ?? [];
    $qtys       = $_POST['quantity_seized'] ?? [];
    $owners     = $_POST['owner_merchant_name'] ?? [];
    $locations  = $_POST['seizure_location'] ?? [];

    // 1. Insert the session header row
    $stmt = $conn->prepare("INSERT INTO seizure_sessions (inspector_id, team_leader_name, zone, team_number, seizure_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $_SESSION['user_id'], $t_leader, $zone, $t_no, $s_date);
    $stmt->execute();
    $session_id = $conn->insert_id;

    // 2. Insert each item row, linked back to that session
    $item_stmt = $conn->prepare("INSERT INTO seizure_items (session_id, godown_register_no, item_details, quantity, owner_merchant_name, seizure_location) VALUES (?, ?, ?, ?, ?, ?)");

    $saved_count = 0;
    foreach ($items as $i => $item_detail) {
        if (trim($item_detail) === '') continue; // skip any blank/unused row

        $g_no  = $godown_nos[$i] ?? '';
        $qty   = intval($qtys[$i] ?? 0);
        $owner = $owners[$i] ?? '';
        $loc   = $locations[$i] ?? '';

        $item_stmt->bind_param("ississ", $session_id, $g_no, $item_detail, $qty, $owner, $loc);
        $item_stmt->execute();
        $saved_count++;
    }

    if ($saved_count > 0) {
        $msg = "<div class='stamp stamp-paid' style='margin-bottom:15px; display:block; text-align:center;'>"
             . __('success_msg') . " (" . $saved_count . " " . __('items_saved') . ")</div>";
    } else {
        $msg = "<div class='stamp stamp-pending' style='margin-bottom:15px; display:block; text-align:center;'>"
             . __('no_items_error') . "</div>";
    }
}
?>

<div style="display: flex; justify-content: center; padding-top: 20px;">
    <div class="card" style="max-width: 800px;">
        <h1 style="font-size: 1.6rem; text-align: center;"><?php echo __('rmc_title'); ?></h1>
        <h2 style="font-size: 1.1rem; text-align: center; color: var(--muted); margin-bottom: 20px; border-bottom: 2px dashed rgba(0,0,0,0.1); padding-bottom: 10px;">
            <?php echo __('dept_title'); ?>
        </h2>

        <?php echo $msg; ?>

        <form method="POST" action="" id="seizureForm">
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

            <h3 style="margin-top:20px;"><?php echo __('items_section_title'); ?></h3>
            <div id="itemsContainer"></div>

            <button type="button" id="addItemBtn" style="background:#6b7280; margin-top:10px;">
                + <?php echo __('add_item'); ?>
            </button>

            <button type="submit" name="log_seizure" style="width:100%; margin-top:20px;">
                <?php echo __('submit'); ?>
            </button>
        </form>
    </div>
</div>

<template id="itemRowTemplate">
    <div class="item-row" style="border:1px dashed rgba(0,0,0,0.15); padding:12px; border-radius:10px; margin-bottom:12px; position:relative;">
        <button type="button" class="removeItemBtn" style="position:absolute; top:8px; right:8px; width:auto; padding:4px 10px; background:#dc2626;">✕</button>

        <label><?php echo __('godown_no'); ?></label>
        <input type="text" name="godown_register_no[]">

        <label><?php echo __('item_details'); ?></label>
        <input type="text" name="item_details[]" placeholder="e.g. Rekdi / Cabin" required>

        <label><?php echo __('quantity'); ?></label>
        <input type="number" name="quantity_seized[]" min="1" required>

        <label><?php echo __('owner_name'); ?></label>
        <input type="text" name="owner_merchant_name[]">

        <label><?php echo __('location'); ?></label>
        <input type="text" name="seizure_location[]">
    </div>
</template>

<script>
const container = document.getElementById('itemsContainer');
const template = document.getElementById('itemRowTemplate');

function addItemRow(){
    const clone = template.content.cloneNode(true);
    clone.querySelector('.removeItemBtn').addEventListener('click', function(e){
        e.target.closest('.item-row').remove();
    });
    container.appendChild(clone);
}

document.getElementById('addItemBtn').addEventListener('click', addItemRow);

// Start with one row visible so the form isn't empty on load
addItemRow();
</script>