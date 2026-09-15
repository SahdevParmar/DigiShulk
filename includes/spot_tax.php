<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'inspector') {
    header('Location: logout.php');
    exit();
}

$form_errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
$form_data   = isset($_SESSION['form_data'])   ? $_SESSION['form_data']   : [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

include 'db_connect.php';
require_once 'helpers/rates.php';
require_once 'helpers/csrf.php';

// Load rates for client-side suggestion.
$ratesMap = get_all_rates($conn);

include 'header.php';
?>

<div class="page">
    <div class="card" style="max-width: 900px;">

        <nav aria-label="Collection steps" style="display: flex; justify-content: center; margin-bottom: var(--space-8); position: relative;">
            <ol style="display: flex; gap: var(--space-4); z-index: 2; list-style: none; padding: 0; margin: 0;">
                <li class="step-indicator active" data-step="1" aria-current="step" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">1</div>
                    <span style="font-size: var(--text-xs); font-weight: 600; color: var(--color-text);">Shop</span>
                </li>
                <li class="step-indicator" data-step="2" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; font-weight: 600;">2</div>
                    <span style="font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted);">Tax</span>
                </li>
                <li class="step-indicator" data-step="3" style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                    <div class="step-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; font-weight: 600;">3</div>
                    <span style="font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted);">Payment</span>
                </li>
            </ol>
        </nav>

        <form action="payment.php" method="POST" class="card-body" novalidate id="spotTaxForm">

            <?= csrf_field() ?>
            <input type="hidden" name="suggested_amount" id="suggested_amount" value="">
            <input type="hidden" name="rate_per_sqft" id="rate_per_sqft" value="">

            <!-- Step 1: Shop Details -->
            <div class="form-step active" data-step="1">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);">Step 1: Shop Details</h2>
                    <p style="color: var(--color-text-muted);">Find or create the shop for this collection</p>
                </div>

                <div class="form-field">
                    <label class="form-label" for="shop_name">Shop Name <span class="required" aria-hidden="true"></span></label>
                    <div class="autocomplete-wrapper" style="position: relative;">
                        <input type="text" id="shop_name" name="shop_name"
                               class="form-input <?= isset($form_errors['shop_name']) ? 'form-input-error' : '' ?>"
                               placeholder="Shop name" autocomplete="off" required
                               value="<?= htmlspecialchars(isset($form_data['shop_name']) ? $form_data['shop_name'] : '') ?>">
                        <?php if (isset($form_errors['shop_name'])): ?>
                            <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['shop_name']) ?></p>
                        <?php else: ?>
                            <p class="form-help">Start typing to search existing shops</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="phone">Phone <span class="required" aria-hidden="true"></span></label>
                        <input type="tel" id="phone" name="phone"
                               class="form-input <?= isset($form_errors['phone']) ? 'form-input-error' : '' ?>"
                               placeholder="10-digit mobile" required pattern="[0-9]{10}" inputmode="numeric"
                               value="<?= htmlspecialchars(isset($form_data['phone']) ? $form_data['phone'] : '') ?>">
                        <?php if (isset($form_errors['phone'])): ?>
                            <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['phone']) ?></p>
                        <?php else: ?>
                            <p class="form-help">10-digit mobile number</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="shop_address">Address <span class="required" aria-hidden="true"></span></label>
                        <input type="text" id="shop_address" name="shop_address"
                               class="form-input <?= isset($form_errors['shop_address']) ? 'form-input-error' : '' ?>"
                               placeholder="Address" required
                               value="<?= htmlspecialchars(isset($form_data['shop_address']) ? $form_data['shop_address'] : '') ?>">
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
                        <option value="Rekdi"   <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Rekdi')   ? 'selected' : '' ?>>Rekdi</option>
                        <option value="Mandap"  <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Mandap')  ? 'selected' : '' ?>>Mandap</option>
                        <option value="Chhajli" <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Chhajli') ? 'selected' : '' ?>>Chhajli</option>
                        <option value="Other"   <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Other')   ? 'selected' : '' ?>>Other (type manually)</option>
                    </select>
                </div>

                <div class="form-field" id="stall_type_other_wrapper" style="display: <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Other') ? 'block' : 'none' ?>;">
                    <label class="form-label" for="stall_type_other">Specify Stall Type <span class="required" aria-hidden="true"></span></label>
                    <input type="text" name="stall_type_other" id="stall_type_other" class="form-input"
                           placeholder="Describe the stall/item type"
                           value="<?= htmlspecialchars(isset($form_data['stall_type_other']) ? $form_data['stall_type_other'] : '') ?>">
                </div>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="amount">Amount to Charge (₹) <span class="required" aria-hidden="true"></span></label>
                        <input type="number" name="amount" id="amount"
                               class="form-input <?= isset($form_errors['amount']) ? 'form-input-error' : '' ?>"
                               step="0.01" min="1" placeholder="Enter amount" required
                               value="<?= htmlspecialchars(isset($form_data['amount']) ? $form_data['amount'] : '') ?>">
                        <p class="form-help" id="amount-help">Enter final amount in rupees</p>
                        <?php if (isset($form_errors['amount'])): ?>
                            <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['amount']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="size">Size (sq ft) <span class="required" aria-hidden="true"></span></label>
                        <input type="number" name="size" id="size" class="form-input"
                               placeholder="Size in square feet" required min="0.01" step="0.01"
                               value="<?= htmlspecialchars(isset($form_data['size']) ? $form_data['size'] : '') ?>">
                        <p class="form-help">Size in square feet</p>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="payment_mode">Payment Mode <span class="required" aria-hidden="true"></span></label>
                    <select name="payment_mode" id="payment_mode"
                            class="form-select <?= isset($form_errors['payment_mode']) ? 'form-input-error' : '' ?>" required>
                        <option value="">-- Select Payment Mode --</option>
                        <option value="cash" <?= (isset($form_data['payment_mode']) && $form_data['payment_mode'] === 'cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="upi"  <?= (isset($form_data['payment_mode']) && $form_data['payment_mode'] === 'upi')  ? 'selected' : '' ?>>UPI</option>
                    </select>
                    <?php if (isset($form_errors['payment_mode'])): ?>
                        <p class="form-error" style="margin-top: var(--space-1);"><?= htmlspecialchars($form_errors['payment_mode']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions" style="margin-top: var(--space-6); display: flex; gap: var(--space-3);">
                    <button type="button" class="btn btn-secondary btn-block" onclick="prevStep(1)">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary btn-block" onclick="nextStep(3)">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Continue to Payment
                    </button>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="form-step" data-step="3" style="display: none;">
                <div style="text-align: center; margin-bottom: var(--space-6);">
                    <h2 style="font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1);">Step 3: Payment &amp; Confirm</h2>
                    <p style="color: var(--color-text-muted);">Review and submit the collection request</p>
                </div>

                <div class="card card-muted" style="padding: var(--space-4); margin-bottom: var(--space-6);">
                    <h3 style="font-size: var(--text-base); font-weight: 600; margin-bottom: var(--space-3);">Summary</h3>
                    <div class="form-grid form-grid-2" style="gap: var(--space-2);">
                        <div><span style="font-size: var(--text-xs); color: var(--color-text-muted);">Shop</span><div id="summary_shop" style="font-weight: 600;">-</div></div>
                        <div><span style="font-size: var(--text-xs); color: var(--color-text-muted);">Phone</span><div id="summary_phone" style="font-weight: 600;">-</div></div>
                        <div><span style="font-size: var(--text-xs); color: var(--color-text-muted);">Stall Type</span><div id="summary_stall" style="font-weight: 600;">-</div></div>
                        <div><span style="font-size: var(--text-xs); color: var(--color-text-muted);">Size</span><div id="summary_size" style="font-weight: 600;">- sq ft</div></div>
                        <div><span style="font-size: var(--text-xs); color: var(--color-text-muted);">Amount</span><div id="summary_amount" style="font-weight: 700; color: var(--color-success); font-size: var(--text-lg);">₹0.00</div></div>
                        <div><span style="font-size: var(--text-xs); color: var(--color-text-muted);">Payment</span><div id="summary_payment" style="font-weight: 600; text-transform: uppercase;">-</div></div>
                    </div>
                </div>

                <input type="hidden" id="shop_id" name="shop_id" value="<?= htmlspecialchars(isset($form_data['shop_id']) ? $form_data['shop_id'] : '') ?>">

                <div class="form-actions" style="margin-top: var(--space-6); display: flex; gap: var(--space-3);">
                    <button type="button" class="btn btn-secondary btn-block" onclick="prevStep(2)">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                    </button>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Generate Collection Request
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
// Rates from the database, injected server-side.
var DIGISHULK_RATES = <?= json_encode($ratesMap, JSON_UNESCAPED_UNICODE) ?>;

var currentStep = 1;
var userEditedAmount = false;

function nextStep(step) {
    if (validateStep(currentStep)) { goToStep(step); }
}
function prevStep(step) { goToStep(step); }

function goToStep(step) {
    document.querySelector('.form-step[data-step="' + currentStep + '"]').style.display = 'none';
    document.querySelector('.step-indicator[data-step="' + currentStep + '"]').classList.remove('active');
    document.querySelector('.form-step[data-step="' + step + '"]').style.display = 'block';
    document.querySelector('.step-indicator[data-step="' + step + '"]').classList.add('active');
    currentStep = step;
    if (step === 3) { updateSummary(); }
}

function validateStep(step) {
    var stepEl = document.querySelector('.form-step[data-step="' + step + '"]');
    var requiredFields = stepEl.querySelectorAll('[required]');
    var valid = true;

    stepEl.querySelectorAll('.form-error.dynamic').forEach(function (el) { el.remove(); });
    stepEl.querySelectorAll('[aria-invalid="true"]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
    stepEl.querySelectorAll('.form-input-error').forEach(function (el) { el.classList.remove('form-input-error'); });

    requiredFields.forEach(function (field) {
        if (!field.value.trim()) {
            valid = false;
            field.setAttribute('aria-invalid', 'true');
            field.classList.add('form-input-error');
            var error = document.createElement('p');
            error.className = 'form-error dynamic';
            error.textContent = 'This field is required';
            field.parentNode.appendChild(error);
        }
    });

    var phone = document.getElementById('phone');
    if (phone.value && !/^[0-9]{10}$/.test(phone.value)) {
        valid = false;
        phone.classList.add('form-input-error');
        var e = document.createElement('p');
        e.className = 'form-error dynamic';
        e.textContent = 'Enter a valid 10-digit mobile number';
        phone.parentNode.appendChild(e);
    }

    var amount = document.getElementById('amount');
    if (amount.value && parseFloat(amount.value) < 1) {
        valid = false;
        amount.classList.add('form-input-error');
        var e2 = document.createElement('p');
        e2.className = 'form-error dynamic';
        e2.textContent = 'Amount must be at least ₹1';
        amount.parentNode.appendChild(e2);
    }

    if (!valid) {
        var firstInvalid = stepEl.querySelector('[aria-invalid="true"]');
        if (firstInvalid) firstInvalid.focus();
    }

    return valid;
}

function updateSummary() {
    document.getElementById('summary_shop').textContent = document.getElementById('shop_name').value || '-';
    document.getElementById('summary_phone').textContent = document.getElementById('phone').value || '-';

    var stallType = document.getElementById('stall_type').value;
    var stallOther = document.getElementById('stall_type_other').value;
    document.getElementById('summary_stall').textContent = (stallType === 'Other' ? stallOther : stallType) || '-';

    var sizeVal = document.getElementById('size').value;
    document.getElementById('summary_size').textContent = (sizeVal ? sizeVal : '-') + ' sq ft';

    var amtVal = parseFloat(document.getElementById('amount').value || 0);
    document.getElementById('summary_amount').textContent = '₹' + amtVal.toFixed(2);
    document.getElementById('summary_payment').textContent = document.getElementById('payment_mode').value || '-';
}

function toggleOtherType() {
    var select = document.getElementById('stall_type');
    var otherWrapper = document.getElementById('stall_type_other_wrapper');
    var otherInput = document.getElementById('stall_type_other');

    if (select.value === 'Other') {
        otherWrapper.style.display = 'block';
        otherInput.required = true;
    } else {
        otherWrapper.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
    recomputeSuggestedFee();
}

function recomputeSuggestedFee() {
    var stallType = document.getElementById('stall_type').value;
    var stallOther = document.getElementById('stall_type_other').value;
    var size = parseFloat(document.getElementById('size').value || 0);
    var amountEl = document.getElementById('amount');
    var helpEl = document.getElementById('amount-help');
    var suggestedHidden = document.getElementById('suggested_amount');
    var rateHidden = document.getElementById('rate_per_sqft');

    var effectiveType = (stallType === 'Other') ? stallOther : stallType;
    var rate = DIGISHULK_RATES[effectiveType];

    if (rate === undefined || size <= 0) {
        // No suggestion possible.
        suggestedHidden.value = '';
        rateHidden.value = '';
        helpEl.textContent = 'Enter final amount in rupees';
        return;
    }

    var suggested = Math.round(size * rate * 100) / 100;

    suggestedHidden.value = suggested.toFixed(2);
    rateHidden.value = rate.toFixed(2);

    if (!userEditedAmount) {
        amountEl.value = suggested.toFixed(2);
        helpEl.textContent = 'Suggested: ₹' + suggested.toFixed(2) +
            ' (' + size + ' sqft × ₹' + rate.toFixed(2) + ')';
    } else {
        helpEl.textContent = 'Suggested was ₹' + suggested.toFixed(2) +
            ' — you have overridden it.';
    }

    updateSummary();
}

// Track whether the user manually edits the amount.
document.getElementById('amount').addEventListener('input', function () {
    userEditedAmount = true;
    recomputeSuggestedFee();
});

// Recompute when stall type or size changes.
document.getElementById('stall_type').addEventListener('change', function () {
    // Reset the override flag when stall type changes — new suggestion applies.
    userEditedAmount = false;
    recomputeSuggestedFee();
});
document.getElementById('stall_type_other').addEventListener('input', recomputeSuggestedFee);
document.getElementById('size').addEventListener('input', function () {
    userEditedAmount = false;
    recomputeSuggestedFee();
});

// Keep the summary in sync on any field change.
['shop_name','phone','stall_type','stall_type_other','size','payment_mode'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateSummary);
        el.addEventListener('change', updateSummary);
    }
});

// Kick off with whatever was pre-filled.
recomputeSuggestedFee();
updateSummary();
</script>
<script src="assets/js/spot_tax.js"></script>
<?php include 'footer.php'; ?>