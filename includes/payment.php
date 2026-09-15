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
    $phone       = trim(isset($_POST['phone'])        ? $_POST['phone']        : '');
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

    // Save/update shop for autocomplete.
    $stmt2 = $conn->prepare("
        INSERT INTO shops
        (shop_name, address, phone, stall_type, last_amount, last_visit)
        VALUES (?, ?, ?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE
            address     = VALUES(address),
            stall_type  = VALUES(stall_type),
            last_amount = VALUES(last_amount),
            last_visit  = CURDATE()
    ");

    if ($stmt2) {
        $stmt2->bind_param('ssssd', $shopName, $shopAddress, $phone, $stallType, $totalAmount);
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
   CREATE / LOAD CASHFREE UPI ORDER + QR
   ========================================================= */

$cashfreeError  = '';
$upiString      = '';

if ($txn['payment_mode'] === 'upi') {

    $sessionQrKey = 'qr_for_txn_' . $transactionId;

    try {
        $orderId = trim((string) ($txn['payment_ref'] ?? ''));

        // PHP 7.2-safe str_starts_with.
        $isCashfreeOrder = (strpos($orderId, 'DGS_CF_') === 0);

        /* ---------------------------------------------------------
           1. Ensure a Cashfree order exists and is ACTIVE.
        --------------------------------------------------------- */
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
                unset($_SESSION[$sessionQrKey]);
                $txn['status'] = 'paid';

                require 'header.php';
                require 'receipt_view.php';
                exit;
            }

            // If order expired or terminated, create a fresh one.
            if (in_array($gatewayStatus, ['EXPIRED', 'TERMINATED', 'TERMINATION_REQUESTED'], true)) {

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

                if (empty($order['payment_session_id'])) {
                    throw new RuntimeException('Cashfree did not return a new payment session.');
                }

                $stmtUpdate = $conn->prepare("
                    UPDATE transactions SET payment_ref = ? WHERE transaction_id = ?
                ");
                $stmtUpdate->bind_param('si', $newOrderId, $transactionId);
                $stmtUpdate->execute();

                $txn['payment_ref'] = $newOrderId;
                $orderId = $newOrderId;

                // New order means the old QR is invalid.
                unset($_SESSION[$sessionQrKey]);
            }
        }

        /* ---------------------------------------------------------
           2. Get the UPI QR string.
           Cache in session so a page refresh doesn't spawn a new QR.
        --------------------------------------------------------- */
        if (!empty($_SESSION[$sessionQrKey])) {
            $upiString = $_SESSION[$sessionQrKey];
        } else {

            $qrResponse = cashfree_create_upi_qr($txn['payment_ref']);
            $upiString  = cashfree_extract_upi_string($qrResponse);

            if ($upiString === null) {
                error_log('DigiShulk UPI QR: unexpected response: ' .
                    json_encode($qrResponse));
                throw new RuntimeException(
                    'Cashfree did not return a usable UPI QR.'
                );
            }

            $_SESSION[$sessionQrKey] = $upiString;
        }

    } catch (Throwable $e) {
        $cashfreeError = $e->getMessage();
        error_log('DigiShulk Cashfree QR: ' . $cashfreeError);
    }
}

require 'header.php';
?>

<div class="page">
    <div class="card" style="max-width: 480px; margin: 0 auto;">

        <?php if ($txn['payment_mode'] === 'cash'): ?>

            <!-- ============ CASH ============ -->
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

                <p style="margin:0 0 8px;color:var(--color-text-muted);font-size:var(--text-sm);">Shop</p>
                <strong><?= htmlspecialchars($txn['shop_name']) ?></strong>

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

            <!-- ============ UPI ERROR ============ -->
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="stat-icon stat-icon-danger" style="width:48px;height:48px;">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="card-title" style="margin:0;">UPI Unavailable</h2>
                        <p class="card-subtitle" style="margin:0;">Could not generate a QR right now.</p>
                    </div>
                </div>
            </div>

            <div class="card-body" style="text-align:center;">
                <p style="font-size:var(--text-sm);color:var(--color-text-muted);">
                    The payment gateway did not respond correctly. Try again in a moment,
                    or collect this payment as cash instead.
                </p>
                <a href="payment.php?id=<?= $transactionId ?>" class="btn btn-primary btn-block" style="margin-top:16px;">
                    <i class="fa-solid fa-rotate" aria-hidden="true"></i> Retry
                </a>
                <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top:8px;">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard
                </a>
            </div>

        <?php else: ?>

            <!-- ============ UPI QR ============ -->
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

                <div id="qrcode" style="display:flex;justify-content:center;align-items:center;padding:16px;background:#fff;border-radius:var(--radius-lg);min-height:280px;">
                    <!-- QR gets rendered here -->
                </div>

                <div id="qrLoadingState" style="padding:40px 0;color:var(--color-text-muted);font-size:var(--text-sm);">
                    <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                    Generating QR...
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

                <div id="qrStatus" style="margin-top:12px;font-size:var(--text-xs);color:var(--color-text-muted);">
                    Waiting for payment...
                </div>

                <a href="dashboard.php" class="btn btn-ghost btn-block" style="margin-top:16px;">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back to Dashboard
                </a>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
            <script>
            (function () {
                var upiString   = <?= json_encode($upiString) ?>;
                var txnId       = <?= (int) $transactionId ?>;
                var qrContainer = document.getElementById('qrcode');
                var loading     = document.getElementById('qrLoadingState');
                var paidState   = document.getElementById('paidState');
                var statusEl    = document.getElementById('qrStatus');

                if (typeof QRCode === 'undefined') {
                    loading.innerHTML = 'QR library failed to load. Check your connection.';
                    return;
                }

                try {
                    new QRCode(qrContainer, {
                        text: upiString,
                        width: 260,
                        height: 260,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                    loading.style.display = 'none';
                } catch (e) {
                    loading.innerHTML = 'Could not render QR. Retry the page.';
                    return;
                }

                // Poll every 3 seconds.
                var pollTimer = setInterval(function () {
                    fetch('payment.php?check_status=1&id=' + txnId, { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data) return;

                            if (data.status === 'paid') {
                                clearInterval(pollTimer);
                                qrContainer.style.display = 'none';
                                loading.style.display = 'none';
                                paidState.style.display = 'block';
                                statusEl.textContent = '';

                                setTimeout(function () {
                                    window.location.href = 'payment.php?id=' + txnId;
                                }, 1200);
                                return;
                            }

                            if (data.gateway_status) {
                                statusEl.textContent = 'Status: ' + data.gateway_status;
                            }
                        })
                        .catch(function () { /* silent — retry next tick */ });
                }, 3000);
            })();
            </script>

        <?php endif; ?>

    </div>
</div>

<?php require 'footer.php'; ?>