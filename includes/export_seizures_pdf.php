<?php
session_start();
include 'db_connect.php';
require 'fpdf.php';

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

// Build the query to get seizure SESSIONS
$sql = "SELECT s.*, u.full_name as inspector_name FROM seizure_sessions s LEFT JOIN users u ON s.inspector_id = u.user_id WHERE 1=1";
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
$sessions = $stmt->get_result();

// --- PDF Generation ---

class PDF_Seizure_Report extends FPDF
{
    function Header()
    {
        $this->SetFont('Helvetica','B',14);
        $this->Cell(0,10,'DigiShulk - Seizure Report',0,1,'C');
        $this->SetFont('Helvetica','',10);
        $this->Cell(0,7,'Generated on: ' . date('d-m-Y H:i:s'),0,1,'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    function SessionHeader($data)
    {
        $this->SetFont('Helvetica','B',11);
        $this->SetFillColor(230,230,230);
        $this->Cell(0, 8, "Session Date: " . date('d M Y', strtotime($data['seizure_date'])) . "  |  Team Leader: " . $data['team_leader_name'] . "  |  Zone: " . $data['zone'], 1, 1, 'L', true);
        $this->SetFont('Helvetica','',9);
        $this->Cell(0, 6, "Inspector: " . ($data['inspector_name'] ?? 'N/A') . "  |  Team No: " . $data['team_number'], 'LRB', 1, 'L');
        $this->Ln(2);
    }

    function ItemsTable($items_result)
    {
        $this->SetFont('Helvetica','B',9);
        $this->SetFillColor(245,245,245);
        $this->Cell(60, 7, 'Item Details', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Qty', 1, 0, 'C', true);
        $this->Cell(60, 7, 'Owner/Merchant', 1, 0, 'C', true);
        $this->Cell(60, 7, 'Location', 1, 0, 'C', true);
        $this->Cell(35, 7, 'Godown No.', 1, 1, 'C', true);

        $this->SetFont('Helvetica','',8);
        while($item = $items_result->fetch_assoc()){
            $this->Cell(60, 6, substr($item['item_details'], 0, 40), 1);
            $this->Cell(20, 6, $item['quantity'], 1, 0, 'C');
            $this->Cell(60, 6, substr($item['owner_merchant_name'], 0, 40), 1);
            $this->Cell(60, 6, substr($item['seizure_location'], 0, 40), 1);
            $this->Cell(35, 6, substr($item['godown_register_no'], 0, 20), 1, 1);
        }
    }
}

$pdf = new PDF_Seizure_Report('L', 'mm', 'A4'); // Landscape
$pdf->AliasNbPages();
$pdf->AddPage();

if($sessions->num_rows > 0) {
    while($session = $sessions->fetch_assoc()) {
        $pdf->SessionHeader($session);

        // Fetch items for this session
        $items_stmt = $conn->prepare("SELECT * FROM seizure_items WHERE session_id = ?");
        $items_stmt->bind_param("i", $session['session_id']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        if($items_result->num_rows > 0){
            $pdf->ItemsTable($items_result);
        } else {
            $pdf->SetFont('Helvetica','I',9);
            $pdf->Cell(0,10,'No items recorded for this session.',0,1);
        }
        $pdf->Ln(10); // Space between sessions
    }
} else {
    $pdf->SetFont('Helvetica','B',12);
    $pdf->Cell(0,20,'No seizure records found for the selected filters.',0,1,'C');
}

$filename = "seizure_report_" . date('Y-m-d') . ".pdf";
$pdf->Output('D', $filename);
exit();
?>
