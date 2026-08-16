<?php
session_start();

// --- POST LOGIC ---
// This block handles the form submission and redirects. It must be before any HTML output.
if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['amount'])){
    include 'db_connect.php'; // DB connection is needed for the POST logic.

    $stall_type = trim($_POST['stall_type'] ?? '');
    if($stall_type === 'Other' && !empty($_POST['stall_type_other'])){
        $stall_type = trim($_POST['stall_type_other']);
    }

    $area = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_FLOAT) ?: 0;
    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_address = trim($_POST['shop_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $payment_mode = $_POST['payment_mode'] ?? '';
    $total_amount = floatval($_POST['amount']);

    // Validation
    $errors = [];
    if ($shop_name === '') $errors['shop_name'] = 'Shop name is required';
    if ($shop_address === '') $errors['shop_address'] = 'Address is required';
    if ($phone === '') {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = 'Enter a valid 10-digit mobile number';
    }
    if ($total_amount <= 0) $errors['amount'] = 'Amount must be at least ₹1';
    if (!in_array($payment_mode, ['cash', 'upi'], true)) $errors['payment_mode'] = 'Select a payment mode';

    if (!empty($errors)) {
        // Store errors and form data in session, redirect back to form
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: spot_tax.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO transactions (inspector_id, stall_type, area_sqft, total_amount, shop_name, shop_address, shopkeeper_phone, payment_mode, status) VALUES (?,?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isidssss", $_SESSION['user_id'], $stall_type, $area, $total_amount, $shop_name, $shop_address, $phone, $payment_mode);
    $stmt->execute();
    $transaction_id = $conn->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO shops (shop_name, address, phone, stall_type, last_amount, last_visit) VALUES (?, ?, ?, ?, ?, CURDATE())
                              ON DUPLICATE KEY UPDATE address=VALUES(address), stall_type=VALUES(stall_type), last_amount=VALUES(last_amount), last_visit=CURDATE()");
    $stmt2->bind_param("ssssd", $shop_name, $shop_address, $phone, $stall_type, $total_amount);
    $stmt2->execute();

    $receipt_number = 'RMC-' . date('Ymd') . '-' . str_pad($transaction_id, 4, '0', STR_PAD_LEFT);
    $stmt3 = $conn->prepare("UPDATE transactions SET receipt_number = ? WHERE transaction_id = ?");
    $stmt3->bind_param("si", $receipt_number, $transaction_id);
    $stmt3->execute();

    header("Location: payment.php?id=" . $transaction_id);
    exit();
}

// --- GET LOGIC & HTML RENDERING ---
include 'db_connect.php';
include 'config.php';
include 'header.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'inspector') {
    http_response_code(403);
    echo "<div class='card' style='max-width: 400px; margin: var(--space-6) auto; text-align: center;'><div class='card-body'><div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation alert-icon' aria-hidden='true'></i><div class='alert-content'><p class='alert-title'>Unauthorized Access</p></div></div></div></div>";
    include 'footer.php';
    exit();
}

$transaction_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$show_receipt = filter_input(INPUT_GET, 'paid', FILTER_VALIDATE_INT) == 1;

if (!$transaction_id) {
    echo "<div class='card' style='max-width: 400px; margin: var(--space-6) auto; text-align: center;'><div class='card-body'><div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation alert-icon' aria-hidden='true'></i><div class='alert-content'><p class='alert-title'>Invalid transaction</p></div></div></div></div>";
    include 'footer.php';
    exit();
}

$stmt = $conn->prepare("SELECT t.*, u.full_name as inspector_name FROM transactions t JOIN users u ON t.inspector_id = u.user_id WHERE t.transaction_id = ? AND t.inspector_id = ?");
$stmt->bind_param("ii", $transaction_id, $_SESSION['user_id']);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if(!$txn) {
    echo "<div class='card' style='max-width: 400px; margin: var(--space-6) auto; text-align: center;'><div class='card-body'><div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation alert-icon' aria-hidden='true'></i><div class='alert-content'><p class='alert-title'>Transaction not found</p></div></div></div></div>";
    include 'footer.php';
    exit();
}

// If already paid, just show the receipt
if($txn['status'] === 'paid' || $show_receipt){
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
}

// ---------- CASH Flow ----------
if($txn['payment_mode'] === 'cash'){
?>
<div class="page">
    <div class="card" style="max-width: 480px;">
        <div class="card-header">
            <h2 class="card-title" style="margin: 0;">Cash Collection</h2>
        </div>
        <div class="card-body" style="text-align: center;">
            <div class="alert alert-success" style="text-align: left;">
                <i class="fa-solid fa-circle-check alert-icon" aria-hidden="true"></i>
                <div class="alert-content">
                    <p class="alert-title" style="margin: 0;">Amount to Collect</p>
                    <p class="alert-message" style="margin: var(--space-2) 0 0; font-size: var(--text-3xl); font-weight: 700; color: var(--color-success);">₹<?php echo number_format($txn['total_amount'],2); ?></p>
                </div>
            </div>

            <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-md); text-align: left;">
                <p style="margin: 0 0 var(--space-2); font-size: var(--text-sm); color: var(--color-text-muted);">Shop Details</p>
                <p style="margin: 0; font-size: var(--text-base);"><strong><?php echo htmlspecialchars($txn['shop_name']); ?></strong></p>
                <p style="margin: var(--space-1) 0 0; font-size: var(--text-sm); color: var(--color-text-muted);"><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></p>
            </div>

            <form method="POST" action="confirm_cash.php" style="margin-top: var(--space-6);">
                <input type="hidden" name="id" value="<?php echo $transaction_id; ?>">
                <button type="submit" class="btn btn-success btn-block btn-lg">
                    <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                    Confirm Cash Received
                </button>
            </form>

            <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top: var(--space-3);">
                Cancel
            </a>
        </div>
    </div>
</div>
<?php
    include 'footer.php';
    exit();
}

// ---------- UPI Flow ----------
$amount_paise = $txn['total_amount'] * 100;

// Only create Razorpay order if not already created
$order_id = $txn['payment_ref'];
if(empty($order_id)) {
    if(defined('RAZORPAY_KEY_ID') && defined('RAZORPAY_KEY_SECRET')){
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "amount" => $amount_paise,
            "currency" => "INR",
            "receipt" => "txn_" . $transaction_id,
            "payment_capture" => 1
        ]));
        $response = curl_exec($ch);
        $order = json_decode($response, true);
        curl_close($ch);

        if(isset($order['id'])){
            $order_id = $order['id'];
            $stmt = $conn->prepare("UPDATE transactions SET payment_ref = ? WHERE transaction_id = ?");
            $stmt->bind_param("si", $order_id, $transaction_id);
            $stmt->execute();
        }
    }
}
?>

