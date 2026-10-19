<?php

require __DIR__ . '/../src/TopicMemberships.php';
require __DIR__ . '/../src/TopicCatalogue.php';
require __DIR__ . '/../database/migrate.php';

$dsn = getenv('TEST_MYSQL_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=travel_tour_test')) {
    fwrite(STDERR, "Use an isolated travel_tour_test database; this test deletes its fixtures.\n");
    exit(1);
}
function testConnection(): PDO
{
    return new PDO(getenv('TEST_MYSQL_DSN'), getenv('TEST_MYSQL_USER') ?: 'root', getenv('TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
if (($argv[1] ?? '') === 'worker') {
    $repo = new TopicMemberships(testConnection());
    fwrite(STDOUT, $repo->request((int)$argv[2], (int)$argv[3]) ? 'created' : 'duplicate');
    exit;
}
$checks = 0;
function verify(bool $value, string $message): void
{
    global $checks;
    $checks++;
    if (!$value) {
        throw new RuntimeException($message);
    }
}
$pdo = testConnection();
foreach (['TopicMemberships', 'SchemaMigrations', 'Data', 'Topics'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `$table`");
}
$legacy = file_get_contents(__DIR__ . '/../topic.sql');
$legacy = preg_replace('/CREATE DATABASE.*?;\s*USE travel_tour_bot;/s', '', $legacy);
$pdo->exec($legacy);
$pdo->exec("INSERT INTO Data (UserId, Name, profile) VALUES (101, 'Synthetic traveler', 'true'), (102, 'Synthetic traveler two', 'true')");
$pdo->exec("INSERT INTO Topics (id, Name, Groups) VALUES (1, 'Root', 0), (2, 'Child', 1), (3, 'Grandchild', 2), (4, 'Leaf', 3), (5, 'Other root', 0)");
$pdo->exec('ALTER TABLE Data ADD topic1 VARCHAR(255), ADD topic4 VARCHAR(255)');
$pdo->exec("UPDATE Data SET topic1 = 'check', topic4 = 'unknown' WHERE UserId = 101");
try {
    migrateTopicMemberships($pdo);
    throw new RuntimeException('Unknown legacy statuses must stop migration.');
} catch (RuntimeException $error) {
    verify(str_contains($error->getMessage(), 'Unrecognized legacy status'), 'migration must report unsupported status');
}
verify((int)$pdo->query('SELECT COUNT(*) FROM TopicMemberships')->fetchColumn() === 0, 'failed migration rolled back copied rows');
verify((int)$pdo->query('SELECT COUNT(*) FROM SchemaMigrations')->fetchColumn() === 0, 'failed migration not marked complete');
$pdo->exec("UPDATE Data SET topic4 = 'verify' WHERE UserId = 101");
migrateTopicMemberships($pdo);
$repo = new TopicMemberships($pdo);
verify($repo->status(101, 1) === 'check', 'pending migration preserved');
verify($repo->status(101, 4) === 'verify', 'approved migration preserved');
verify($repo->status(102, 1) === null, 'null status stays absent');
verify($pdo->query('SELECT topic4 FROM Data WHERE UserId = 101')->fetchColumn() === 'verify', 'legacy data retained');
migrateTopicMemberships($pdo);
verify((int)$pdo->query('SELECT COUNT(*) FROM TopicMemberships')->fetchColumn() === 2, 'migration repeatable');
verify(!$repo->request(101, 1), 'duplicate request rejected');
verify($repo->approve(101, 1), 'pending approval succeeds');
verify(!$repo->approve(101, 1), 'duplicate approval rejected');
verify(!$repo->approve(102, 1), 'cannot approve nonexistent request');
verify(array_map('intval', $repo->approvedUsers(1)) === [101], 'targeted member listing');
try {
    $repo->request(999, 1);
    throw new RuntimeException('Missing user accepted.');
} catch (PDOException $error) {
    verify(($error->errorInfo[1] ?? null) === 1452, 'foreign key rejects missing user');
}
// Eight distinct PHP processes race for one unique membership.
$workers = [];
for ($i = 0; $i < 8; $i++) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', '102', '5'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    verify(is_resource($process), 'worker started');
    $workers[] = [$process, $pipes];
}
$created = 0;
foreach ($workers as [$process, $pipes]) {
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    verify(proc_close($process) === 0 && $errors === '', 'worker completed cleanly');
    $created += $output === 'created' ? 1 : 0;
}
verify($created === 1, 'exactly one concurrent request wins');
$catalogue = new TopicCatalogue($pdo);
$newId = $catalogue->create('Synthetic new topic', 5);
verify($newId > 5, 'automatic topic ID');
verify($repo->request(101, $newId), 'new topic needs no dynamic column');
verify($catalogue->deleteTree(1) === 4, 'arbitrary-depth deletion');
verify((int)$pdo->query('SELECT COUNT(*) FROM Topics WHERE id IN (1,2,3,4)')->fetchColumn() === 0, 'no orphan descendants');
verify($repo->status(101, 4) === null, 'related memberships cascade');
verify($repo->status(102, 5) === 'check', 'unrelated memberships preserved');
verify($catalogue->deleteTree(1) === 0, 'repeated deletion safe');
try {
    $catalogue->create('Invalid child', 999);
    throw new RuntimeException('Missing parent accepted.');
} catch (InvalidArgumentException) {
    verify(!$pdo->inTransaction(), 'failed creation rolls back');
}
fwrite(STDOUT, "MySQL integration tests passed ({$checks} checks).\n");
