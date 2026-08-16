<?php
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role']!='inspector'){
    header("Location: logout.php");
    exit();
}

// Retrieve form errors and data from session
$form_errors = $_SESSION['form_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

include 'db_connect.php';
include 'header.php';
?>

<div class="page">
    <div class="card" style="max-width: 640px;">

        <!-- Step Indicator -->
        <nav aria-label="Collection steps" style="display: flex; justify-content: center; margin-bottom: var(--space-8); position: relative;">
            <div style="position: absolute; top: calc(50% + 20px); left: 0; right: 0; height: 2px; background: var(--color-border); transform: translateY(-50%); z-index: 1;" aria-hidden="true"></div>
            <ol style="display: flex; gap: var(--space-4); z-index: 2; list-style: none; padding: 0; margin: 0;">
                <li class="step-indicator active" data-step="1" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);" aria-current="step">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;" aria-label="Step 1">1</div>
                    <span style="font-size: var(--text-xs); font-weight: 600; color: var(--color-text);">Shop</span>
                </li>
                <li class="step-indicator" data-step="2" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; font-weight: 600;" aria-label="Step 2">2</div>
                    <span style="font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted);">Tax</span>
                </li>
                <li class="step-indicator" data-step="3" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; font-weight: 600;" aria-label="Step 3">3</div>
                    <span style="font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted);">Payment</span>
                </li>
            </ol>
        </nav>

        <form action="payment.php" method="POST" class="card-body" novalidate id="spotTaxForm">

            <!-- Step 1: Shop Details -->
            <div class="form-step active" data-step="1">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);">Step 1: Shop Details</h2>
                    <p style="color: var(--color-text-muted);">Find or create the shop for this collection</p>
                </div>

                <div class="form-field">
                    <label class="form-label" for="shop_name"><?php echo __('shop_name'); ?> <span class="required" aria-hidden="true"></span></label>
                    <div class="autocomplete-wrapper" style="position: relative;">
                        <input
                            type="text"
                            id="shop_name"
                            name="shop_name"
                            class="form-input <?= isset($form_errors['shop_name']) ? 'form-input-error' : '' ?>"
                            placeholder="<?php echo __('shop_name'); ?>"
                            autocomplete="off"
                            required
                            aria-autocomplete="list"
                            aria-controls="shopSuggestions"
                            aria-invalid="<?= isset($form_errors['shop_name']) ? 'true' : 'false' ?>"
                            value="<?= htmlspecialchars($form_data['shop_name'] ?? '') ?>">
                        <div id="shopSuggestions" class="autocomplete-box" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); max-height: 200px; overflow-y: auto; display: none;"></div>
                        <?php if (isset($form_errors['shop_name'])): ?>
                            <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['shop_name']) ?></p>
                        <?php else: ?>
                            <p class="form-help">Start typing to search existing shops</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="phone"><?php echo __('phone'); ?> <span class="required" aria-hidden="true"></span></label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            class="form-input <?= isset($form_errors['phone']) ? 'form-input-error' : '' ?>"
                            placeholder="<?php echo __('phone'); ?>"
                            required
                            pattern="[0-9]{10}"
                            inputmode="numeric"
                            aria-invalid="<?= isset($form_errors['phone']) ? 'true' : 'false' ?>"
                            value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>">
                        <?php if (isset($form_errors['phone'])): ?>
                            <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['phone']) ?></p>
                        <?php else: ?>
                            <p class="form-help">10-digit mobile number</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="shop_address"><?php echo __('address'); ?> <span class="required" aria-hidden="true"></span></label>
                        <input
                            type="text"
                            id="shop_address"
                            name="shop_address"
                            class="form-input <?= isset($form_errors['shop_address']) ? 'form-input-error' : '' ?>"
                            placeholder="<?php echo __('address'); ?>"
                            required
                            aria-invalid="<?= isset($form_errors['shop_address']) ? 'true' : 'false' ?>"
                            value="<?= htmlspecialchars($form_data['shop_address'] ?? '') ?>">
                        <?php if (isset($form_errors['shop_address'])): ?>
                            <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['shop_address']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: var(--space-6);">
                    <button type="button" class="btn btn-primary btn-block" onclick="nextStep(2)">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        Continue to Tax Details
                    </button>
                </div>
            </div>

            <!-- Step 2: Tax Details -->
            <div class="form-step" data-step="2" style="display: none;">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);">Step 2: Tax Details</h2>
                    <p style="color: var(--color-text-muted);">Enter the stall type, amount, and size</p>
                </div>

                <div class="form-field">
                    <label class="form-label" for="stall_type">Select Stall Type <span class="required" aria-hidden="true"></span></label>
                    <select name="stall_type" id="stall_type" class="form-select" required onchange="toggleOtherType()">
                        <option value="">-- Select --</option>
                        <option value="Rekdi" <?= ($form_data['stall_type'] ?? '') === 'Rekdi' ? 'selected' : '' ?>>Rekdi</option>
                        <option value="Mandap" <?= ($form_data['stall_type'] ?? '') === 'Mandap' ? 'selected' : '' ?>>Mandap</option>
                        <option value="Chhajli" <?= ($form_data['stall_type'] ?? '') === 'Chhajli' ? 'selected' : '' ?>>Chhajli</option>
                        <option value="Other" <?= ($form_data['stall_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other (type manually)</option>
                    </select>
                </div>

                <div class="form-field" id="stall_type_other_wrapper" style="display: <?= (($form_data['stall_type'] ?? '') === 'Other') ? 'block' : 'none' ?>;">
                    <label class="form-label" for="stall_type_other">Specify Stall Type <span class="required" aria-hidden="true"></span></label>
                    <input
                        type="text"
                        name="stall_type_other"
                        id="stall_type_other"
                        class="form-input"
                        placeholder="Describe the stall/item type"
                        aria-required="<?= (($form_data['stall_type'] ?? '') === 'Other') ? 'true' : 'false' ?>"
                        value="<?= htmlspecialchars($form_data['stall_type_other'] ?? '') ?>">
                </div>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="amount">Amount to Charge (₹) <span class="required" aria-hidden="true"></span></label>
                        <input
                            type="number"
                            name="amount"
                            id="amount"
                            class="form-input <?= isset($form_errors['amount']) ? 'form-input-error' : '' ?>"
                            step="0.01"
                            min="1"
                            placeholder="Enter amount"
                            required
                            aria-describedby="amount-help"
                            aria-invalid="<?= isset($form_errors['amount']) ? 'true' : 'false' ?>"
                            value="<?= htmlspecialchars($form_data['amount'] ?? '') ?>">
                        <?php if (isset($form_errors['amount'])): ?>
                            <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['amount']) ?></p>
                        <?php else: ?>
                            <p class="form-help" id="amount-help">Enter final amount in rupees</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="size"><?php echo __('enter_size'); ?> <span class="required" aria-hidden="true"></span></label>
                        <input
                            type="number"
                            name="size"
                            id="size"
                            class="form-input"
                            placeholder="<?php echo __('size_placeholder'); ?>"
                            required
                            min="1"
                            step="0.01"
                            aria-describedby="size-help"
                            value="<?= htmlspecialchars($form_data['size'] ?? '') ?>">
                        <p class="form-help" id="size-help">Size in square feet</p>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="payment_mode"><?php echo __('payment_mode'); ?> <span class="required" aria-hidden="true"></span></label>
                    <select name="payment_mode" id="payment_mode" class="form-select <?= isset($form_errors['payment_mode']) ? 'form-input-error' : '' ?>" required aria-invalid="<?= isset($form_errors['payment_mode']) ? 'true' : 'false' ?>">
                        <option value="">-- Select Payment Mode --</option>
                        <option value="cash" <?= ($form_data['payment_mode'] ?? '') === 'cash' ? 'selected' : '' ?>><?php echo __('cash'); ?></option>
                        <option value="upi" <?= ($form_data['payment_mode'] ?? '') === 'upi' ? 'selected' : '' ?>><?php echo __('upi'); ?></option>
                    </select>
                    <?php if (isset($form_errors['payment_mode'])): ?>
                        <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['payment_mode']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions" style="margin-top: var(--space-6); display: flex; gap: var(--space-3);">
                    <button type="button" class="btn btn-secondary btn-block" onclick="prevStep(1)">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        Back
                    </button>
                    <button type="button" class="btn btn-primary btn-block" onclick="nextStep(3)">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        Continue to Payment
                    </button>
                </div>
            </div>

            <!-- Step 3: Payment & Confirm -->
            <div class="form-step" data-step="3" style="display: none;">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);">Step 3: Payment & Confirm</h2>
                    <p style="color: var(--color-text-muted);">Review and submit the collection request</p>
                </div>

                <div class="card card-muted" style="padding: var(--space-4); margin-bottom: var(--space-6);">
                    <h3 style="font-size: var(--text-base); font-weight: 600; margin-bottom: var(--space-3);">Summary</h3>
                    <div class="form-grid form-grid-2" style="gap: var(--space-2);">
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Shop</span>
                            <div id="summary_shop" style="font-weight: 600;">-</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Phone</span>
                            <div id="summary_phone" style="font-weight: 600;">-</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Stall Type</span>
                            <div id="summary_stall" style="font-weight: 600;">-</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Size</span>
                            <div id="summary_size" style="font-weight: 600;">- sq ft</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Amount</span>
                            <div id="summary_amount" style="font-weight: 700; color: var(--color-success); font-size: var(--text-lg);">₹0.00</div>
                        </div>
                        <div>
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted);">Payment</span>
                            <div id="summary_payment" style="font-weight: 600; text-transform: uppercase;">-</div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="shop_id" name="shop_id" value="<?= htmlspecialchars($form_data['shop_id'] ?? '') ?>">

                <div class="form-actions" style="margin-top: var(--space-6); display: flex; gap: var(--space-3);">
                    <button type="button" class="btn btn-secondary btn-block" onclick="prevStep(2)">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        Back
                    </button>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        Generate Collection Request
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

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
    
    // Update summary on step 3
    if (step === 3) {
        updateSummary();
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
    
    // Validate phone format
    const phone = document.getElementById('phone');
    if (phone.value && !/^[0-9]{10}$/.test(phone.value)) {
        valid = false;
        phone.setAttribute('aria-invalid', 'true');
        phone.classList.add('form-input-error');
        const error = document.createElement('p');
        error.className = 'form-error';
        error.textContent = 'Enter a valid 10-digit mobile number';
        phone.parentNode.appendChild(error);
    }
    
    // Validate amount
    const amount = document.getElementById('amount');
    if (amount.value && (parseFloat(amount.value) < 1)) {
        valid = false;
        amount.setAttribute('aria-invalid', 'true');
        amount.classList.add('form-input-error');
        const error = document.createElement('p');
        error.className = 'form-error';
        error.textContent = 'Amount must be at least ₹1';
        amount.parentNode.appendChild(error);
    }
    
    if (!valid) {
        const firstInvalid = stepEl.querySelector('[aria-invalid="true"]');
        if (firstInvalid) firstInvalid.focus();
    }
    
    return valid;
}

