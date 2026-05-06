# ТСЖ «Наш Дом» — Система управления жилищно-коммунальным хозяйством

Веб-приложение для управления ТСЖ, облегчающее коммуникацию между жильцами и администрацией. Жильцы подают заявки на ремонт, передают показания счётчиков, а председатель ТСЖ управляет всем через панель администратора.

---

## 🔧 Технологии

| Компонент | Технология |
|-----------|-----------|
| **Backend** | PHP 8.1 (нативный, без фреймворков) |
| **БД** | SQLite 3 |
| **Frontend** | HTML5, CSS3 (Tailwind), Vanilla JS |
| **Безопасность** | Cloudflare Turnstile (защита от ботов), bcrypt пароли, CSRF токены |
| **Инфраструктура** | Docker, Docker Compose, Nginx, PHP-FPM |

---

## ✨ Основные возможности

### Для жильцов 🏠
- ✅ **Регистрация и верификация** — через Email с кодом подтверждения (6 цифр)
- ✅ **Профиль** — просмотр и редактирование ФИО, телефона, изменение пароля
- ✅ **Заявки на ремонт** — создание, просмотр статуса, история обращений
- ✅ **Передача показаний** — ежемесячная передача показаний по счётчикам воды и электричества
- ✅ **История платежей** — просмотр начислений и задолженности
- ✅ **Статистика на главной** — показания за месяц, размер задолженности, кол-во открытых заявок
- ✅ **Объявления** — важная информация от администрации на главной странице

### Для администрации 👨‍💼
- ✅ **Управление заявками** — просмотр, изменение статуса, добавление комментариев
- ✅ **Журнал показаний** — сводная таблица по всем квартирам за месяц
- ✅ **Управление объявлениями** — создание, редактирование, удаление, закрепление
- ✅ **Панель администратора** — быстрый доступ ко всем функциям

### Безопасность 🔒
- ✅ **CSRF защита** — все POST запросы требуют валидный токен
- ✅ **Cloudflare Turnstile** — защита от автоматических атак при регистрации и входе
- ✅ **Хеширование паролей** — bcrypt алгоритм (PASSWORD_DEFAULT)
- ✅ **SQL инъекции** — подготовленные запросы (prepared statements)
- ✅ **XSS защита** — HTML экранирование (`htmlspecialchars`)
- ✅ **Валидация на сервере** — email, пароли, номера квартир (поддержка русских букв)
- ✅ **Безопасные cookies** — httponly, secure, samesite=Strict
- ✅ **Безопасный выход** — POST запрос вместо GET

---

## 📁 Структура проекта

```
sited/
├── public/                          # Веб-приложение
│   ├── index.php                    # 🏠 Главная (статистика + объявления)
│   ├── login.php                    # 🔐 Вход (с Turnstile)
│   ├── register.php                 # ✍️ Регистрация (с Turnstile)
│   ├── verify.php                   # ✉️ Подтверждение email (6 цифр)
│   ├── logout.php                   # 🚪 Выход (POST + CSRF)
│   ├── forgot-password.php          # 🔑 Восстановление пароля
│   ├── reset-password.php           # 🔄 Сброс пароля (по ссылке)
│   ├── profile.php                  # 👤 Профиль + смена пароля
│   │
│   ├── my-requests.php              # 📋 Мои заявки (жилец)
│   ├── my-payments.php              # 💳 История платежей
│   ├── meter-submit.php             # ⚡ Передача показаний
│   │
│   ├── admin-requests.php           # 📋 Все заявки (админ)
│   ├── admin-readings.php           # 📊 Журнал показаний (админ)
│   ├── admin-announcements.php      # 📢 Управление объявлениями (админ)
│   ├── admin_edit.php               # ✏️ Редактор заявок (админ)
│   │
│   ├── inc/
│   │   ├── init.php                 # Инициализация (сессии, CSRF, constants)
│   │   └── header.php               # Шапка сайта (навигация, favicon)
│   │
│   └── src/
│       ├── config.php               # Конфигурация (Turnstile, SMTP, пути)
│       ├── db.php                   # Подключение к БД (PDO), verifyTurnstile()
│       └── mail.php                 # Отправка писем (регистрация, восстановление)
│
├── private/
│   └── _hidden_db_/
│       └── schema.sql               # Схема БД SQLite
│
├── docker/
│   ├── healthcheck.sh               # Проверка здоровья контейнера
│   └── nginx/
│       └── conf.d/
│           └── default.conf         # Конфигурация Nginx
│
├── scripts/
│   └── backup.sh                    # Скрипт резервной копии БД
│
├── docker-compose.yml               # Оркестрация контейнеров
├── Dockerfile                       # Образ PHP приложения
├── entrypoint.sh                    # Точка входа контейнера
└── README.md                        # Этот файл
```

