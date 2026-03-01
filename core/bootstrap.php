<?php
$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set('UTC');

$logDir = __DIR__ . '/../magic_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/app.log');

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['database']['host'],
    $config['database']['name'],
    $config['database']['charset']
);
try {
    $pdo = new PDO($dsn, $config['database']['user'], $config['database']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Auto-initialize schema on first deploy if premium tables are missing.
    try {
        $prefix = (string)($config['database']['table_prefix'] ?? 'premium_');
        $keyTable = $prefix . 'services';
        $check = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $check->execute([$keyTable]);
        $exists = (bool)$check->fetchColumn();

        if (!$exists) {
            $schemaPath = __DIR__ . '/../sql/schema.sql';
            if (is_file($schemaPath)) {
                $schemaSql = file_get_contents($schemaPath) ?: '';
                $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
                foreach ($statements as $statement) {
                    if ($statement !== '') {
                        $pdo->exec($statement);
                    }
                }
            }
        }
    } catch (Throwable $schemaErr) {
        error_log('[bootstrap] Schema initialization warning: ' . $schemaErr->getMessage());
    }
} catch (Throwable $e) {
    error_log('[bootstrap] DB unavailable: ' . $e->getMessage());
    $sqliteFile = sys_get_temp_dir() . '/growthworld-premium-tools-dev.sqlite';
    $pdo = new PDO('sqlite:' . $sqliteFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_type TEXT NOT NULL,
        title TEXT NOT NULL UNIQUE,
        slug TEXT NOT NULL UNIQUE,
        feature_image TEXT NOT NULL,
        seo_description TEXT NOT NULL,
        long_description TEXT NOT NULL,
        tool_html TEXT NULL,
        download_url TEXT NULL,
        extension_url TEXT NULL,
        demo_tutorial_url TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL DEFAULT 0,
        updated_at INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "active",
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_temp_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        otp_code TEXT NOT NULL,
        otp_expires_at INTEGER NOT NULL,
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        rating INTEGER NOT NULL,
        review_text TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "pending",
        is_favorite INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        approved_at INTEGER NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        paypal_subscription_id TEXT NULL,
        status TEXT NOT NULL,
        last_payment_at INTEGER NULL,
        next_billing_at INTEGER NULL,
        cancelled_at INTEGER NULL,
        last_event TEXT NULL,
        last_checked INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_service_features (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER NOT NULL,
        feature_text TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_service_instructions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER NOT NULL,
        instruction_text TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS premium_contact_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        message TEXT NOT NULL,
        region TEXT NULL,
        ip_address TEXT NULL,
        created_at INTEGER NOT NULL
    )');

    $ensureSqliteColumn = static function (PDO $db, string $table, string $column, string $definition): void {
        $cols = $db->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (($c['name'] ?? '') === $column) {
                return;
            }
        }
        $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    };

    $ensureSqliteColumn($pdo, 'premium_subscriptions', 'paypal_subscription_id', 'TEXT NULL');
    $ensureSqliteColumn($pdo, 'premium_subscriptions', 'last_event', 'TEXT NULL');
    $ensureSqliteColumn($pdo, 'premium_subscriptions', 'last_checked', 'INTEGER NOT NULL DEFAULT 0');
    $ensureSqliteColumn($pdo, 'premium_subscriptions', 'updated_at', 'INTEGER NOT NULL DEFAULT 0');

    $seedCheck = (int)$pdo->query('SELECT COUNT(*) FROM premium_services')->fetchColumn();
    if ($seedCheck === 0) {
        $pdo->exec("INSERT INTO premium_services (service_type, title, seo_description, slug, feature_image, long_description, is_active, created_at, updated_at) VALUES ('browser', 'Demo Growth Tool', 'Demo SEO description used when DB is unavailable in local dev mode.', 'demo-growth-tool', 'static/images/demo-growth-tool.webp', 'Demo long description paragraph one.', 1, strftime('%s','now'), strftime('%s','now'))");
    }
}

session_name($config['security']['session_cookie']);
session_start();

function cfg(string $path) {
    global $config;
    $parts = explode('.', $path);
    $value = $config;
    foreach ($parts as $p) {
        $value = $value[$p] ?? null;
    }
    return $value;
}

function table_name(string $base): string {
    return cfg('database.table_prefix') . $base;
}

function app_base_path(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    // 1) explicit override from environment/config if provided
    $envBase = trim((string)($_SERVER['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH') ?: ''));
    if ($envBase !== '') {
        $envBase = '/' . trim($envBase, '/');
        return $cached = ($envBase === '/' ? '' : $envBase);
    }

    $cfgBase = trim((string)(cfg('app.base_path') ?? ''));
    if ($cfgBase !== '') {
        $cfgBase = '/' . trim($cfgBase, '/');
        return $cached = ($cfgBase === '/' ? '' : $cfgBase);
    }

    // 2) auto-detect from executing script location (works for subdirectory deploys)
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== '') {
        $dir = str_replace('\\', '/', dirname($scriptName));
        $dir = '/' . trim($dir, '/');
        if ($dir !== '/' && $dir !== '/.') {
            return $cached = $dir;
        }
    }

    // 3) safe default for root deploy. Subdirectory deploys are already covered
    // by SCRIPT_NAME detection or APP_BASE_PATH/config overrides above.
    return $cached = '';
}

