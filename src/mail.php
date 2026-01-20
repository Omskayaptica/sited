<?php
// src/mail.php - ИСПРАВЛЕННАЯ ВЕРСИЯ
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

// Общие настройки для всех писем
function getMailer(): PHPMailer {
    $cfg = require __DIR__ . '/config.php';
    
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['smtp_host'] ?? 'smtp.yandex.ru';
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['smtp_user'];
    $mail->Password   = $cfg['smtp_pass'];
    $mail->SMTPSecure = isset($cfg['smtp_secure']) ? $cfg['smtp_secure'] : 'ssl';
    $mail->Port       = $cfg['smtp_port'] ?? 465;
    
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Timeout = 10;
    $mail->SMTPKeepAlive = false;
    $mail->SMTPAutoTLS = true;
    
    $mail->setFrom(
        $cfg['from_email'], 
        $cfg['from_name'] ?? 'ТСЖ "Омская причал"'
    );
    
    // Логирование
    $log = '/var/www/mysite/logs/mail_debug.log';
    $mail->SMTPDebug = 0;
    $mail->Debugoutput = function($str, $level) use ($log) {
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . $str . PHP_EOL, FILE_APPEND);
    };
    
    return $mail;
}

function sendVerificationCode(string $to, string $code): bool {
    $log = '/var/www/mysite/logs/mail_debug.log';
    
    try {
        $mail = getMailer();
        $mail->addAddress($to);
        $mail->Subject = 'Код подтверждения';
        $mail->Body    = "Ваш код: $code";
        $mail->isHTML(false);
        
        $ok = $mail->send();
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "VERIFICATION CODE SENT to=$to\n", FILE_APPEND);
        return (bool)$ok;
    } catch (Exception $e) {
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "VERIFICATION CODE ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

function sendPasswordResetEmail($email, $user_name, $reset_link): bool {
    $log = '/var/www/mysite/logs/mail_debug.log';
    
    try {
        $mail = getMailer();
        $mail->addAddress($email);
        $mail->Subject = 'Восстановление пароля - ТСЖ "Омская причал"';
        
        // HTML версия
        $html = '
        <!DOCTYPE html>
        <html lang="ru">
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: #007bff; color: white; padding: 20px; text-align: center;">
                    <h1>ТСЖ "Омская причал"</h1>
                    <h2>Восстановление пароля</h2>
                </div>
                <div style="padding: 30px; background: #f9f9f9;">
                    <p>Здравствуйте, ' . htmlspecialchars($user_name) . '!</p>
                    <p>Вы запросили восстановление пароля.</p>
                    <p style="text-align: center;">
                        <a href="' . htmlspecialchars($reset_link) . '" 
                           style="display: inline-block; background: #007bff; color: white; 
                                  padding: 12px 24px; text-decoration: none; border-radius: 4px; 
                                  margin: 20px 0;">
                            Сбросить пароль
                        </a>
                    </p>
                    <p>Или скопируйте ссылку:<br>
                       <code style="word-break: break-all; background: #eee; padding: 10px; display: block;">
                       ' . htmlspecialchars($reset_link) . '</code>
                    </p>
                    <p><small>Ссылка действительна 1 час.</small></p>
                </div>
            </div>
        </body>
        </html>';
        
        // Текстовая версия
        $text = "Восстановление пароля - ТСЖ Омская причал\n\n" .
                "Здравствуйте, $user_name!\n\n" .
                "Ссылка для сброса пароля: $reset_link\n\n" .
                "Ссылка действительна 1 час.\n";
        
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        
        $ok = $mail->send();
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "PASSWORD RESET SENT to=$email, success=" . ($ok ? 'yes' : 'no') . "\n", FILE_APPEND);
        
        return (bool)$ok;
    } catch (Exception $e) {
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "PASSWORD RESET ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

function sendPasswordChangedNotification($email, $user_name): bool {
    $log = '/var/www/mysite/logs/mail_debug.log';
    
    try {
        $mail = getMailer();
        $mail->addAddress($email);
        $mail->Subject = 'Пароль изменен - ТСЖ "Омская причал"';
        
        $html = '
        <div style="font-family: Arial, sans-serif;">
            <h2>Уведомление об изменении пароля</h2>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px;">
                <strong>Пароль для вашего аккаунта был успешно изменен.</strong>
            </div>
            <p>Здравствуйте, ' . htmlspecialchars($user_name) . '!</p>
            <p>Ваш пароль был изменен ' . date('d.m.Y H:i:s') . '.</p>
            <p>Если это были не вы, свяжитесь с администрацией.</p>
        </div>';
        
        $text = "Уведомление об изменении пароля\n\n" .
                "Здравствуйте, $user_name!\n\n" .
                "Ваш пароль был изменен " . date('d.m.Y H:i:s') . ".\n" .
                "Если это были не вы, свяжитесь с администрацией.\n";
        
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        
        $ok = $mail->send();
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "PASSWORD CHANGED NOTIFICATION to=$email\n", FILE_APPEND);
        
        return (bool)$ok;
    } catch (Exception $e) {
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "PASSWORD CHANGED ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
?>
