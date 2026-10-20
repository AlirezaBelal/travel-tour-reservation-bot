<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../database/migrate.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
function migrationEnv(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false ? $default : (string)$value;
}
try {
    $host = migrationEnv('DB_SERVER');
    $database = migrationEnv('DB_DATABASE');
    $port = migrationEnv('DB_PORT', '3306');
    if (!preg_match('/^[A-Za-z0-9_.-]+$/D', $host) || !preg_match('/^[A-Za-z0-9_]+$/D', $database) || !ctype_digit($port)) {
        throw new RuntimeException('Invalid database configuration.');
    }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", migrationEnv('DB_USERNAME'), migrationEnv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    migrateTopicMemberships($pdo);
    require_once __DIR__ . '/../database/product.php';
    migrateProduct($pdo);
    migrateTravelerIdentity($pdo);
    fwrite(STDOUT, "Migrations 001, 002 and 003 applied or already current.\n");
} catch (Throwable $error) {
    // Driver messages can include data. Do not print them to shared CI logs.
    fwrite(STDERR, "Migration failed. Legacy columns are retained; inspect configuration and data privately.\n");
    exit(1);
}
