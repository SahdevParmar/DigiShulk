<link rel="stylesheet" href="style.css">

<?php
session_start();
include 'db_connect.php';  

if(!isset($_SESSION['user_id'])) die("Unauthorized");

if($_SERVER["REQUEST_METHOD"]=="POST"){
    $stall_type = $_POST['stall_type'];
    
    // If "Other" was picked, use their typed description instead
    if($stall_type === 'Other' && !empty($_POST['stall_type_other'])){
        $stall_type = trim($_POST['stall_type_other']);
    }
    
    $area = $_POST['size'];
    $shop_name = $_POST['shop_name'];
    $shop_address = $_POST['shop_address'];
    $phone = $_POST['phone'];
    $payment_mode = $_POST['payment_mode'];
    $total_amount = floatval($_POST['amount']);

    if($total_amount <= 0){
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