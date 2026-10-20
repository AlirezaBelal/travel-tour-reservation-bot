<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    require dirname(__DIR__) . '/vendor/autoload.php';
    require dirname(__DIR__) . '/src/webhook.php';
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    $env = static function (string $key, string $default = ''): string {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false ? $default : (string)$value;
    };
    $secret = $env('TELEGRAM_WEBHOOK_SECRET');
    if (!is_string($secret) || !webhookSecretIsValid($secret, $secret) || !$env('TELEGRAM_BOT_TOKEN')) {
        throw new RuntimeException('Not configured.');
    }
    $host = $env('DB_SERVER');
    $database = $env('DB_DATABASE');
    $port = $env('DB_PORT', '3306');
    if (!is_string($host) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $host)
        || !is_string($database) || !preg_match('/^[A-Za-z0-9_]+$/D', $database) || !ctype_digit($port)) {
        throw new RuntimeException('Not configured.');
    }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $env('DB_USERNAME'), $env('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $query = $pdo->prepare('SELECT version FROM SchemaMigrations WHERE version = ?');
    $query->execute(['002_product']);
    if ($query->fetchColumn() === false) {
        throw new RuntimeException('Migration required.');
    }
    echo '{"status":"ok"}';
} catch (Throwable) {
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