function url(string $path = ''): string {
    $path = '/' . ltrim($path, '/');
    return app_base_path() . ($path === '/' ? '' : $path);
}

function xor_encrypt(string $plain, string $key): string {
    $out = '';
    for ($i = 0, $j = 0; $i < strlen($plain); $i++, $j++) {
        if ($j >= strlen($key)) $j = 0;
        $out .= chr(ord($plain[$i]) ^ ord($key[$j]));
    }
    return base64_encode($out);
}

function xor_decrypt(string $cipherB64, string $key): string {
    $cipher = base64_decode($cipherB64, true);
    if ($cipher === false) return '';
    $out = '';
    for ($i = 0, $j = 0; $i < strlen($cipher); $i++, $j++) {
        if ($j >= strlen($key)) $j = 0;
        $out .= chr(ord($cipher[$i]) ^ ord($key[$j]));
    }
    return $out;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf']) || ($_SESSION['csrf_exp'] ?? 0) < time()) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_exp'] = time() + (int)cfg('app.csrf_ttl');
    }
    return $_SESSION['csrf'];
}

function check_csrf(?string $token): bool {
    return hash_equals($_SESSION['csrf'] ?? '', (string)$token) && ($_SESSION['csrf_exp'] ?? 0) >= time();
}

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}

function require_admin(): void {
    if (empty($_SESSION['admin'])) {
        header('Location: ' . url('/admin/login'));
        exit;
    }
}

function render(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    include __DIR__ . '/../templates/layout.php';
}


function apply_cors_headers(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = cfg('security.cors_allowed_origins') ?? [];
    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function user_has_active_subscription(int $userId): bool {
    global $pdo;
    $stmt = $pdo->prepare('SELECT status FROM ' . table_name('subscriptions') . ' WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $status = strtoupper((string)$stmt->fetchColumn());
    return in_array($status, ['ACTIVE','APPROVAL_PENDING'], true);
}

function youtube_embed_url(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return '';
    $parts = parse_url($url);
    if (!$parts) return '';
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    $videoId = '';
    if (str_contains($host, 'youtu.be')) {
        $videoId = trim($path, '/');
    } elseif (str_contains($host, 'youtube.com')) {
        parse_str($parts['query'] ?? '', $q);
        $videoId = $q['v'] ?? '';
        if (!$videoId && str_starts_with($path, '/embed/')) $videoId = basename($path);
    }
    if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', (string)$videoId)) return '';
    return 'https://www.youtube.com/embed/' . $videoId;
}

function db_is_sqlite(): bool {
    global $pdo;
    return str_starts_with(strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)), 'sqlite');
}