---

## 🗄️ Структура базы данных

### Таблица `users`
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    full_name TEXT NOT NULL,
    apartment TEXT NOT NULL,
    phone TEXT,
    role TEXT DEFAULT 'resident' CHECK(role IN ('admin', 'resident')),
    is_verified INTEGER DEFAULT 0,
    verify_code_hash TEXT,
    verify_expires INTEGER,
    verify_attempts INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Таблица `requests`
```sql
CREATE TABLE requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    status TEXT DEFAULT 'new' CHECK(status IN ('new', 'in_progress', 'done', 'rejected')),
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
```

### Таблица `meter_readings`
```sql
CREATE TABLE meter_readings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    month_year TEXT NOT NULL,
    water_cold REAL,
    water_hot REAL,
    electricity REAL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id),
    UNIQUE(user_id, month_year)
);
```

### Таблица `bills`
```sql
CREATE TABLE bills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    month_year TEXT NOT NULL,
    amount REAL NOT NULL,
    status TEXT DEFAULT 'unpaid' CHECK(status IN ('paid', 'unpaid')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
```

### Таблица `announcements`
```sql
CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    is_pinned INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Таблица `password_resets`
```sql
CREATE TABLE password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used INTEGER DEFAULT 0,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
```

---

## 🚀 Быстрый старт

### Требования
- Docker & Docker Compose
- Git
- Современный браузер

### Установка

```bash
# Клонировать репозиторий
git clone https://github.com/Omichpolyebok/sited.git
cd sited

# Запустить контейнеры
docker compose up -d --build

# Получить IP адрес (для WSL2)
wsl hostname -I
```

### Доступ к приложению

- **URL:** `http://<ваш-ip>:8080` или `http://localhost:8080`
- **Админ по умолчанию:**
  - Email: `admin@yandex.ru`
  - Пароль: `password123`

---

## 📝 Описание ключевых файлов

### `public/index.php` — Главная страница
- Статистика для жилца: ✅ показания, 💰 задолженность, 📋 открытые заявки
- Все объявления (закреплённые сверху)
- Карточки быстрого доступа к функциям
- Только для авторизованных пользователей

### `public/register.php` — Регистрация
- ✅ Email валидация (макс 255 символов)
- ✅ Пароль валидация (мин 8 символов)
- ✅ Cloudflare Turnstile для защиты от ботов
- ✅ Санитизация номера квартиры: цифры + русские/английские буквы (1-6 цифр)
- ✅ Отправка кода подтверждения по email
- ✅ CSRF защита

### `public/verify.php` — Подтверждение email
- Проверка кода подтверждения (6 цифр)
- Ограничение попыток (max 5 попыток)
- Срок действия кода (15 минут)
- Редирект на вход после подтверждения

### `public/login.php` — Вход
- ✅ Email/пароль валидация
- ✅ Cloudflare Turnstile
- ✅ Проверка подтверждения email
- ✅ Безопасные сессии с httponly cookies
- ✅ CSRF защита

### `public/logout.php` — Выход
- POST запрос (безопаснее, чем GET)
- CSRF токен обязателен
- Полная очистка сессии и cookies

### `public/profile.php` — Профиль
- Редактирование ФИО и телефона (макс 255 символов)
- Смена пароля (требуется текущий пароль, новый мин 8 символов)
- CSRF защита на всех формах

### `public/meter-submit.php` — Передача показаний
- Ежемесячная передача показаний счётчиков
- Валидация числовых значений
- Проверка дублей (одна запись в месяц)
- История предыдущих показаний

### `public/admin-announcements.php` — Управление объявлениями
- Создание новых объявлений
- Редактирование (без поля updated_at)
- Удаление объявлений
- Закрепление объявления (📌 сверху на главной)

### `inc/init.php` — Инициализация
- Подключение сессии с безопасными параметрами
- Генерация CSRF токена
- Загрузка конфигурации (constants)
- Проверка требуемых переменных окружения

### `inc/header.php` — Навигация
- Функция `render_head_content()` — favicon и meta теги
- Функция `render_header()` — навигация с меню
- Адаптивная (мобильная + десктоп)
- Меню зависит от роли (админ/жилец)

### `src/db.php` — База данных
- Подключение к SQLite через PDO
- Функция `verifyTurnstile()` для проверки токенов Cloudflare
- Обработка ошибок соединения

