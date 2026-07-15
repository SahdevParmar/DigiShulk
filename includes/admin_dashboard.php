<link rel="stylesheet" href="style.css">
<?php
session_start();
include 'db_connect.php';
if(!isset($_SESSION['role'])|| $_SESSION['role']!='admin'){
    header("Location: logout.php");
    exit();
}
include 'header.php';



//search
$query="select t.*,u.username from transactions t join users u on t.inspector_id=u.id";

if(isset($_GET['search'])&& !empty($_GET['search'])){
$search="%".$_GET['search']."%";
$query.=" where(shop_name like ? or Shopkeeper_phone like ?) order by t.created_at desc";
$stmt=$conn->prepare($query);
$stmt->bind_param("ss",$search,$search);
$stmt->execute();
$result=$stmt->get_result();
}else{
    $query.=" order by t.created_at desc";
    $result=$conn->query($query);
}

?>

<form method="GET">
    <div style="display:flex;gap:10px;">
        <input type="text" name="search" placeholder="Search">

        <button type="submit">Search</button>
    </div>
</form>
<br>


<table border="1">
    <tr>
        <th>Inspector</th>
        <th>Shop Name</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Time</th>
    </tr>
    <?php 
    $total=0;
    while($row=$result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['username'];?></td>
            <td><?php echo $row['shop_name'];?></td>
            <td><?php echo $row['total_amount'];?></td>
            <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
            <?php $stampClass = $row['status']=='paid' ? 'stamp-paid' : 'stamp-pending'; ?>
            <td><span class="stamp <?php echo $stampClass; ?>"><?php echo $row['status']; ?></span></td>
            <td><?php echo $row['created_at']; ?></td>
            <?php
            if($row['status']=='paid'){
                 $total+=$row['total_amount'];
            }
            ?>
        </tr>
        <?php endwhile;?>
</table>
<br>
<div class="total-collection">Total Collection for Selection: ₹<?php echo number_format($total,2);?></div>