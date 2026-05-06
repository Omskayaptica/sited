<?php
require_once '/var/www/mysite/inc/init.php'; 


if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')
) {
    header('Location: index.php');
    exit;
}

$_SESSION = [];

$cookieParams = session_get_cookie_params();
setcookie(
    session_name(),
    '',                          // пустое значение
    time() - 3600,              
    $cookieParams['path'],
    $cookieParams['domain'],
    $cookieParams['secure'],
    $cookieParams['httponly']
);

session_destroy();

header('Location: login.php');
exit;