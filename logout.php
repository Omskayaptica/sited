<?php
ob_start();
session_set_cookie_params([
    'lifetime' => 0,           // cookie живёт до закрытия браузера
    'path' => '/',
    'domain' => 'omkayaprica.shop', // замени на свой домен
    'secure' => true,          // только по HTTPS
    'httponly' => true,        // нельзя читать из JS
    'samesite' => 'Strict'     // запрет кросс-сайтовых запросов
]);
session_start();
$_SESSION = [];
setcookie(session_name(), '', time()-3600, '/', '', true, true);
session_destroy();
while (ob_get_level()) ob_end_clean();
header('Location: login.php');
exit;
