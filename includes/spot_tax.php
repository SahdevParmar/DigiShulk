<?php
session_start();

if(!isset($_SESSION['role']) || $_SESSION['role']!='inspector'){
    header("Location: logout.php");
    exit();
}

include 'db_connect.php';
include 'header.php';
?>

<div class="page-center">
    <div class="card">

        <h1><?php echo __('new_entry'); ?></h1>

        <form action="payment.php" method="POST">

            <div class="autocomplete-wrapper">

            <input
                type="text"
                id="shop_name"
                name="shop_name"
                placeholder="<?php echo __('shop_name'); ?>"
                autocomplete="off"
                required>

            <div id="shopSuggestions" class="autocomplete-box"></div>

            </div>

            <input
                type="text"
                id="shop_address"
                name="shop_address"
                placeholder="<?php echo __('address'); ?>"
                required>
           <input
                type="tel"
                id="phone"
                name="phone"
                placeholder="<?php echo __('phone'); ?>"
                required>

           <label>Select Stall Type:</label>
            <select name="stall_type" id="stall_type" required onchange="toggleOtherType()">
                <option value="Rekdi">Rekdi</option>
                <option value="Mandap">Mandap</option>
                <option value="Chhajli">Chhajli</option>
                <option value="Other">Other (type manually)</option>
            </select>
            <input type="text" name="stall_type_other" id="stall_type_other" 
                        placeholder="Describe the stall/item type" style="display:none;">
            <label>Amount to Charge (₹)</label>
            <input type="number" name="amount" step="0.01" min="1" placeholder="Enter amount" required>
            <input type="hidden" id="shop_id" name="shop_id">

            <label><?php echo __('payment_mode'); ?></label>

            <select name="payment_mode" required>
                <option value="cash"><?php echo __('cash'); ?></option>
                <option value="upi"><?php echo __('upi'); ?></option>
            </select>

            <label><?php echo __('enter_size'); ?></label>

            <input type="number" name="size"
                placeholder="<?php echo __('size_placeholder'); ?>" required>
                

            <button type="submit">
                Create Collection
            </button>

        </form>

    </div>
</div>
<script>
function toggleOtherType(){
    const select = document.getElementById('stall_type');
    const otherBox = document.getElementById('stall_type_other');
    if(select.value === 'Other'){
        otherBox.style.display = 'block';
        otherBox.required = true;
    } else {
        otherBox.style.display = 'none';
        otherBox.required = false;
        otherBox.value = '';
    }
}
</script>
<script src="assets/js/spot_tax.js"></script>
</body>
</html>