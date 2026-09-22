<?php
session_start();

require_once 'db_connect.php';
require_once 'config.php';
require_once 'cashfree_helper.php';
require_once 'helpers/transactions.php';
require_once 'helpers/csrf.php';

/**
 * DigiShulk payment flow.
 *
 * CASH:  inspector physically receives cash -> confirm_cash.php -> paid
 * UPI:   create Cashfree order -> hosted Cashfree checkout
 *        -> Cashfree webhook + server-side order verification -> paid
 */

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'inspector') {
    http_response_code(403);
    exit('Unauthorized');
}

$userId = (int) $_SESSION['user_id'];

/* =========================================================
   JSON STATUS ENDPOINT
   ========================================================= */
if (isset($_GET['check_status']) && $_GET['check_status'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $transactionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['status' => 'invalid']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT transaction_id, status, payment_mode, payment_ref, total_amount
        FROM transactions
        WHERE transaction_id = ? AND inspector_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $transactionId, $userId);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if (!$txn) {
        http_response_code(404);
        echo json_encode(['status' => 'not_found']);
        exit;
    }

    if ($txn['status'] === 'paid' || $txn['payment_mode'] !== 'upi') {
        echo json_encode(['status' => $txn['status']]);
        exit;
    }

    if (empty($txn['payment_ref'])) {
        echo json_encode(['status' => 'pending']);
        exit;
    }

    try {
        $order = cashfree_get_order($txn['payment_ref']);

        if (($order['order_status'] ?? '') === 'PAID') {
            mark_transaction_paid($conn, $transactionId);
            echo json_encode(['status' => 'paid', 'gateway_status' => 'PAID']);
            exit;
        }

        echo json_encode([
            'status'         => $txn['status'],
            'gateway_status' => $order['order_status'] ?? 'UNKNOWN',
        ]);
        exit;

    } catch (Throwable $e) {
        error_log('DigiShulk Cashfree status check: ' . $e->getMessage());
        echo json_encode(['status' => $txn['status'], 'gateway_status' => 'UNKNOWN']);
        exit;
    }
}

/* =========================================================
   POST: CREATE LOCAL TRANSACTION
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {

    csrf_require_or_die();

    $stallType = trim(isset($_POST['stall_type']) ? $_POST['stall_type'] : '');

    if ($stallType === 'Other' && !empty($_POST['stall_type_other'])) {
        $stallType = trim($_POST['stall_type_other']);
    }

    $area = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_FLOAT);
    $area = ($area !== false && $area !== null) ? (float) $area : 0;

    $shopName    = trim(isset($_POST['shop_name'])    ? $_POST['shop_name']    : '');
    $shopAddress = trim(isset($_POST['shop_address']) ? $_POST['shop_address'] : '');
    $phone = preg_replace('/\D/', '', (string)($_POST['phone'] ?? ''));
    $paymentMode = isset($_POST['payment_mode']) ? $_POST['payment_mode'] : '';
    $totalAmount = filter_var(isset($_POST['amount']) ? $_POST['amount'] : 0, FILTER_VALIDATE_FLOAT);

    if ($totalAmount === false || $totalAmount === null) {
        $totalAmount = 0;
    }

    // Optional audit fields.
    $suggestedRaw = isset($_POST['suggested_amount']) ? $_POST['suggested_amount'] : '';
    $suggested    = is_numeric($suggestedRaw) ? (float) $suggestedRaw : null;
    $rateRaw      = isset($_POST['rate_per_sqft']) ? $_POST['rate_per_sqft'] : '';
    $ratePerSqft  = is_numeric($rateRaw) ? (float) $rateRaw : null;

    $errors = [];

    // --- Optional fields (all low-risk, no validation errors) ---
$ownerName = trim($_POST['owner_name'] ?? '');
$zone      = trim($_POST['zone']       ?? '');
$notes     = trim($_POST['notes']      ?? '');
$latitude  = is_numeric($_POST['latitude']  ?? null) ? (float) $_POST['latitude']  : null;
$longitude = is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;

// Trim caps to match schema
if (strlen($ownerName) > 150) { $ownerName = substr($ownerName, 0, 150); }
if (strlen($zone)      > 100) { $zone      = substr($zone, 0, 100); }
if (strlen($notes)     > 500) { $notes     = substr($notes, 0, 500); }

    if ($shopName === '')    { $errors['shop_name']    = 'Shop name is required'; }
    if ($shopAddress === '') { $errors['shop_address'] = 'Address is required'; }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = 'Enter a valid 10-digit mobile number';
    }

    if ($totalAmount < 1) {
        $errors['amount'] = 'Amount must be at least ₹1';
    }

    if (!in_array($paymentMode, ['cash', 'upi'], true)) {
        $errors['payment_mode'] = 'Select a payment mode';
    }

    if ($stallType === '') {
        $errors['stall_type'] = 'Stall type is required';
    }


    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data']   = $_POST;
        header('Location: spot_tax.php');
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO transactions
        (
            inspector_id,
            stall_type,
            area_sqft,
            total_amount,
            suggested_amount,
            rate_per_sqft,
            shop_name,
            shop_address,
            shopkeeper_phone,
            payment_mode,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    if (!$stmt) {
        die('Unable to prepare transaction: ' . $conn->error);
    }

    $stmt->bind_param(
        'isdddsssss',
        $userId,
        $stallType,
        $area,
        $totalAmount,
        $suggested,
        $ratePerSqft,
        $shopName,
        $shopAddress,
        $phone,
        $paymentMode
    );

    if (!$stmt->execute()) {
        die('Unable to create transaction: ' . $stmt->error);
    }

    $transactionId = (int) $conn->insert_id;

$stmt2 = $conn->prepare("
    INSERT INTO shops
    (shop_name, owner_name, address, phone, zone, stall_type, notes, latitude, longitude, last_amount, last_visit)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
    ON DUPLICATE KEY UPDATE
        owner_name  = COALESCE(NULLIF(VALUES(owner_name), ''), owner_name),
        address     = VALUES(address),
        zone        = COALESCE(NULLIF(VALUES(zone), ''), zone),
        stall_type  = VALUES(stall_type),
        notes       = COALESCE(NULLIF(VALUES(notes), ''), notes),
        latitude    = COALESCE(VALUES(latitude),  latitude),
        longitude   = COALESCE(VALUES(longitude), longitude),
        last_amount = VALUES(last_amount),
        last_visit  = CURDATE()
");

if ($stmt2) {
    $stmt2->bind_param(
        'sssssssddd',
        $shopName,
        $ownerName,
        $shopAddress,
        $phone,
        $zone,
        $stallType,
        $notes,
        $latitude,
        $longitude,
        $totalAmount
    );
    $stmt2->execute();
}

    // Receipt number — generated once, independent of gateway.
    $receiptNumber = 'RMC-' . date('Ymd') . '-' .
        str_pad((string) $transactionId, 4, '0', STR_PAD_LEFT);

    $stmt3 = $conn->prepare("
        UPDATE transactions SET receipt_number = ? WHERE transaction_id = ?
    ");
    $stmt3->bind_param('si', $receiptNumber, $transactionId);
    $stmt3->execute();

    header('Location: payment.php?id=' . $transactionId);
    exit;
}

/* =========================================================
   GET / PAYMENT SCREEN
   ========================================================= */

$transactionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$transactionId) {
    http_response_code(400);
    exit('Invalid transaction ID.');
}

$stmt = $conn->prepare("
    SELECT t.*, u.full_name AS inspector_name, u.username
    FROM transactions t
    JOIN users u ON t.inspector_id = u.user_id
    WHERE t.transaction_id = ? AND t.inspector_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $transactionId, $userId);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if (!$txn) {
    http_response_code(404);
    exit('Transaction not found.');
}

/* Cashfree return handling */
if (isset($_GET['cashfree_return']) && $_GET['cashfree_return'] === '1') {
    $returnedOrderId = trim(isset($_GET['order_id']) ? $_GET['order_id'] : '');

    if ($returnedOrderId !== '' &&
        !empty($txn['payment_ref']) &&
        hash_equals((string) $txn['payment_ref'], $returnedOrderId)) {

        try {
            $order = cashfree_get_order($returnedOrderId);
            if (($order['order_status'] ?? '') === 'PAID') {
                mark_transaction_paid($conn, $transactionId);
                $txn['status'] = 'paid';
            }
        } catch (Throwable $e) {
            error_log('DigiShulk Cashfree return verification: ' . $e->getMessage());
        }
    }
}

if (($txn['status'] ?? '') === 'paid') {
    require 'header.php';
    require 'receipt_view.php';
    exit;
}

if (in_array($txn['status'], ['cancelled', 'failed'], true)) {
    require 'header.php';
    require 'status_cancelled.php';
    exit;
}

/* =========================================================
   CREATE / LOAD CASHFREE UPI ORDER
   ========================================================= */

$cashfreeError    = '';
$paymentSessionId = '';
$gatewayStatus    = '';

