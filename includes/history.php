<?php
include 'header.php';
if(!isset($_SESSION['user_id'])){ header("Location: logout.php"); exit(); }

$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$view = $_GET['view'] ?? 'tax'; // 'tax' or 'seizures'
?>

<div class="page">

    <!-- Page Header -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0;">History</h1>
            <p class="subtitle" style="margin-top: var(--space-1);">View and filter collection records</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" role="tablist" aria-label="History views">
        <button class="tab-trigger <?= $view === 'tax' ? 'active' : '' ?>" role="tab" aria-selected="<?= $view === 'tax' ? 'true' : 'false' ?>" aria-controls="tax-panel" id="tax-tab" onclick="location.href='history.php?view=tax'">
            <i class="fa-solid fa-receipt" aria-hidden="true"></i>
            <?php echo __('spot_tax_tab'); ?>
        </button>
        <button class="tab-trigger <?= $view === 'seizures' ? 'active' : '' ?>" role="tab" aria-selected="<?= $view === 'seizures' ? 'true' : 'false' ?>" aria-controls="seizures-panel" id="seizures-tab" onclick="location.href='history.php?view=seizures'">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <?php echo __('seizures_tab'); ?>
        </button>
    </div>

    <?php if($view === 'tax'): ?>
        <?php include 'history_tax_section.php'; ?>
    <?php else: ?>
        <?php include 'history_seizures_section.php'; ?>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>