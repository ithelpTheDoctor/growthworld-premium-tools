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
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE premium_services (id INTEGER PRIMARY KEY, title TEXT, seo_description TEXT, slug TEXT, is_active INTEGER, updated_at INTEGER, service_type TEXT, feature_image TEXT, long_description TEXT, tool_html TEXT, download_url TEXT, extension_url TEXT, demo_tutorial_url TEXT)');
    $pdo->exec('CREATE TABLE premium_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    $pdo->exec('CREATE TABLE premium_reviews (id INTEGER PRIMARY KEY, user_id INTEGER, rating INTEGER, review_text TEXT, status TEXT, is_favorite INTEGER, created_at INTEGER, approved_at INTEGER)');
    $pdo->exec('CREATE TABLE premium_subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT)');
    $pdo->exec('CREATE TABLE premium_service_features (id INTEGER PRIMARY KEY, service_id INTEGER, feature_text TEXT, sort_order INTEGER)');
    $pdo->exec('CREATE TABLE premium_service_instructions (id INTEGER PRIMARY KEY, service_id INTEGER, instruction_text TEXT, sort_order INTEGER)');
    $pdo->exec("INSERT INTO premium_services (title, seo_description, slug, is_active, updated_at, service_type, feature_image, long_description) VALUES ('Demo Growth Tool', 'Demo SEO description used when DB is unavailable in local dev mode.', 'demo-growth-tool', 1, strftime('%s','now'), 'browser', 'static/images/demo-growth-tool.webp', 'Demo long description paragraph one.')");
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

    // 3) fallback from configured base_url path
    $baseUrlPath = (string)(parse_url((string)cfg('app.base_url'), PHP_URL_PATH) ?? '');
    $baseUrlPath = '/' . trim($baseUrlPath, '/');
    return $cached = ($baseUrlPath === '/' ? '' : $baseUrlPath);
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
