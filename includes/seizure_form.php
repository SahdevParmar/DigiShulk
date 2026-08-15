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
        $msg = "<div class='alert alert-success' style='margin-bottom: var(--space-4);'>
            <i class='fa-solid fa-circle-check alert-icon' aria-hidden='true'></i>
            <div class='alert-content'>
                <p class='alert-title'>" . __('success_msg') . "</p>
                <p class='alert-message'>" . $saved_count . " " . __('items_saved') . "</p>
            </div>
        </div>";
    } else {
        $msg = "<div class='alert alert-warning' style='margin-bottom: var(--space-4);'>
            <i class='fa-solid fa-triangle-exclamation alert-icon' aria-hidden='true'></i>
            <div class='alert-content'>
                <p class='alert-title'>" . __('no_items_error') . "</p>
            </div>
        </div>";
    }
}
?>

<div class="page">
    <div class="card" style="max-width: 800px;">
        <div class="card-header" style="text-align: center; padding-bottom: var(--space-4);">
            <h1 class="card-title" style="font-size: var(--text-2xl);"><?php echo __('rmc_title'); ?></h1>
            <p class="card-subtitle" style="margin: 0; border-bottom: 2px dashed var(--color-border); padding-bottom: var(--space-4);"><?php echo __('dept_title'); ?></p>
        </div>

        <?php echo $msg; ?>

        <form method="POST" action="" id="seizureForm" class="card-body" novalidate>

            <fieldset>
                <legend style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">Session Details</legend>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="team_leader_name"><?php echo __('team_leader'); ?> <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" name="team_leader_name" id="team_leader_name" class="form-input" required>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="zone"><?php echo __('zone'); ?> <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" name="zone" id="zone" class="form-input" placeholder="e.g. Central" required>
                    </div>
                </div>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="team_number"><?php echo __('team_no'); ?> <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" name="team_number" id="team_number" class="form-input" required>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="seizure_date"><?php echo __('date'); ?> <span class="required" aria-hidden="true">*</span></label>
                        <input type="date" name="seizure_date" id="seizure_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </fieldset>

            <fieldset style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--color-border);">
                <legend style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);"><?php echo __('items_section_title'); ?></legend>

                <div id="itemsContainer"></div>

                <div style="margin-top: var(--space-4);">
                    <button type="button" id="addItemBtn" class="btn btn-secondary">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <?php echo __('add_item'); ?>
                    </button>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" name="log_seizure" class="btn btn-primary btn-block btn-lg">
                    <i class="fa-solid fa-save" aria-hidden="true"></i>
                    <?php echo __('submit'); ?>
                </button>
            </div>

        </form>
    </div>
</div>

<template id="itemRowTemplate">
    <div class="card card-muted" style="margin-bottom: var(--space-3); padding: var(--space-4); position: relative;">
        <button type="button" class="removeItemBtn" style="position: absolute; top: var(--space-3); right: var(--space-3); padding: var(--space-1); background: none; border: none; color: var(--color-text-muted); cursor: pointer; border-radius: var(--radius-sm);" aria-label="Remove item">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <div class="form-grid form-grid-2" style="gap: var(--space-3);">
            <div class="form-field" style="margin-bottom: 0;">
                <label class="form-label"><?php echo __('godown_no'); ?></label>
                <input type="text" name="godown_register_no[]" class="form-input" placeholder="Register number">
            </div>

            <div class="form-field" style="margin-bottom: 0; grid-column: 1 / -1;">
                <label class="form-label"><?php echo __('item_details'); ?> <span class="required" aria-hidden="true">*</span></label>
                <input type="text" name="item_details[]" class="form-input" placeholder="e.g. Rekdi / Cabin" required>
            </div>

            <div class="form-field" style="margin-bottom: 0;">
                <label class="form-label"><?php echo __('quantity'); ?> <span class="required" aria-hidden="true">*</span></label>
                <input type="number" name="quantity_seized[]" class="form-input" min="1" required>
            </div>

            <div class="form-field" style="margin-bottom: 0;">
                <label class="form-label"><?php echo __('owner_name'); ?></label>
                <input type="text" name="owner_merchant_name[]" class="form-input" placeholder="Owner/Merchant name">
            </div>

            <div class="form-field" style="margin-bottom: 0; grid-column: 1 / -1;">
                <label class="form-label"><?php echo __('location'); ?></label>
                <input type="text" name="seizure_location[]" class="form-input" placeholder="Seizure location">
            </div>
        </div>
    </div>
</template>

<script>
const container = document.getElementById('itemsContainer');
const template = document.getElementById('itemRowTemplate');

function addItemRow(){
    const clone = template.content.cloneNode(true);
    clone.querySelector('.removeItemBtn').addEventListener('click', function(e){
        e.target.closest('.card').remove();
        updateItemCount();
    });
    container.appendChild(clone);
    updateItemCount();
}

function updateItemCount() {
    const count = container.querySelectorAll('.card').length;
    // Optional: show item count somewhere
}

document.getElementById('addItemBtn').addEventListener('click', addItemRow);

// Start with one row visible so the form isn't empty on load
addItemRow();

// Client-side validation
document.getElementById('seizureForm').addEventListener('submit', function(e) {
    const form = this;
    let hasError = false;

    // Clear previous errors
    form.querySelectorAll('.form-error').forEach(el => el.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));

    // Validate required fields in session details
    form.querySelectorAll('[required]').forEach(function(field) {
        if (!field.value.trim()) {
            hasError = true;
            field.setAttribute('aria-invalid', 'true');
            const error = document.createElement('p');
            error.className = 'form-error';
            error.textContent = 'This field is required';
            field.parentNode.appendChild(error);
        }
    });

    // Validate at least one item with details
    const itemDetails = form.querySelectorAll('input[name="item_details[]"]');
    let hasItemDetail = false;
    itemDetails.forEach(function(input) {
        if (input.value.trim()) {
            hasItemDetail = true;
        }
    });

    if (!hasItemDetail) {
        hasError = true;
        const firstItemInput = itemDetails[0];
        if (firstItemInput) {
            firstItemInput.setAttribute('aria-invalid', 'true');
            const error = document.createElement('p');
            error.className = 'form-error';
            error.textContent = 'Please add at least one seized item';
            firstItemInput.parentNode.appendChild(error);
        }
    }

    if (hasError) {
        e.preventDefault();
        // Focus first invalid field
        const firstInvalid = form.querySelector('[aria-invalid="true"]');
        if (firstInvalid) firstInvalid.focus();
    }
});
</script>

<?php include 'footer.php'; ?>