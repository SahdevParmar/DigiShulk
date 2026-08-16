<?php
// Receipt View - Included from payment.php when status is paid
$txn = $txn ?? [];
$transaction_id = $transaction_id ?? 0;
?>
<div class="page">
    <div class="card" id="receiptCard" style="max-width: 480px;">
        <div class="card-body" style="text-align: center;">
            <div style="font-size: 48px; color: var(--color-success); margin-bottom: var(--space-3);">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            </div>
            <h2 style="color: var(--color-success); margin: 0 0 var(--space-2);">Payment Confirmed</h2>
            <p style="color: var(--color-text-muted); margin: 0 0 var(--space-4);">Receipt #<?php echo htmlspecialchars($txn['receipt_number'] ?? ''); ?></p>

            <hr style="margin: var(--space-4) 0; border: none; border-top: 1px dashed var(--color-border);">

            <div style="font-size: var(--text-4xl); font-weight: 700; text-align: center; margin-bottom: var(--space-6); color: var(--color-text);">
                ₹<?php echo number_format($txn['total_amount'],2); ?>
                <span class="badge badge-<?= $txn['payment_mode'] === 'upi' ? 'primary' : 'success' ?>" style="font-size: var(--text-sm); vertical-align: middle; margin-left: var(--space-2);"><?php echo strtoupper($txn['payment_mode']); ?></span>
            </div>

            <table class="table" style="font-size: var(--text-base); margin-bottom: var(--space-6);">
                <tbody>
                    <tr><td style="color: var(--color-text-muted); width: 40%;">Shop Name</td><td style="text-align: right; font-weight: 600;"><?php echo htmlspecialchars($txn['shop_name']); ?></td></tr>
                    <tr><td style="color: var(--color-text-muted);">Phone</td><td style="text-align: right; font-weight: 600;"><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></td></tr>
                    <tr><td style="color: var(--color-text-muted);">Stall Type</td><td style="text-align: right; font-weight: 600;"><?php echo htmlspecialchars($txn['stall_type']); ?></td></tr>
                    <tr><td style="color: var(--color-text-muted);">Date & Time</td><td style="text-align: right; font-weight: 600;"><?php echo date('d M Y, h:i A', strtotime($txn['created_at'])); ?></td></tr>
                    <tr><td style="color: var(--color-text-muted);">Inspector</td><td style="text-align: right; font-weight: 600;"><?php echo htmlspecialchars($txn['inspector_name'] ?? ''); ?></td></tr>
                </tbody>
            </table>

            <!-- Verification QR Code -->
            <div style="margin-bottom: var(--space-6); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-md); text-align: center;">
                <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin: 0 0 var(--space-3);">Scan to verify receipt</p>
                <div id="receiptQR" style="display: inline-block; padding: var(--space-2); background: white; border-radius: var(--radius-sm);"></div>
                <p style="font-size: var(--text-xs); color: var(--color-text-subtle); margin-top: var(--space-2);">
                    Receipt: <?php echo htmlspecialchars($txn['receipt_number'] ?? ''); ?>
                </p>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
            <script>
            (function() {
                var qrData = "DigiShulk Receipt Verification\nReceipt: <?php echo htmlspecialchars($txn['receipt_number'] ?? ''); ?>\nAmount: ₹<?php echo number_format($txn['total_amount'],2); ?>\nShop: <?php echo htmlspecialchars($txn['shop_name']); ?>\nDate: <?php echo date('d M Y, h:i A', strtotime($txn['created_at'])); ?>\nTransaction ID: <?php echo $transaction_id; ?>";
                new QRCode(document.getElementById("receiptQR"), {
                    text: qrData,
                    width: 120,
                    height: 120,
                    colorDark: "#111827",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            })();
            </script>

            <div class="form-actions" style="flex-direction: column; gap: var(--space-2); border-top: none; padding-top: 0; margin-top: 0;" class="no-print">
                <div style="display: flex; gap: var(--space-2); width: 100%;">
                    <button onclick="window.print()" class="btn btn-secondary btn-block" style="flex: 1;">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                        Print
                    </button>
                    <a href="generate_receipt_pdf.php?id=<?php echo $transaction_id; ?>" class="btn btn-success btn-block" style="flex: 1; text-align: center;">
                        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                        Download PDF
                    </a>
                </div>
                <a href="dashboard.php" class="btn btn-primary btn-block">
                    Done
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        .app-sidebar,
        .app-topbar,
        .app-bottom-nav,
        .sidebar-toggle,
        .sidebar-overlay,
        .search-overlay,
        .no-print,
        .form-actions {
            display: none !important;
        }
        .app-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .page {
            padding: 0 !important;
        }
        #receiptCard {
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>
<?php
include 'footer.php';
exit();
?>