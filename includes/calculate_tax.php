<link rel="stylesheet" href="style.css">

<?php
session_start();
include 'db_connect.php';  

if(!isset($_SESSION['user_id'])) die("Unauthorized");

if($_SERVER["REQUEST_METHOD"]=="POST"){
    $stall_type= $_POST['stall_type'];
    $area=$_POST['size'];
    $shop_name=$_POST['shop_name'];
    $shop_address=$_POST['shop_address'];
    $phone=$_POST['phone'];

    


    $stmt=$conn->prepare("SELECT price_per_sqft FROM rates where stall_type=?");
    $stmt->bind_param("s",$stall_type);
    $stmt->execute();
    $result=$stmt->get_result()->fetch_assoc();
    $total_amount=$area * $result['price_per_sqft'];

    $stmt = $conn->prepare("INSERT INTO transactions (inspector_id, stall_type, area_sqft, total_amount, shop_name, shop_address, shopkeeper_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isidsss", $_SESSION['user_id'], $stall_type, $area, $total_amount, $shop_name, $shop_address, $phone);
    $stmt->execute();

    $transaction_id=$conn->insert_id;

    echo "Total Amount: $total_amount<br>";
    echo "<br><a href='process_payment.php?id=$transaction_id'>Pay Now</a>";

}
?>