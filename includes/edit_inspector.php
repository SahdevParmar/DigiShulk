<?php
session_start();
include 'db_connect.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: logout.php");
    exit();
}
$id=$_GET['id'];

if($_SERVER["REQUEST_METHOD"]=="GET"){
    $stmt=$conn->prepare("select * from users where id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $user=$stmt->get_result()->fetch_assoc();
}

if($_SERVER["REQUEST_METHOD"]=="POST"){
    if(isset($_POST['update_password'])){
        if(!empty($_POST['password'])){

            $new_pass=password_hash($_POST['password'],PASSWORD_DEFAULT);
            $stmt=$conn->prepare("Update users set password =? where id=?");
            $stmt->bind_param("si",$new_pass,$id);
            if($stmt->execute()){
            echo "Password updated successfully!";
                } else {
                    echo "Error updating password: ".$stmt->error;
                    }   
        }else{
            echo"no changes made- password field was empty.";
        }
        exit();
    }
    else if(isset($_POST['delete_inspector'])){
        $stmt=$conn->prepare("delete from users where id=?");
        $stmt->bind_param("i",$id);
        if($stmt->execute()){
            header("Location: admin_dashboard.php");
            exit();
        } else {
            echo "Error deleting inspector: ".$stmt->error;
        }
        
        exit();
    }
}
if(!isset($user)){
    $stmt=$conn->prepare("select * from users where id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $user=$stmt->get_result()->fetch_assoc();
}

include 'header.php';
?>
<form method="POST">
    <h2>Edit Inspector Password</h2>
    <label>Username:</label>
    <input type="text" name="username" value="<?php echo $user['username'];?>" readonly>
    <label>Password:</label>
    <input type="password" name="password" placeholder="Leave blank to keep current password">
    
    <button type="submit" name="update_password">Update Password</button>
</form>
<form method="POST" onsubmit="return confirm('Are you sure you want to delete this inspector?');">
        <button type="submit" name="delete_inspector">Delete This Inspector</button>
</form>