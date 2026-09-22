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
$status = $_GET['status'] ?? '';
$payment_mode = $_GET['payment_mode'] ?? '';

// Build the same SQL query as the history page
$sql = "SELECT t.*, u.full_name as inspector_name FROM transactions t LEFT JOIN users u ON t.inspector_id = u.user_id WHERE 1=1";
$params = [];
$types = "";

if(!$is_admin){
    $sql .= " AND t.inspector_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

if(!empty($date_from)){ $sql .= " AND date(t.created_at) >= ?"; $params[]=$date_from; $types.="s"; }
if(!empty($date_to)){ $sql .= " AND date(t.created_at) <= ?"; $params[]=$date_to; $types.="s"; }
if($is_admin && !empty($status)){ $sql.=" AND t.status = ?"; $params[]=$status; $types.="s"; }
if(!empty($payment_mode)){ $sql.=" AND t.payment_mode = ?"; $params[]=$payment_mode; $types.="s"; }
$sql .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

// --- CSV Generation ---

$filename = "tax_report_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add header row
$header = ['Receipt Number', 'Shop Name', 'Shop Address', 'Phone', 'Amount', 'Status', 'Payment Mode', 'Stall Type', 'Area (sqft)', 'Date', 'Inspector'];
fputcsv($output, $header);

// Add data rows
while($row = $result->fetch_assoc()) {
    $csvRow = [
        $row['receipt_number'],
        $row['shop_name'],
        $row['shop_address'],
        $row['shopkeeper_phone'],
        $row['total_amount'],
        $row['status'],
        $row['payment_mode'],
        $row['stall_type'],
        $row['area_sqft'],
        $row['created_at'],
        $row['inspector_name'] ?? 'N/A'
    ];
    fputcsv($output, $csvRow);
}

fclose($output);
exit();
?>
