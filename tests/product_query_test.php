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
$bookings = new BookingService($pdo, $access, $outbox);
$queries = new BookingQueryService($pdo, $bookings, $access);
$profiles = new TravelerProfileService($pdo, new TravelerIdentity($pdo), new PrivacyService($pdo), $access);
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($label);
};
$denied = static function (callable $action) use ($check): void {
    try {
        $action();
        throw new RuntimeException('Expected application authorization/validation denial.');
    } catch (RuleViolation $error) {
        $check(true, 'direct service denies invalid operation');
    }
};
foreach ([830, 831] as $actor) {
    $bookings->ensureUser($actor);
    $profiles->saveName($actor, "Synthetic query traveler $actor");
}
$denied(static fn () => $profiles->saveName(830, str_repeat('x', 151)));
$check($profiles->name(830) === 'Synthetic query traveler 830', 'invalid name leaves existing profile intact');
Database::run($pdo, 'UPDATE Data SET step=?,Mobile=?,N_Code=? WHERE UserId=830', ['contact', 'old-mobile', 'old-code']);
$check($profiles->legacyStep(830) === 'contact', 'legacy state read');
$profiles->resetLegacyState(830);
$legacy = Database::run($pdo, 'SELECT Mobile,N_Code FROM Data WHERE UserId=830')->fetch(PDO::FETCH_ASSOC);
$check($profiles->legacyStep(830) === 'defult' && $legacy['Mobile'] === 'old-mobile'
    && $legacy['N_Code'] === 'old-code', 'state retirement preserves historical data');
$denied(static fn () => $profiles->leaderForReview(831, 830));
$access->assign(999, 832, 'reviewer');
$denied(static fn () => $profiles->leaderForReview(832, 830));
$check($profiles->leaderForReview(999, 830) === $profiles->name(830), 'authorized leader view');
$check((int)Database::run($pdo, "SELECT COUNT(*) FROM AuditLog WHERE actor_id=999 AND action='profile.view' AND target_id=830")->fetchColumn() === 1,
    'authorized cross-user profile view is audited');
$tour = $bookings->saveTour(999, ['category_id' => null, 'title' => 'Synthetic query boundary',
    'destination' => 'Synthetic', 'description' => 'Synthetic query test',
    'starts_at' => gmdate('Y-m-d H:i:s', time() + 172800), 'ends_at' => gmdate('Y-m-d H:i:s', time() + 180000),
    'price' => 0, 'capacity' => 10, 'publication' => 'published']);
putenv('REQUIRE_VERIFIED_PROFILE=false');
$id = $bookings->reserve(830, $tour, 2, 'synthetic-query-boundary');
$check((int)$queries->mine(830, 0)[0]['id'] === $id && $queries->mine(831, 0) === [], 'mine scopes rows to actor');
$check($queries->history(830, $id)[0]['new_status'] === 'pending', 'owner history includes status');
$denied(static fn () => $queries->history(831, $id));
$denied(static fn () => $queries->history(831, $id, true));
$denied(static fn () => $queries->pending(831, 0));
$denied(static fn () => $queries->all(831, 0, $tour));
$check((int)$queries->all(832, 0, $tour)[0]['id'] === $id, 'reviewer list authorization resides in service');
$check($queries->history(832, $id, true)[0]['new_status'] === 'pending', 'reviewer history authorized');
$check(array_keys($queries->mine(830, 0)[0]) === ['id', 'status'], 'list projection excludes identity');
$denied(static fn () => $queries->mine(830, -1));
$denied(static fn () => $queries->all(999, 100001));
$bookings->transition(830, $id, 'cancelled');
$profiles->erase(830);
$check($profiles->name(830) === null && $queries->mine(830, 0) === [], 'erasure through profile service removes personal association');
fwrite(STDOUT, "Product query boundaries passed ($checks checks).\n");
