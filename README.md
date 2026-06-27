# Веб-приложение для управления ТСЖ

Сервис для коммуникации между жильцами и председателем ТСЖ: жильцы подают заявки на ремонт, передают показания счётчиков и оплачивают начисления, а председатель управляет всем этим через панель администратора.

🌐 **Живая версия:** https://omkayaprica.shop/login.php

> Демо развёрнуто на VPS под HTTPS (Let's Encrypt), за реверс-прокси, с автодеплоем из этого репозитория и внешним мониторингом.

---

## Содержание

- [Возможности](#возможности)
- [Стек](#стек)
- [Архитектура](#архитектура)
- [Безопасность](#безопасность)
- [CI/CD](#cicd)
- [Мониторинг и алертинг](#мониторинг-и-алертинг)
- [Резервное копирование](#резервное-копирование)
- [SSL / HTTPS](#ssl--https)
- [Ветка с PostgreSQL](#ветка-с-postgresql)
- [Локальный запуск](#локальный-запуск)
- [Конфигурация](#конфигурация-env)
- [Структура проекта](#структура-проекта)
- [Скриншоты](#скриншоты)
- [Диагностика](#диагностика)

---

## Возможности

- Регистрация с верификацией email (код подтверждения с истечением срока)
- Две роли: **Жилец** и **Председатель ТСЖ**
- Заявки на ремонт со статусами: новая → в работе → выполнена / отклонена, с комментарием администратора
- Передача показаний счётчиков жильцами
- Просмотр показаний председателем и **экспорт в Excel**
- Расчёт и оплата начислений (переход на СБП — заглушка)
- Объявления от председателя (с закреплением)
- Восстановление пароля по одноразовому токену
- Защита форм через Cloudflare Turnstile
- Защита от брутфорса логина (лимит попыток по сессии)

---

## Стек

| Слой | Технология |
|------|-----------|
| Backend | PHP 8.1 (нативный, без фреймворков) |
| База данных | SQLite 3 (ветка `main`) / PostgreSQL (ветка `postgre`) |
| Frontend | HTML5, CSS3, Vanilla JS |
| Веб-сервер | Nginx 1.25 (alpine) + PHP-FPM |
| Контейнеризация | Docker, Docker Compose |
| Почта | PHPMailer (SMTP) |
| Антибот | Cloudflare Turnstile |
| CI/CD | GitHub Actions |
| Мониторинг | Grafana Cloud + Grafana Alloy |

---

## Архитектура

```
                          Internet
                             |
                       HTTPS | :443
                             v
                  +--------------------+
                  |  Nginx/Xray на     |   SSL (Let's Encrypt)
                  |   хосте (VPS)      |   reverse proxy
                  +---------+----------+
                            | 127.0.0.1:8080
        +-------------------+--------------------+
        |             docker compose             |
        |                                        |
        |   +--------------+    +--------------+ |
        |   | mysite_nginx |--->|  mysite_php  | |
        |   |   :80 (ro)   |FCGI|   PHP-FPM    | |
        |   +--------------+9000|    :9000     | |
        |                       +------+-------+ |
        |                              |         |
        |                       +------v-------+ |
        |                       |  SQLite DB   | |
        |                       | (volume,     | |
        |                       |  private/)   | |
        |                       +--------------+ |
        +----------------------------------------+
                            |
          +-----------------+------------------+
          v                 v                  v
   Grafana Alloy      GitHub Actions      PHPMailer
   -> Grafana Cloud   (deploy + backup)   -> SMTP
   -> алерты в TG
```

Контейнер `nginx` монтирует код только на чтение (`:ro`) и принимает соединения от хостового реверс-прокси на `127.0.0.1:8080` — наружу контейнеры напрямую не торчат. Для PHP и Nginx заданы health-проверки и лимиты CPU/RAM (сервер слабый, ресурсы ограничены сознательно).

---

## Безопасность

- **CSRF** — токен в сессии, проверяется на всех POST-формах
- **Пароли** — хеширование через bcrypt (`password_hash`)
- **Брутфорс** — не более 5 попыток входа за 5 минут на сессию
- **Антибот** — Cloudflare Turnstile на регистрации, входе и восстановлении пароля
- **Изоляция данных** — Nginx отдаёт `403/404` на пути к БД, `.env`, `.git` и приватным папкам; SQLite-файл лежит вне `public/`
- **Заголовки** — `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`
- **Секреты** — только через `.env` (в репозитории лежит `.env.example`), в коде нет хардкода

---

## CI/CD

Два пайплайна GitHub Actions:

**`docker-build.yml`** — при пуше в `main`:
1. Собирает Docker-образ
2. Прогоняет `php -l` (синтаксис-чек) по всем `.php`
3. При успехе деплоит на VPS по SSH: `git reset --hard`, пересборка и `docker compose up -d`
4. Ждёт статус `healthy` обоих контейнеров (до 30 попыток), иначе падает с выводом логов
5. Чистит висячие образы (`docker image prune`)

**`backup.yml`** — по расписанию (ежедневно в 02:00 UTC) и вручную: заходит на VPS по SSH и снимает резервную копию БД.

Доступы (`SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_PORT`) хранятся в GitHub Secrets.

---

## Мониторинг и алертинг

На VPS установлен **Grafana Alloy** — лёгкий агент (выбран из-за слабого сервера), который шлёт метрики в **Grafana Cloud**. Настроены алерты, например на нехватку места на диске; уведомления приходят в **Telegram**.

Пример уведомления:

```
[Resolved] Disk space — critical
summary: Disk is low
folder: test
datasource: grafanacloud-prom
```

---

## Резервное копирование

`scripts/backup.sh` копирует файл БД в `./backups/` с меткой времени и удаляет копии старше 7 дней (ротация). Скрипт вызывается с VPS по расписанию через workflow `backup.yml`.

```bash
bash scripts/backup.sh
# Backup: ./backups/database_20260623_020000.db
```

---

## SSL / HTTPS

HTTPS обеспечивается на хосте сертификатом **Let's Encrypt** с автообновлением. Терминация TLS и проксирование на контейнер настраиваются через отдельный установщик сервера — [Omskayaptica/solid-meme](https://github.com/Omskayaptica/solid-meme), который поднимает Nginx, выпускает сертификат и проксирует трафик на приложение.

---

## Ветка с PostgreSQL

Версия на **PostgreSQL** вынесена в отдельную ветку:
[`postgre`](https://github.com/Omskayaptica/sited/tree/postgre)

`main` использует SQLite (просто, без внешних зависимостей, удобно для демо). Ветка `postgre` переводит хранилище на PostgreSQL в отдельном контейнере — ближе к продакшен-сценарию с отдельным сервером БД.

---

## Локальный запуск

### Требования
- Docker и Docker Compose
- Git

### Установка

```bash
git clone https://github.com/Omskayaptica/sited.git
cd sited

# Создать .env из примера и заполнить значения
cp .env.example .env

# Поднять контейнеры
docker compose up -d --build
```

База данных создаётся автоматически при первом старте (`entrypoint.sh` -> `init_db.php`), включая дефолтного администратора.

### Доступ

- **URL:** http://127.0.0.1:8080
- **Админ по умолчанию:** `admin@yandex.ru` / `admin`

> Смените пароль администратора перед использованием в production.

---

## Конфигурация (`.env`)

Скопируйте `.env.example` в `.env` и заполните:

```env
# SMTP
SMTP_HOST=smtp.yandex.ru
SMTP_PORT=465
SMTP_USER=your@email.ru
SMTP_PASS=your-app-password

# Cloudflare Turnstile
TURNSTILE_SITE_KEY=your-site-key
TURNSTILE_SECRET_KEY=your-secret-key

# App
APP_ENV=production
```

Для Yandex используйте **пароль приложения**, а не основной пароль почты. Порт 465 — SSL, 587 — TLS.

---

## Структура проекта

```
sited/
├── .docker/
│   └── init_db.php              # Инициализация БД при первом старте
├── .github/workflows/
│   ├── docker-build.yml         # CI: сборка, php -l, деплой по SSH
│   └── backup.yml               # Бэкап БД по расписанию
├── docker/
│   ├── nginx/conf.d/default.conf# Конфиг Nginx (security, FCGI, блокировки)
│   └── healthcheck.sh           # Health-проверка PHP-FPM
├── public/                      # Корень сайта (отдаётся Nginx)
│   ├── inc/                     # init.php (CSRF, Turnstile, антибрут), header
│   ├── src/                     # config.php, db.php, mail.php
│   └── *.php                    # Страницы (login, register, dashboard, ...)
├── private/_hidden_db_/
│   └── schema.sql               # Схема БД + дефолтный админ
├── scripts/
│   └── backup.sh                # Бэкап БД с ротацией 7 дней
├── docker-compose.yml
├── Dockerfile                   # php:8.1-fpm + pdo_sqlite + composer
├── entrypoint.sh
└── .env.example
```

---

## Скриншоты

[Вход](docs/screenshots/login.png)
[Панель председателя](docs/screenshots/admin.png)
[Кабинет](docs/screenshots/dashboard.png)
[Показания](docs/screenshots/readings.png)
[Grafana](docs/screenshots/grafana.png)
[Алерт](docs/screenshots/tg-alert.png)

---

## Диагностика

```bash
# Статус и health контейнеров
docker compose ps

# Логи
docker logs mysite_php
docker logs mysite_nginx

# Health PHP-FPM вручную
docker exec mysite_php /usr/local/bin/php-fpm-healthcheck

# Письма не уходят — проверить SMTP в логах
docker logs mysite_php | grep -i mail

# Перезагрузить PHP без пересборки
docker exec mysite_php kill -USR2 1
```

| Проблема | Причина | Решение |
|----------|---------|---------|
| БД в режиме readonly | Права на файл/папку | `entrypoint.sh` чинит права при старте; перезапустите контейнер |
| Письма не отправляются | Неверный SMTP / не пароль приложения | Проверьте `.env`, см. логи `mail` |
| Turnstile не проходит | Неверные ключи или не передан реальный IP | Сверьте ключи в `.env`, проверьте `set_real_ip_from` в Nginx |

---

## Лицензия

MIT

