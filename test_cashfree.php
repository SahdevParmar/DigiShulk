<?php
/**
 * TEMPORARY DIAGNOSTIC — DELETE AFTER USE.
 * Not for production. Do not leave this file deployed.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'config.php';
require_once 'cashfree_helper.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DigiShulk Cashfree Diagnostic ===\n\n";

echo "CASHFREE_ENV:          " . CASHFREE_ENV . "\n";
echo "CASHFREE_API_VERSION:  " . CASHFREE_API_VERSION . "\n";
echo "CLIENT_ID length:      " . strlen(CASHFREE_CLIENT_ID) . "\n";
echo "CLIENT_SECRET length:  " . strlen(CASHFREE_CLIENT_SECRET) . "\n";
echo "APP_BASE_URL:          " . APP_BASE_URL . "\n";
echo "\n";

$orderId = 'DGS_TEST_' . time();
$amount  = 1.00;

echo "--- TEST 1: Create order ---\n";
try {
    $order = cashfree_create_order(
        $orderId,
        $amount,
        'DGS_TEST_CUST',
        'Test Customer',
        '9999999999',
        APP_BASE_URL . '/payment.php?id=0&cashfree_return=1&order_id={order_id}',
        APP_BASE_URL . '/webhook.php'
    );
    echo "✅ Order creation OK\n";
    echo "order_status:       " . ($order['order_status'] ?? '?') . "\n";
    echo "payment_session_id: " . substr(($order['payment_session_id'] ?? ''), 0, 30) . "...\n";
    echo "\nFull response:\n";
    echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    echo "❌ Order creation FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit;
}

echo "\n--- TEST 2: Create UPI QR payment ---\n";
try {
    $qr = cashfree_create_upi_qr($orderId);
    echo "✅ QR call returned OK\n";
    echo "\nFull response:\n";
    echo json_encode($qr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    echo "\n--- TEST 3: Extract UPI string ---\n";
    $upi = cashfree_extract_upi_string($qr);
    if ($upi === null) {
        echo "❌ Extractor found NO UPI string in the response above.\n";
        echo "   → We need to add a new path to cashfree_extract_upi_string().\n";
    } else {
        echo "✅ Extracted UPI string:\n";
        echo "   " . substr($upi, 0, 120) . "...\n";
    }
} catch (Throwable $e) {
    echo "❌ QR creation FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nThis usually means either:\n";
    echo "  - The API version doesn't support this endpoint\n";
    echo "  - The endpoint path is wrong\n";
    echo "  - The request body shape is wrong\n";
}