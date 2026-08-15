<?php
include 'header.php';
if(!isset($_SESSION['user_id'])){ header("Location: logout.php"); exit(); }

$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$view = $_GET['view'] ?? 'tax'; // 'tax' or 'seizures'
?>

<div style="max-width:1000px; margin:0 auto; padding:20px;">

    <!-- Tab switcher -->
    <div style="display:flex; gap:10px; margin-bottom:20px;">
        <a href="history.php?view=tax" class="navbar a" 
           style="padding:10px 20px; border-radius:10px; background:<?php echo $view=='tax' ? 'var(--primary)' : '#e5e7eb'; ?>; color:<?php echo $view=='tax' ? 'white' : 'var(--text)'; ?>;">
            <i class="fa-solid fa-receipt" aria-hidden="true"></i> <?php echo __('spot_tax_tab'); ?>
        </a>
        <a href="history.php?view=seizures" 
           style="padding:10px 20px; border-radius:10px; background:<?php echo $view=='seizures' ? 'var(--primary)' : '#e5e7eb'; ?>; color:<?php echo $view=='seizures' ? 'white' : 'var(--text)'; ?>;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?php echo __('seizures_tab'); ?>
        </a>
    </div>

    <?php if($view === 'tax'): ?>
        <?php include 'history_tax_section.php'; ?>
    <?php else: ?>
        <?php include 'history_seizures_section.php'; ?>
    <?php endif; ?>

</div>
