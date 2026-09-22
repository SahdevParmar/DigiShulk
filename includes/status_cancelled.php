<?php
// Cancelled/Failed Status View - Included from payment.php when status is cancelled or failed
$txn = $txn ?? [];
$transaction_id = $transaction_id ?? 0;
$status = $txn['status'] ?? 'cancelled';
$statusLabel = ucfirst($status);
$statusIcon = $status === 'cancelled' ? 'fa-solid fa-ban' : 'fa-solid fa-triangle-exclamation';
$statusColor = $status === 'cancelled' ? 'var(--color-warning)' : 'var(--color-danger)';
$statusBg = $status === 'cancelled' ? 'var(--color-warning-light)' : 'var(--color-danger-light)';
$statusBorder = $status === 'cancelled' ? 'var(--color-warning)' : 'var(--color-danger)';
?>
<div class="page">
    <div class="card" style="max-width: 480px;">

        <!-- Status Header -->
        <div class="card-header" style="background: <?php echo $statusBg; ?>; border-bottom: 1px solid <?php echo $statusBorder; ?>;">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div class="stat-icon" style="width: 48px; height: 48px; background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; border-radius: var(--radius-md);">
                    <i class="<?php echo $statusIcon; ?>" aria-hidden="true" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                    <h2 class="card-title" style="margin: 0; color: <?php echo $statusColor; ?>;"><?php echo $statusLabel; ?></h2>
                    <p class="card-subtitle" style="margin: 0;">Transaction was <?php echo strtolower($statusLabel); ?></p>
                </div>
            </div>
        </div>

        <div class="card-body" style="text-align: center;">

            <!-- Amount Display -->
            <div style="margin-bottom: var(--space-6); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-lg);">
                <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin: 0 0 var(--space-2);">Transaction Amount</p>
                <div style="font-size: var(--text-4xl); font-weight: 700; color: var(--color-text);">
                    ₹<?php echo number_format($txn['total_amount'] ?? 0,2); ?>
                </div>
                <span class="badge badge-<?= ($txn['payment_mode'] ?? '') === 'upi' ? 'primary' : 'success' ?>" style="font-size: var(--text-sm); vertical-align: middle; margin-left: var(--space-2);">
                    <?php echo strtoupper($txn['payment_mode'] ?? ''); ?>
                </span>
            </div>

            <!-- Shop Details -->
            <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-md); text-align: left;">
                <p style="margin: 0 0 var(--space-2); font-size: var(--text-sm); color: var(--color-text-muted);">Shop Details</p>
                <p style="margin: 0; font-size: var(--text-base);"><strong><?php echo htmlspecialchars($txn['shop_name'] ?? ''); ?></strong></p>
                <p style="margin: var(--space-1) 0 0; font-size: var(--text-sm); color: var(--color-text-muted);"><?php echo htmlspecialchars($txn['shopkeeper_phone'] ?? ''); ?></p>
                <p style="margin: var(--space-1) 0 0; font-size: var(--text-sm); color: var(--color-text-muted);">Stall: <?php echo htmlspecialchars($txn['stall_type'] ?? ''); ?></p>
            </div>

            <!-- Status Message -->
            <div class="alert alert-<?php echo $status === 'cancelled' ? 'warning' : 'danger'; ?>" style="margin-top: var(--space-6); text-align: left;">
                <i class="<?php echo $statusIcon; ?> alert-icon" aria-hidden="true"></i>
                <div class="alert-content">
                    <p class="alert-title"><?php echo $statusLabel; ?></p>
                    <p class="alert-message">
                        <?php if ($status === 'cancelled'): ?>
                            This transaction was cancelled. No payment was processed.
                        <?php else: ?>
                            This transaction failed. Please try again or contact support.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if ($status === 'cancelled'): ?>
                <!-- Allow retry for cancelled -->
                <a href="spot_tax.php" class="btn btn-primary btn-block" style="margin-top: var(--space-4);">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Create New Collection
                </a>
            <?php endif; ?>

            <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top: var(--space-3);">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back to Dashboard
            </a>

        </div>
    </div>
</div>

<?php
include 'footer.php';
exit();
?>