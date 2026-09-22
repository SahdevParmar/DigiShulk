<?php
/**
 * Cashfree Payment Webhook
 *
 * Public HTTPS endpoint:
 * https://digishulk.site.je/webhook.php
 *
 * IMPORTANT:
 * - Do not parse/reformat the body before signature verification.
 * - Verify x-webhook-signature using timestamp + raw body.
 * - Cashfree may retry webhooks, so processing must be idempotent.
 */

require_once 'db_connect.php';
require_once 'config.php';
require_once 'cashfree_helper.php';
require_once 'sms_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

$rawBody = file_get_contents('php://input');

$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';

if ($rawBody === '' || $signature === '' || $timestamp === '') {
    http_response_code(400);
    exit('Missing webhook verification data.');
}

if (!cashfree_verify_webhook($rawBody, $timestamp, $signature)) {
    http_response_code(400);
    exit('Invalid signature.');
}

$event = json_decode($rawBody, true);

if (!is_array($event)) {
    http_response_code(400);
    exit('Invalid JSON.');
}

$eventType = $event['type'] ?? '';
$orderId = $event['data']['order']['order_id'] ?? '';
$paymentStatus = $event['data']['payment']['payment_status'] ?? '';

if ($orderId === '') {
    http_response_code(200);
    exit('Ignored: no order ID.');
}

/*
 * We only make a transaction PAID when Cashfree explicitly reports
 * PAYMENT_SUCCESS_WEBHOOK / SUCCESS.
 */
if ($eventType === 'PAYMENT_SUCCESS_WEBHOOK' && $paymentStatus === 'SUCCESS') {

    $stmt = $conn->prepare("
        SELECT
            transaction_id,
            shopkeeper_phone,
            total_amount,
            status
        FROM transactions
        WHERE payment_ref = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $orderId);
    $stmt->execute();

    $txn = $stmt->get_result()->fetch_assoc();

    if ($txn) {

        /*
         * Idempotent:
         * duplicate Cashfree deliveries won't send duplicate SMS or
         * perform duplicate state transitions.
         */
        if ($txn['status'] !== 'paid') {

            $update = $conn->prepare("
                UPDATE transactions
                SET status = 'paid'
                WHERE transaction_id = ?
                  AND status <> 'paid'
            ");

            $update->bind_param('i', $txn['transaction_id']);
            $update->execute();

            if ($update->affected_rows > 0) {
                try {
                    $smsResponse = send_payment_sms(
                        $txn['shopkeeper_phone'],
                        $txn['total_amount']
                    );

                    $log = $conn->prepare("
                        UPDATE transactions
                        SET sms_log = ?
                        WHERE transaction_id = ?
                    ");

                    $log->bind_param(
                        'si',
                        $smsResponse,
                        $txn['transaction_id']
                    );

                    $log->execute();

                } catch (Throwable $e) {
                    error_log(
                        'DigiShulk SMS after Cashfree payment: ' .
                        $e->getMessage()
                    );
                }
            }
        }
    }
}

/*
 * FAILED and USER_DROPPED intentionally remain pending.
 * The inspector can retry the same order while Cashfree remains ACTIVE,
 * or DigiShulk can create a new order if Cashfree expires/terminates it.
 */
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true]);
?>
