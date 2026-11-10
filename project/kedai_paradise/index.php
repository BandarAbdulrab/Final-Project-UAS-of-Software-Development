<?php
/**
 * Entry point: send visitors to the login page, or to the home page of their role.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth_functions.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    redirect('login.php');
}

redirect(homePageFor((string)$_SESSION['role']));