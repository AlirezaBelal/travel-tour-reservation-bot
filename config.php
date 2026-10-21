<?php

date_default_timezone_set('Asia/Tehran');

use Dotenv\Dotenv;
use Medoo\Medoo;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/validators.php';
require_once __DIR__ . '/src/webhook.php';
require_once __DIR__ . '/src/IncomingUpdate.php';
require_once __DIR__ . '/src/TopicMemberships.php';
require_once __DIR__ . '/src/TopicCatalogue.php';
require_once __DIR__ . '/src/profiles.php';
require_once __DIR__ . '/src/product_bootstrap.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

function environmentValue(string $name, string $default = ''): string
{
    $value = $_ENV[$name] ?? getenv($name);
    return $value === false ? $default : (string)$value;
}

function requiredEnv(string $name): string
{
    $value = trim(environmentValue($name));
    if ($value === '') {
        throw new RuntimeException("Missing required environment variable: {$name}");
    }

    return $value;
}

$telegramToken = requiredEnv('TELEGRAM_BOT_TOKEN');
$adminIds = parseAdminIds(requiredEnv('TELEGRAM_ADMIN_IDS'));
if ($adminIds === []) {
    throw new RuntimeException('TELEGRAM_ADMIN_IDS must contain at least one numeric Telegram user ID.');
}

define('TOKEN', $telegramToken);
define('ADMINS', $adminIds);

$webhookSecret = requiredEnv('TELEGRAM_WEBHOOK_SECRET');
if (!webhookSecretIsValid($webhookSecret, $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null)) {
    http_response_code(403);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}



function bot(string $method, array $data = []): array|false
{
    global $outbox, $update, $fromId;
    static $sequence = 0;
    $chat = (int)($data['chat_id'] ?? $fromId);
    $key = $method === 'answerCallbackQuery' && isset($data['callback_query_id']) ? 'callback:' . $data['callback_query_id'] : 'legacy:' . $update['update_id'] . ':' . $sequence++;
    $outbox->enqueue($chat, $method, $data, $key);
    return ['ok' => true, 'queued' => true];
}

function sendMessage(int|string $chatId, string $text): array|false
{
    return bot('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
}

function sendkeyboard(int|string $chatId, string $text, string $keyboard): array|false
{
    return bot('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => $keyboard,
    ]);
}

function copymessage(int|string $chatId, int|string $fromChatId, int|string $messageId): array|false
{
    return bot('copyMessage', [
        'chat_id' => $chatId,
        'from_chat_id' => $fromChatId,
        'message_id' => $messageId,
    ]);
}

function editMessageText(int|string $chatId, int|string $messageId, string $text): array|false
{
    return bot('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
    ]);
}

function editMessageKeyboard(int|string $chatId, int|string $messageId, string $text, string $keyboard, mixed $caption = null): array|false
{
    return bot('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'reply_markup' => $keyboard,
    ]);
}

function editMessageCaption(int|string $chatId, int|string $messageId, string $caption): array|false
{
    return bot('editMessageCaption', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'caption' => $caption,
    ]);
}

function sendfile(int|string $chatId, string $document, string $text): array|false
{
    return bot('sendDocument', [
        'chat_id' => $chatId,
        'document' => $document,
        'caption' => $text,
        'parse_mode' => 'Markdown',
    ]);
}

function advancedBuild(array $buttons, int|array $perline, bool $json = true, string $TEXT = '', string $perfix = ''): array|string|false
{
    $keyboard = [];
    $n = $m = 0;

    foreach ($buttons as $button) {
        $callback = $button['id'];
        $text = $button['Name'];
        if ((is_array($perline) && ($perline[$m] ?? null) === $n) || $n === $perline) {
            $m++;
            $n = 0;
        }

        $keyboard[$m][] = ['text' => $text, 'callback_data' => (string)$text];
        $keyboard[$m][] = ['text' => $TEXT, 'callback_data' => $perfix . $callback];
        $n += 2;
    }

    return $json
        ? json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE)
        : $keyboard;
}


$rawUpdate = file_get_contents('php://input', false, null, 0, 1048577);
if (!is_string($rawUpdate) || trim($rawUpdate) === '') {
    http_response_code(200);
    exit;
}

if (strlen($rawUpdate) > 1048576) {
    http_response_code(413);
    exit;
}

try {
    $update = json_decode($rawUpdate, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    http_response_code(400);
    exit;
}

if (!isSupportedPrivateUpdate($update)) {
    // Acknowledge unsupported updates without side effects or Telegram retries.
    http_response_code(200);
    exit;
}

$db = new Medoo([
    'database_type' => 'mysql',
    'error' => PDO::ERRMODE_EXCEPTION,
    'server' => requiredEnv('DB_SERVER'),
    'port' => (int)environmentValue('DB_PORT', '3306'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'database_name' => requiredEnv('DB_DATABASE'),
    'username' => requiredEnv('DB_USERNAME'),
    'password' => requiredEnv('DB_PASSWORD'),
]);

Database::configure($db->pdo);
$outbox = new Outbox($db->pdo);
$access = new AccessControl($db->pdo, ADMINS);
$paymentReview = new PaymentReviewService($db->pdo, $access, $outbox);
$bookings = BookingServiceFactory::create($db->pdo, $access, $outbox, $paymentReview);
$productBot = ProductBotFactory::create($db->pdo, $bookings, $access, $outbox, $paymentReview, environmentValue('APP_TIMEZONE','Asia/Tehran'));
$processor = new WebhookProcessor($db->pdo, $bookings, $productBot, $outbox);

$memberships = new TopicMemberships($db->pdo);
$catalogue = new TopicCatalogue($db->pdo);
$topicForms = new LegacyTopicService($db->pdo, $catalogue, ADMINS);

// The pure ingress mapper preserves the lexical variables needed by the
// compatibility adapter, without mixing JSON shape parsing with PDO bootstrap.
$incoming = IncomingUpdate::context($update);
$text = $incoming['text'];
$chatType = $incoming['chatType'];
$chatTitle = $incoming['chatTitle'];
$userName = $incoming['userName'];
$firstName = $incoming['firstName'];
$callbackId = $incoming['callbackId'];
$data = $incoming['data'];
$query = $incoming['query'];
$fromId = $incoming['fromId'];
$text2 = $incoming['text2'];
$messageId2 = $incoming['messageId2'];
$message = $incoming['message'];
$photo = $incoming['photo'];
$document = $incoming['document'];
$video = $incoming['video'];
$chatId = $incoming['chatId'];
$messageId = $incoming['messageId'];

if (isset($update['callback_query']) && isSensitiveAdminCallback($data) && !isAdmin($fromId)) {
    if (is_string($callbackId) && $callbackId !== '') {
        bot('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => 'این عملیات فقط برای ادمین مجاز است.',
            'show_alert' => true,
        ]);
    }
    $data = false;
}
