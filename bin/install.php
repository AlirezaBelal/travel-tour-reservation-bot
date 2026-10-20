<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$env = static function (string $name, string $default = ''): string {
    $value = $_ENV[$name] ?? getenv($name);
    return $value === false ? $default : (string)$value;
};
try {
    $host = $env('DB_SERVER');
    $database = $env('DB_DATABASE');
    $port = $env('DB_PORT', '3306');
    if (!preg_match('/^[A-Za-z0-9_.-]+$/D', $host) || !preg_match('/^[A-Za-z0-9_]+$/D', $database) || !ctype_digit($port)) {
        throw new RuntimeException('Invalid database configuration.');
    }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $env('DB_USERNAME'), $env('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $schema = file_get_contents(__DIR__ . '/../topic.sql');
    $schema = preg_replace('/CREATE DATABASE.*?;\s*USE travel_tour_bot;/s', '', $schema);
    $pdo->exec($schema);
    require __DIR__ . '/../database/migrate.php';
    migrateTopicMemberships($pdo);
    require_once __DIR__ . '/../database/product.php';
    migrateProduct($pdo);
    migrateTravelerIdentity($pdo);
    fwrite(STDOUT, "Schema initialized and migrations applied.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Installation failed (" . get_class($error) . "/" . $error->getCode() . "). Inspect configuration and database privately.\n");
    exit(1);
}
