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

// Status-based flow
$status = $txn['status'] ?? 'pending';

// If already paid, show receipt
if($status === 'paid' || $show_receipt){
    include 'receipt_view.php';
    exit();
}

// If cancelled or failed
if(in_array($status, ['cancelled', 'failed'])){
    include 'status_cancelled.php';
    exit();
}

// ---------- PENDING: Review & Payment ----------
?>
<div class="page">
    <div class="card" style="max-width: 480px;">

        <!-- Status Header -->
        <div class="card-header" style="background: var(--color-warning-light); border-bottom: 1px solid var(--color-warning);">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div class="stat-icon stat-icon-warning" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="card-title" style="margin: 0; color: var(--color-warning);">Payment Pending</h2>
                    <p class="card-subtitle" style="margin: 0;">Transaction created, awaiting payment</p>
                </div>
            </div>
        </div>

        <div class="card-body" style="text-align: center;">

            <!-- Amount Display -->
            <div style="margin-bottom: var(--space-6); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-lg);">
                <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin: 0 0 var(--space-2);">Amount to <?= $txn['payment_mode'] === 'upi' ? 'Pay' : 'Collect' ?></p>
                <div style="font-size: var(--text-4xl); font-weight: 700; color: var(--color-<?= $txn['payment_mode'] === 'upi' ? 'primary' : 'success' ?>);">
                    ₹<?php echo number_format($txn['total_amount'],2); ?>
                </div>
                <span class="badge badge-<?= $txn['payment_mode'] === 'upi' ? 'primary' : 'success' ?>" style="font-size: var(--text-sm); vertical-align: middle; margin-left: var(--space-2);">
                    <?php echo strtoupper($txn['payment_mode']); ?>
                </span>
            </div>

            <!-- Shop Details -->
            <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--color-surface-muted); border-radius: var(--radius-md); text-align: left;">
                <p style="margin: 0 0 var(--space-2); font-size: var(--text-sm); color: var(--color-text-muted);">Shop Details</p>
                <p style="margin: 0; font-size: var(--text-base);"><strong><?php echo htmlspecialchars($txn['shop_name']); ?></strong></p>
                <p style="margin: var(--space-1) 0 0; font-size: var(--text-sm); color: var(--color-text-muted);"><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></p>
                <p style="margin: var(--space-1) 0 0; font-size: var(--text-sm); color: var(--color-text-muted);">Stall: <?php echo htmlspecialchars($txn['stall_type']); ?></p>
            </div>

            <?php if($txn['payment_mode'] === 'cash'): ?>
                <!-- CASH FLOW -->
                <form method="POST" action="confirm_cash.php" style="margin-top: var(--space-6);">
                    <input type="hidden" name="id" value="<?php echo $transaction_id; ?>">
                    <button type="submit" class="btn btn-success btn-block btn-lg">
                        <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                        Confirm Cash Received
                    </button>
                </form>

                <p style="margin-top: var(--space-3); font-size: var(--text-xs); color: var(--color-text-subtle);">
                    Only mark as received after physically collecting cash from the shopkeeper.
                </p>

            <?php else: ?>
                <!-- UPI FLOW -->
                <?php
                $amount_paise = $txn['total_amount'] * 100;
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

                <?php if(!empty($order_id)): ?>
                    <button id="payBtn" class="btn btn-primary btn-block btn-lg" style="margin-top: var(--space-6);">
                        <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                        Pay with UPI
                    </button>

                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                    document.getElementById('payBtn').onclick = function(e){
                        var btn = this;
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Opening UPI...';

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
                                // Razorpay succeeded. Webhook will mark it paid.
                                // Show waiting state
                                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Verifying payment...';
                                btn.disabled = true;

                                // Poll for payment status
                                var pollCount = 0;
                                var pollInterval = setInterval(function() {
                                    pollCount++;
                                    fetch("payment.php?id=<?php echo $transaction_id; ?>&check_status=1", { cache: 'no-cache' })
                                        .then(r => r.json())
                                        .then(data => {
                                            if (data.status === 'paid') {
                                                clearInterval(pollInterval);
                                                window.location.href = "payment.php?id=<?php echo $transaction_id; ?>&paid=1";
                                            } else if (pollCount >= 30) {
                                                clearInterval(pollInterval);
                                                btn.disabled = false;
                                                btn.innerHTML = '<i class="fa-solid fa-qrcode" aria-hidden="true"></i> Pay with UPI';
                                                alert('Payment verification taking longer than expected. Please check status in History.');
                                            }
                                        })
                                        .catch(() => {});
                                }, 2000);
                            },
                            "modal": {
                                "ondismiss": function() {
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fa-solid fa-qrcode" aria-hidden="true"></i> Pay with UPI';
                                }
                            },
                            "theme": { "color": "#2563eb" }
                        };
                        var rzp = new Razorpay(options);
                        rzp.open();
                        e.preventDefault();
                    };
                    </script>

                    <p style="margin-top: var(--space-3); font-size: var(--text-xs); color: var(--color-text-subtle);">
                        After payment, status will update automatically. Check History for confirmation.
                    </p>

                <?php else: ?>
                    <div class="alert alert-danger" style="margin-top: var(--space-6);">
                        <i class="fa-solid fa-triangle-exclamation alert-icon" aria-hidden="true"></i>
                        <div class="alert-content">
                            <p class="alert-title">UPI Gateway Not Configured</p>
                            <p class="alert-message">Please use cash payment method instead.</p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top: var(--space-4);">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back to Dashboard
            </a>

        </div>
    </div>
</div>

<?php
include 'footer.php';
?>

<?php
// --- Status Check Endpoint (for UPI polling) ---
if (isset($_GET['check_status']) && $_GET['check_status'] == 1) {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("SELECT status FROM transactions WHERE transaction_id = ? AND inspector_id = ?");
    $stmt->bind_param("ii", $transaction_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode(['status' => $result['status'] ?? 'pending']);
    exit();
}
?>