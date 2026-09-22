<?php
session_start();
if (isset($_POST['lang'])) {
    $allowed = ['en', 'hi', 'gu'];
    if (in_array($_POST['lang'], $allowed)) {
        $_SESSION['lang'] = $_POST['lang'];
        
        // Save to browser cookies for 1 year across the whole domain path (/)
        setcookie('user_lang', $_POST['lang'], time() + (365 * 24 * 60 * 60), '/');
    }
}
$referrer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: " . $referrer);
exit();
?>