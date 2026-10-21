<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/validators.php';
require __DIR__ . '/../src/profiles.php';
require __DIR__ . '/../src/TopicMemberships.php';
require __DIR__ . '/../src/TopicCatalogue.php';
require __DIR__ . '/../src/LegacyHandlerAdapter.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/LegacyTopicService.php';

define('ADMINS', [999]);
$dsn = getenv('TEST_MYSQL_DSN');
if (!$dsn || preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D', $dsn) !== 1) {
    throw new RuntimeException('Use the isolated travel_tour_test database.');
}
$pdo = new PDO($dsn, getenv('TEST_MYSQL_USER') ?: 'root', getenv('TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Medoo\Medoo(['pdo' => $pdo, 'database_type' => 'mysql']);
$memberships = new TopicMemberships($pdo);
$catalogue = new TopicCatalogue($pdo);
$topicForms = new LegacyTopicService($pdo, $catalogue, ADMINS);
$calls = [];
$checks = 0;
function expectFlow(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($label);
    }
}
// Only the external Telegram boundary is faked. The production legacy adapter,
// handler, validators,
// catalogue, repositories, Medoo queries and real MySQL transactions run unchanged.
function bot(string $method, array $payload = []): array
{
    global $calls;
    $calls[] = ['method' => $method, 'payload' => $payload];
    return ['ok' => true, 'result' => ['message_id' => count($calls)]];
}
function sendMessage(int|string $chatId, string $text): array
{
    return bot('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
}
function sendkeyboard(int|string $chatId, string $text, string $keyboard): array
{
    return bot('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'reply_markup' => $keyboard]);
}
function editMessageText(int|string $chatId, int|string $messageId, string $text): array
{
    return bot('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text]);
}
function editMessageKeyboard(int|string $chatId, int|string $messageId, string $text, string $keyboard, mixed $caption = null): array
{
    return bot('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'reply_markup' => $keyboard]);
}
function copymessage(int|string $chatId, int|string $fromChatId, int|string $messageId): array
{
    return bot('copyMessage', ['chat_id' => $chatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId]);
}
function dispatchFlow(int $actor, ?string $input = null, ?string $callback = null, array $contactPayload = []): array
{
    global $db, $memberships, $catalogue, $topicForms, $calls;
    $calls = [];
    $fromId = $chatId = $actor;
    $text = $input;
    $data = $callback;
    $messageId = 1;
    $callbackId = 'synthetic-query';
    $text2 = 'Synthetic administrator message';
    $userName = 'synthetic_fixture';
    $message = $contactPayload === [] ? [] : ['contact' => $contactPayload];
    $photo = $document = $video = null;
    LegacyHandlerAdapter::dispatch(
        $db, $memberships, $catalogue, $fromId, $chatId,
        $text, $data, $messageId, $callbackId, $text2, $userName,
        $message, $photo, $document, $video, $topicForms
    );
    return $calls;
}

// Must follow mysql_test.php, which creates the isolated legacy schema and migrates it.
$pdo->exec("INSERT INTO Data (UserId, profile) VALUES (999, 'true')");
dispatchFlow(201, '/start');
expectFlow($db->has('Data', ['UserId' => 201]), '/start creates user');
dispatchFlow(201, '/setprofile');
expectFlow($db->get('Data', 'step', ['UserId' => 201]) === 'defult', 'legacy profile command cannot start plaintext form');
foreach (['name','contact','NationalCode','sex','birthday'] as $oldStep) {
    $db->update('Data', ['step' => $oldStep], ['UserId' => 201]);
    dispatchFlow(201, 'Synthetic discarded legacy input', null, ['phone_number' => '09121234567', 'user_id' => 201]);
    $row = $db->get('Data', '*', ['UserId' => 201]);
    expectFlow($row['step'] === 'defult', 'legacy state retired: '.$oldStep);
    expectFlow($row['Mobile'] === null && $row['N_Code'] === null && $row['Name'] === null, 'legacy input never persisted: '.$oldStep);
}
// Existing completed profiles and healthy catalogue workflows remain supported.
$db->update('Data', ['Name' => 'Synthetic Traveler', 'profile' => 'true'], ['UserId' => 201]);

// Administrator catalogue creation through actual conversation steps.
dispatchFlow(999, '/AddTopic Synthetic root');
$root = (int)$db->get('Topics', 'id', ['Name' => 'Synthetic root']);
expectFlow($root > 0, 'admin root creation');
$parent = $root;
for ($level = 1; $level <= 3; $level++) {
    dispatchFlow(999, null, "AddTopic-$parent");
    dispatchFlow(999, "Synthetic level $level");
    $parent = (int)$db->get('Topics', 'id', ['Name' => "Synthetic level $level"]);
    dispatchFlow(999, "Synthetic caption $level");
}
$leaf = $parent;
expectFlow(dispatchFlow(201, '/list') !== [], 'traveler catalogue listing');
expectFlow(dispatchFlow(201, null, "join-$root") !== [], 'nested catalogue navigation');
dispatchFlow(202, '/start');
dispatchFlow(202, null, "join-$leaf");
expectFlow($memberships->status(202, $leaf) === null, 'incomplete profile cannot join');
$before = (int)$pdo->query('SELECT COUNT(*) FROM TopicMemberships')->fetchColumn();
dispatchFlow(201, null, 'join-999999');
expectFlow((int)$pdo->query('SELECT COUNT(*) FROM TopicMemberships')->fetchColumn() === $before, 'unknown topic cannot join');
$calls = dispatchFlow(201, null, "join-$leaf");
expectFlow($memberships->status(201, $leaf) === 'check', 'request pending');
$adminMessages = array_values(array_filter($calls, fn($call) => ($call['payload']['chat_id'] ?? null) === 999));
expectFlow(count($adminMessages) === 1, 'one administrator request notification');
$keyboard = json_decode($adminMessages[0]['payload']['reply_markup'], true);
$approval = $keyboard['inline_keyboard'][0][0]['callback_data'];
expectFlow($approval === "approve:201:$leaf" && strlen($approval) <= 64, 'bounded approval callback');
expectFlow(dispatchFlow(201, null, "join-$leaf") === [], 'duplicate request sends no repeated notification');
dispatchFlow(202, null, $approval);
expectFlow($memberships->status(201, $leaf) === 'check', 'unauthorized approval rejected');
$calls = dispatchFlow(999, null, $approval);
expectFlow($memberships->status(201, $leaf) === 'verify', 'administrator approves');
expectFlow(count(array_filter($calls, fn($call) => (string)($call['payload']['chat_id'] ?? '') === '201')) === 1, 'traveler receives approval notification');
expectFlow(dispatchFlow(999, null, $approval) === [], 'duplicate approval sends no duplicate notification');
dispatchFlow(999, null, "delete-$root");
expectFlow($db->has('Topics', ['id' => $leaf]), 'first delete click does not delete');
dispatchFlow(202, null, "confirmDelete-$root");
expectFlow($db->has('Topics', ['id' => $leaf]), 'unauthorized confirmed delete rejected');
dispatchFlow(999, null, "confirmDelete-$root");
expectFlow(!$db->has('Topics', ['id' => $leaf]) && !$db->has('Topics', ['id' => $root]), 'confirmed deep deletion');
expectFlow($memberships->status(201, $leaf) === null, 'deleted catalogue removes related membership');
// Direct application calls have the same configured-owner boundary as legacy routing.
$before = (int)$pdo->query('SELECT COUNT(*) FROM Topics')->fetchColumn();
try {
    $topicForms->createRoot(201, 201, 'Synthetic unauthorized category');
    throw new RuntimeException('Direct legacy creation bypassed owner permissions.');
} catch (RuleViolation $error) {
    expectFlow((int)$pdo->query('SELECT COUNT(*) FROM Topics')->fetchColumn() === $before,
        'direct service denies non-owner without writes');
}
$db->update('Data', ['step' => 'AddTopic-0'], ['UserId' => 201]);
expectFlow($topicForms->submit(201, 201, 'Synthetic unauthorized child') === 'ignored',
    'forged persisted topic form does not grant authority');
expectFlow($db->get('Data', 'step', ['UserId' => 201]) === 'AddTopic-0',
    'unauthorized topic form does not advance');
$db->update('Data', ['step' => 'defult'], ['UserId' => 201]);
$topicForms->begin(999, 999, 0);
expectFlow($topicForms->submit(999, 999, '   ') === 'ignored', 'empty topic retains form');
expectFlow($topicForms->submit(999, 999, 'Synthetic direct legacy category') === 'caption',
    'service creates category without Telegram helpers');
$directTopic = (int)$db->get('Topics', 'id', ['Name' => 'Synthetic direct legacy category']);
expectFlow($topicForms->submit(999, 999, "Synthetic caption ' ?") === 'saved', 'direct caption accepted literally');
expectFlow($db->get('Topics', 'caption', ['id' => $directTopic]) === "Synthetic caption ' ?",
    'direct caption stored with prepared query');
$db->update('Data', ['step' => 'caption-999999'], ['UserId' => 999]);
expectFlow($topicForms->submit(999, 999, 'Synthetic missing caption') === 'missing', 'missing category reported');
expectFlow($db->get('Data', 'step', ['UserId' => 999]) === 'caption-999999', 'missing category preserves legacy state');
$db->update('Data', ['step' => 'AddTopic-invalid'], ['UserId' => 999]);
expectFlow($topicForms->submit(999, 999, 'Synthetic malformed form') === 'ignored'
    && $db->get('Data', 'step', ['UserId' => 999]) === 'defult', 'malformed parent safely resets form');
// Category creation and conversation advancement roll back together on storage failure.
$topicForms->begin(999, 999, 0);
$pdo->exec("CREATE TRIGGER synthetic_fail_legacy_step BEFORE UPDATE ON Data FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic legacy state failure'");
try {
    $topicForms->submit(999, 999, 'Synthetic rollback legacy category');
    throw new RuntimeException('Expected synthetic legacy state failure.');
} catch (PDOException $error) {
    expectFlow(!$db->has('Topics', ['Name' => 'Synthetic rollback legacy category']),
        'conversation failure rolls back newly created category');
} finally {
    $pdo->exec('DROP TRIGGER synthetic_fail_legacy_step');
}
$db->update('Data', ['step' => 'defult'], ['UserId' => 999]);
expectFlow($topicForms->submit(999, 999, 'Synthetic unrelated input') === null, 'unrelated legacy input remains unhandled');
fwrite(STDOUT, "Telegram workflow regression tests passed ({$checks} checks).\n");
