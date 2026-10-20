<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    require dirname(__DIR__) . '/src/webhook.php';
    $secret = getenv('TELEGRAM_WEBHOOK_SECRET');
    if (!is_string($secret) || !webhookSecretIsValid($secret, $secret) || !getenv('TELEGRAM_BOT_TOKEN')) {
        throw new RuntimeException('Not configured.');
    }
    $host = getenv('DB_SERVER');
    $database = getenv('DB_DATABASE');
    $port = getenv('DB_PORT') ?: '3306';
    if (!is_string($host) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $host)
        || !is_string($database) || !preg_match('/^[A-Za-z0-9_]+$/D', $database) || !ctype_digit($port)) {
        throw new RuntimeException('Not configured.');
    }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
