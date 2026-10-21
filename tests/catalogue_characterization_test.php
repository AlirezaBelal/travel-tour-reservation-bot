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
$bot = new ProductBot($pdo, $bookings, $access, $outbox, new TourCatalogService($pdo, $bookings, $access));
$processor = new WebhookProcessor($pdo, $bookings, $bot, $outbox);
$checks = 0;
$update = 900000;
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($label);
    }
};
$send = static function (int $actor, ?string $text, ?string $callback = null) use ($processor, $pdo, &$update): array {
    $processor->handle(++$update, $actor, $text, $callback);
    $payload = Database::run($pdo,
        "SELECT payload FROM NotificationOutbox WHERE chat_id=? AND method='sendMessage' ORDER BY id DESC LIMIT 1",
        [$actor])->fetchColumn();
    return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
};
$callbacks = static function (array $reply): array {
    $keyboard = json_decode($reply['reply_markup'], true, 512, JSON_THROW_ON_ERROR);
    $result = [];
    foreach ($keyboard['inline_keyboard'] as $row) {
        foreach ($row as $button) {
            $result[] = $button['callback_data'];
        }
    }
    return $result;
};
$category = (new TopicCatalogue($pdo))->create('Synthetic catalogue characterization', 0);
$values = ['category_id' => $category, 'title' => 'Synthetic catalogue 00',
    'destination' => 'Synthetic catalogue needle', 'description' => 'Synthetic catalogue fixture',
    'starts_at' => gmdate('Y-m-d H:i:s', time() + 172800),
    'ends_at' => gmdate('Y-m-d H:i:s', time() + 180000),
    'price' => 0, 'capacity' => 20, 'publication' => 'published'];
$ids = [];
for ($i = 0; $i < 11; $i++) {
    $ids[] = $bookings->saveTour(999, array_replace($values, ['title' => sprintf('Synthetic catalogue %02d', $i)]));
}
$draft = $bookings->saveTour(999, array_replace($values, ['publication' => 'draft']));
$past = $bookings->saveTour(999, array_replace($values, [
    'starts_at' => gmdate('Y-m-d H:i:s', time() - 7200),
    'ends_at' => gmdate('Y-m-d H:i:s', time() - 3600),
]));
$first = $callbacks($send(620, null, "p:cat_tours:$category:0"));
$check(array_slice($first, 0, 10) === array_map(static fn ($id) => "p:tour:$id", array_slice($ids, 0, 10)),
    'catalogue ordering and first-page limit');
$check(in_array("p:cat_tours:$category:10", $first, true), 'category pagination preserves category');
$second = $callbacks($send(620, null, "p:cat_tours:$category:10"));
$check($second === ["p:tour:{$ids[10]}", 'p:home:0'], 'last page excludes past and draft tours');
$send(620, '/search');
$search = $callbacks($send(620, 'Synthetic catalogue needle'));
$check(array_slice($search, 0, 10) === array_slice($first, 0, 10), 'destination search matches literal text');
$send(620, '/search');
$literal = $send(620, "%_' OR 1=1 --");
$check($callbacks($literal) === ['p:home:0'], 'search does not interpret SQL or wildcard syntax');
$send(620, '/cancel');
$check(str_contains($send(620, null, "p:tour:$draft")['text'], 'منتشر نشده'), 'stranger cannot inspect draft');
$check(str_contains($send(999, null, "p:tour:$draft")['text'], 'پیش‌نویس'), 'tour administrator may inspect draft');
$check(str_contains($send(620, null, 'p:manage_tours:0')['text'], 'اجازه'), 'admin listing denies traveler');
$check(str_contains($send(620, null, 'p:tours:100001')['text'], 'صفحه'), 'page bound is enforced');
$bookings->ensureUser(621);
Database::run($pdo, 'UPDATE Data SET Name=? WHERE UserId=621', ['Synthetic catalogue owner']);
putenv('REQUIRE_VERIFIED_PROFILE=false');
$booking = $bookings->reserve(621, $ids[0], 1, 'synthetic-catalogue-ownership');
$bookings->publication(999, $ids[0], 'inactive');
$check(str_contains($send(621, null, "p:tour:{$ids[0]}")['text'], 'Synthetic catalogue 00'),
    'booking owner retains access after unpublication');
$check(str_contains($send(620, null, "p:tour:{$ids[0]}")['text'], 'منتشر نشده'),
    'unpublication still denies unrelated traveler');
$check(in_array("p:booking:$booking", $callbacks($send(621, '/bookings')), true), 'own booking listing retained');
fwrite(STDOUT, "Catalogue characterization passed ($checks checks).\n");

// The extracted boundary is callable without Telegram, sessions or an outbox sender.
$catalogue = new TourCatalogService($pdo, $bookings, $access);
$direct = $catalogue->tours(620, 0, $category);
$check(count($direct) === 10 && (int)$direct[0]['id'] === $ids[1], 'direct catalogue applies publication rules');
$check($catalogue->tours(620, 0, $category, false, "%_' OR 1=1 --") === [], 'direct search remains literal');
$check(count($catalogue->tours(999, 0, $category, true, 'no match')) === 10, 'admin catalogue ignores traveler filter');
$check($catalogue->detail(621, $ids[0])['remaining'] === 19, 'direct detail preserves capacity calculation');
foreach ([
    static fn () => $catalogue->tours(620, 0, null, true),
    static fn () => $catalogue->detail(620, $draft),
    static fn () => $catalogue->detail(620, $ids[0]),
    static fn () => $catalogue->tours(620, -1),
    static fn () => $catalogue->categories(0, 100001),
] as $denied) {
    try {
        $denied();
        throw new RuntimeException('Direct catalogue call bypassed authorization or bounds.');
    } catch (RuleViolation $error) {
        $check(true, 'direct service boundary fails closed');
    }
}
fwrite(STDOUT, "Catalogue service integration passed ($checks total checks).\n");
