DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS users;

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

INSERT INTO users (email, password, full_name, apartment, role, is_verified) VALUES ('admin@yandex.ru', '$2y$10$lwaDmNh0AG3gy/m95vF/0OWeVkNr/kviOcBeXXYOQBV/KyxCZkEf6', 'Председатель ТСЖ', 'Офис', 'admin', 1);
