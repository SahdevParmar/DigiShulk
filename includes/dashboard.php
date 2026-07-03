<?php
    session_start();
    if(!isset($_SESSION['user_id'])){
        header("Location: login.php");
        exit();
    }
    ?>
<h1>Welcome,inspector</h1>
<a href="logout.php">Logout</a>

<form action="calculate_tax.php" method="POST">
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