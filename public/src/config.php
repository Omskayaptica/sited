<?php

// Конфигурация почты и других сервисов.
// ВАЖНО: реальные пароли лучше передавать через переменные окружения (.env),
// а не хранить в репозитории.

return [
    // SMTP сервер
    'smtp_host'   => getenv('SMTP_HOST') ?: 'smtp.example.com',
    'smtp_port'   => (int)(getenv('SMTP_PORT') ?: 465),
    'smtp_secure' => getenv('SMTP_SECURE') ?: 'ssl', // ssl / tls

    // Учетные данные
    'smtp_user'   => getenv('SMTP_USER') ?: 'user@example.com',
    'smtp_pass'   => getenv('SMTP_PASS') ?: 'change-me',

    // Отправитель по умолчанию
    'from_email'  => getenv('MAIL_FROM_EMAIL') ?: 'no-reply@example.com',
    'from_name'   => getenv('MAIL_FROM_NAME') ?: 'ТСЖ "Наш Дом"',
];

