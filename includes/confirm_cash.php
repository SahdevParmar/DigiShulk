<?php
session_start();
include 'db_connect.php';
require_once 'helpers/csrf.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

csrf_require_or_die();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$stmt = $conn->prepare("
    SELECT shopkeeper_phone, total_amount, inspector_id, status
    FROM transactions
    WHERE transaction_id = ? AND payment_mode = 'cash'
");
$stmt->bind_param('i', $id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if (!$txn || $txn['inspector_id'] != $_SESSION['user_id']) {
    die('Invalid request.');
}

if ($txn['status'] === 'paid') {
    header('Location: dashboard.php?paid=1');
    exit();
}

$update = $conn->prepare("UPDATE transactions SET status='paid' WHERE transaction_id=?");
$update->bind_param('i', $id);
$update->execute();

header('Location: dashboard.php?paid=1');
exit();