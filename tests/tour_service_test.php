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
$tours = new TourService($pdo, $inventory, $access, $outbox);
$bookings = new BookingService($pdo, $access, $outbox, null, $inventory, $tours);
if (($argv[1] ?? '') === 'worker') {
    try {
        $result = Database::atomic($pdo, static function () use ($pdo, $bookings, $argv): bool {
            $pdo->query('SELECT COUNT(*) FROM Bookings')->fetchColumn();
            usleep(50000);
            return $bookings->transition((int)$argv[2], (int)$argv[3], $argv[4], null, $argv[4] === 'confirmed');
        });
        echo $result ? 'changed' : 'ignored';
    } catch (RuleViolation $error) {
        echo 'denied';
    }
    exit;
}
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($label);
};
$values = ['category_id' => null, 'title' => 'Synthetic direct tour management',
    'destination' => 'Synthetic', 'description' => 'Synthetic direct tour service',
    'starts_at' => gmdate('Y-m-d H:i:s', time() + 172800), 'ends_at' => gmdate('Y-m-d H:i:s', time() + 180000),
    'price' => 0, 'capacity' => 5, 'publication' => 'published'];
try {
    $tours->saveTour(850, $values);
    throw new RuntimeException('Unauthorized tour creation accepted.');
} catch (RuleViolation $error) {
    $check(true, 'direct tour authorization');
}
$id = $tours->saveTour(999, $values);
$check($inventory->tour($id)['title'] === $values['title'], 'tour service independent of booking creation');
$bookings->ensureUser(850);
Database::run($pdo, 'UPDATE Data SET Name=? WHERE UserId=850', ['Synthetic lifecycle traveler']);
putenv('REQUIRE_VERIFIED_PROFILE=false');
$booking = $bookings->reserve(850, $id, 2, 'synthetic-tour-boundary');
try {
    $tours->saveTour(999, array_replace($values, ['capacity' => 1]), $id);
    throw new RuntimeException('Tour capacity shrank below held seats.');
} catch (RuleViolation $error) {
    $check($inventory->used($id) === 2, 'shared capacity boundary protects held seats');
}
$before = $inventory->tour($id);
$pdo->beginTransaction();
$tours->saveTour(999, array_replace($values, ['title' => 'Synthetic uncommitted tour']), $id);
$check($pdo->inTransaction(), 'tour service joins outer transaction');
$pdo->rollBack();
$check($inventory->tour($id)['title'] === $before['title'], 'outer rollback restores tour');
$pdo->exec("CREATE TRIGGER synthetic_fail_tour_outbox BEFORE INSERT ON NotificationOutbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic tour outbox failure'");
try {
    $tours->saveTour(999, array_replace($values, ['title' => 'Synthetic failed tour edit']), $id);
    throw new RuntimeException('Expected synthetic tour outbox failure.');
} catch (PDOException $error) {
    $check((int)$inventory->tour($id)['version'] === (int)$before['version'], 'outbox failure restores tour version');
    $check($inventory->tour($id)['title'] === $before['title'], 'outbox failure restores tour fields');
} finally {
    $pdo->exec('DROP TRIGGER synthetic_fail_tour_outbox');
}
$workers = [];
foreach ([[999, 'confirmed'], [850, 'cancelled']] as [$actor, $status]) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', (string)$actor, (string)$booking, $status],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $workers[] = [$process, $pipes, $status];
}
foreach ($workers as [$process, $pipes, $status]) {
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $check(proc_close($process) === 0 && $errors === '', 'lifecycle race worker succeeds');
    $check($status === 'cancelled' ? $output === 'changed' : in_array($output, ['changed', 'denied'], true),
        'concurrent confirmation/cancellation follows permitted transitions');
}
$check($bookings->booking(850, $booking)['status'] === 'cancelled' && $inventory->used($id) === 0,
    'concurrent lifecycle releases capacity without reviving cancellation');
fwrite(STDOUT, "Tour service and lifecycle race passed ($checks checks).\n");
