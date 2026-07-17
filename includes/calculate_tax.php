<link rel="stylesheet" href="style.css">

<?php
session_start();
include 'db_connect.php';  

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'inspector') {
    http_response_code(403);
    exit('Unauthorized');
}

if($_SERVER["REQUEST_METHOD"] === "POST"){
    $stall_type = trim($_POST['stall_type'] ?? '');
    
    // If "Other" was picked, use their typed description instead
    if($stall_type === 'Other' && !empty($_POST['stall_type_other'])){
        $stall_type = trim($_POST['stall_type_other']);
    }
    
    $area = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_FLOAT);
    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_address = trim($_POST['shop_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $payment_mode = $_POST['payment_mode'] ?? '';
    $total_amount = floatval($_POST['amount']);

    if ($shop_name === '' || $shop_address === '' || $phone === '' || !$area || $area <= 0 ||
        $total_amount <= 0 || !in_array($payment_mode, ['cash', 'upi'], true)) {
        die("Invalid amount entered.");
    }

    $stmt = $conn->prepare("INSERT INTO transactions (inspector_id, stall_type, area_sqft, total_amount, shop_name, shop_address, shopkeeper_phone, payment_mode, status) VALUES (?,?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isidssss", $_SESSION['user_id'], $stall_type, $area, $total_amount, $shop_name, $shop_address, $phone, $payment_mode);
    $stmt->execute();
    $transaction_id = $conn->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO shops (shop_name, address, phone, stall_type) VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE address=VALUES(address), stall_type=VALUES(stall_type)");
    $stmt2->bind_param("ssss", $shop_name, $shop_address, $phone, $stall_type);
    $stmt2->execute();

    echo "Total Amount: ₹$total_amount<br>";
    echo "<br><a href='process_payment.php?id=$transaction_id'>Pay Now</a>";
}
?>
