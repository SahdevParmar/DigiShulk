<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'inspector') {
    header("Location: logout.php");
    exit();
}

include 'db_connect.php';
include 'header.php';

$inspector = $_SESSION['user_id'];

/* -----------------------------
   Today's Statistics
------------------------------*/

$stmt = $conn->prepare("
SELECT
COALESCE(SUM(total_amount),0) AS total,
COALESCE(SUM(CASE WHEN payment_mode='cash' THEN total_amount ELSE 0 END),0) AS cash_total,
COALESCE(SUM(CASE WHEN payment_mode='upi' THEN total_amount ELSE 0 END),0) AS upi_total
FROM transactions
WHERE inspector_id=?
AND DATE(created_at)=CURDATE()
");

$stmt->bind_param("i",$inspector);
$stmt->execute();
$stats=$stmt->get_result()->fetch_assoc();

/* -----------------------------
   Recent Collections
------------------------------*/

$stmt=$conn->prepare("
SELECT shop_name,total_amount,payment_mode,created_at
FROM transactions
WHERE inspector_id=?
ORDER BY created_at DESC
LIMIT 5
");

$stmt->bind_param("i",$inspector);
$stmt->execute();

$recent=$stmt->get_result();

?>

<div class="dashboard">

    <h1>👋 Welcome</h1>

    <p class="subtitle">
        Ready to start today's work.
    </p>

    <div class="stats-grid">

        <div class="stat-card">
            <span>Today's Collection</span>
            <h2>₹<?=number_format($stats['total'],2)?></h2>
        </div>

        <div class="stat-card">
            <span>Cash</span>
            <h2>₹<?=number_format($stats['cash_total'],2)?></h2>
        </div>

        <div class="stat-card">
            <span>UPI</span>
            <h2>₹<?=number_format($stats['upi_total'],2)?></h2>
        </div>

    </div>

    <div class="quick-actions">

        <a href="spot_tax.php" class="action-btn">
            🧾
            <strong>Spot Tax</strong>
        </a>

        <a href="seizure_form.php" class="action-btn">
            🚨
            <strong>Seizure Report</strong>
        </a>

        <a href="history.php" class="action-btn">
            📜
            <strong>History</strong>
        </a>

    </div>

    <div class="card">

        <h2>Recent Collections</h2>

        <table>

            <thead>

            <tr>
                <th>Shop</th>
                <th>Amount</th>
                <th>Mode</th>
                <th>Time</th>
            </tr>

            </thead>

            <tbody>

            <?php while($row=$recent->fetch_assoc()): ?>

                <tr>

                    <td><?=htmlspecialchars($row['shop_name'])?></td>

                    <td>₹<?=$row['total_amount']?></td>

                    <td><?=strtoupper($row['payment_mode'])?></td>

                    <td><?=date("h:i A",strtotime($row['created_at']))?></td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>