function updateSummary() {
    document.getElementById('summary_shop').textContent = document.getElementById('shop_name').value || '-';
    document.getElementById('summary_phone').textContent = document.getElementById('phone').value || '-';
    
    const stallType = document.getElementById('stall_type').value;
    const stallOther = document.getElementById('stall_type_other').value;
    document.getElementById('summary_stall').textContent = (stallType === 'Other' ? stallOther : stallType) || '-';
    
    document.getElementById('summary_size').textContent = document.getElementById('size').value + ' sq ft' || '- sq ft';
    document.getElementById('summary_amount').textContent = '₹' + parseFloat(document.getElementById('amount').value || 0).toFixed(2);
    document.getElementById('summary_payment').textContent = document.getElementById('payment_mode').value || '-';
}

function toggleOtherType(){
    const select = document.getElementById('stall_type');
    const otherWrapper = document.getElementById('stall_type_other_wrapper');
    const otherInput = document.getElementById('stall_type_other');

    if(select.value === 'Other'){
        otherWrapper.style.display = 'block';
        otherInput.required = true;
        otherInput.setAttribute('aria-required', 'true');
    } else {
        otherWrapper.style.display = 'none';
        otherInput.required = false;
        otherInput.removeAttribute('aria-required');
        otherInput.value = '';
    }
}

// Auto-update summary when fields change
['shop_name', 'phone', 'stall_type', 'stall_type_other', 'size', 'amount', 'payment_mode'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateSummary);
        el.addEventListener('change', updateSummary);
    }
});
</script>
<script src="assets/js/spot_tax.js"></script>
<?php include 'footer.php'; ?>