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

// --- PDF Generation ---

class PDF_Report extends FPDF
{
    // Page header
    function Header()
    {
        $this->SetFont('Helvetica','B',14);
        $this->Cell(0,10,'DigiShulk - Tax Collection Report',0,1,'C');
        $this->SetFont('Helvetica','',10);
        $this->Cell(0,7,'Generated on: ' . date('d-m-Y H:i:s'),0,1,'C');
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    // Table Header
    function ReportHeader($header)
    {
        $this->SetFillColor(240, 240, 240); // Light grey
        $this->SetFont('Helvetica','B',8);
        $w = array(30, 45, 20, 20, 35, 35); // Column widths
        for($i=0;$i<count($header);$i++)
            $this->Cell($w[$i],7,$header[$i],1,0,'C',true);
        $this->Ln();
    }

    // Table row
    function ReportRow($data)
    {
        $this->SetFont('Helvetica','',8);
        $w = array(30, 45, 20, 20, 35, 35); // Column widths
        $this->Cell($w[0],6,$data[0],'LR');
        $this->Cell($w[1],6,$data[1],'LR');
        $this->Cell($w[2],6,$data[2],'LR',0,'C');
        $this->Cell($w[3],6,$data[3],'LR',0,'C');
        $this->Cell($w[4],6,$data[4],'LR');
        $this->Cell($w[5],6,$data[5],'LR');
        $this->Ln();
    }
}

$pdf = new PDF_Report('L', 'mm', 'A4'); // Landscape, millimeters, A4
$pdf->AliasNbPages();
$pdf->AddPage();

// Define Table Header
$header = ['Receipt #', 'Shop Name', 'Amount', 'Status', 'Date', 'Inspector'];
$pdf->ReportHeader($header);

$fill = false;
while($row = $result->fetch_assoc()) {
    $data = [
        $row['receipt_number'],
        substr($row['shop_name'], 0, 25), // Truncate long names
        'Rs. ' . number_format($row['total_amount'], 2),
        ucfirst($row['status']),
        date('d-m-Y H:i', strtotime($row['created_at'])),
        substr($row['inspector_name'] ?? 'N/A', 0, 20)
    ];
    $pdf->ReportRow($data);
}
// Closing line
$pdf->Cell(array_sum(array(30, 45, 20, 20, 35, 35)),0,'','T');

$filename = "tax_report_" . date('Y-m-d') . ".pdf";
$pdf->Output('D', $filename);
exit();
?>
