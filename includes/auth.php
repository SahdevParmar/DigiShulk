<?php
session_start();
include 'db_connect.php';

$user = $_POST['username'];
$pass = $_POST['password'];

// Perform authentication logic here
$stmt=$conn->prepare("SELECT user_id,password,role from users where username=?");
if (!$stmt) {
    die("Database error. Did you forget to run migrate_v1.sql? Error: " . $conn->error);
}
$stmt->bind_param("s", $user);
$stmt->execute();
$result=$stmt->get_result();
if($row=$result->fetch_assoc()){
    if(password_verify($pass,$row['password'])){
        $_SESSION['user_id']=$row['user_id'];
        $_SESSION['role']=$row['role'];

        if($row['role']=='admin'){
            header("Location: admin_dashboard.php");
        } else if($row['role']=='inspector'){
            header("Location: dashboard.php");
        }
    } else {
        echo "Invalid password!";
    }
} else {
    echo "Invalid username!";
}
?>