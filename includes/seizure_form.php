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

        <!-- Step Indicator -->
        <nav aria-label="Seizure report steps" style="display: flex; justify-content: center; margin-bottom: var(--space-8); position: relative;">
            <div style="position: absolute; top: calc(50% + 20px); left: 0; right: 0; height: 2px; background: var(--color-border); transform: translateY(-50%); z-index: 1;" aria-hidden="true"></div>
            <ol style="display: flex; gap: var(--space-4); z-index: 2; list-style: none; padding: 0; margin: 0;">
                <li class="step-indicator active" data-step="1" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);" aria-current="step">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;" aria-label="Step 1">1</div>
                    <span style="font-size: var(--text-xs); font-weight: 600; color: var(--color-text);">Session</span>
                </li>
                <li class="step-indicator" data-step="2" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; font-weight: 600;" aria-label="Step 2">2</div>
                    <span style="font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted);">Items</span>
                </li>
                <li class="step-indicator" data-step="3" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; font-weight: 600;" aria-label="Step 3">3</div>
                    <span style="font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted);">Review</span>
                </li>
            </ol>
        </nav>

        <?php echo $msg; ?>

        <form method="POST" action="" id="seizureForm" class="card-body" novalidate>

            <!-- Step 1: Session Details -->
            <div class="form-step active" data-step="1">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);"><?php echo __('rmc_title'); ?></h2>
                    <p style="color: var(--color-text-muted);">Step 1: Session Details</p>
                </div>

                <div class="form-section">
                    <h3 style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">Session Details</h3>

                    <div class="form-grid form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="team_leader_name"><?php echo __('team_leader'); ?> <span class="required" aria-hidden="true"></span></label>
                            <input type="text" name="team_leader_name" id="team_leader_name" class="form-input" required>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="zone"><?php echo __('zone'); ?> <span class="required" aria-hidden="true"></span></label>
                            <input type="text" name="zone" id="zone" class="form-input" placeholder="e.g. Central" required>
                        </div>
                    </div>

                    <div class="form-grid form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="team_number"><?php echo __('team_no'); ?> <span class="required" aria-hidden="true"></span></label>
                            <input type="text" name="team_number" id="team_number" class="form-input" required>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="seizure_date"><?php echo __('date'); ?> <span class="required" aria-hidden="true"></span></label>
                            <input type="date" name="seizure_date" id="seizure_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: var(--space-6);">
                    <button type="button" class="btn btn-primary btn-block" onclick="nextStep(2)">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        Continue to Items
                    </button>
                </div>
            </div>

            <!-- Step 2: Seized Items -->
            <div class="form-step" data-step="2" style="display: none;">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);"><?php echo __('rmc_title'); ?></h2>
                    <p style="color: var(--color-text-muted);">Step 2: Add Seized Items</p>
                </div>

                <div id="itemsContainer"></div>

                <div style="margin-top: var(--space-4); display: flex; gap: var(--space-3);">
                    <button type="button" id="addItemBtn" class="btn btn-secondary" style="flex: 1;">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <?php echo __('add_item'); ?>
                    </button>
                </div>

                <div class="form-actions" style="margin-top: var(--space-6); display: flex; gap: var(--space-3);">
                    <button type="button" class="btn btn-secondary btn-block" onclick="prevStep(1)">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        Back
                    </button>
                    <button type="button" class="btn btn-primary btn-block" onclick="nextStep(3)">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        Review & Submit
                    </button>
                </div>
            </div>

            <!-- Step 3: Review & Submit -->
            <div class="form-step" data-step="3" style="display: none;">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);"><?php echo __('rmc_title'); ?></h2>
                    <p style="color: var(--color-text-muted);">Step 3: Review & Submit</p>
                </div>

                <div class="card card-muted" style="padding: var(--space-4); margin-bottom: var(--space-6);">
                    <h3 style="font-size: var(--text-base); font-weight: 600; margin-bottom: var(--space-3);">Session Summary</h3>
                    <div class="form-grid form-grid-2" style="gap: var(--space-2);">
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Team Leader</span>
                            <div id="review_leader" style="font-weight: 600;">-</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Zone</span>
                            <div id="review_zone" style="font-weight: 600;">-</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Team Number</span>
                            <div id="review_team" style="font-weight: 600;">-</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Date</span>
                            <div id="review_date" style="font-weight: 600;">-</div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: var(--space-4);">
                    <h3 style="font-size: var(--text-base); font-weight: 600; margin-bottom: var(--space-3);">Seized Items (<span id="review_item_count">0</span>)</h3>
                    <div id="review_items" style="max-height: 300px; overflow-y: auto;"></div>
                </div>

                <div class="form-actions" style="margin-top: var(--space-6); display: flex; gap: var(--space-3);">
                    <button type="button" class="btn btn-secondary btn-block" onclick="prevStep(2)">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        Back
                    </button>
                    <button type="submit" name="log_seizure" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-save" aria-hidden="true"></i>
                        <?php echo __('submit'); ?>
                    </button>
                </div>
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
                <label class="form-label"><?php echo __('item_details'); ?> <span class="required" aria-hidden="true"></span></label>
                <input type="text" name="item_details[]" class="form-input" placeholder="e.g. Rekdi / Cabin" required>
            </div>

            <div class="form-field" style="margin-bottom: 0;">
                <label class="form-label"><?php echo __('quantity'); ?> <span class="required" aria-hidden="true"></span></label>
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
let currentStep = 1;
const totalSteps = 3;

