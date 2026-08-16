<?php
session_start();
include 'db_connect.php';
if(!isset($_SESSION['role'])|| $_SESSION['role']!='admin'){
    header("Location: logout.php");
    exit();
}
include 'header.php';

$transaction_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$transaction_id) {
    echo "<div class='card' style='max-width: 400px; margin: var(--space-6) auto; text-align: center;'><div class='card-body'><div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation alert-icon' aria-hidden='true'></i><div class='alert-content'><p class='alert-title'>Invalid transaction</p></div></div></div></div>";
    include 'footer.php';
    exit();
}

$stmt = $conn->prepare("SELECT t.*, u.username, u.full_name FROM transactions t JOIN users u ON t.inspector_id = u.user_id WHERE t.transaction_id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if(!$txn) {
    echo "<div class='card' style='max-width: 400px; margin: var(--space-6) auto; text-align: center;'><div class='card-body'><div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation alert-icon' aria-hidden='true'></i><div class='alert-content'><p class='alert-title'>Transaction not found</p></div></div></div></div>";
    include 'footer.php';
    exit();
}
?>

<div class="page">
    <div class="card" style="max-width: 800px;">

        <!-- Header -->
        <div class="card-header">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4);">
                <div>
                    <h1 class="card-title" style="font-size: var(--text-2xl); margin: 0;">Transaction Detail</h1>
                    <p class="card-subtitle" style="margin: 0;">Transaction #<?php echo htmlspecialchars($txn['receipt_number'] ?? ''); ?></p>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <a href="history.php" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        Back to History
                    </a>
                    <?php if ($txn['status'] === 'paid'): ?>
                    <a href="generate_receipt_pdf.php?id=<?php echo $transaction_id; ?>" class="btn btn-success btn-sm" target="_blank">
                        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                        PDF
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                        Print
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status Badge -->
        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-6); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-lg);">
            <?php
            $statusClass = $txn['status'] === 'paid' ? 'success' : ($txn['status'] === 'pending' ? 'warning' : 'danger');
            $statusIcon = $txn['status'] === 'paid' ? 'check-circle' : ($txn['status'] === 'pending' ? 'clock' : 'xmark-circle');
            ?>
            <span class="badge badge-<?= $statusClass ?> badge-lg" style="font-size: var(--text-base);">
                <i class="fa-solid fa-<?= $statusIcon ?>" aria-hidden="true"></i>
                <?= ucfirst($txn['status']) ?>
            </span>
            <span style="font-size: var(--text-sm); color: var(--color-text-muted);">
                Payment: <strong><?= strtoupper($txn['payment_mode']) ?></strong>
            </span>
        </div>

        <!-- Main Details Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6);">

            <!-- Transaction Info -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title" style="font-size: var(--text-lg);">Transaction Info</h2>
                </div>
                <div class="card-body">
                    <dl style="display: grid; gap: var(--space-3);">
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Receipt #</dt>
                            <dd style="font-weight: 600;"><?php echo htmlspecialchars($txn['receipt_number'] ?? ''); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Transaction ID</dt>
                            <dd style="font-weight: 600;">#<?php echo $txn['transaction_id']; ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Date & Time</dt>
                            <dd><?php echo date('d M Y, h:i A', strtotime($txn['created_at'])); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Stall Type</dt>
                            <dd><?php echo htmlspecialchars($txn['stall_type']); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Area</dt>
                            <dd><?php echo number_format($txn['area_sqft'] ?? 0, 2); ?> sq ft</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Amount Details -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title" style="font-size: var(--text-lg);">Amount Details</h2>
                </div>
                <div class="card-body">
                    <dl style="display: grid; gap: var(--space-3);">
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Total Amount</dt>
                            <dd style="font-size: var(--text-2xl); font-weight: 700; color: var(--color-success);">₹<?php echo number_format($txn['total_amount'],2); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Payment Mode</dt>
                            <dd>
                                <span class="badge badge-<?= $txn['payment_mode'] === 'upi' ? 'primary' : 'success' ?>">
                                    <?= strtoupper($txn['payment_mode']) ?>
                                </span>
                            </dd>
                        </div>
                        <?php if ($txn['payment_mode'] === 'upi' && $txn['payment_ref']): ?>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Razorpay Order</dt>
                            <dd style="font-family: var(--font-mono); font-size: var(--text-sm);"><?php echo htmlspecialchars($txn['payment_ref']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($txn['status'] === 'paid' && $txn['receipt_number']): ?>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Receipt</dt>
                            <dd style="font-family: var(--font-mono); font-size: var(--text-sm);"><?php echo htmlspecialchars($txn['receipt_number']); ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Shop Details -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title" style="font-size: var(--text-lg);">Shop Details</h2>
                </div>
                <div class="card-body">
                    <dl style="display: grid; gap: var(--space-3);">
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Shop Name</dt>
                            <dd style="font-weight: 600;"><?php echo htmlspecialchars($txn['shop_name']); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Phone</dt>
                            <dd><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Address</dt>
                            <dd><?php echo htmlspecialchars($txn['shop_address']); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Inspector Details -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title" style="font-size: var(--text-lg);">Inspector</h2>
                </div>
                <div class="card-body">
                    <dl style="display: grid; gap: var(--space-3);">
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Name</dt>
                            <dd style="font-weight: 600;"><?php echo htmlspecialchars($txn['full_name'] ?? $txn['username']); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Username</dt>
                            <dd>@<?php echo htmlspecialchars($txn['username']); ?></dd>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: var(--space-2); align-items: start;">
                            <dt style="font-size: var(--text-sm); color: var(--color-text-muted);">Role</dt>
                            <dd><span class="badge badge-primary">Inspector</span></dd>
                        </div>
                    </dl>
                </div>
            </div>

        </div>

        <!-- SMS Log -->
        <?php if (!empty($txn['sms_log'])): ?>
        <div class="card" style="margin-top: var(--space-6);">
            <div class="card-header">
                <h2 class="card-title" style="font-size: var(--text-lg);">SMS Log</h2>
            </div>
            <div class="card-body">
                <pre style="background: var(--color-surface-muted); padding: var(--space-4); border-radius: var(--radius-md); overflow-x: auto; font-size: var(--text-xs); white-space: pre-wrap;"><?php echo htmlspecialchars($txn['sms_log']); ?></pre>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>