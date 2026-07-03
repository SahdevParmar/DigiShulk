<?php
include 'db_connect.php';

$user = $_POST['username'];
$pass = $_POST['password'];

// Perform authentication logic here
$stmt=$conn->prepare("SELECT id,password from users where username=?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result=$stmt->get_result();
if($row=$result->fetch_assoc()){
    if($pass==$row['password']){
        session_start();
        $_SESSION['user_id']=$row['id'];
        echo "Login successful!";
    }else{
        echo "Invalid password!";
    }
}else{
    echo "Invalid username!";
}
?>