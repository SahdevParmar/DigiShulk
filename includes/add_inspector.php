
<link rel="stylesheet" href="style.css">
<?php
session_start();
include 'db_connect.php';

if(!isset($_SESSION['role']) || $_SESSION['role']!='admin'){
    header("Location: logout.php");
    exit();
}
if($_SERVER["REQUEST_METHOD"]=="POST"){
    $username=$_POST['username'];
    $password=$_POST['password'];
    $stmt=$conn->prepare("insert into users(username,password) values(?,?)");
    $stmt->bind_param("ss",$username,$password);
    if($stmt->execute()){
        echo "Inspector added successfully!";
    } else {
        echo "Error adding inspector: ".$stmt->error;
    }
    exit();
}

?>
<form method="POST">
    <h2>Add New Inspector</h2>
    <label>Username:</label>
    <input type="text" name="username" placeholder="Username" required>
    <label>Password:</label>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Add Inspector</button>
</form>