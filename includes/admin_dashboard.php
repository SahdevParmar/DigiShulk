<link rel="stylesheet" href="style.css">
<?php
session_start();
include 'db_connect.php';
if(!isset($_SESSION['role'])|| $_SESSION['role']!='admin'){
    header("Location: logout.php");
    exit();
}
include 'header.php';
echo "<a href='logout.php'>Logout</a><br>";
echo "<a href='add_inspector.php'>Add New Inspector</a>";

$query="select* from transactions order by created_at desc";
$result=$conn->query($query);

$sum_query="select sum(total_amount) as daily_total from transactions where status='paid'";
$sum_result=$conn->query($sum_query)->fetch_assoc();
echo "<h2>Total Revenue Collected Today:".$sum_result['daily_total']."</h2>";

$query="select t.*,u.username from transactions t join users u on t.inspector_id=u.id order by t.created_at desc";

if(isset($_GET['search']) && !empty($_GET['search'])){
    $search=$_GET['search'];
    $query.=" and (shop_name like '%$search%' or shopkeeper_phone like '%$search%')";

}
$result=$conn->query($query);

?>

<form method="GET">
    <input type="text" name="search" placeholder="Search">
    <button type="submit">Search</button>
</form>

<table border="1">
    <tr>
        <th>Inspector</th>
        <th>Shop Name</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Time</th>
    </tr>
    <?php while($row=$result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['username'];?></td>
            <td><?php echo $row['shop_name'];?></td>
            <td><?php echo $row['total_amount'];?></td>
            <td><?php echo $row['status'];?></td>
            <td><?php echo $row['created_at'];?></td>
        </tr>
        <?php endwhile;?>
</table>
<br>
<?php
    $inspectors=$conn->query("select id,username,last_active from users where role='inspector'");
    echo "<table border='1'>
    <tr>
            <th>Inspector ID</th>
            <th>Username</th>
            <th>Last Active</th>
        </tr>";
        while($row= $inspectors->fetch_assoc()){
            $is_online=(strtotime($row['last_active'])>strtotime('-1 minutes'))? "🟢 Online" : "🔴 Offline";
            echo "<tr>
            <td>".$row['id']."</td>
                <td>".$row['username']."</td>
                <td>".$is_online."</td>
                <td><a href='edit_inspector.php?id={$row['id']}'>Edit</a></td>
                </tr>";
        }
        echo "</table>";
?>