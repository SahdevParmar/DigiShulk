<link rel="stylesheet" href="style.css">
<?php 
session_start();
include 'db_connect.php';

if(!isset($_SESSION['user_id'])){
    header("Location:logout.php");
    exit();
}
$message="";
//language change
if($_SERVER["REQUEST_METHOD"]=="POST"&& isset($_POST['update_language'])){
    $_SESSION['lang']=$_POST['lang'];
    $message="language updated!";
}

//password change
if($_SERVER["REQUEST_METHOD"]=="POST"&&isset($_POST['update_password'])){
    if(!empty($_POST['password'])){
        $new_pass=password_hash($_POST['password'],PASSWORD_DEFAULT);
        $stmt=$conn->prepare("update users set password =? where id=?");
        $stmt->bind_param("si",$new_pass,$_SESSION['user_id']);
        if($stmt->execute()){
            $message="Password updated successfully";
        }else{
            $message="Error updating Password:".$stmt->error;
        }
    }
    else{
        $message="Password field was empty- no changes made.";
    }
}
include 'header.php'
?>
<div class="card">
    <h2>Settings</h2>
    <?php
    if($message):
    ?>
    <p style="color:yellow;font-weight:bold;" <?php echo $message; ?>></p>
    <?php endif; ?>
    <h3>Languages</h3>
    <form method="POST">
        <select name="lang" >
            <option value="en"<?php if(($_SESSION['lang']?? 'en')=='en') echo 'selected';?>>English</option>
            <option value="gu" <?php if(($_SESSION['lang'] ?? '')=='gu') echo 'selected'; ?> >ગુજરાતી (Gujarati)</option>
        </select>
        <button type="submit" name="update_language">Update Language</button>
    </form>

    <h3>Change Password</h3>
    <form method="POST">
        <input type="password" name="password" placeholder="New password (leave blank to keep current)">
        <button type="submit" name="update_password">Update Password</button>
    </form>
</div>