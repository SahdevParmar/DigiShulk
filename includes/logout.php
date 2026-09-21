<?php
session_start();

require_once 'helpers/csrf.php';

// Only allow POST + valid CSRF. This blocks <img src="logout.php"> attacks.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    // A GET visit to logout.php just redirects home — does NOT log out.
    header('Location: ' . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php'));
    exit();
}

// Clear session data, then destroy the cookie.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit();