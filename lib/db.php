<?php
// PDO/SQLite connection, schema creation and first-run seeding.

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0775, true) && !is_dir(DATA_DIR)) {
        throw new RuntimeException('Cannot create data directory');
    }
    $pdo = new PDO('sqlite:' . DATA_DIR . '/pos.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_db($pdo);
    return $pdo;
}

/** Create tables if missing and seed on a brand-new database. */
function init_db(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('admin','cashier')),
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sku TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'General',
            cost_price REAL NOT NULL DEFAULT 0,
            sale_price REAL NOT NULL DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 0,
            low_stock_alert INTEGER NOT NULL DEFAULT 5,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_no TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL REFERENCES users(id),
            subtotal REAL NOT NULL,
            discount REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS sale_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products(id),
            qty INTEGER NOT NULL,
            price REAL NOT NULL,
            total REAL NOT NULL
        );
        CREATE TABLE IF NOT EXISTS stock_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL REFERENCES products(id),
            delta INTEGER NOT NULL,
            reason TEXT NOT NULL DEFAULT '',
            user_id INTEGER NOT NULL REFERENCES users(id),
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        seed_db($pdo);
    }
}

/** First-run seed: admin user, shop settings, sample mobile-shop products. */
function seed_db(PDO $pdo): void {
    $now = date('Y-m-d H:i:s');
    $pdo->beginTransaction();

    $st = $pdo->prepare(
        "INSERT INTO users (name, username, password_hash, role, active, created_at)
         VALUES ('Administrator', 'admin', ?, 'admin', 1, ?)"
    );
    $st->execute([password_hash('admin123', PASSWORD_DEFAULT), $now]);

    $settings = [
        'shop_name'        => 'Zangarry Mobile Mart',
        'tagline'          => 'Smart retail. Clear accounts.',
        'last_invoice_seq' => '0',
        'default_admin_pw' => '1', // cleared once the admin changes their password
    ];
    $st = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $st->execute([$k, $v]);
    }

    $products = [
        // sku, name, category, cost, sale, stock
        ['PHN-001', 'Smartphone X12',            'Phones',      28500, 32000, 15],
        ['ACC-101', 'Fast Charger 25W',          'Accessories',   850,  1200, 40],
        ['ACC-102', 'USB-C Cable 1m',            'Accessories',   220,   350, 60],
        ['ACC-103', 'Wireless Earbuds Pro',      'Accessories',  2400,  3500, 25],
        ['ACC-104', 'Back Cover (Universal)',    'Accessories',   180,   300, 50],
        ['ACC-105', 'Tempered Glass Protector',  'Accessories',    90,   150, 80],
        ['ACC-106', 'Memory Card 64GB',          'Accessories',  1100,  1600, 30],
        ['PHN-201', 'Power Bank 20000mAh',       'Accessories',  2600,  3400, 20],
    ];
    $st = $pdo->prepare(
        "INSERT INTO products (sku, name, category, cost_price, sale_price, stock, low_stock_alert, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 5, ?)"
    );
    foreach ($products as $p) {
        $st->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $now]);
    }

    $pdo->commit();
}

/** Get a setting value (empty string when missing). */
function setting(string $key): string {
    $st = db()->prepare("SELECT value FROM settings WHERE key = ?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? '' : (string)$v;
}

/** Upsert a setting value. */
function set_setting(string $key, string $value): void {
    $st = db()->prepare(
        "INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value"
    );
    $st->execute([$key, $value]);
}

/** Next sequential invoice number, e.g. ZM-000001. Call inside a transaction. */
function next_invoice_no(PDO $pdo): string {
    $seq = (int)$pdo->query(
        "SELECT value FROM settings WHERE key = 'last_invoice_seq'"
    )->fetchColumn();
    $seq++;
    $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'last_invoice_seq'")
        ->execute([(string)$seq]);
    return 'ZM-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}
