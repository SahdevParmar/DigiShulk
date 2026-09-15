<?php
/**
 * CSRF protection — pure PHP, no framework.
 *
 * Usage in any form:
 *     <?= csrf_field() ?>
 *
 * Usage in any POST handler:
 *     require_once 'helpers/csrf.php';
 *     csrf_require_or_die();
 */

function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function csrf_verify()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sent = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if ($sent === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $sent);
}

function csrf_require_or_die()
{
    if (!csrf_verify()) {
        http_response_code(419);
        header('Content-Type: text/plain; charset=utf-8');
        exit('CSRF token invalid or missing. Please reload and try again.');
    }
}