<?php
/**
 * Transaction state helpers.
 * Shared between payment.php and webhook.php.
 */

/**
 * Mark a transaction paid. Idempotent — never downgrades.
 *
 * @param  mysqli $conn
 * @param  int    $transactionId
 * @return bool   True if this call performed the transition.
 */
function mark_transaction_paid($conn, $transactionId)
{
    $stmt = $conn->prepare(
        "UPDATE transactions
            SET status = 'paid'
          WHERE transaction_id = ?
            AND status <> 'paid'"
    );

    if (!$stmt) {
        error_log('mark_transaction_paid: prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('i', $transactionId);
    $stmt->execute();

    return $stmt->affected_rows > 0;
}