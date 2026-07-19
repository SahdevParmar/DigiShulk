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

    if ($shop_name === '' || $shop_address === '' || $phone === '' ||
        $total_amount <= 0 || !in_array($payment_mode, ['cash', 'upi'], true)) {
        die("Invalid details entered.");
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
    echo "<div class='card'><h1>Unauthorized Access</h1></div>";
    include 'footer.php';
    exit();
}

$transaction_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$show_receipt = filter_input(INPUT_GET, 'paid', FILTER_VALIDATE_INT) == 1;

if (!$transaction_id) {
    echo "<div class='card' style='text-align:center;'><h2>Invalid transaction.</h2></div>";
    include 'footer.php';
    exit();
}

$stmt = $conn->prepare("SELECT t.*, u.full_name as inspector_name FROM transactions t JOIN users u ON t.inspector_id = u.user_id WHERE t.transaction_id = ? AND t.inspector_id = ?");
$stmt->bind_param("ii", $transaction_id, $_SESSION['user_id']);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if(!$txn) {
    echo "<div class='card' style='text-align:center;'><h2>Transaction not found.</h2></div>";
    include 'footer.php';
    exit();
}

// If already paid, just show the receipt
if($txn['status'] === 'paid' || $show_receipt){
?>
    <div class="card" id="receiptCard" style="max-width: 400px; margin: 20px auto; padding: 20px;">
        <div style="text-align:center;">
            <div style="font-size: 40px; color: var(--success); margin-bottom:10px;">✅</div>
            <h2 style="color: var(--success); margin: 0 0 10px 0;">Payment Confirmed</h2>
            <p style="color: var(--muted); margin: 0;">Receipt #<?php echo htmlspecialchars($txn['receipt_number'] ?? ''); ?></p>
        </div>
        <hr style="margin: 20px 0; border: none; border-top: 1px dashed var(--border);">
        <div style="font-size: 32px; font-weight: bold; text-align: center; margin-bottom: 20px;">
            ₹<?php echo number_format($txn['total_amount'],2); ?>
            <span style="font-size: 14px; background: #eef4ff; color: var(--primary); padding: 4px 8px; border-radius: 6px; vertical-align: middle; margin-left: 10px;"><?php echo strtoupper($txn['payment_mode']); ?></span>
        </div>

        <table style="width: 100%; font-size: 14px; box-shadow: none; border: none;">
            <tr><td style="padding: 8px 0; border: none; color: var(--muted);">Shop Name</td><td style="padding: 8px 0; border: none; text-align: right; font-weight: bold;"><?php echo htmlspecialchars($txn['shop_name']); ?></td></tr>
            <tr><td style="padding: 8px 0; border: none; color: var(--muted);">Phone</td><td style="padding: 8px 0; border: none; text-align: right; font-weight: bold;"><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></td></tr>
            <tr><td style="padding: 8px 0; border: none; color: var(--muted);">Stall Type</td><td style="padding: 8px 0; border: none; text-align: right; font-weight: bold;"><?php echo htmlspecialchars($txn['stall_type']); ?></td></tr>
            <tr><td style="padding: 8px 0; border: none; color: var(--muted);">Date & Time</td><td style="padding: 8px 0; border: none; text-align: right; font-weight: bold;"><?php echo date('d M Y, h:i A', strtotime($txn['created_at'])); ?></td></tr>
            <tr><td style="padding: 8px 0; border: none; color: var(--muted);">Inspector</td><td style="padding: 8px 0; border: none; text-align: right; font-weight: bold;"><?php echo htmlspecialchars($txn['inspector_name'] ?? ''); ?></td></tr>
        </table>

        <div style="margin-top: 25px; display: flex; flex-direction: column; gap: 10px;" class="no-print">
            <div style="display: flex; gap: 10px;">
                <button style="flex:1; background: #f1f5f9; color: var(--text);" onclick="window.print()">🖨 Print</button>
                <a href="generate_receipt_pdf.php?id=<?php echo $transaction_id; ?>" style="flex:1; text-align:center; padding: 13px; background: #84cc16; color: white; border-radius: 10px; font-weight: 600;">📄 Download PDF</a>
            </div>
            <a href="../dashboard.php" style="text-align:center; padding: 13px; background: var(--primary); color: white; border-radius: 10px; font-weight: 600;">Done</a>
        </div>
    </div>

    <style>
        @media print {
            body * { visibility: hidden; }
            #receiptCard, #receiptCard * { visibility: visible; }
            #receiptCard { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; }
            .no-print { display: none !important; }
            .navarea, .navbar { display: none !important; }
        }
    </style>
<?php
    exit();
}

// ---------- CASH Flow ----------
if($txn['payment_mode'] === 'cash'){
?>
    <div class="card" style="max-width: 400px; margin: 20px auto; text-align:center;">
        <h2 style="margin-bottom: 20px;">Cash Collection</h2>
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
            <p style="margin: 0 0 5px 0; color: var(--muted);">Amount to Collect</p>
            <div style="font-size: 36px; font-weight: bold; color: var(--text);">₹<?php echo number_format($txn['total_amount'],2); ?></div>
        </div>
        <p style="font-size: 16px; margin-bottom: 20px;">Shop: <strong><?php echo htmlspecialchars($txn['shop_name']); ?></strong><br>
           Phone: <strong><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></strong>
        </p>
        <form method="POST" action="confirm_cash.php">
            <input type="hidden" name="id" value="<?php echo $transaction_id; ?>">
            <button type="submit" style="width: 100%; padding: 16px; font-size: 18px; border-radius: 12px;">Confirm Cash Received</button>
        </form>
        <a href="../dashboard.php" style="display:block; margin-top: 15px; color: var(--muted);">Cancel</a>
    </div>
<?php
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

<div class="card" style="max-width: 400px; margin: 20px auto; text-align:center;">
    <h2 style="margin-bottom: 20px;">UPI Payment</h2>
    <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
        <p style="margin: 0 0 5px 0; color: var(--muted);">Amount to Collect</p>
        <div style="font-size: 36px; font-weight: bold; color: var(--text);">₹<?php echo number_format($txn['total_amount'],2); ?></div>
    </div>
    <p style="font-size: 16px; margin-bottom: 20px;">Shop: <strong><?php echo htmlspecialchars($txn['shop_name']); ?></strong><br>
       Phone: <strong><?php echo htmlspecialchars($txn['shopkeeper_phone']); ?></strong>
    </p>

    <?php if(!empty($order_id)): ?>
        <button id="payBtn" style="width: 100%; padding: 16px; font-size: 18px; border-radius: 12px; background: #3b82f6;">Pay with UPI</button>
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
        <p style="color: var(--danger);">UPI Gateway not configured. Please use cash.</p>
    <?php endif; ?>

    <a href="../dashboard.php" style="display:block; margin-top: 15px; color: var(--muted);">Cancel</a>
</div>
