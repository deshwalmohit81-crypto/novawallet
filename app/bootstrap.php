<?php
declare(strict_types=1);

const NW_ROOT = dirname(__DIR__);
$configFile = __DIR__ . '/config.local.php';
$config = is_file($configFile) ? require $configFile : require __DIR__ . '/config.example.php';

if (!is_array($config)) {
    http_response_code(500);
    exit('NOVAWALLET configuration is invalid.');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
if ($https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

require_once __DIR__ . '/helpers.php';

function nw_config(): array
{
    global $config;
    return $config;
}

function nw_db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = nw_config()['database'] ?? [];
    foreach (['host', 'name', 'user', 'password'] as $required) {
        if (empty($db[$required])) {
            throw new RuntimeException('Database configuration is incomplete. Copy app/config.example.php to app/config.local.php and configure it.');
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function nw_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function nw_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        header('Allow: ' . strtoupper($method));
        nw_json(['error' => 'Method not allowed.'], 405);
    }
}

function nw_require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!is_string($token) || !hash_equals(nw_csrf_token(), $token)) {
        nw_json(['error' => 'Your session expired. Refresh the page and try again.'], 403);
    }
}

function nw_rate_limit(string $bucket, int $limit = 40, int $windowSeconds = 60): void
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash_hmac('sha256', $bucket . '|' . $ip, (string) (nw_config()['app_key'] ?? ''));
    $pdo = nw_db();
    $pdo->prepare('DELETE FROM rate_limits WHERE window_started < UTC_TIMESTAMP() - INTERVAL 1 DAY')->execute();
    $statement = $pdo->prepare(
        'INSERT INTO rate_limits (bucket_key, window_started, hit_count) VALUES (?, UTC_TIMESTAMP(), 1)
         ON DUPLICATE KEY UPDATE
         hit_count = IF(window_started < UTC_TIMESTAMP() - INTERVAL ? SECOND, 1, hit_count + 1),
         window_started = IF(window_started < UTC_TIMESTAMP() - INTERVAL ? SECOND, UTC_TIMESTAMP(), window_started)'
    );
    $statement->execute([$key, $windowSeconds, $windowSeconds]);
    $check = $pdo->prepare('SELECT hit_count FROM rate_limits WHERE bucket_key = ?');
    $check->execute([$key]);
    if ((int) $check->fetchColumn() > $limit) {
        header('Retry-After: ' . $windowSeconds);
        nw_json(['error' => 'Too many requests. Please wait a moment and retry.'], 429);
    }
}
