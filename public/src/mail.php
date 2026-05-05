<?php
// src/mail.php - отправка писем через PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Создание предконфигурированного объекта PHPMailer
 * 
 * @return PHPMailer
 * @throws Exception
 */
function getMailer(): PHPMailer {
    $cfg = require __DIR__ . '/config.php';
    
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $cfg['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $cfg['smtp_user'];
    $mail->Password = $cfg['smtp_pass'];
    $mail->SMTPSecure = $cfg['smtp_secure'] ?? 'ssl';
    $mail->Port = (int)($cfg['smtp_port'] ?? 465);
    
    // Кодировка и таймауты
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Timeout = 10;
    $mail->SMTPAutoTLS = true;
    
    // Отправитель
    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    
    // Debug логирование
    $mail->SMTPDebug = 0;
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer: $str");
    };
    
    return $mail;
}

/**
 * Отправка кода подтверждения
 */
function sendVerificationCode(string $to, string $code): bool {
    try {
        $mail = getMailer();
        $mail->addAddress($to);
        $mail->Subject = 'Код подтверждения';
        $mail->Body = "Ваш код подтверждения: $code";
        $mail->isHTML(false);
        
        $result = $mail->send();
        error_log("Verification code sent to $to");
        return $result;
    } catch (Exception $e) {
        error_log("Verification code error: " . $e->getMessage());
        return false;
    }
}

/**
 * Отправка письма восстановления пароля
 */
function sendPasswordResetEmail(string $email, string $user_name, string $reset_link): bool {
    try {
        $mail = getMailer();
        $mail->addAddress($email);
        $mail->Subject = 'Восстановление пароля';
        
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 4px;">
                    <h1>Восстановление пароля</h1>
                </div>
                <div style="padding: 30px; background: #f9f9f9; border: 1px solid #ddd;">
                    <p>Здравствуйте, {$user_name}!</p>
                    <p>Вы запросили восстановление пароля. Нажмите на кнопку ниже:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{$reset_link}" 
                           style="background: #007bff; color: white; padding: 12px 30px; 
                                  text-decoration: none; border-radius: 4px; display: inline-block;">
                            Сбросить пароль
                        </a>
                    </div>
                    <p>Или скопируйте ссылку:</p>
                    <code style="background: #eee; padding: 10px; display: block; word-break: break-all;">
                        {$reset_link}
                    </code>
                    <p><small><em>Ссылка действительна 1 час.</em></small></p>
                </div>
            </div>
        </body>
        </html>
        HTML;
        
        $text = "Восстановление пароля\n\n" .
                "Здравствуйте, $user_name!\n\n" .
                "Ссылка для сброса: $reset_link\n\n" .
                "Ссылка действительна 1 час.\n";
        
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        
        $result = $mail->send();
        error_log("Password reset email sent to $email");
        return $result;
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        return false;
    }
}

/**
 * Уведомление об изменении пароля
 */
function sendPasswordChangedNotification(string $email, string $user_name): bool {
    try {
        $mail = getMailer();
        $mail->addAddress($email);
        $mail->Subject = 'Пароль изменен';
        
        $date = date('d.m.Y H:i:s', strtotime('now', 0));
        
        $html = <<<HTML
        <div style="font-family: Arial, sans-serif;">
            <h2>Уведомление об изменении пароля</h2>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">
                <strong>✓ Пароль для вашего аккаунта был успешно изменен</strong>
            </div>
            <p>Здравствуйте, {$user_name}!</p>
            <p>Ваш пароль был изменен {$date}.</p>
            <p>Если это были не вы, <strong>немедленно свяжитесь с администрацией</strong>.</p>
        </div>
        HTML;
        
        $text = "Пароль изменен\n\n" .
                "Здравствуйте, $user_name!\n\n" .
                "Ваш пароль был изменен $date.\n\n" .
                "Если это были не вы, свяжитесь с администрацией.\n";
        
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        
        $result = $mail->send();
        error_log("Password changed notification sent to $email");
        return $result;
    } catch (Exception $e) {
        error_log("Password changed notification error: " . $e->getMessage());
        return false;
    }
}