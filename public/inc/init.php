<?php
// inc/init.php — единый init: CSRF, PDO, простая защита от брута



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('TURNSTILE_SITE_KEY', getenv('TURNSTILE_SITE_KEY') ?: 'default_value_if_not_set');
define('TURNSTILE_SECRET_KEY', getenv('TURNSTILE_SECRET_KEY'));

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Простая защита от грубой силы (session-based)
if (!isset($_SESSION['failed_login'])) {
    $_SESSION['failed_login'] = ['count' => 0, 'first_ts' => 0];
}
function too_many_attempts(): bool {
    $f = &$_SESSION['failed_login'];
    if ($f['count'] >= 5 && (time() - $f['first_ts']) < 300) return true;
    if ((time() - $f['first_ts']) >= 300) {
        $f = ['count' => 0, 'first_ts' => 0];
    }
    return false;
}
function record_failed_attempt(): void {
    $f = &$_SESSION['failed_login'];
    if ($f['first_ts'] === 0) $f['first_ts'] = time();
    $f['count'] = ($f['count'] ?? 0) + 1;
}
function reset_attempts(): void {
    $_SESSION['failed_login'] = ['count' => 0, 'first_ts' => 0];
}

function statusLabel(string $status): string {
    return match($status) {
        'new'         => 'Новая',
        'in_progress' => 'В работе',
        'done'        => 'Выполнена',
        'rejected'    => 'Отклонена',
        default       => $status
    };
}

