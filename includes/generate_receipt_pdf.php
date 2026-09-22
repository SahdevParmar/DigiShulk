<?php
session_start();
require('db_connect.php');
require('fpdf.php');

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'inspector') {
    http_response_code(403);
    exit('Unauthorized');
}

$transaction_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$transaction_id) {
    die('Invalid Transaction ID');
}

// Fetch transaction details
$stmt = $conn->prepare("SELECT t.*, u.full_name as inspector_name FROM transactions t JOIN users u ON t.inspector_id = u.user_id WHERE t.transaction_id = ? AND t.inspector_id = ?");
$stmt->bind_param("ii", $transaction_id, $_SESSION['user_id']);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if(!$txn) {
    die('Transaction not found or access denied.');
}

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        // Arial bold 15
        $this->SetFont('Arial','B',15);
        // Move to the right
        $this->Cell(80);
        // Title
        $this->Cell(30,10,'RMC DigiShulk',0,0,'C');
        // Line break
        $this->Ln(15);
    }

    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial','I',8);
        // Page number
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    function ReceiptLine($label, $value) {
        $this->SetFont('Arial','B',12);
        $this->Cell(50, 10, $label, 0, 0);
        $this->SetFont('Arial','',12);
        $this->Cell(0, 10, $value, 0, 1);
    }
}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Payment Receipt',0,1,'C');
$pdf->Ln(5);

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,'Receipt #: ' . htmlspecialchars($txn['receipt_number'] ?? ''),0,1,'C');
$pdf->Ln(10);

$pdf->ReceiptLine('Shop Name:', htmlspecialchars($txn['shop_name']));
$pdf->ReceiptLine('Phone:', htmlspecialchars($txn['shopkeeper_phone']));
$pdf->ReceiptLine('Stall Type:', htmlspecialchars($txn['stall_type']));
$pdf->ReceiptLine('Date & Time:', date('d M Y, h:i A', strtotime($txn['created_at'])));
$pdf->ReceiptLine('Inspector:', htmlspecialchars($txn['inspector_name'] ?? ''));
$pdf->ReceiptLine('Payment Mode:', strtoupper($txn['payment_mode']));

$pdf->Ln(10);
$pdf->SetFont('Arial','B',18);
$pdf->Cell(0,10,'Total Amount: Rs. ' . number_format($txn['total_amount'],2),0,1,'C');

$pdf->Output('D', 'RMC_Receipt_'.$txn['receipt_number'].'.pdf');
?>
