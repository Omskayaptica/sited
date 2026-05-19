<?php
// inc/init.php — единый init: CSRF, PDO, простая защита от брута



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('TURNSTILE_SITE_KEY', getenv('TURNSTILE_SITE_KEY') ?: null);
define('TURNSTILE_SECRET_KEY', getenv('TURNSTILE_SECRET_KEY') ?: null);
define('SKIP_TURNSTILE_CHECK', getenv('SKIP_TURNSTILE_CHECK') === 'true');

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

function verifyTurnstile(string $secretKey, string $responseToken): array {
    // Проверка отключена для тестирования
    if (SKIP_TURNSTILE_CHECK) {
        return ['success' => true, 'error_codes' => []];
    }
    
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    $data = [
        'secret' => $secretKey,
        'response' => $responseToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];

    $context = stream_context_create($options);
    set_error_handler(function() { return true; });
    $result = file_get_contents($url, false, $context);
    restore_error_handler();

    if ($result === false) {
        error_log("Turnstile API недоступна или произошла ошибка сети");
        return ['success' => false, 'error' => 'network_error'];
    }

    $decoded = json_decode($result, true);
    if ($decoded === null) {
        error_log("Ошибка декодирования JSON от Turnstile: " . $result);
        return ['success' => false, 'error' => 'json_decode_error'];
    }

    return $decoded;
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

