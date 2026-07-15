<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role']!='inspector'){
    header("Location: logout.php");
    exit();
}
include 'db_connect.php';
include 'header.php'; // Already connects to lang_engine.php internally
?>
<div style="display: flex; justify-content: center; padding-top: 30px;">
    <div class="card">
        <h1><?php echo __('new_entry'); ?></h1>

        <form action="calculate_tax.php" method="POST">
            <input type="text" name="shop_name" placeholder="<?php echo __('shop_name'); ?>" required>
            <input type="text" name="shop_address" placeholder="<?php echo __('address'); ?>" required>
            <input type="tel" name="phone" placeholder="<?php echo __('phone'); ?>" required >
            
            <label><?php echo __('select_stall'); ?></label>
            <select name="stall_type" required>
                <option value="Rekdi"><?php echo __('rekdi'); ?></option>
                <option value="Mandap"><?php echo __('mandap'); ?></option>
                <option value="Chhajli"><?php echo __('chhajli'); ?></option>
            </select>
            
            <label><?php echo __('payment_mode'); ?></label>
            <select name="payment_mode" required>
                <option value="cash"><?php echo __('cash'); ?></option>
                <option value="upi"><?php echo __('upi'); ?></option>
            </select>
            
            <label><?php echo __('enter_size'); ?></label>
            <input type="number" name="size" placeholder="<?php echo __('size_placeholder'); ?>" required>
            
            <button type="submit" style="width: 100%; max-width: 100%; margin-top: 15px;">
                <?php echo __('btn_calculate'); ?>
            </button>
        </form>
    </div>
</div>
</body>
</html>