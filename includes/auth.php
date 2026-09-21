<?php
session_start();

require_once 'db_connect.php';
require_once 'helpers/csrf.php';

// CSRF first.
csrf_require_or_die();

$user = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
$pass = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($user === '' || $pass === '') {
    // Generic message — do not reveal which field is wrong.
    header('Location: login.php?error=1');
    exit();
}

$stmt = $conn->prepare(
    "SELECT user_id, password, role, full_name FROM users WHERE username = ? LIMIT 1"
);

if (!$stmt) {
    error_log('auth.php: prepare failed: ' . $conn->error);
    header('Location: login.php?error=1');
    exit();
}

$stmt->bind_param('s', $user);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

// Use a constant-time-ish check even when the user does not exist.
$dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
$hashToCheck = $row ? $row['password'] : $dummyHash;

if (password_verify($pass, $hashToCheck) && $row) {

    // Kill session-fixation.
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $row['user_id'];
    $_SESSION['role']      = $row['role'];
    $_SESSION['username']  = $user;
    $_SESSION['full_name'] = $row['full_name'];

    if ($row['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Generic failure — no username/password disclosure.
header('Location: login.php?error=1');
exit();
?>