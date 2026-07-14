<?php
session_start();
include 'db_connect.php';
include 'config.php';

if(!isset($_SESSION['user_id'])) die("Unauthorized");

$transaction_id = $_GET['id'];

// Fetch this transaction's details
$stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if(!$txn){
    die("Transaction not found.");
}

// Razorpay wants amount in PAISE, not rupees (₹500 = 50000 paise)
$amount_paise = $txn['total_amount'] * 100;

// --- Create an Order on Razorpay's servers ---
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "amount" => $amount_paise,
    "currency" => "INR",
    "receipt" => "txn_" . $transaction_id,   // ties this order back to YOUR transaction
    "payment_capture" => 1
]));

$response = curl_exec($ch);
$order = json_decode($response, true);
curl_close($ch);

if(!isset($order['id'])){
    die("Error creating payment order: " . $response);
}

// Save Razorpay's order id against our transaction, so the webhook can match it later
$stmt = $conn->prepare("UPDATE transactions SET payment_ref = ? WHERE id = ?");
$stmt->bind_param("si", $order['id'], $transaction_id);
$stmt->execute();

$razorpay_order_id = $order['id'];
?>