<div class="page">
    <div class="card" style="max-width: 480px;">
        <div class="card-header">
            <h2 class="card-title" style="margin: 0;">UPI Payment</h2>
        </div>
        <div class="card-body" style="text-align: center;">
            <div class="alert alert-primary" style="text-align: left;">
                <i class="fa-solid fa-qrcode alert-icon" aria-hidden="true"></i>
                <div class="alert-content">
                    <p class="alert-title" style="margin: 0;">Amount to Pay</p>
                    <p class="alert-message" style="margin: var(--space-2) 0 0; font-size: var(--text-3xl); font-weight: 700; color: var(--color-primary);">₹<?php echo number_format($txn['total_amount'],2); ?></p>
                </div>
            </div>

            <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-md); text-align: left;">
                <p style="margin: 0 0 var(--space-2); font-size: var(--text-sm); color: var(--color-text-muted);">Shop Details</p>
                <p style="margin: 0; font-size: var(--text-base);"><strong><?php echo htmlspecialchars($txn['shop_name']); ?></strong></p>
                <p style="margin: var(--space-1) 0 0; font-size: var(--text-sm); color: var(--color-text-muted);"><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></p>
            </div>

            <?php if(!empty($order_id)): ?>
                <button id="payBtn" class="btn btn-primary btn-block btn-lg" style="margin-top: var(--space-6);">
                    <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                    Pay with UPI
                </button>
                <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                <script>
                document.getElementById('payBtn').onclick = function(e){
                    var options = {
                        "key": "<?php echo RAZORPAY_KEY_ID; ?>",
                        "amount": "<?php echo $amount_paise; ?>",
                        "currency": "INR",
                        "order_id": "<?php echo $order_id; ?>",
                        "name": "RMC DigiShulk",
                        "description": "Spot Tax Payment",
                        "prefill": {
                            "contact": <?php echo json_encode($txn['shopkeeper_phone']); ?>,
                            "name": <?php echo json_encode($txn['shop_name']); ?>
                        },
                        "config": {
                            "display": {
                                "hide": [{ "method": "card" }, { "method": "netbanking" }, { "method": "wallet" }, { "method": "paylater" }, { "method": "emi" }]
                            }
                        },
                        "handler": function(response){
                            // Razorpay succeeded. Webhook will mark it paid. Polling or manual redirect:
                            window.location.href = "payment.php?id=<?php echo $transaction_id; ?>&paid=1";
                        },
                        "theme": { "color": "#2563eb" }
                    };
                    var rzp = new Razorpay(options);
                    rzp.open();
                    e.preventDefault();
                };
                </script>
            <?php else: ?>
                <div class="alert alert-danger" style="margin-top: var(--space-6);">
                    <i class="fa-solid fa-triangle-exclamation alert-icon" aria-hidden="true"></i>
                    <div class="alert-content">
                        <p class="alert-title">UPI Gateway Not Configured</p>
                        <p class="alert-message">Please use cash payment method instead.</p>
                    </div>
                </div>
            <?php endif; ?>

            <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top: var(--space-4);">
                Cancel
            </a>
        </div>
    </div>
</div>
<?php
include 'footer.php';
?>