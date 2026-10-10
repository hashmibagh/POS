-- Zangarry POS — PostgreSQL schema (run once in the Neon SQL Editor).
-- The app also creates these tables automatically on first run, so this file
-- is optional; it is handy for inspecting or rebuilding the schema by hand.
-- Default login after seeding: admin / admin123 (change it immediately).

CREATE TABLE IF NOT EXISTS users (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin','cashier')),
    active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    sku TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'General',
    cost_price DOUBLE PRECISION NOT NULL DEFAULT 0,
    sale_price DOUBLE PRECISION NOT NULL DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 0,
    low_stock_alert INTEGER NOT NULL DEFAULT 5,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS sales (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    invoice_no TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL REFERENCES users(id),
    subtotal DOUBLE PRECISION NOT NULL,
    discount DOUBLE PRECISION NOT NULL DEFAULT 0,
    total DOUBLE PRECISION NOT NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS sale_items (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    qty INTEGER NOT NULL,
    price DOUBLE PRECISION NOT NULL,
    total DOUBLE PRECISION NOT NULL
);

CREATE TABLE IF NOT EXISTS stock_log (
    id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id),
    delta INTEGER NOT NULL,
    reason TEXT NOT NULL DEFAULT '',
    user_id INTEGER NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

-- Seed (only when the users table is empty):
-- default admin password is admin123
INSERT INTO users (name, username, password_hash, role, active, created_at)
SELECT 'Administrator', 'admin', '$2y$10$4trhncKpUvVUIKVl3sh0.ux5R30OugiiNkxTwIeYbqjzOqNapgm6S', 'admin', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM users);

INSERT INTO settings (key, value)
SELECT 'shop_name', 'Zangarry Mobile Mart'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key = 'shop_name');

INSERT INTO settings (key, value)
SELECT 'tagline', 'Smart retail. Clear accounts.'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key = 'tagline');

INSERT INTO settings (key, value)
SELECT 'last_invoice_seq', '0'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key = 'last_invoice_seq');

INSERT INTO settings (key, value)
SELECT 'default_admin_pw', '1'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key = 'default_admin_pw');

INSERT INTO products (sku, name, category, cost_price, sale_price, stock, low_stock_alert, created_at)
SELECT * FROM (VALUES
    ('PHN-001', 'Smartphone X12',            'Phones',      28500, 32000, 15, 5, NOW()),
    ('ACC-101', 'Fast Charger 25W',          'Accessories',   850,  1200, 40, 5, NOW()),
    ('ACC-102', 'USB-C Cable 1m',            'Accessories',   220,   350, 60, 5, NOW()),
    ('ACC-103', 'Wireless Earbuds Pro',      'Accessories',  2400,  3500, 25, 5, NOW()),
    ('ACC-104', 'Back Cover (Universal)',    'Accessories',   180,   300, 50, 5, NOW()),
    ('ACC-105', 'Tempered Glass Protector',  'Accessories',    90,   150, 80, 5, NOW()),
    ('ACC-106', 'Memory Card 64GB',          'Accessories',  1100,  1600, 30, 5, NOW()),
    ('PHN-201', 'Power Bank 20000mAh',       'Accessories',  2600,  3400, 20, 5, NOW())
) AS v(sku, name, category, cost_price, sale_price, stock, low_stock_alert, created_at)
WHERE NOT EXISTS (SELECT 1 FROM products);
