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
$payments = new PaymentReviewService($pdo, $access, $outbox);
$bookings = new BookingService($pdo, $access, $outbox, $payments);
if (($argv[1] ?? '') === 'worker') {
    echo $payments->record(999, (int)$argv[2], 'SYNTHETIC-PAYMENT-RACE') ? 'recorded' : 'duplicate';
    exit;
}
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($label);
    }
};
$values = ['category_id' => null, 'title' => 'Synthetic direct payment review',
    'destination' => 'Synthetic', 'description' => 'Synthetic payment test',
    'starts_at' => gmdate('Y-m-d H:i:s', time() + 172800),
    'ends_at' => gmdate('Y-m-d H:i:s', time() + 180000),
    'price' => 1000, 'capacity' => 20, 'publication' => 'published'];
$tour = $bookings->saveTour(999, $values);
$bookingIds = [];
putenv('REQUIRE_VERIFIED_PROFILE=false');
for ($actor = 710; $actor < 714; $actor++) {
    $bookings->ensureUser($actor);
    Database::run($pdo, 'UPDATE Data SET Name=? WHERE UserId=?', ['Synthetic payment traveler', $actor]);
    $bookingIds[] = $bookings->reserve($actor, $tour, 2, 'synthetic-direct-payment');
}
[$first, $second, $third, $race] = $bookingIds;
$denied = static function (callable $action) use ($check): void {
    try {
        $action();
        throw new RuntimeException('Payment boundary was bypassed.');
    } catch (RuleViolation $error) {
        $check(true, 'payment denial');
    }
};
$denied(static fn () => $payments->record(710, $first, 'SYNTHETIC-DENIED'));
$denied(static fn () => $payments->record(999, $first, 'bad ref'));
$denied(static fn () => $payments->record(999, 99999999, 'SYNTHETIC-MISSING'));
$check($payments->record(999, $first, 'SYNTHETIC-DIRECT-ONE'), 'direct payment succeeds');
$check(!$bookings->manualPayment(999, $first, 'SYNTHETIC-DIRECT-ONE'), 'compatibility facade shares idempotency');
$denied(static fn () => $payments->record(999, $second, 'SYNTHETIC-DIRECT-ONE'));
$check($bookings->booking(711, $second)['payment_status'] === 'pending', 'reference collision leaves second payment pending');
$check((int)$bookings->booking(710, $first)['amount'] === 2000, 'payment preserves price snapshot');
$bookings->transition(711, $second, 'cancelled');
$denied(static fn () => $payments->record(999, $second, 'SYNTHETIC-CLOSED'));
// An injected application service must join its caller's transaction, never commit it.
$pdo->beginTransaction();
$payments->record(999, $third, 'SYNTHETIC-OUTER-ROLLBACK');
$check($pdo->inTransaction(), 'manual review retains outer transaction ownership');
$pdo->rollBack();
$check($bookings->booking(712, $third)['payment_status'] === 'pending', 'outer rollback reverts payment');
$check(!(bool)Database::run($pdo, "SELECT id FROM AuditLog WHERE action='payment.manual' AND target_id=?", [$third])->fetchColumn(),
    'outer rollback reverts payment audit');
$check(!(bool)Database::run($pdo, 'SELECT id FROM NotificationOutbox WHERE idem_key=?', [hash('sha256', "payment:$third:paid")])->fetchColumn(),
    'outer rollback reverts payment notification');
$pdo->exec("CREATE TRIGGER synthetic_fail_manual_outbox BEFORE INSERT ON NotificationOutbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic manual outbox failure'");
try {
    $payments->record(999, $third, 'SYNTHETIC-OUTBOX-FAILURE');
    throw new RuntimeException('Expected synthetic outbox failure.');
} catch (PDOException $error) {
    $check($bookings->booking(712, $third)['payment_status'] === 'pending', 'outbox failure rolls back payment');
    $check(!(bool)Database::run($pdo, "SELECT id FROM AuditLog WHERE action='payment.manual' AND target_id=?", [$third])->fetchColumn(),
        'outbox failure rolls back audit');
} finally {
    $pdo->exec('DROP TRIGGER synthetic_fail_manual_outbox');
}
// Competing reviews lock tour -> booking -> payment and emit a single audit/event.
$workers = [];
for ($i = 0; $i < 4; $i++) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', (string)$race], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $workers[] = [$process, $pipes];
}
$recorded = 0;
foreach ($workers as [$process, $pipes]) {
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $check(proc_close($process) === 0 && $errors === '' && in_array($output, ['recorded', 'duplicate'], true),
        'payment concurrency worker succeeds');
    $recorded += $output === 'recorded' ? 1 : 0;
}
$check($recorded === 1, 'only one concurrent manual review records payment');
$check((int)Database::run($pdo, "SELECT COUNT(*) FROM AuditLog WHERE action='payment.manual' AND target_id=?", [$race])->fetchColumn() === 1,
    'one payment audit under concurrency');
$check((int)Database::run($pdo, 'SELECT COUNT(*) FROM NotificationOutbox WHERE idem_key=?', [hash('sha256', "payment:$race:paid")])->fetchColumn() === 1,
    'one payment notification under concurrency');
fwrite(STDOUT, "Direct payment review passed ($checks checks).\n");
