<link rel="stylesheet" href="style.css">


<?php
    session_start();
    if(!isset($_SESSION['role']) || $_SESSION['role']!='inspector'){
        header("Location: logout.php");
        exit();
    }
    ?>
    <?php
include 'header.php';
?>
<div class="card">
<h1>New Entry</h1>
<a href="logout.php">Logout</a>

<form action="calculate_tax.php" method="POST">
    <input type="text" name="shop_name" placeholder="Shop/Stall Name" required>
    <input type="text" name="shop_address" placeholder="Address/Location" required>
    <input type="tel" name="phone" placeholder="Phone Number" required >
    
    <label>Select Stall Type:</label>
    <select name="stall_type" required>
        <option value="Rekdi">Rekdi(Daily)</option>
        <option value="Mandap">Festival Mandap</option>
        <option value="Chhajli">Chhajli</option>
    </select>
    <label> Enter Size(in sq ft):</label>
    <input type="number" name="size" placeholder="Size in sq ft" required>
    <button type="submit">Calculate</button>
</form>
</div>