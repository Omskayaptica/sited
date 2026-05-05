<?php
/**
 * src/config.php - конфигурация для отправки писем
 * 
 * Загружает SMTP параметры из переменных окружения (.env файл)
 * 
 * Обязательные переменные:
 * - SMTP_HOST
 * - SMTP_USER
 * - SMTP_PASS
 * 
 * Опциональные переменные:
 * - SMTP_PORT (по умолчанию 465)
 * - SMTP_SECURE (по умолчанию ssl)
 * - MAIL_FROM_EMAIL
 * - MAIL_FROM_NAME
 */

// Обязательные параметры
$requiredParams = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS'];
foreach ($requiredParams as $param) {
    if (!getenv($param)) {
        throw new RuntimeException("Missing required environment variable: $param");
    }
}

return [
    // ============ SMTP Сервер ============
    'smtp_host'   => getenv('SMTP_HOST'),
    'smtp_port'   => (int)(getenv('SMTP_PORT') ?: 465),
    'smtp_secure' => getenv('SMTP_SECURE') ?: 'ssl',
    
    // ============ Учётные данные ============
    'smtp_user'   => getenv('SMTP_USER'),
    'smtp_pass'   => getenv('SMTP_PASS'),
    
    // ============ Отправитель ============
    'from_email'  => getenv('MAIL_FROM_EMAIL') ?: getenv('SMTP_USER'),
    'from_name'   => getenv('MAIL_FROM_NAME') ?: 'Application',
    
    // ============ Дополнительно ============
    'timeout'     => (int)(getenv('MAIL_TIMEOUT') ?: 10),
    'from_reply_email' => getenv('MAIL_REPLY_EMAIL') ?: null,
];