<?php
/**
 * DigiShulk — Search API
 * Endpoint: /includes/api/search.php?q=...
 * Returns JSON shaped for search.js.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['groups' => [], 'suggestions' => []]);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$q = preg_replace('/\s+/', ' ', $q);

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['query' => $q, 'groups' => [], 'suggestions' => []]);
    exit;
}

$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$user_id  = (int) $_SESSION['user_id'];
$like     = '%' . $q . '%';

$groups = [];

/* ---------- 1. Shops ---------- */
$stmt = $conn->prepare("
    SELECT shop_id, shop_name, phone, address
    FROM shops
    WHERE shop_name LIKE ? OR phone LIKE ? OR address LIKE ?
    ORDER BY shop_name ASC
    LIMIT 6
");
if ($stmt) {
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $sub = trim(($row['phone'] ?? '') . ' · ' . ($row['address'] ?? ''), ' ·');
        $items[] = [
            'icon' => 'fa-solid fa-store',
            'title' => $row['shop_name'],
            'subtitle' => $sub,
            'url' => 'spot_tax.php?shop=' . (int) $row['shop_id'],
            'badge' => null,
        ];
    }
    if ($items) $groups[] = ['icon' => 'fa-solid fa-store', 'label' => 'Shops', 'items' => $items];
}

/* ---------- 2. Transactions ---------- */
$txn_sql = "
    SELECT transaction_id, receipt_number, shop_name, shopkeeper_phone,
           total_amount, status, created_at
    FROM transactions
    WHERE receipt_number LIKE ? OR shop_name LIKE ? OR shopkeeper_phone LIKE ?
";
$params = [$like, $like, $like];
$types  = 'sss';

if (!$is_admin) {
    $txn_sql .= " AND inspector_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
}
$txn_sql .= " ORDER BY created_at DESC LIMIT 6";

$stmt = $conn->prepare($txn_sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $statusClass = $row['status'] === 'paid' ? 'badge-success'
                     : ($row['status'] === 'pending' ? 'badge-warning' : 'badge-danger');
        $items[] = [
            'icon' => 'fa-solid fa-receipt',
            'title' => $row['receipt_number'] ?: ('Txn #' . $row['transaction_id']),
            'subtitle' => $row['shop_name'] . ' · ₹' . number_format((float) $row['total_amount'], 2),
            'url' => 'transaction_detail.php?id=' . (int) $row['transaction_id'],
            'badge' => ['class' => $statusClass, 'label' => ucfirst($row['status'])],
        ];
    }
    if ($items) $groups[] = ['icon' => 'fa-solid fa-receipt', 'label' => 'Transactions', 'items' => $items];
}

/* ---------- 3. Seizure sessions ---------- */
if ($conn->query("SHOW TABLES LIKE 'seizure_sessions'")->num_rows) {
    $seiz_sql = "
        SELECT session_id, team_leader_name, zone, team_number, seizure_date
        FROM seizure_sessions
        WHERE team_leader_name LIKE ? OR zone LIKE ? OR team_number LIKE ?
    ";
    $params = [$like, $like, $like];
    $types  = 'sss';

    if (!$is_admin) {
        $seiz_sql .= " AND inspector_id = ?";
        $params[] = $user_id;
        $types   .= 'i';
    }
    $seiz_sql .= " ORDER BY seizure_date DESC LIMIT 5";

    $stmt = $conn->prepare($seiz_sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'icon' => 'fa-solid fa-triangle-exclamation',
                'title' => $row['team_leader_name'] . ' — Zone ' . $row['zone'],
                'subtitle' => 'Team ' . $row['team_number'] . ' · ' . date('d M Y', strtotime($row['seizure_date'])),
                'url' => 'history.php?view=seizures',
                'badge' => null,
            ];
        }
        if ($items) $groups[] = ['icon' => 'fa-solid fa-triangle-exclamation', 'label' => 'Seizures', 'items' => $items];
    }
}

/* ---------- 4. Inspectors (admin only) ---------- */
if ($is_admin) {
    $stmt = $conn->prepare("
        SELECT user_id, username, full_name
        FROM users
        WHERE role = 'inspector' AND (username LIKE ? OR full_name LIKE ?)
        ORDER BY username ASC LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'icon' => 'fa-solid fa-user-shield',
                'title' => $row['full_name'] ?: $row['username'],
                'subtitle' => '@' . $row['username'],
                'url' => 'edit_inspector.php?id=' . (int) $row['user_id'],
                'badge' => null,
            ];
        }
        if ($items) $groups[] = ['icon' => 'fa-solid fa-user-shield', 'label' => 'Inspectors', 'items' => $items];
    }
}

/* ---------- Suggestions ---------- */
$suggestions = [];
if (empty($groups)) {
    $r = $conn->query("SELECT stall_type FROM rates LIMIT 3");
    if ($r) {
        while ($row = $r->fetch_assoc()) $suggestions[] = $row['stall_type'];
    }
}

echo json_encode([
    'query' => $q,
    'groups' => $groups,
    'suggestions' => $suggestions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;