<?php
declare(strict_types=1);

require __DIR__ . '/../src/product_bootstrap.php';
$dsn = getenv('TEST_MYSQL_DSN');
if (!$dsn || preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D', $dsn) !== 1) {
    throw new RuntimeException('Isolated test database required.');
}
$pdo = new PDO($dsn, getenv('TEST_MYSQL_USER') ?: 'root', getenv('TEST_MYSQL_PASSWORD') ?: '');
Database::configure($pdo);
$access = new AccessControl($pdo, [999]);
$outbox = new Outbox($pdo);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$pdo->exec('DELETE FROM NotificationOutbox');
// A malformed persisted row must not interrupt later valid deliveries or retry forever.
foreach (['{', 'null'] as $index => $payload) {
    $outbox->message(880, 'Synthetic corrupt fixture', 'boundary-corrupt-' . $index);
    Database::run($pdo, 'UPDATE NotificationOutbox SET payload=? WHERE idem_key=?',
        [$payload, hash('sha256', 'boundary-corrupt-' . $index)]);
}
$outbox->message(881, 'Synthetic healthy delivery', 'boundary-healthy');
$sent = 0;
$transport = static function () use (&$sent): array {
    $sent++;
    return ['ok' => true, 'code' => 200];
};
(new NotificationWorker($pdo, $access))->tick($transport, 10, false);
$check($sent === 1, 'corrupt payloads do not block valid delivery');
$check((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE status='failed' AND error_code='invalid_payload' AND attempts=1")->fetchColumn() === 2,
    'invalid JSON and scalar payloads are quarantined without storing their contents');
(new NotificationWorker($pdo, $access))->tick($transport, 10, false);
$check($sent === 1, 'invalid payloads are not retried automatically');

// Keep the malformed rows while erasing another account: JSON extraction must be guarded.
Database::run($pdo, "INSERT INTO Data(UserId,Name,step) VALUES (882,'Synthetic erasure target','defult')");
$outbox->message(882, 'Synthetic own message', 'boundary-erase-own');
$outbox->message(999, 'Synthetic subject message', 'boundary-erase-subject', [], 882);
$privacy = new PrivacyService($pdo);
$privacy->erase(882);
$check(Database::run($pdo, 'SELECT UserId FROM Data WHERE UserId=882')->fetchColumn() === false,
    'unrelated malformed JSON does not prevent account erasure');
$check((int)Database::run($pdo, 'SELECT COUNT(*) FROM NotificationOutbox WHERE idem_key IN (?,?)',
    [hash('sha256', 'boundary-erase-own'), hash('sha256', 'boundary-erase-subject')])->fetchColumn() === 0,
    'erasure removes own messages and valid subject-tagged messages in administrator chats');
$check((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE chat_id=880 AND error_code='invalid_payload'")->fetchColumn() === 2
    && (int)$pdo->query('SELECT COUNT(*) FROM NotificationOutbox WHERE chat_id=881')->fetchColumn() === 1,
    'erasure preserves unrelated malformed and healthy messages');
$privacy->erase(882);
$privacy->erase(880);
$check((int)$pdo->query('SELECT COUNT(*) FROM NotificationOutbox WHERE chat_id=880')->fetchColumn() === 0,
    'erasure is repeatable and also removes malformed payloads belonging to its recipient');

$pdo->exec('DELETE FROM NotificationOutbox');
$outbox->message(880, 'Synthetic rate-limited delivery', 'boundary-429');
(new NotificationWorker($pdo, $access))->tick(static fn () => ['ok' => false, 'code' => 429, 'retry_after' => 45], 1, false);
// Removing the recipient's queued data must not remove the shared cooldown.
(new PrivacyService($pdo))->erase(880);
// These messages did not exist when Telegram requested the pause.
$outbox->message(881, 'Synthetic arrival during cooldown', 'boundary-late');
$outbox->enqueue(881, 'answerCallbackQuery', ['callback_query_id' => 'synthetic-callback'], 'boundary-callback');
$sent = 0;
(new NotificationWorker($pdo, $access))->tick($transport, 10, false);
$check($sent === 0, 'a fresh worker respects cooldown for newly queued messages and callbacks');
$check((int)$pdo->query('SELECT SUM(attempts) FROM NotificationOutbox')->fetchColumn() === 0,
    'cooldown does not claim new work or consume retry attempts');
// Advancing only synthetic timestamps avoids a slow wall-clock sleep in CI.
$pdo->exec('UPDATE NotificationDeliveryState SET blocked_until=UTC_TIMESTAMP()-INTERVAL 1 SECOND');
$pdo->exec('UPDATE NotificationOutbox SET available_at=UTC_TIMESTAMP()');
(new NotificationWorker($pdo, $access))->tick($transport, 10, false);
$check($sent === 2, 'all remaining deliveries resume after the persisted cooldown expires');
$pdo->exec('DELETE FROM NotificationDeliveryState');
fwrite(STDOUT, "Worker boundary tests passed ($checks checks).\n");
