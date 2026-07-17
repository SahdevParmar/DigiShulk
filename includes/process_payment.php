<?php
session_start();
include 'db_connect.php';
include 'config.php';

if(!isset($_SESSION['user_id'])) die("Unauthorized");

$transaction_id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if(!$txn) die("Transaction not found.");

if($txn['status'] === 'paid'){
    echo "<h2>This transaction is already marked paid.</h2>";
    exit();
}

// ---------- CASH: no gateway needed, just a confirm button ----------
if($txn['payment_mode'] === 'cash'){
    ?>
    <link rel="stylesheet" href="style2.css">
    <div class="card" style="text-align:center;">
        <h2>Cash Collection</h2>
        <p>Amount: <strong>₹<?php echo number_format($txn['total_amount'],2); ?></strong></p>
        <p>Shop: <?php echo htmlspecialchars($txn['shop_name']); ?></p>
        <form method="POST" action="confirm_cash.php">
            <input type="hidden" name="id" value="<?php echo $transaction_id; ?>">
            <button type="submit">Confirm Cash Received</button>
        </form>
    </div>
    <?php
    exit();
}

// ---------- UPI: create a Razorpay order, then show Checkout ----------
$amount_paise = $txn['total_amount'] * 100;

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "amount" => $amount_paise,
    "currency" => "INR",
    "receipt" => "txn_" . $transaction_id,
    "payment_capture" => 1
]));
$response = curl_exec($ch);
$order = json_decode($response, true);
curl_close($ch);

if(!isset($order['id'])){
    die("Error creating payment order: " . $response);
}

$stmt = $conn->prepare("UPDATE transactions SET payment_ref = ? WHERE id = ?");
$stmt->bind_param("si", $order['id'], $transaction_id);
$stmt->execute();
?>
<link rel="stylesheet" href="style2.css">
<div class="card" style="text-align:center;">
    <h2>Scan & Pay</h2>
    <p>Amount: <strong>₹<?php echo number_format($txn['total_amount'],2); ?></strong></p>
    <p>Shop: <?php echo htmlspecialchars($txn['shop_name']); ?></p>
    <button id="payBtn">Pay Now</button>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('payBtn').onclick = function(e){
    var options = {
        "key": "<?php echo RAZORPAY_KEY_ID; ?>",
        "amount": "<?php echo $amount_paise; ?>",
        "currency": "INR",
        "order_id": "<?php echo $order['id']; ?>",
        "name": "RMC DigiShulk",
        "description": "Spot Tax Payment",

        // Auto-fills the shopkeeper's details we already have —
        // skips the "enter your number" screen entirely
        "prefill": {
            "contact": "<?php echo $txn['shopkeeper_phone']; ?>",
            "name": "<?php echo htmlspecialchars($txn['shop_name']); ?>"
        },

        // Hides Cards/Netbanking/Wallet/Pay Later — leaves only UPI
        "config": {
            "display": {
                "hide": [
                    { "method": "card" },
                    { "method": "netbanking" },
                    { "method": "wallet" },
                    { "method": "paylater" },
                    { "method": "emi" }
                ]
            }
        },

        "handler": function(response){
            document.querySelector('.card').innerHTML =
                "<h2>Payment submitted!</h2><p>Confirming with RMC servers...</p>" +
                "<a href='dashboard.php'>Return to Dashboard</a>";
        },
        "theme": { "color": "#2563eb" }
    };
    var rzp = new Razorpay(options);
    rzp.open();
    e.preventDefault();
};
</script>