function nextStep(step) {
    if (validateStep(currentStep)) {
        goToStep(step);
    }
}

function prevStep(step) {
    goToStep(step);
}

function goToStep(step) {
    // Hide current step
    document.querySelector('.form-step[data-step="' + currentStep + '"]').style.display = 'none';
    document.querySelector('.step-indicator[data-step="' + currentStep + '"]').classList.remove('active');
    
    // Show new step
    document.querySelector('.form-step[data-step="' + step + '"]').style.display = 'block';
    document.querySelector('.step-indicator[data-step="' + step + '"]').classList.add('active');
    
    currentStep = step;
    
    // Update review on step 3
    if (step === 3) {
        updateReview();
    }
}

function validateStep(step) {
    const stepEl = document.querySelector('.form-step[data-step="' + step + '"]');
    const requiredFields = stepEl.querySelectorAll('[required]');
    let valid = true;
    
    // Clear previous errors
    stepEl.querySelectorAll('.form-error').forEach(el => el.remove());
    stepEl.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));
    stepEl.querySelectorAll('.form-input-error').forEach(el => el.classList.remove('form-input-error'));
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            valid = false;
            field.setAttribute('aria-invalid', 'true');
            field.classList.add('form-input-error');
            const error = document.createElement('p');
            error.className = 'form-error';
            error.textContent = 'This field is required';
            field.parentNode.appendChild(error);
        }
    });
    
    if (!valid) {
        const firstInvalid = stepEl.querySelector('[aria-invalid="true"]');
        if (firstInvalid) firstInvalid.focus();
    }
    
    return valid;
}

function updateReview() {
    // Update session summary
    document.getElementById('review_leader').textContent = document.getElementById('team_leader_name').value || '-';
    document.getElementById('review_zone').textContent = document.getElementById('zone').value || '-';
    document.getElementById('review_team').textContent = document.getElementById('team_number').value || '-';
    document.getElementById('review_date').textContent = document.getElementById('seizure_date').value || '-';
    
    // Update items review
    const container = document.getElementById('itemsContainer');
    const items = container.querySelectorAll('.card');
    const reviewItems = document.getElementById('review_items');
    const reviewCount = document.getElementById('review_item_count');
    
    let hasItems = false;
    let html = '';
    
    items.forEach((card, index) => {
        const itemDetail = card.querySelector('input[name="item_details[]"]');
        const qty = card.querySelector('input[name="quantity_seized[]"]');
        const owner = card.querySelector('input[name="owner_merchant_name[]"]');
        const location = card.querySelector('input[name="seizure_location[]"]');
        const godown = card.querySelector('input[name="godown_register_no[]"]');
        
        if (itemDetail && itemDetail.value.trim()) {
            hasItems = true;
            html += `
                <div class="card card-muted" style="padding: var(--space-3); margin-bottom: var(--space-2);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                        <strong style="font-size: var(--text-sm);">Item ${index + 1}</strong>
                        <span class="badge badge-primary badge-sm">${qty?.value || 0} units</span>
                    </div>
                    <div style="font-size: var(--text-sm); color: var(--color-text-muted); display: grid; gap: var(--space-1);">
                        <div><strong>Item:</strong> ${itemDetail?.value || '-'}</div>
                        ${godown?.value ? `<div><strong>Godown:</strong> ${godown.value}</div>` : ''}
                        ${owner?.value ? `<div><strong>Owner:</strong> ${owner.value}</div>` : ''}
                        ${location?.value ? `<div><strong>Location:</strong> ${location.value}</div>` : ''}
                    </div>
                </div>
            `;
        }
    });
    
    if (hasItems) {
        reviewItems.innerHTML = html;
        reviewCount.textContent = document.querySelectorAll('#itemsContainer .card').length;
    } else {
        reviewItems.innerHTML = '<p style="color: var(--color-text-muted); text-align: center; padding: var(--space-4);">No items added yet</p>';
        reviewCount.textContent = '0';
    }
}

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

// Update review when session fields change
['team_leader_name', 'zone', 'team_number', 'seizure_date'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateReview);
        el.addEventListener('change', updateReview);
    }
});

// Client-side validation
document.getElementById('seizureForm').addEventListener('submit', function(e) {
    const form = this;
    let hasError = false;

    // Clear previous errors
    form.querySelectorAll('.form-error').forEach(el => el.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));
    form.querySelectorAll('.form-input-error').forEach(el => el.classList.remove('form-input-error'));

    // Validate required fields in session details
    form.querySelectorAll('[required]').forEach(function(field) {
        if (!field.value.trim()) {
            hasError = true;
            field.setAttribute('aria-invalid', 'true');
            field.classList.add('form-input-error');
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