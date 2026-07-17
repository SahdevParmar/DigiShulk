<?php
session_start();
include 'db_connect.php';
include 'sms_helper.php';

if(!isset($_SESSION['user_id'])) die("Unauthorized");

$id = $_POST['id'] ?? 0;

$stmt = $conn->prepare("SELECT shopkeeper_phone, total_amount, inspector_id FROM transactions WHERE id=? AND payment_mode='cash'");
$stmt->bind_param("i", $id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if(!$txn || $txn['inspector_id'] != $_SESSION['user_id']){
    die("Invalid request.");
}

$update = $conn->prepare("UPDATE transactions SET status='paid' WHERE id=?");
$update->bind_param("i", $id);
$update->execute();

send_payment_sms($txn['shopkeeper_phone'], $txn['total_amount']);

header("Location: dashboard.php?paid=1");
exit();
?>