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
$inventory = new TourInventory($pdo);
$reader = new BookingReader($pdo, $access);
$history = new BookingHistoryStore($pdo);
$identity = new TravelerIdentity($pdo);
$strict = new ReservationService($pdo, $inventory, $access, $outbox, $identity, $history, static fn (): bool => true);
$permissive = new ReservationService($pdo, $inventory, $access, $outbox, $identity, $history, static fn (): bool => false);
$lifecycle = new BookingLifecycleService($pdo, $inventory, $reader, $access, $outbox, $history);
$tours = new TourService($pdo, $inventory, $access, $outbox);
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($label);
};
foreach ([870, 871] as $actor) {
    $permissive->ensureUser($actor);
    Database::run($pdo, 'UPDATE Data SET Name=? WHERE UserId=?', ['Synthetic independent use case', $actor]);
}
$values = ['category_id' => null, 'title' => 'Synthetic independent booking use cases',
    'destination' => 'Synthetic', 'description' => 'Synthetic use cases',
    'starts_at' => gmdate('Y-m-d H:i:s', time() + 172800), 'ends_at' => gmdate('Y-m-d H:i:s', time() + 180000),
    'price' => 0, 'capacity' => 4, 'publication' => 'published'];
$tour = $tours->saveTour(999, $values);
putenv('REQUIRE_VERIFIED_PROFILE=false');
try {
    $strict->reserve(870, $tour, 2, 'synthetic-policy-denied');
    throw new RuntimeException('Explicit strict policy was overridden by environment.');
} catch (RuleViolation $error) {
    $check(str_contains($error->getMessage(), 'کد ملی'), 'strict injected policy wins over legacy environment');
}
putenv('REQUIRE_VERIFIED_PROFILE=true');
$id = $permissive->reserve(870, $tour, 2, 'synthetic-policy-permitted');
$check($inventory->used($tour) === 2, 'permissive injected policy is independently controlled');
$check($permissive->reserve(870, $tour, 2, 'synthetic-policy-permitted') === $id, 'direct reservation use case is idempotent');
try {
    $lifecycle->transition(871, $id, 'confirmed', null, true);
    throw new RuntimeException('Direct lifecycle bypassed reviewer permissions.');
} catch (RuleViolation $error) {
    $check($reader->booking(870, $id)['status'] === 'pending', 'direct lifecycle authorization preserves booking');
}
$check($lifecycle->transition(999, $id, 'confirmed', null, true), 'independent lifecycle confirms paid-free booking');
$check($lifecycle->transition(870, $id, 'cancelled'), 'independent lifecycle permits owner cancellation');
$check($inventory->used($tour) === 0, 'independent lifecycle releases seats');
$check((int)Database::run($pdo, 'SELECT COUNT(*) FROM BookingStatusHistory WHERE booking_id=?', [$id])->fetchColumn() === 3,
    'shared append-only history preserves creation and transitions');
// A surrounding use-case transaction retains ownership of every reservation side effect.
$pdo->beginTransaction();
$rolledBack = $permissive->reserve(871, $tour, 1, 'synthetic-outer-use-case');
$check($pdo->inTransaction(), 'reservation joins outer transaction');
$pdo->rollBack();
$check(!(bool)Database::run($pdo, 'SELECT id FROM Bookings WHERE id=?', [$rolledBack])->fetchColumn(), 'outer rollback removes reservation');
$check(!(bool)Database::run($pdo, 'SELECT booking_id FROM Payments WHERE booking_id=?', [$rolledBack])->fetchColumn(), 'outer rollback removes payment');
$check(!(bool)Database::run($pdo, 'SELECT booking_id FROM BookingStatusHistory WHERE booking_id=?', [$rolledBack])->fetchColumn(), 'outer rollback removes history');
$check(!(bool)Database::run($pdo, 'SELECT id FROM NotificationOutbox WHERE idem_key=?', [hash('sha256', "booking:$rolledBack:pending")])->fetchColumn(),
    'outer rollback removes notification');
putenv('REQUIRE_VERIFIED_PROFILE=false');
$compat = new BookingService($pdo, $access, $outbox);
$check($compat->reserve(actor: 870, tourId: $tour, quantity: 2, requestKey: 'synthetic-policy-permitted') === $id,
    'existing facade preserves named arguments and idempotency');
fwrite(STDOUT, "Independent booking use cases passed ($checks checks).\n");
