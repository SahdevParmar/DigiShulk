<link rel="stylesheet" href="style.css">
<?php
if(session_status()==PHP_SESSION_NONE){
    session_start();
}
$lang=isset($_SESSION['lang'])? $_SESSION['lang']:'en';
$labels=[
    'en'=>['home'=>'Home','history'=>'History','settings'=>'Settings','logout'=>'Logout','manage'=>'Inspectors'],
    'gu'=>['home' => 'મુખ્ય પૃષ્ઠ', 'history' => 'ઇતિહાસ', 'settings' => 'સેટિંગ્સ', 'logout' => 'લોગ આઉટ', 'manage' => 'ઇન્સ્પેક્ટર મેનેજમેન્ટ']
];

?>
<div class="navarea">
<header class="navbar">
    <div class="logo">
    </div>
    <div class="emptySpace"></div>
    <nav>
        
        
        <?php if(isset($_SESSION['role']) && $_SESSION['role']=='admin'): ?>
            <a href="admin_dashboard.php"><?php echo $labels[$lang]['home'];?></a>
            <a href="add_inspector.php"><?php echo $labels[$lang]['manage'];?></a>
            <?php else:?>
                <a href="dashboard.php"><?php echo $labels[$lang]['home'];?></a>
            <?php endif; ?>
        <a href="history.php"><?php echo $labels[$lang]['history'];?></a>
        <a href="settings.php"><?php echo $labels[$lang]['settings']?></a>
        <a href="logout.php" class="logout-btn"><?php echo $labels[$lang]['logout'];?></a>
    </nav>

</header>
</div>