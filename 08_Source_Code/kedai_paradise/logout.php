<?php
/**
 * Logout: clears the session, removes the session cookie and returns to the login page.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => 'Strict',
    ]);
}

session_destroy();
redirect('login.php');
