<?php
session_start();
include 'db_connect.php';

// Check for user session and role
if(!isset($_SESSION['user_id'])){
    header("HTTP/1.1 403 Forbidden");
    exit("Unauthorized access.");
}
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

// Get filter parameters from URL
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$zone_filter = $_GET['zone_filter'] ?? '';

// Build a query that joins all necessary tables
$sql = "SELECT
            s.session_id,
            s.seizure_date,
            s.team_leader_name,
            s.zone,
            s.team_number,
            u.full_name as inspector_name,
            i.item_details,
            i.quantity,
            i.owner_merchant_name,
            i.seizure_location,
            i.godown_register_no
        FROM seizure_sessions s
        JOIN seizure_items i ON s.session_id = i.session_id
        LEFT JOIN users u ON s.inspector_id = u.user_id
        WHERE 1=1";

$params = [];
$types = "";

if(!$is_admin){
    $sql .= " AND s.inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
if(!empty($date_from)){ $sql .= " AND s.seizure_date >= ?"; $params[] = $date_from; $types .= "s"; }
if(!empty($date_to)){ $sql .= " AND s.seizure_date <= ?"; $params[] = $date_to; $types .= "s"; }
if(!empty($zone_filter)){ $sql .= " AND s.zone = ?"; $params[] = $zone_filter; $types .= "s"; }

$sql .= " ORDER BY s.seizure_date DESC, s.session_id DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

// --- CSV Generation ---

$filename = "seizure_report_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add header row
$header = [
    'Session ID', 'Seizure Date', 'Team Leader', 'Zone', 'Team Number', 'Inspector',
    'Item Details', 'Quantity', 'Owner/Merchant', 'Seizure Location', 'Godown Reg. No.'
];
fputcsv($output, $header);

// Add data rows
while($row = $result->fetch_assoc()) {
    $csvRow = [
        $row['session_id'],
        $row['seizure_date'],
        $row['team_leader_name'],
        $row['zone'],
        $row['team_number'],
        $row['inspector_name'] ?? 'N/A',
        $row['item_details'],
        $row['quantity'],
        $row['owner_merchant_name'],
        $row['seizure_location'],
        $row['godown_register_no']
    ];
    fputcsv($output, $csvRow);
}

fclose($output);
exit();
?>
