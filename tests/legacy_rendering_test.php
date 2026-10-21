<?php
declare(strict_types=1);

require __DIR__ . '/../src/product_bootstrap.php';
require __DIR__ . '/../src/TopicMemberships.php';
require __DIR__ . '/../src/LegacyHandlerAdapter.php';
$dsn = getenv('TEST_MYSQL_DSN');
if (!$dsn || preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D', $dsn) !== 1) {
    throw new RuntimeException('Isolated test database required.');
}
$pdo = new PDO($dsn, getenv('TEST_MYSQL_USER') ?: 'root', getenv('TEST_MYSQL_PASSWORD') ?: '');
Database::configure($pdo);
$catalogue = new TopicCatalogue($pdo);
$memberships = new TopicMemberships($pdo);
$flow = new LegacyMembershipService($pdo, $memberships, $catalogue, [999]);
$forms = new LegacyTopicService($pdo, $catalogue, [999]);
$broadcasts = new LegacyBroadcastService($pdo, $memberships, [999]);
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($label);
};
$send = static function (int $actor, ?string $text, ?string $callback = null) use ($catalogue, $flow, $forms, $broadcasts): array {
    $calls = [];
    $responder = new LegacyResponder(static function (string $method, array $payload) use (&$calls): array {
        $calls[] = ['method' => $method, 'payload' => $payload];
        return ['ok' => true];
    });
    LegacyHandlerAdapter::dispatch($catalogue, $forms, $flow, $broadcasts, [999],
        new LegacyUpdate($actor, $actor, $text, $callback, 77, 'synthetic-render-query', $text !== null), $responder);
    return $calls;
};
$keyboard = static fn (array $calls): array => json_decode($calls[0]['payload']['reply_markup'], true, 512, JSON_THROW_ON_ERROR)['inline_keyboard'];
$pdo->beginTransaction();
try {
    $flow->ensureUser(97101);
    Database::run($pdo, "UPDATE Data SET profile='true',step='defult' WHERE UserId=97101");
    $roots = [];
    foreach (['Synthetic alpha', 'Synthetic بتا', 'Synthetic gamma'] as $name) $roots[] = $catalogue->create($name, 0);
    $child = $catalogue->create('Synthetic child', $roots[0]);
    $memberships->request(97101, $roots[1]);
    $memberships->request(97101, $roots[2]);
    $memberships->approve(97101, $roots[2]);
    $rootRows = $catalogue->children(0);
    $expected = array_map(static fn (array $row): array => [
        ['text' => $row['Name'], 'callback_data' => 'sub-' . $row['id']],
        ['text' => '🗑', 'callback_data' => 'delete-' . $row['id']],
    ], $rootRows);
    $expected[] = [['text' => 'ایجاد دسته', 'callback_data' => 'AddTopic-0']];
    $expected[] = [['text' => 'بازگشت', 'callback_data' => 'BackHome']];
    $topics = $send(999, 'Topics');
    $check($keyboard($topics) === $expected, 'owner root menu row/order/callback contract');
    $back = $send(999, null, 'BackHome');
    $check($keyboard($back) === $expected && $back[0]['method'] === 'editMessageText'
        && $back[0]['payload']['message_id'] === 77, 'owner back edits the same menu/message');
    $list = $keyboard($send(97101, '/list'));
    $check(count($list) === count($rootRows), 'membership root listing has one pair per topic');
    foreach ($roots as $index => $id) {
        $row = array_values(array_filter($list, static fn (array $r): bool => $r[0]['callback_data'] === "join-$id"))[0];
        $check($row[1] === ['text' => ['❌', '🔄', '✅'][$index], 'callback_data' => "join-$id"], 'membership state icon and callback');
    }
    $check($keyboard($send(97101, null, 'BackToHome')) === $list, 'traveler back preserves membership menu');
    $nested = $keyboard($send(999, null, 'sub-' . $roots[0]));
    $check($nested === [
        [['text' => 'Synthetic child', 'callback_data' => "sub-$child"], ['text' => '🗑', 'callback_data' => "delete-$child"]],
        [['text' => 'ایجاد دسته', 'callback_data' => 'AddTopic-' . $roots[0]]],
        [['text' => 'بازگشت', 'callback_data' => 'BackHome']],
    ], 'nested owner menu preserves parent and footer');
    $empty = $keyboard($send(999, null, "sub-$child"));
    $check($empty === [
        [['text' => 'ایجاد دسته', 'callback_data' => "AddTopic-$child"]],
        [['text' => 'بازگشت', 'callback_data' => 'BackHome']],
    ], 'empty child menu still offers create/back');
    $all = $catalogue->all();
    $broadcast = $keyboard($send(999, '/send'));
    $check(count($broadcast) === count($all) + 1, 'broadcast offers each category and all recipients');
    foreach ($all as $i => $row) $check($broadcast[$i] === [
        ['text' => $row['Name'], 'callback_data' => 'send' . $row['id']],
    ], 'broadcast category order and callback');
    $check(end($broadcast) === [['text' => 'ارسال به همه ', 'callback_data' => 'sendAll']], 'broadcast all callback');
    foreach (['BackHome', 'sub-' . $roots[0], 'AddTopic-0', 'sendAll', 'confirmDelete-' . $roots[0]] as $callback) {
        $check($send(97101, null, $callback) === [], 'non-owner cannot render or execute owner action');
    }
    foreach (['sub-x', 'AddTopic-x', 'join-x', 'approve:0:1', 'taeed;;bad'] as $callback) {
        $check($send(999, null, $callback) === [], 'malformed callback remains silent');
    }
} finally {
    $pdo->rollBack();
}
fwrite(STDOUT, "Legacy rendering characterization passed ($checks checks).\n");
