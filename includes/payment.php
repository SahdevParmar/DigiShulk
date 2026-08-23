<?php
session_start();

require_once 'db_connect.php';
require_once 'config.php';
require_once 'cashfree_helper.php';

/**
 * DigiShulk payment flow
 *
 * CASH:
 *   inspector physically receives cash -> confirm_cash.php -> paid
 *
 * UPI:
 *   create Cashfree order -> hosted Cashfree checkout
 *   -> Cashfree webhook + server-side order verification
 *   -> only then transactions.status becomes paid
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
            echo json_encode([
                'status' => 'paid',
                'gateway_status' => 'PAID'
            ]);
            exit;
        }

        echo json_encode([
            'status' => $txn['status'],
            'gateway_status' => $order['order_status'] ?? 'UNKNOWN'
        ]);
        exit;

    } catch (Throwable $e) {
        error_log('DigiShulk Cashfree status check: ' . $e->getMessage());

        echo json_encode([
            'status' => $txn['status'],
            'gateway_status' => 'UNKNOWN'
        ]);
        exit;
    }
}

/* =========================================================
   POST: CREATE LOCAL TRANSACTION
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {

    $stallType = trim($_POST['stall_type'] ?? '');

    if ($stallType === 'Other' && !empty($_POST['stall_type_other'])) {
        $stallType = trim($_POST['stall_type_other']);
    }

    $area = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_FLOAT);
    $area = ($area !== false && $area !== null) ? (float) $area : 0;

    $shopName = trim($_POST['shop_name'] ?? '');
    $shopAddress = trim($_POST['shop_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $paymentMode = $_POST['payment_mode'] ?? '';
    $totalAmount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);

    if ($totalAmount === false || $totalAmount === null) {
        $totalAmount = 0;
    }

    $errors = [];

    if ($shopName === '') {
        $errors['shop_name'] = 'Shop name is required';
    }

    if ($shopAddress === '') {
        $errors['shop_address'] = 'Address is required';
    }

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
        $_SESSION['form_data'] = $_POST;

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
            shop_name,
            shop_address,
            shopkeeper_phone,
            payment_mode,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    if (!$stmt) {
        die('Unable to prepare transaction: ' . $conn->error);
    }

    $stmt->bind_param(
        'isidssss',
        $userId,
        $stallType,
        $area,
        $totalAmount,
        $shopName,
        $shopAddress,
        $phone,
        $paymentMode
    );

    if (!$stmt->execute()) {
        die('Unable to create transaction: ' . $stmt->error);
    }

    $transactionId = (int) $conn->insert_id;

    /* Save/update shop for autocomplete. */
    $stmt2 = $conn->prepare("
        INSERT INTO shops
        (shop_name, address, phone, stall_type, last_amount, last_visit)
        VALUES (?, ?, ?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE
            address = VALUES(address),
            stall_type = VALUES(stall_type),
            last_amount = VALUES(last_amount),
            last_visit = CURDATE()
    ");

    if ($stmt2) {
        $stmt2->bind_param(
            'ssssd',
            $shopName,
            $shopAddress,
            $phone,
            $stallType,
            $totalAmount
        );
        $stmt2->execute();
    }

    /* Receipt number is generated once and never depends on Cashfree. */
    $receiptNumber = 'RMC-' . date('Ymd') . '-' .
        str_pad((string) $transactionId, 4, '0', STR_PAD_LEFT);

    $stmt3 = $conn->prepare("
        UPDATE transactions
        SET receipt_number = ?
        WHERE transaction_id = ?
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
    SELECT
        t.*,
        u.full_name AS inspector_name,
        u.username
    FROM transactions t
    JOIN users u ON t.inspector_id = u.user_id
    WHERE t.transaction_id = ?
      AND t.inspector_id = ?
    LIMIT 1
");

$stmt->bind_param('ii', $transactionId, $userId);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if (!$txn) {
    http_response_code(404);
    exit('Transaction not found.');
}

/* =========================================================
   CASHFREE RETURN URL
   ========================================================= */

if (isset($_GET['cashfree_return']) && $_GET['cashfree_return'] === '1') {

    $returnedOrderId = trim($_GET['order_id'] ?? '');

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

/* Paid = receipt. */
if (($txn['status'] ?? '') === 'paid') {
    require 'header.php';
    require 'receipt_view.php';
    exit;
}

/* Cancelled/failed local states. */
if (in_array($txn['status'], ['cancelled', 'failed'], true)) {
    require 'header.php';
    require 'status_cancelled.php';
    exit;
}

/* =========================================================
   CREATE / LOAD CASHFREE UPI ORDER
   ========================================================= */

$cashfreeError = '';
$paymentSessionId = '';
$gatewayStatus = '';

if ($txn['payment_mode'] === 'upi') {

    try {

        $orderId = trim((string) ($txn['payment_ref'] ?? ''));

        /* If an old Razorpay reference exists, do not reuse it. */
        $isCashfreeOrder = str_starts_with($orderId, 'DGS_CF_');

        if (!$isCashfreeOrder) {

            $orderId = 'DGS_CF_' . $transactionId . '_' .
                strtoupper(bin2hex(random_bytes(4)));

            $returnUrl =
                APP_BASE_URL .
                '/payment.php?id=' . $transactionId .
                '&cashfree_return=1&order_id={order_id}';

            $notifyUrl =
                APP_BASE_URL .
                '/webhook.php';

            $customerName = trim((string) ($txn['shop_name'] ?? 'Customer'));

            $order = cashfree_create_order(
                $orderId,
                (float) $txn['total_amount'],
                'DGS_TXN_' . $transactionId,
                $customerName,
                $txn['shopkeeper_phone'],
                $returnUrl,
                $notifyUrl
            );

            if (empty($order['payment_session_id'])) {
                throw new RuntimeException('Cashfree did not return a payment session.');
            }

            $paymentSessionId = $order['payment_session_id'];

            $stmtUpdate = $conn->prepare("
                UPDATE transactions
                SET payment_ref = ?
                WHERE transaction_id = ?
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

            /*
             * The original session is returned by Get Order.
             * This lets us continue an ACTIVE order without creating duplicates.
             */
            $paymentSessionId = $order['payment_session_id'] ?? '';

            /*
             * If the order expired/terminated, create a fresh Cashfree order.
             */
            if ($paymentSessionId === '' ||
                in_array($gatewayStatus, ['EXPIRED', 'TERMINATED', 'TERMINATION_REQUESTED'], true)) {

                $newOrderId = 'DGS_CF_' . $transactionId . '_' .
                    strtoupper(bin2hex(random_bytes(4)));

                $returnUrl =
                    APP_BASE_URL .
                    '/payment.php?id=' . $transactionId .
                    '&cashfree_return=1&order_id={order_id}';

                $notifyUrl = APP_BASE_URL . '/webhook.php';

                $order = cashfree_create_order(
                    $newOrderId,
                    (float) $txn['total_amount'],
                    'DGS_TXN_' . $transactionId,
                    trim((string) ($txn['shop_name'] ?? 'Customer')),
                    $txn['shopkeeper_phone'],
                    $returnUrl,
                    $notifyUrl
                );

                $paymentSessionId = $order['payment_session_id'] ?? '';

                if ($paymentSessionId === '') {
                    throw new RuntimeException('Cashfree did not return a new payment session.');
                }

                $stmtUpdate = $conn->prepare("
                    UPDATE transactions
                    SET payment_ref = ?
                    WHERE transaction_id = ?
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
    <div class="card" style="max-width: 520px; margin: 0 auto;">

        <div class="card-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="stat-icon stat-icon-warning"
                     style="width:48px;height:48px;">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                </div>

                <div>
                    <h2 class="card-title" style="margin:0;">
                        Payment Pending
                    </h2>
                    <p class="card-subtitle" style="margin:0;">
                        Complete the collection to issue the receipt.
                    </p>
                </div>
            </div>
        </div>

        <div class="card-body" style="text-align:center;">

            <div style="
                margin-bottom:24px;
                padding:24px;
                background:var(--color-surface-muted);
                border-radius:var(--radius-lg);
            ">
                <p style="
                    font-size:var(--text-sm);
                    color:var(--color-text-muted);
                    margin-bottom:6px;
                ">
                    Amount to Collect
                </p>

                <div style="
                    font-size:var(--text-4xl);
                    font-weight:700;
                    color:var(--color-success);
                ">
                    ₹<?= number_format((float)$txn['total_amount'], 2) ?>
                </div>

                <span class="badge badge-<?= $txn['payment_mode'] === 'upi' ? 'primary' : 'success' ?>">
                    <?= strtoupper(htmlspecialchars($txn['payment_mode'])) ?>
                </span>
            </div>

            <div style="
                padding:18px;
                background:var(--color-surface-muted);
                border-radius:var(--radius-md);
                text-align:left;
            ">
                <p style="
                    margin:0 0 8px;
                    color:var(--color-text-muted);
                    font-size:var(--text-sm);
                ">
                    Shop
                </p>

                <strong>
                    <?= htmlspecialchars($txn['shop_name']) ?>
                </strong>

                <p style="
                    margin:5px 0 0;
                    color:var(--color-text-muted);
                    font-size:var(--text-sm);
                ">
                    <?= htmlspecialchars($txn['shopkeeper_phone']) ?>
                </p>

                <p style="
                    margin:5px 0 0;
                    color:var(--color-text-muted);
                    font-size:var(--text-sm);
                ">
                    Stall: <?= htmlspecialchars($txn['stall_type']) ?>
                </p>
            </div>

            <?php if ($txn['payment_mode'] === 'cash'): ?>

                <form method="POST"
                      action="confirm_cash.php"
                      style="margin-top:24px;">

                    <input type="hidden"
                           name="id"
                           value="<?= $transactionId ?>">

                    <button type="submit"
                            class="btn btn-success btn-block btn-lg">

                        <i class="fa-solid fa-money-bill-wave"
                           aria-hidden="true"></i>

                        Confirm Cash Received
                    </button>
                </form>

                <p style="
                    margin-top:12px;
                    font-size:var(--text-xs);
                    color:var(--color-text-subtle);
                ">
                    Only confirm after physically receiving the cash.
                </p>

            <?php else: ?>

                <?php if ($cashfreeError !== ''): ?>

                    <div class="alert alert-danger"
                         style="margin-top:24px;text-align:left;">

                        <i class="fa-solid fa-triangle-exclamation alert-icon"
                           aria-hidden="true"></i>

                        <div class="alert-content">
                            <p class="alert-title">
                                UPI payment is temporarily unavailable
                            </p>

                            <p class="alert-message">
                                <?= htmlspecialchars($cashfreeError) ?>
                            </p>

                            <p class="alert-message">
                                No payment has been marked as paid.
                                You may retry after checking the gateway configuration.
                            </p>
                        </div>
                    </div>

                <?php elseif ($paymentSessionId !== ''): ?>

                    <button id="cashfreePayBtn"
                            type="button"
                            class="btn btn-primary btn-block btn-lg"
                            style="margin-top:24px;">

                        <i class="fa-solid fa-qrcode"
                           aria-hidden="true"></i>

                        Open Secure UPI Payment
                    </button>

                    <div id="cashfreePaymentState"
                         style="
                            display:none;
                            margin-top:16px;
                            padding:14px;
                            border-radius:12px;
                            background:var(--color-surface-muted);
                            color:var(--color-text-muted);
                         ">
                    </div>

                    <p style="
                        margin-top:12px;
                        font-size:var(--text-xs);
                        color:var(--color-text-subtle);
                    ">
                        Payment confirmation comes from Cashfree.
                        The inspector does not manually confirm UPI payment.
                    </p>

                    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>

                    <script>
                    (() => {
                        const button = document.getElementById('cashfreePayBtn');
                        const state = document.getElementById('cashfreePaymentState');

                        const cashfree = Cashfree({
                            mode: <?= json_encode(CASHFREE_ENV === 'production' ? 'production' : 'sandbox') ?>
                        });

                        button.addEventListener('click', async () => {

                            button.disabled = true;

                            state.style.display = 'block';
                            state.textContent = 'Opening secure payment...';

                            try {
                                const result = await cashfree.checkout({
                                    paymentSessionId: <?= json_encode($paymentSessionId) ?>,
                                    redirectTarget: '_self'
                                });

                                /*
                                 * For redirect checkout the browser normally leaves
                                 * this page. If Cashfree reports an error before
                                 * redirecting, re-enable the button.
                                 */
                                if (result && result.error) {
                                    state.textContent =
                                        result.error.message || 'Unable to open payment.';
                                    button.disabled = false;
                                }

                            } catch (error) {
                                console.error(error);

                                state.textContent =
                                    'Unable to open the secure payment page. Please retry.';

                                button.disabled = false;
                            }
                        });
                    })();
                    </script>

                <?php else: ?>

                    <div class="alert alert-danger"
                         style="margin-top:24px;text-align:left;">

                        <i class="fa-solid fa-triangle-exclamation alert-icon"
                           aria-hidden="true"></i>

                        <div class="alert-content">
                            <p class="alert-title">
                                UPI gateway not ready
                            </p>

                            <p class="alert-message">
                                Cashfree order creation did not return a payment session.
                            </p>
                        </div>
                    </div>

                <?php endif; ?>

            <?php endif; ?>

            <a href="dashboard.php"
               class="btn btn-ghost btn-block"
               style="margin-top:16px;">

                <i class="fa-solid fa-arrow-left"
                   aria-hidden="true"></i>

                Back to Dashboard
            </a>

        </div>
    </div>
</div>

<?php
require 'footer.php';

/* =========================================================
   HELPER
   ========================================================= */

function mark_transaction_paid(mysqli $conn, int $transactionId): void
{
    /*
     * Only move pending -> paid.
     * Never downgrade a paid transaction.
     */
    $stmt = $conn->prepare("
        UPDATE transactions
        SET status = 'paid'
        WHERE transaction_id = ?
          AND status <> 'paid'
    ");

    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
}
?>
