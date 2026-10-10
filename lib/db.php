<?php
// PDO database layer: SQLite (local file) or PostgreSQL (e.g. Neon).
// Configure in db-config.php (copy of db-config.example.php); the
// DATABASE_URL environment variable overrides the file when set.

/** Merged database configuration. */
function db_config(): array {
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }
    $cfg = ['driver' => 'sqlite', 'url' => ''];
    $file = APP_ROOT . '/db-config.php';
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) {
            $cfg = array_merge($cfg, $loaded);
        }
    }
    $env = getenv('DATABASE_URL');
    if ($env !== false && $env !== '') {
        $cfg['driver'] = 'pgsql';
        $cfg['url'] = $env;
    }
    if (!in_array($cfg['driver'], ['sqlite', 'pgsql'], true)) {
        throw new RuntimeException('Unsupported DB driver: ' . $cfg['driver']);
    }
    return $cfg;
}

/** Active driver: 'sqlite' or 'pgsql'. */
function db_driver(): string {
    return db_config()['driver'];
}

/** Case-insensitive match operator for the active driver. */
function like_op(): string {
    return db_driver() === 'pgsql' ? 'ILIKE' : 'LIKE';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = db_config();
    if ($cfg['driver'] === 'pgsql') {
        $pdo = pgsql_connect($cfg['url']);
    } else {
        if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0775, true) && !is_dir(DATA_DIR)) {
            throw new RuntimeException('Cannot create data directory');
        }
        $pdo = new PDO('sqlite:' . DATA_DIR . '/pos.db');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    init_db($pdo);
    return $pdo;
}

/** Build a PDO connection from a postgres:// connection string (e.g. Neon's). */
function pgsql_connect(string $url): PDO {
    $parts = parse_url($url);
    if ($parts === false || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)) {
        throw new RuntimeException('DB_URL is not a valid Postgres connection string.');
    }
    if (empty($parts['host']) || empty($parts['path']) || $parts['path'] === '/') {
        throw new RuntimeException('DB_URL is missing the host or database name.');
    }
    parse_str($parts['query'] ?? '', $q);
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $parts['host'],
        (int)($parts['port'] ?? 5432),
        ltrim($parts['path'], '/')
    );
    // Neon mandates TLS; default to require unless the URL says otherwise.
    $dsn .= ';sslmode=' . ($q['sslmode'] ?? 'require');
    if (!empty($q['connect_timeout'])) {
        $dsn .= ';connect_timeout=' . (int)$q['connect_timeout'];
    }
    $user = isset($parts['user']) ? rawurldecode($parts['user']) : null;
    $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : null;
    return new PDO($dsn, $user, $pass);
}

/** Create tables if missing and seed on a brand-new database. */
function init_db(PDO $pdo): void {
    if (db_driver() === 'pgsql') {
        $id = 'INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY';
        $ts = 'TIMESTAMP NOT NULL';
        $money = 'DOUBLE PRECISION NOT NULL';
    } else {
        $id = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $ts = 'TEXT NOT NULL';
        $money = 'REAL NOT NULL';
    }
    // One statement per exec(): PDO drivers differ on multi-statement strings.
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id $id,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('admin','cashier')),
            active INTEGER NOT NULL DEFAULT 1,
            created_at $ts
        )",
        "CREATE TABLE IF NOT EXISTS products (
            id $id,
            sku TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'General',
            cost_price $money DEFAULT 0,
            sale_price $money DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 0,
            low_stock_alert INTEGER NOT NULL DEFAULT 5,
            created_at $ts
        )",
        "CREATE TABLE IF NOT EXISTS sales (
            id $id,
            invoice_no TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL REFERENCES users(id),
            subtotal $money,
            discount $money DEFAULT 0,
            total $money,
            created_at $ts
        )",
        "CREATE TABLE IF NOT EXISTS sale_items (
            id $id,
            sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products(id),
            qty INTEGER NOT NULL,
            price $money,
            total $money
        )",
        "CREATE TABLE IF NOT EXISTS stock_log (
            id $id,
            product_id INTEGER NOT NULL REFERENCES products(id),
            delta INTEGER NOT NULL,
            reason TEXT NOT NULL DEFAULT '',
            user_id INTEGER NOT NULL REFERENCES users(id),
            created_at $ts
        )",
        "CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

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

/**
 * Run an INSERT and return the new row's id.
 * (PDO::lastInsertId() needs a sequence name on Postgres, so use RETURNING.)
 */
function insert_get_id(PDO $pdo, string $sql, array $params): int {
    if (db_driver() === 'pgsql') {
        $st = $pdo->prepare($sql . ' RETURNING id');
        $st->execute($params);
        return (int)$st->fetchColumn();
    }
    $pdo->prepare($sql)->execute($params);
    return (int)$pdo->lastInsertId();
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
    // Lock the row on Postgres so two concurrent checkouts can't take the same number.
    $lock = db_driver() === 'pgsql' ? ' FOR UPDATE' : '';
    $seq = (int)$pdo->query(
        "SELECT value FROM settings WHERE key = 'last_invoice_seq'" . $lock
    )->fetchColumn();
    $seq++;
    $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'last_invoice_seq'")
        ->execute([(string)$seq]);
    return 'ZM-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}
