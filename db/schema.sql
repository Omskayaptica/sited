DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS password_resets;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    full_name TEXT,
    apartment TEXT,
    phone TEXT,
    role TEXT DEFAULT 'resident',
    is_verified INTEGER DEFAULT 0,
    verify_code_hash TEXT,
    verify_expires INTEGER,
    verify_attempts INT DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    status TEXT DEFAULT 'new',
    admin_comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    used INTEGER DEFAULT 0,
    ip_address TEXT,
    user_agent TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE meter_readings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    apartment TEXT NOT NULL,
    cold_water REAL NOT NULL DEFAULT 0.000,
    hot_water REAL NOT NULL DEFAULT 0.000,
    electricity REAL NOT NULL DEFAULT 0.000,
    reading_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    month_year DATE NOT NULL, 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (user_id, apartment, month_year) -- Запрет дублей
);

CREATE TABLE bills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    amount REAL NOT NULL,           -- Сумма долга
    period TEXT NOT NULL,           -- За какой месяц (например '2025-01')
    status TEXT DEFAULT 'unpaid',   -- 'unpaid' (не оплачено) или 'paid' (оплачено)
    paid_at DATETIME DEFAULT NULL,  -- Дата оплаты
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

-- Создайте индекс для быстрого поиска
CREATE INDEX idx_month_year ON meter_readings(month_year);
CREATE INDEX idx_user_apartment ON meter_readings(user_id, apartment);

CREATE INDEX idx_password_resets_token ON password_resets(token_hash);
CREATE INDEX idx_password_resets_expires ON password_resets(expires_at);
CREATE INDEX idx_password_resets_user ON password_resets(user_id);


INSERT INTO users (email, password, full_name, apartment, role, is_verified) VALUES ('admin@yandex.ru', '$2y$10$lwaDmNh0AG3gy/m95vF/0OWeVkNr/kviOcBeXXYOQBV/KyxCZkEf6', 'Председатель ТСЖ', 'Офис', 'admin', 1);
