<?php
include 'db_connect.php';
include 'header.php';
$date_from=$_GET['date_from'] ??'';
$date_to=$_GET['date_to']??'';
$status=$_GET['status']??'';
$payment_mode=$_GET['payment_mode']??'';

$sql="select*from transactions where 1=1";
$parmas=[];
$types="";

if(!empty($date_from)){
    $sql .=" and date(created_at)>= ?";
    $parmas[]=$date_from;
    $types.="s";
}
if(!empty($date_to)){
    $sql .= " and date(created_at)<=?";
    $parmas[]=$date_to;
    $types.="s";
}
if(!empty($status)){
    $sql.=" and status=?";
    $parmas[]=$status;
    $types.="s";
}
if(!empty($payment_mode)){
    $sql.=" and payment_mode=?";
    $parmas[]=$payment_mode;
    $types.="s";
}

$stmt=$conn->prepare($sql);
if(!empty($parmas)){
    $stmt->bind_param($types,...$parmas);
}
$stmt->execute();
$result=$stmt->get_result();

?>
<div style="display: flex; flex-wrap: wrap;gap:50px;
        justify-content: center;"">
    <div>
    <table>
        <tr>
            <th>Shop Name</th>
            <th>Amount</th>
            <th>Date</th>
            <th>Status</th>
        </tr>
        <?php 
        $total=0;
        while($row=$result->fetch_assoc()):?>
        <tr>
            <td><?php echo $row['shop_name'];?></td>
            <td><?php echo $row['total_amount'];?></td>
            <td><?php echo $row['created_at'];?></td>
            <?php $stampClass = $row['status']=='paid' ? 'stamp-paid' : 'stamp-pending'; ?>
            <td><span class="stamp <?php echo $stampClass; ?>"><?php echo $row['status']; ?></span></td>
            <?php
            if($row['status']=='paid'){
                 $total+=$row['total_amount'];
            }
            ?>
        </tr>
        <?php endwhile; 
        
        ?>
        
    </table>
    </div>
<div >
 <form method="GET" class="card">
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <!-- Date Inputs -->
        <div>
            <label>From:</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        <div>
            <label>To:</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        
        <!-- Status Dropdown -->
        <div>
            <label>Status:</label>
            <select name="status">
                <option value="">All</option>
                <option value="paid" <?php if($status=='paid') echo 'selected'; ?>>Paid</option>
                <option value="pending" <?php if($status=='pending') echo 'selected'; ?>>Pending</option>
            </select>
        </div>
        <div>
            <label>Payment Mode:</label>
            <select name="payment_mode">
                <option value="">All</option>
                <option value="cash"<?php if($payment_mode=='cash') echo 'selected';?>>Cash</option>
                <option value="upi" <?php if($payment_mode=='upi') echo 'selected';?>>UPI</option>
            </select>
        </div>

        <button type="submit" style="background:#2ecc71; padding: 15px 30px; font-size: 1.1rem; border-radius: 8px;">
            Apply Filters
        </button>
    </div>
</form>
<br>
<div class="total-collection">Total Collection for Selection: ₹<?php echo number_format($total,2);?></div>
</div>

</div>
    
</div>
