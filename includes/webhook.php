<?php
include 'db_connect.php';
include 'config.php';
include 'sms_helper.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

$expected_signature = hash_hmac('sha256', $payload, RAZORPAY_WEBHOOK_SECRET);
if (!hash_equals($expected_signature, $signature)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);

if ($event['event'] === 'payment.captured') {
    $razorpay_order_id = $event['payload']['payment']['entity']['order_id'];

    $stmt = $conn->prepare("SELECT transaction_id, shopkeeper_phone, total_amount, status FROM transactions WHERE payment_ref = ?");
    $stmt->bind_param("s", $razorpay_order_id);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if ($txn && $txn['status'] !== 'paid') {
        $update = $conn->prepare("UPDATE transactions SET status='paid' WHERE transaction_id=?");
        $update->bind_param("i", $txn['transaction_id']);
        $update->execute();

        send_payment_sms($txn['shopkeeper_phone'], $txn['total_amount']);
    }
}

http_response_code(200);
?>