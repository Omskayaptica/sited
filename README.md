# Веб-приложение для управления ТСЖ

Сервис упрощает коммуникацию между жильцами и председателем ТСЖ.
Жильцы подают заявки на ремонт, председатель управляет ими через панель администратора.

## Технологии

- **Backend:** PHP 8.1 (нативный, без фреймворков)
- **Database:** SQLite 3
- **Frontend:** HTML5, CSS3, Vanilla JS
- **Infrastructure:** Docker, Docker Compose, Nginx, PHP-FPM

## Возможности

✅ Регистрация и верификация через Email  
✅ Роли пользователей (Жилец / Председатель ТСЖ)  
✅ Создание и управление заявками на ремонт  
✅ Система статусов заявок (новая, в работе, выполнена, отклонена)  
✅ Защита от CSRF атак  
✅ Хеширование паролей через bcrypt  

## Быстрый старт

### Требования

- Docker & Docker Compose
- Git

### Установка и запуск

```bash
# Клонировать репозиторий
git clone <ваш-репо>
cd site-main

# Запустить контейнеры
docker compose up -d --build

# Получить IP адрес WSL2 (если используется WSL2)
wsl ip addr show eth0 | grep -oP '(?<=inet\s)\d+(\.\d+){3}'
```

### Доступ к приложению

- **URL:** `http://<ваш-ip>:8080` (на другом PC) или `http://127.0.0.1:8080` (локально)
- **Админ по умолчанию:**
  - Email: `admin@yandex.ru`
  - Пароль: `admin`

## Структура проекта

```
site-main/
├── docker/              # Конфигурация Nginx
│   └── nginx/conf.d/
├── src/                 # PHP логика приложения
│   ├── config.php       # Конфиг (SMTP, БД параметры)
│   ├── db.php           # Подключение к SQLite
│   └── mail.php         # Отправка писем через PHPMailer
├── inc/                 # Включаемые файлы (header, init)
├── db/                  # База данных и схема
│   ├── schema.sql       # SQL схема и дефолтный админ
│   └── users.db         # SQLite БД (создаётся автоматически)
├── *.php                # Основные страницы (index, login, register и т.д.)
├── docker-compose.yml   # Конфиг Docker Compose
├── Dockerfile           # Конфиг PHP контейнера
└── entrypoint.sh        # Скрипт инициализации контейнера
```

## Настройка SMTP (Email)

Отредактируйте `src/config.php` и укажите параметры вашего почтового сервера:

```php
return [
    'smtp_host'   => 'smtp.yandex.ru',       // Ваш SMTP хост
    'smtp_user'   => 'your-email@yandex.ru', // Ваш email
    'smtp_pass'   => 'app-password',         // Пароль приложения
    'smtp_port'   => 465,                    // SMTP порт (465 для SSL, 587 для TLS)
    'from_email'  => 'your-email@yandex.ru',
    'from_name'   => 'ТСЖ Система',
];
```

## Управление контейнерами

```bash
# Запустить
docker compose up -d

# Остановить
docker compose down

# Пересоздать с новой сборкой образа
docker compose up -d --build

# Просмотр логов PHP
docker logs mysite_php

# Просмотр логов Nginx
docker logs mysite_nginx
```

## Учетные данные по умолчанию

| Роль | Email | Пароль |
|------|-------|--------|
| Админ | admin@yandex.ru | admin |

**⚠️ Измените пароль администратора перед использованием в production!**

## Разработка

Все файлы синхронизируются с хостом через volume. Изменения видны сразу без пересборки контейнера.

```bash
# Перезагрузить PHP после изменений (если нужно)
docker exec mysite_php kill -USR2 1
```

## Проблемы и решения

### БД работает в режиме readonly

Решение: `entrypoint.sh` автоматически исправляет права доступа при запуске контейнера.

### Письма не отправляются

1. Проверьте параметры SMTP в `src/config.php`
2. Посмотрите логи: `docker logs mysite_php | grep -i mail`
3. Убедитесь, что пароль приложения верный (не основной пароль от почты)

## Лицензия

MIT



Добавление администратора
Создать файл /var/www/mysite/add_admin.php:

    
<?php
// add_admin.php
require_once '/var/www/mysite/src/db.php';

$email = 'admin@tsj.local'; // Можно поменять
$password = 'admin';        // Пароль
$fullName = 'Председатель ТСЖ';
$role = 'admin';

// Генерируем правильный хеш
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->exec("DELETE FROM users WHERE email = '$email'");
    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, role, is_verified, apartment) VALUES (?, ?, ?, ?, 1, 'Офис')");
    $stmt->execute([$email, $hash, $fullName, $role]);

    echo "Администратор успешно создан!<br>";
    echo "Email: $email<br>";
    echo "Пароль: $password<br>";
    echo "<a href='public/login.php'>Войти</a>";

} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}

В консоли php /var/www/mysite/add_admin.php
Важно: После проверки удалить файл add_admin.php, чтобы никто случайно не сбросил админа.

rm /var/www/mysite/add_admin.php

    
