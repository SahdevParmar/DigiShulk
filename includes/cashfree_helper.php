<?php
/**
 * Cashfree Payment Gateway helper for DigiShulk.
 *
 * Uses Cashfree Payment Gateway API v2026-01-01.
 * Server-side only: the client secret never leaves PHP.
 */

if (!defined('CASHFREE_API_VERSION')) {
    require_once __DIR__ . '/config.php';
}

function cashfree_base_url(): string
{
    return (defined('CASHFREE_ENV') && CASHFREE_ENV === 'production')
        ? 'https://api.cashfree.com/pg'
        : 'https://sandbox.cashfree.com/pg';
}

function cashfree_request(string $method, string $path, ?array $body = null): array
{
    if (!defined('CASHFREE_CLIENT_ID') || !defined('CASHFREE_CLIENT_SECRET')) {
        throw new RuntimeException('Cashfree credentials are not configured.');
    }

    if (CASHFREE_CLIENT_ID === 'YOUR_CASHFREE_CLIENT_ID' ||
        CASHFREE_CLIENT_SECRET === 'YOUR_CASHFREE_CLIENT_SECRET') {
        throw new RuntimeException('Cashfree credentials are still using placeholder values.');
    }

    $url = rtrim(cashfree_base_url(), '/') . '/' . ltrim($path, '/');

    $headers = [
        'accept: application/json',
        'content-type: application/json',
        'x-api-version: ' . CASHFREE_API_VERSION,
        'x-client-id: ' . CASHFREE_CLIENT_ID,
        'x-client-secret: ' . CASHFREE_CLIENT_SECRET,
        'x-request-id: ' . bin2hex(random_bytes(16)),
    ];

    if ($method === 'POST') {
        $headers[] = 'x-idempotency-key: ' . cashfree_uuid_v4();
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $curlError !== '') {
        throw new RuntimeException('Cashfree connection failed: ' . $curlError);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new RuntimeException('Cashfree returned an invalid response.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $data['message'] ?? 'Cashfree API request failed.';
        $code = $data['code'] ?? ('HTTP_' . $httpCode);
        throw new RuntimeException($message . ' [' . $code . ']');
    }

    return $data;
}

function cashfree_create_order(
    string $orderId,
    float $amount,
    string $customerId,
    string $customerName,
    string $customerPhone,
    string $returnUrl,
    string $notifyUrl
): array {
    return cashfree_request('POST', '/orders', [
        'order_id' => $orderId,
        'order_amount' => round($amount, 2),
        'order_currency' => 'INR',
        'customer_details' => [
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
        ],
        'order_meta' => [
            'return_url' => $returnUrl,
            'notify_url' => $notifyUrl,
        ],
        'order_note' => 'DigiShulk Spot Tax Collection',
        'order_tags' => [
            'application' => 'DigiShulk',
            'transaction_id' => $customerId,
        ],
    ]);
}

function cashfree_get_order(string $orderId): array
{
    return cashfree_request('GET', '/orders/' . rawurlencode($orderId));
}

function cashfree_get_payments(string $orderId): array
{
    return cashfree_request('GET', '/orders/' . rawurlencode($orderId) . '/payments');
}

function cashfree_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function cashfree_verify_webhook(string $rawBody, string $timestamp, string $receivedSignature): bool
{
    $signedPayload = $timestamp . $rawBody;

    $generatedSignature = base64_encode(
        hash_hmac('sha256', $signedPayload, CASHFREE_CLIENT_SECRET, true)
    );

    return hash_equals($generatedSignature, $receivedSignature);
}

/**
 * Create a UPI QR payment for an order.
 *
 * Cashfree returns a UPI deep-link string (upi://pay?...) that we encode
 * as a QR client-side. The shopkeeper scans it with any UPI app.
 *
 * @param  string $orderId
 * @return array  Raw Cashfree response.
 * @throws RuntimeException
 */
function cashfree_create_upi_qr($orderId)
{
    return cashfree_request(
        'POST',
        '/orders/' . rawurlencode($orderId) . '/payments',
        [
            'payment_method' => [
                'upi' => [
                    'channel' => 'qrcode',
                ],
            ],
        ]
    );
}

/**
 * Extract the UPI deep-link string from a Cashfree QR response.
 *
 * Cashfree has changed the response shape across API versions, so we
 * defensively check several plausible paths.
 *
 * @param  array $response
 * @return string|null  The upi:// URL, or null if not found.
 */
function cashfree_extract_upi_string($response)
{
    if (!is_array($response)) {
        return null;
    }

    $candidates = [
        isset($response['data']['url'])                             ? $response['data']['url']                             : null,
        isset($response['data']['qrcode'])                          ? $response['data']['qrcode']                          : null,
        isset($response['data']['payload']['qrcode'])               ? $response['data']['payload']['qrcode']               : null,
        isset($response['data']['payload']['url'])                  ? $response['data']['payload']['url']                  : null,
        isset($response['payment_method_details']['upi']['qrcode']) ? $response['payment_method_details']['upi']['qrcode'] : null,
        isset($response['payment_method_details']['upi']['url'])    ? $response['payment_method_details']['upi']['url']    : null,
        isset($response['qrcode'])                                  ? $response['qrcode']                                  : null,
    ];

    foreach ($candidates as $c) {
        if (is_string($c) && $c !== '') {
            return $c;
        }
    }

    return null;
}