### `src/mail.php` — Отправка писем
- `sendVerificationCode()` — код подтверждения регистрации
- `sendPasswordResetEmail()` — ссылка восстановления пароля
- `sendPasswordChangedNotification()` — уведомление о смене пароля

### `src/config.php` — Конфигурация
- `TURNSTILE_SITE_KEY` — публичный ключ Cloudflare
- `TURNSTILE_SECRET_KEY` — секретный ключ
- `MAIL_HOST`, `MAIL_USER`, `MAIL_PASS` — параметры почты (SMTP)
- `BASE_PATH` — абсолютный путь к приложению

---

## 🔐 Безопасность

### Защита от CSRF
- Каждая форма содержит CSRF токен
- Проверка через `hash_equals()` (устойчива к timing attacks)
- Всё POST запросы требуют валидный токен

### Защита от SQL инъекций
- Все запросы используют подготовленные statements (prepared statements)
- Параметры передаются отдельно от SQL кода

### Защита от XSS
- Весь вывод в HTML экранируется через `htmlspecialchars()`
- Использование Tailwind CSS вместо inline styles

### Защита от ботов
- Cloudflare Turnstile на регистрации и входе
- JavaScript валидация на клиенте
- Проверка токенов на сервере

### Безопасные куки
- `httponly` — недоступны JavaScript
- `secure` — передаются только по HTTPS
- `samesite=Strict` — защита от CSRF

### Хеширование паролей
- Использование `password_hash()` с bcrypt
- Проверка через `password_verify()`

---

## 🐳 Docker команды

```bash
# Запустить контейнеры
docker compose up -d

# Остановить контейнеры
docker compose down

# Просмотр логов
docker compose logs -f

# Перестроить образы
docker compose up -d --build

# Удалить всё (том, сеть, образы)
docker compose down -v --rmi all

# Зайти в контейнер PHP
docker compose exec php sh

# Зайти в контейнер Nginx
docker compose exec nginx sh
```

---

## 🐛 Решённые проблемы в v2.0

✅ Ошибка с undefined переменной `$passwordValid` при входе  
✅ Email не передавался на страницу `verify.php`  
✅ Русские буквы в номере квартиры блокировались браузером  
✅ Месяц на главной выводился на английском (May → мая)  
✅ Ошибки с несуществующим полем `updated_at` в объявлениях  
✅ Отсутствовал favicon на всех страницах (добавлена emoji 🏠)  
✅ Выход требовал GET запрос (небезопасно) → переделано на POST с CSRF  
✅ Объявления были на отдельной странице → перенесены на `index.php`  

---

## 📊 Примеры использования

### Регистрация жилца
1. Нажать "Регистрация"
2. Заполнить форму (email, пароль, ФИО, квартира)
3. Пройти проверку Turnstile
4. Получить 6-значный код на email
5. Ввести код в форме подтверждения
6. Войти в систему

### Передача показаний (жилец)
1. На главной нажать "⚡ Сдать показания"
2. Ввести показания счётчиков
3. Нажать "Отправить"
4. Статистика на главной обновится

### Управление заявками (админ)
1. На главной нажать "Заявки жильцов"
2. Выбрать заявку
3. Изменить статус (в работе, выполнена, отклонена)
4. Добавить комментарий (опционально)
5. Сохранить

### Создание объявления (админ)
1. На главной нажать "Управление объявлениями"
2. Нажать "Создать объявление"
3. Ввести заголовок и текст
4. Опционально закрепить (📌) сверху
5. Сохранить — объявление появится на главной для всех

---

## 📝 TODO (В разработке)

- [ ] Интеграция реальной платежной системы (СБП)
- [ ] Email уведомления жильцам о статусе заявок
- [ ] Экспорт показаний в Excel
- [ ] Двухфакторная аутентификация (2FA)
- [ ] Система рейтинга и отзывов
- [ ] Календарь событий (отключения воды, электричества)
- [ ] Чат поддержки между жильцом и админом
- [ ] Мобильное приложение (Flutter/React Native)
- [ ] API для интеграции с внешними системами

---

## 🤝 Внесение изменений

Если вы хотите доработать приложение:

1. Создайте свою ветку: `git checkout -b feature/your-feature`
2. Внесите изменения
3. Протестируйте: `docker compose up`
4. Коммитьте: `git commit -m "Описание"`
5. Пушьте: `git push origin feature/your-feature`

---

## 👨‍💻 Разработка

**Язык:** PHP 8.1 (нативный)  
**БД:** SQLite 3  
**Frontend:** Tailwind CSS + Vanilla JS  
**Версия:** 2.0  
**Дата обновления:** май 2026  

---

## 📄 Лицензия

Этот проект распространяется свободно. Используйте как угодно!