if ($txn['payment_mode'] === 'upi') {

    try {
        $orderId = trim((string) ($txn['payment_ref'] ?? ''));
        $isCashfreeOrder = (strpos($orderId, 'DGS_CF_') === 0);

        if (!$isCashfreeOrder) {

            $orderId = 'DGS_CF_' . $transactionId . '_' .
                strtoupper(bin2hex(random_bytes(4)));

            $returnUrl = APP_BASE_URL .
                '/payment.php?id=' . $transactionId .
                '&cashfree_return=1&order_id={order_id}';
            $notifyUrl = APP_BASE_URL . '/webhook.php';

            $safePhone = preg_replace('/\D/', '', (string) $txn['shopkeeper_phone']);
            if (strlen($safePhone) !== 10) {
                $safePhone = '9999999999';
            }

            $order = cashfree_create_order(
                $orderId,
                (float) $txn['total_amount'],
                'DGS_TXN_' . $transactionId,
                trim((string) ($txn['shop_name'] ?? 'Customer')),
                $safePhone,
                $returnUrl,
                $notifyUrl
            );

            if (empty($order['payment_session_id'])) {
                throw new RuntimeException('Cashfree did not return a payment session.');
            }

            $paymentSessionId = $order['payment_session_id'];

            $stmtUpdate = $conn->prepare("
                UPDATE transactions SET payment_ref = ? WHERE transaction_id = ?
            ");
            $stmtUpdate->bind_param('si', $orderId, $transactionId);
            $stmtUpdate->execute();

            $txn['payment_ref'] = $orderId;

        } else {

            $order = cashfree_get_order($orderId);
            $gatewayStatus = $order['order_status'] ?? '';

            if ($gatewayStatus === 'PAID') {
                mark_transaction_paid($conn, $transactionId);
                $txn['status'] = 'paid';

                require 'header.php';
                require 'receipt_view.php';
                exit;
            }

            $paymentSessionId = $order['payment_session_id'] ?? '';

            if ($paymentSessionId === '' ||
                in_array($gatewayStatus, ['EXPIRED', 'TERMINATED', 'TERMINATION_REQUESTED'], true)) {

                $newOrderId = 'DGS_CF_' . $transactionId . '_' .
                    strtoupper(bin2hex(random_bytes(4)));

                $returnUrl = APP_BASE_URL .
                    '/payment.php?id=' . $transactionId .
                    '&cashfree_return=1&order_id={order_id}';
                $notifyUrl = APP_BASE_URL . '/webhook.php';

                $safePhone = preg_replace('/\D/', '', (string) $txn['shopkeeper_phone']);
                if (strlen($safePhone) !== 10) {
                    $safePhone = '9999999999';
                }

                $order = cashfree_create_order(
                    $newOrderId,
                    (float) $txn['total_amount'],
                    'DGS_TXN_' . $transactionId,
                    trim((string) ($txn['shop_name'] ?? 'Customer')),
                    $safePhone,
                    $returnUrl,
                    $notifyUrl
                );

                $paymentSessionId = $order['payment_session_id'] ?? '';

                if ($paymentSessionId === '') {
                    throw new RuntimeException('Cashfree did not return a new payment session.');
                }

                $stmtUpdate = $conn->prepare("
                    UPDATE transactions SET payment_ref = ? WHERE transaction_id = ?
                ");
                $stmtUpdate->bind_param('si', $newOrderId, $transactionId);
                $stmtUpdate->execute();

                $txn['payment_ref'] = $newOrderId;
            }
        }

    } catch (Throwable $e) {
        $cashfreeError = $e->getMessage();
        error_log('DigiShulk Cashfree: ' . $cashfreeError);
    }
}

require 'header.php';
?>

<div class="page">
    <div class="card" style="max-width: 480px; margin: 0 auto;">

        <?php if ($txn['payment_mode'] === 'cash'): ?>

            <!-- CASH (unchanged) -->
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="stat-icon stat-icon-success" style="width:48px;height:48px;">
                        <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="card-title" style="margin:0;">Cash Collection</h2>
                        <p class="card-subtitle" style="margin:0;">Confirm after receiving cash.</p>
                    </div>
                </div>
            </div>
            <div class="card-body" style="text-align:center;">
                <div style="margin-bottom:24px;padding:24px;background:var(--color-surface-muted);border-radius:var(--radius-lg);">
                    <p style="font-size:var(--text-sm);color:var(--color-text-muted);margin-bottom:6px;">Amount</p>
                    <div style="font-size:var(--text-4xl);font-weight:700;color:var(--color-success);">
                        ₹<?= number_format((float) $txn['total_amount'], 2) ?>
                    </div>
                </div>
                <form method="POST" action="confirm_cash.php" style="margin-top:24px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $transactionId ?>">
                    <button type="submit" class="btn btn-success btn-block btn-lg">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        Confirm Cash Received
                    </button>
                </form>
                <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top:16px;">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back to Dashboard
                </a>
            </div>

        <?php elseif ($cashfreeError !== ''): ?>

            <!-- UPI ERROR -->
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="stat-icon stat-icon-danger" style="width:48px;height:48px;">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="card-title" style="margin:0;">UPI Unavailable</h2>
                        <p class="card-subtitle" style="margin:0;">Could not open payment right now.</p>
                    </div>
                </div>
            </div>
            <div class="card-body" style="text-align:center;">
                <p style="font-size:var(--text-sm);color:var(--color-text-muted);">
                    Please try again in a moment, or collect this payment as cash instead.
                </p>
                <a href="payment.php?id=<?= $transactionId ?>" class="btn btn-primary btn-block" style="margin-top:16px;">
                    <i class="fa-solid fa-rotate" aria-hidden="true"></i> Retry
                </a>
                <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top:8px;">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard
                </a>
            </div>

        <?php else: ?>

            <!-- UPI — QR auto-pops on load -->
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="stat-icon stat-icon-primary" style="width:48px;height:48px;">
                        <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="card-title" style="margin:0;">Scan to Pay</h2>
                        <p class="card-subtitle" style="margin:0;">Show this to the shopkeeper.</p>
                    </div>
                </div>
            </div>

            <div class="card-body" style="text-align:center;">

                <div style="margin-bottom:16px;">
                    <div style="font-size:var(--text-3xl);font-weight:700;color:var(--color-text);">
                        ₹<?= number_format((float) $txn['total_amount'], 2) ?>
                    </div>
                    <div style="font-size:var(--text-sm);color:var(--color-text-muted);margin-top:4px;">
                        <?= htmlspecialchars($txn['shop_name']) ?>
                    </div>
                </div>

                <div id="qrLaunchState" style="padding:40px 0;color:var(--color-text-muted);font-size:var(--text-sm);">
                    <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                    Opening payment window...
                </div>

                <div id="reopenButton" style="display:none;">
                    <button type="button" id="reopenPayBtn" class="btn btn-primary btn-block btn-lg">
                        <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                        Show QR Again
                    </button>
                </div>

                <div id="paidState" style="display:none;padding:24px 0;">
                    <div style="font-size:3rem;color:var(--color-success);margin-bottom:8px;">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    </div>
                    <div style="font-size:var(--text-xl);font-weight:700;color:var(--color-success);">
                        Payment Received
                    </div>
                    <div style="font-size:var(--text-sm);color:var(--color-text-muted);margin-top:4px;">
                        Opening receipt...
                    </div>
                </div>

                <div id="qrStatus" style="margin-top:12px;font-size:var(--text-xs);color:var(--color-text-muted);"></div>

                <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top:16px;">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back to Dashboard
                </a>
            </div>

            <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
            <script>
            (function () {
                var txnId   = <?= (int) $transactionId ?>;
                var session = <?= json_encode($paymentSessionId) ?>;
                var launch  = document.getElementById('qrLaunchState');
                var reopen  = document.getElementById('reopenButton');
                var paidEl  = document.getElementById('paidState');
                var statusEl = document.getElementById('qrStatus');

                if (typeof Cashfree === 'undefined') {
                    launch.innerHTML = 'Payment library failed to load. Check your connection.';
                    return;
                }

                var cashfree = Cashfree({
                    mode: <?= json_encode(CASHFREE_ENV === 'production' ? 'production' : 'sandbox') ?>
                });

                function openCheckout() {
                    launch.style.display = 'block';
                    launch.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Opening payment window...';
                    reopen.style.display = 'none';

                    cashfree.checkout({
                        paymentSessionId: session,
                        redirectTarget: '_modal',
                        // Lock to UPI so shopkeeper sees QR immediately.
                        paymentMethods: 'upi'
                    }).then(function (result) {
                        // _modal target: Cashfree closes the modal on success and
                        // resolves this promise. If payment succeeded, polling
                        // will pick it up. If the user closed the modal, we offer
                        // a "Show QR Again" button.
                        if (result && result.error) {
                            launch.style.display = 'none';
                            reopen.style.display = 'block';
                            statusEl.textContent = result.error.message || '';
                        } else {
                            // Modal closed cleanly — polling loop will handle state.
                            launch.style.display = 'none';
                            reopen.style.display = 'block';
                        }
                    }).catch(function (err) {
                        launch.style.display = 'none';
                        reopen.style.display = 'block';
                        statusEl.textContent = 'Could not open payment window.';
                    });
                }

                document.getElementById('reopenPayBtn').addEventListener('click', openCheckout);

                // Auto-open immediately on page load — no clicks needed.
                openCheckout();

                // Poll every 3 seconds.
                var pollTimer = setInterval(function () {
                    fetch('payment.php?check_status=1&id=' + txnId, { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data) return;
                            if (data.status === 'paid') {
                                clearInterval(pollTimer);
                                launch.style.display = 'none';
                                reopen.style.display = 'none';
                                paidEl.style.display = 'block';
                                statusEl.textContent = '';
                                setTimeout(function () {
                                    window.location.href = 'payment.php?id=' + txnId;
                                }, 1200);
                            }
                        })
                        .catch(function () {});
                }, 3000);
            })();
            </script>

        <?php endif; ?>

    </div>
</div>

<?php require 'footer.php'; ?>