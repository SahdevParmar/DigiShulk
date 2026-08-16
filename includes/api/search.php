<?php
session_start();
require_once "../db_connect.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 1) {
    echo json_encode([
        'groups' => [],
        'suggestions' => []
    ]);
    exit;
}

$results = [
    'groups' => [],
    'suggestions' => []
];

/* -----------------------------
   Search Shops
----------------------------- */
$stmt = $conn->prepare("
SELECT
shop_id,
shop_name,
phone,
address,
stall_type
FROM shops
WHERE shop_name LIKE CONCAT('%', ?, '%')
LIMIT 5
");
$stmt->bind_param("s", $q);
$stmt->execute();
$res = $stmt->get_result();

$shops = [];
while ($row = $res->fetch_assoc()) {
    $shops[] = [
        "type" => "shop",
        "id" => $row["shop_id"],
        "icon" => "fa-solid fa-store",
        "title" => $row["shop_name"],
        "subtitle" => $row["address"] . " • " . $row["stall_type"],
        "url" => "spot_tax.php?shop=" . $row["shop_id"],
        "action" => "Create collection"
    ];
}

if (!empty($shops)) {
    $results['groups'][] = [
        'label' => 'Shops',
        'icon' => 'fa-solid fa-store',
        'items' => $shops
    ];
}

/* -----------------------------
   Search Transactions (by receipt # or shop)
----------------------------- */
$stmt = $conn->prepare("
SELECT t.transaction_id, t.receipt_number, t.shop_name, t.total_amount, t.status, t.created_at, u.username as inspector_name
FROM transactions t
JOIN users u ON t.inspector_id = u.user_id
WHERE (t.receipt_number LIKE CONCAT('%', ?, '%') OR t.shop_name LIKE CONCAT('%', ?, '%'))
ORDER BY t.created_at DESC
LIMIT 5
");
$stmt->bind_param("ss", $q, $q);
$stmt->execute();
$res = $stmt->get_result();

$transactions = [];
while ($row = $res->fetch_assoc()) {
    $statusClass = $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger');
    $transactions[] = [
        "type" => "transaction",
        "id" => $row["transaction_id"],
        "icon" => "fa-solid fa-receipt",
        "title" => "Receipt: " . $row["receipt_number"],
        "subtitle" => $row["shop_name"] . " • ₹" . number_format($row['total_amount'], 2) . " • " . ucfirst($row['status']),
        "url" => "payment.php?id=" . $row["transaction_id"] . ($row['status'] === 'paid' ? '&paid=1' : ''),
        "action" => "View details",
        "badge" => [
            "label" => ucfirst($row['status']),
            "class" => "badge-$statusClass"
        ]
    ];
}

if (!empty($transactions)) {
    $results['groups'][] = [
        'label' => 'Transactions',
        'icon' => 'fa-solid fa-receipt',
        'items' => $transactions
    ];
}

/* -----------------------------
   Search Inspectors (Admin Only)
----------------------------- */
if ($_SESSION['role'] == 'admin') {
    $stmt = $conn->prepare("
    SELECT user_id, username, full_name, last_active
    FROM users
    WHERE username LIKE CONCAT('%', ?, '%') AND role='inspector'
    LIMIT 5
    ");
    $stmt->bind_param("s", $q);
    $stmt->execute();
    $res = $stmt->get_result();

    $inspectors = [];
    while ($row = $res->fetch_assoc()) {
        $isOnline = strtotime($row['last_active']) > strtotime('-1 minutes');
        $inspectors[] = [
            "type" => "inspector",
            "id" => $row["user_id"],
            "icon" => "fa-solid fa-user",
            "title" => $row["full_name"] ? $row["full_name"] . " (@{$row['username']})" : $row['username'],
            "subtitle" => ($isOnline ? "Online" : "Offline") . " • Last: " . date('d M Y', strtotime($row['last_active'])),
            "url" => "edit_inspector.php?id=" . $row["user_id"],
            "action" => "Manage"
        ];
    }

    if (!empty($inspectors)) {
        $results['groups'][] = [
            'label' => 'Inspectors',
            'icon' => 'fa-solid fa-users-gear',
            'items' => $inspectors
        ];
    }
}

/* -----------------------------
   Search Seizures (by team leader, zone)
----------------------------- */
if ($conn->query("SHOW TABLES LIKE 'rmc_seizures'")->num_rows) {
    $stmt = $conn->prepare("
    SELECT s.session_id, s.team_leader_name, s.zone, s.team_number, s.seizure_date, u.full_name as inspector_name
    FROM seizure_sessions s
    LEFT JOIN users u ON s.inspector_id = u.user_id
    WHERE s.team_leader_name LIKE CONCAT('%', ?, '%') OR s.zone LIKE CONCAT('%', ?, '%')
    ORDER BY s.seizure_date DESC
    LIMIT 5
    ");
    $stmt->bind_param("ss", $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();

    $seizures = [];
    while ($row = $res->fetch_assoc()) {
        $seizures[] = [
            "type" => "seizure",
            "id" => $row["session_id"],
            "icon" => "fa-solid fa-triangle-exclamation",
            "title" => $row["team_leader_name"] . " — Zone {$row['zone']}",
            "subtitle" => "Team {$row['team_number']} • {$row['seizure_date']}",
            "url" => "history.php?view=seizures",
            "action" => "View report"
        ];
    }

    if (!empty($seizures)) {
        $results['groups'][] = [
            'label' => 'Seizure Reports',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'items' => $seizures
        ];
    }
}

/* -----------------------------
   Static Pages (always available)
----------------------------- */
$role = $_SESSION['role'];
$basePages = [
    ["Dashboard", "dashboard.php", "fa-solid fa-house"],
    ["New Spot Tax", "spot_tax.php", "fa-solid fa-receipt"],
    ["Seizure Report", "seizure_form.php", "fa-solid fa-triangle-exclamation"],
    ["History", "history.php", "fa-solid fa-clock-rotate-left"],
    ["Settings", "settings.php", "fa-solid fa-gear"]
];

if ($role === 'admin') {
    $basePages[] = ["Manage Inspectors", "add_inspector.php", "fa-solid fa-users-gear"];
}

$pages = [];
foreach ($basePages as $page) {
    if (stripos($page[0], $q) !== false) {
        $pages[] = [
            "type" => "page",
            "icon" => $page[2],
            "title" => $page[0],
            "subtitle" => "Page",
            "url" => $page[1],
            "action" => "Open"
        ];
    }
}

if (!empty($pages)) {
    $results['groups'][] = [
        'label' => 'Quick Actions',
        'icon' => 'fa-solid fa-bolt',
        'items' => $pages
    ];
}

/* -----------------------------
   Search Suggestions (for autocomplete)
----------------------------- */
$suggestions = [];
// Add shop name suggestions
$stmt = $conn->prepare("SELECT shop_name FROM shops WHERE shop_name LIKE CONCAT('%', ?, '%') LIMIT 3");
$stmt->bind_param("s", $q);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $suggestions[] = $row['shop_name'];
}

// Add page suggestions
foreach ($basePages as $page) {
    if (stripos($page[0], $q) !== false && strlen($q) > 1) {
        $suggestions[] = $page[0];
    }
}
$results['suggestions'] = array_unique($suggestions);

header('Content-Type: application/json');
echo json_encode($results);
exit;