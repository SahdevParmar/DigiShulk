<?php
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role']!='inspector'){
    header("Location: logout.php");
    exit();
}

include 'db_connect.php';
include 'header.php';
?>

<div class="page">
    <div class="card" style="max-width: 800px;">
        <div class="card-header">
            <h1 class="card-title"><?php echo __('new_entry'); ?></h1>
            <p class="card-subtitle">Create a new spot tax collection entry</p>
        </div>

        <form action="payment.php" method="POST" class="card-body" novalidate>

            <fieldset>
                <legend style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">Shop Details</legend>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="shop_name"><?php echo __('shop_name'); ?> <span class="required" aria-hidden="true">*</span></label>
                        <div class="autocomplete-wrapper" style="position: relative;">
                            <input
                                type="text"
                                id="shop_name"
                                name="shop_name"
                                class="form-input"
                                placeholder="<?php echo __('shop_name'); ?>"
                                autocomplete="off"
                                required
                                aria-autocomplete="list"
                                aria-controls="shopSuggestions">
                            <div id="shopSuggestions" class="autocomplete-box" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); max-height: 200px; overflow-y: auto; display: none;"></div>
                        </div>
                        <p class="form-help">Start typing to search existing shops</p>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="phone"><?php echo __('phone'); ?> <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            class="form-input"
                            placeholder="<?php echo __('phone'); ?>"
                            required
                            pattern="[0-9]{10}"
                            inputmode="numeric">
                        <p class="form-help">10-digit mobile number</p>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="shop_address"><?php echo __('address'); ?> <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="shop_address"
                        name="shop_address"
                        class="form-input"
                        placeholder="<?php echo __('address'); ?>"
                        required>
                </div>
            </fieldset>

            <fieldset style="margin-top: var(--space-6); padding-top: var(--space-6); border-top: 1px solid var(--color-border);">
                <legend style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border);">Stall & Tax Details</legend>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="stall_type">Select Stall Type <span class="required" aria-hidden="true">*</span></label>
                        <select name="stall_type" id="stall_type" class="form-select" required onchange="toggleOtherType()">
                            <option value="">-- Select --</option>
                            <option value="Rekdi">Rekdi</option>
                            <option value="Mandap">Mandap</option>
                            <option value="Chhajli">Chhajli</option>
                            <option value="Other">Other (type manually)</option>
                        </select>
                    </div>

                    <div class="form-field" id="stall_type_other_wrapper" style="display: none;">
                        <label class="form-label" for="stall_type_other">Specify Stall Type <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            name="stall_type_other"
                            id="stall_type_other"
                            class="form-input"
                            placeholder="Describe the stall/item type"
                            aria-required="false">
                    </div>
                </div>

                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="amount">Amount to Charge (₹) <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="number"
                            name="amount"
                            id="amount"
                            class="form-input"
                            step="0.01"
                            min="1"
                            placeholder="Enter amount"
                            required
                            aria-describedby="amount-help">
                        <p class="form-help" id="amount-help">Enter final amount in rupees</p>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="size"><?php echo __('enter_size'); ?> <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="number"
                            name="size"
                            id="size"
                            class="form-input"
                            placeholder="<?php echo __('size_placeholder'); ?>"
                            required
                            min="1"
                            step="0.01"
                            aria-describedby="size-help">
                        <p class="form-help" id="size-help">Size in square feet</p>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="payment_mode"><?php echo __('payment_mode'); ?> <span class="required" aria-hidden="true">*</span></label>
                    <select name="payment_mode" id="payment_mode" class="form-select" required>
                        <option value="">-- Select Payment Mode --</option>
                        <option value="cash"><?php echo __('cash'); ?></option>
                        <option value="upi"><?php echo __('upi'); ?></option>
                    </select>
                </div>
            </fieldset>

            <input type="hidden" id="shop_id" name="shop_id">

            <div class="form-actions">
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Generate Collection Request
                </button>
            </div>

        </form>
    </div>
</div>

<script>
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

// Client-side validation
document.querySelector('form').addEventListener('submit', function(e) {
    const form = this;
    let hasError = false;

    // Clear previous errors
    form.querySelectorAll('.form-error').forEach(el => el.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));

    // Validate required fields
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

    // Validate phone format
    const phone = document.getElementById('phone');
    if (phone.value && !/^[0-9]{10}$/.test(phone.value)) {
        hasError = true;
        phone.setAttribute('aria-invalid', 'true');
        const error = document.createElement('p');
        error.className = 'form-error';
        error.textContent = 'Enter a valid 10-digit mobile number';
        phone.parentNode.appendChild(error);
    }

    // Validate amount
    const amount = document.getElementById('amount');
    if (amount.value && (parseFloat(amount.value) < 1)) {
        hasError = true;
        amount.setAttribute('aria-invalid', 'true');
        const error = document.createElement('p');
        error.className = 'form-error';
        error.textContent = 'Amount must be at least ₹1';
        amount.parentNode.appendChild(error);
    }

    if (hasError) {
        e.preventDefault();
        // Focus first invalid field
        const firstInvalid = form.querySelector('[aria-invalid="true"]');
        if (firstInvalid) firstInvalid.focus();
    }
});
</script>
<script src="assets/js/spot_tax.js"></script>
<?php include 'footer.php'; ?>