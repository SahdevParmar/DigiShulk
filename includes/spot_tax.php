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

        <form action="calculate_tax.php" method="POST">

            <input type="text" name="shop_name"
                placeholder="<?php echo __('shop_name'); ?>" required>

            <input type="text" name="shop_address"
                placeholder="<?php echo __('address'); ?>" required>

            <input type="tel" name="phone"
                placeholder="<?php echo __('phone'); ?>" required>

            <label>Item / Stall Type</label>
            <input type="text" name="stall_type"
                placeholder="Rekdi, Cabin, Banner, Other..." required>

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

</body>
</html>