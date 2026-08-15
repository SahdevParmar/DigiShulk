
<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: logout.php");
    exit();
}
include 'db_connect.php';


if($_SERVER["REQUEST_METHOD"]=="POST"){
    $username=$_POST['username'];
    $password=password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt=$conn->prepare("insert into users(username,password) values(?,?)");
    $stmt->bind_param("ss",$username,$password);
    if($stmt->execute()){
        echo "Inspector added successfully!";
    } else {
        echo "Error adding inspector: ".$stmt->error;
    }
    exit();
}

include 'header.php';
?>
<div style="display: flex; flex-wrap: wrap; gap:50px;
        justify-content: center;"">
<div>
<?php
    $inspectors=$conn->query("select user_id,username,last_active from users where role='inspector'");
    echo "<table border='1'>
    <tr>
            <th>Inspector ID</th>
            <th>Username</th>
            <th>Last Active</th>
            <th>Manage</th>
        </tr>";
        while($row= $inspectors->fetch_assoc()){
            $is_online=(strtotime($row['last_active'])>strtotime('-1 minutes'))
                ? "<i class='fa-solid fa-circle-check status-online' aria-hidden='true'></i> Online"
                : "<i class='fa-solid fa-circle-xmark status-offline' aria-hidden='true'></i> Offline";
            echo "<tr>
            <td>".$row['user_id']."</td>
                <td>".$row['username']."</td>
                <td>".$is_online."</td>
                <td><a href='edit_inspector.php?id={$row['user_id']}'>Edit</a></td>
                </tr>";
        }
        echo "</table>";
?>
</div>
<div style="width:400px;">
    
<form method="POST">
    <h2>Add New Inspector</h2>
    <label>Username:</label>
    <input type="text" name="username" placeholder="Username" required>
    <label>Password:</label>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Add Inspector</button>
</form>
</div>

</div>
