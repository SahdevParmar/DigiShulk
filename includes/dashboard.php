<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'inspector') {
    header("Location: logout.php");
    exit();
}

include 'db_connect.php';
include 'header.php';

$inspector = $_SESSION['user_id'];

/* -----------------------------
   Today's Statistics
------------------------------*/

$stmt = $conn->prepare("
SELECT
COALESCE(SUM(total_amount),0) AS total,
COALESCE(SUM(CASE WHEN payment_mode='cash' THEN total_amount ELSE 0 END),0) AS cash_total,
COALESCE(SUM(CASE WHEN payment_mode='upi' THEN total_amount ELSE 0 END),0) AS upi_total
FROM transactions
WHERE inspector_id=?
AND DATE(created_at)=CURDATE()
");

$stmt->bind_param("i",$inspector);
$stmt->execute();
$stats=$stmt->get_result()->fetch_assoc();

/* -----------------------------
   Pending Collections (Need Attention)
------------------------------*/
$stmt = $conn->prepare("
SELECT COUNT(*) as count, COALESCE(SUM(total_amount),0) as total
FROM transactions
WHERE inspector_id=?
AND status='pending'
");
$stmt->bind_param("i", $inspector);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();

/* -----------------------------
   Recent Collections
------------------------------*/

$stmt=$conn->prepare("
SELECT shop_name,total_amount,payment_mode,created_at,status
FROM transactions
WHERE inspector_id=?
ORDER BY created_at DESC
LIMIT 5
");

$stmt->bind_param("i",$inspector);
$stmt->execute();

$recent=$stmt->get_result();
?>

<div class="page">

    <!-- Page Header -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0;">Dashboard</h1>
            <p class="subtitle" style="margin-top: var(--space-1);">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Inspector'); ?></p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Today's Collection</div>
                    <div class="stat-value">₹<?=number_format($stats['total'],2)?></div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Cash</div>
                    <div class="stat-value">₹<?=number_format($stats['cash_total'],2)?></div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">UPI</div>
                    <div class="stat-value">₹<?=number_format($stats['upi_total'],2)?></div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <?php if ($pending['count'] > 0): ?>
        <div class="stat-card card-accent-warning">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="stat-label">Pending Collections</div>
                    <div class="stat-value"><?= $pending['count'] ?></div>
                    <div class="stat-change stat-change-warning" style="margin-top: var(--space-1);">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        ₹<?= number_format($pending['total'], 2) ?> awaiting confirmation
                    </div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Primary Actions -->
    <div style="margin-top: var(--space-6); margin-bottom: var(--space-6);">
        <div style="display: flex; flex-wrap: wrap; gap: var(--space-3);">
            <a href="spot_tax.php" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                New Spot Tax
            </a>
            <a href="seizure_form.php" class="btn btn-secondary btn-lg">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                New Seizure Report
            </a>
            <a href="history.php" class="btn btn-ghost btn-lg">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                View History
            </a>
        </div>
    </div>

    <!-- Recent Collections -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);">
                <div>
                    <h2 class="card-title" style="font-size: var(--text-xl);">Recent Collections</h2>
                    <p class="card-subtitle">Your latest 5 transactions</p>
                </div>
                <a href="history.php" class="btn btn-sm btn-ghost">View All</a>
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th>Amount</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent->num_rows > 0): ?>
                            <?php while($row=$recent->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?=htmlspecialchars($row['shop_name'])?></strong>
                                    </td>
                                    <td>₹<?=number_format($row['total_amount'],2)?></td>
                                    <td>
                                        <span class="badge badge-<?= $row['payment_mode'] === 'upi' ? 'primary' : 'neutral' ?>">
                                            <?=strtoupper($row['payment_mode'])?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <i class="fa-solid fa-<?= $row['status'] === 'paid' ? 'check' : ($row['status'] === 'pending' ? 'clock' : 'xmark') ?>" aria-hidden="true"></i>
                                            <?=ucfirst($row['status'])?>
                                        </span>
                                    </td>
                                    <td><?=date("d M Y, h:i A", strtotime($row['created_at']))?></td>
                                    <td>
                                        <a href="payment.php?id=<?= $row['transaction_id'] ?>" class="table-action-btn" style="padding: var(--space-1) var(--space-2); font-size: var(--text-xs);">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: var(--space-8); color: var(--color-text-muted);">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-3);">
                                        <i class="fa-solid fa-receipt" style="font-size: 2rem; color: var(--color-text-subtle);" aria-hidden="true"></i>
                                        <p>No collections yet</p>
                                        <a href="spot_tax.php" class="btn btn-primary btn-sm">Create First Collection</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>