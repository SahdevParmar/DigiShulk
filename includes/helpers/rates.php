<?php
/**
 * Fee calculation — single source of truth.
 *
 * PHP 7.2 compatible. No framework, no Composer.
 *
 * Rules:
 *   - The `rates` table is the authority on price-per-sqft.
 *   - If a stall type has no rate, we return null (inspector enters manually).
 *   - If size is 0 or missing, we return null.
 *   - The suggested fee is advisory. Inspectors may override.
 */

/**
 * Look up price per sqft for a stall type.
 *
 * @param  mysqli $conn
 * @param  string $stallType
 * @return float|null
 */
function get_rate_per_sqft($conn, $stallType)
{
    if ($stallType === null) {
        return null;
    }

    $stallType = trim($stallType);

    if ($stallType === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT price_per_sqft FROM rates WHERE stall_type = ? LIMIT 1"
    );

    if (!$stmt) {
        error_log('get_rate_per_sqft: prepare failed: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('s', $stallType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || $row['price_per_sqft'] === null) {
        return null;
    }

    return (float) $row['price_per_sqft'];
}

/**
 * Calculate the suggested fee for a stall.
 *
 * @param  mysqli $conn
 * @param  string $stallType
 * @param  mixed  $areaSqft
 * @return float|null  Null when no suggestion can be made.
 */
function calculate_suggested_fee($conn, $stallType, $areaSqft)
{
    $rate = get_rate_per_sqft($conn, $stallType);

    if ($rate === null) {
        return null;
    }

    $area = (float) $areaSqft;

    if ($area <= 0) {
        return null;
    }

    return round($area * $rate, 2);
}

/**
 * Return all rates as a simple map: ['Rekdi' => 10.00, 'Mandap' => 20.00]
 *
 * Used to embed rates into the page for client-side suggestion.
 *
 * @param  mysqli $conn
 * @return array
 */
function get_all_rates($conn)
{
    $out = [];
    $res = $conn->query("SELECT stall_type, price_per_sqft FROM rates");

    if (!$res) {
        return $out;
    }

    while ($row = $res->fetch_assoc()) {
        $out[$row['stall_type']] = (float) $row['price_per_sqft'];
    }

    return $out;
}