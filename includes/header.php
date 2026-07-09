<link rel="stylesheet" href="style.css">
<?php
if(session_status()==PHP_SESSION_NONE){
    session_start();
}
$lang=isset($_SESSION['lang'])? $_SESSION['lang']:'en';
$labels=[
    'en'=>['home'=>'Home','history'=>'History','settings'=>'Settings','logout'=>'Logout','manage'=>'Manage Inspectors'],
    'gu'=>['home' => 'મુખ્ય પૃષ્ઠ', 'history' => 'ઇતિહાસ', 'settings' => 'સેટિંગ્સ', 'logout' => 'લોગ આઉટ', 'manage' => 'ઇન્સ્પેક્ટર મેનેજમેન્ટ']
];

?>
<div class="navarea">
<header class="navbar">
    <div class="logo"></div>
    <div class="emptySpace"></div>
    <nav>
        <a href="dashboard.php"><?php echo $labels[$lang]['home'];?></a>
        <a href="history.php"><?php echo $labels[$lang]['history'];?></a>
        <?php if(isset($_SESSION['role']) && $_SESSION['role']=='admin'): ?>
            <a href="admin_dashboard.php"><?php echo $labels[$lang]['manage'];?></a>
            <?php endif; ?>
        <a href="settings.php"><?php echo $labels[$lang]['settings']?></a>
        <a href="logout.php" class="logout-btn"><?php echo $labels[$lang]['logout'];?></a>
    </nav>

</header>
</div>