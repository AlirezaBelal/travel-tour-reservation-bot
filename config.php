<?php

date_default_timezone_set('Asia/Tehran');

use Dotenv\Dotenv;
use Medoo\Medoo;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/validators.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

function requiredEnv(string $name): string
{
    $value = trim((string)($_ENV[$name] ?? ''));
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

$webhookSecret = trim((string)($_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? ''));
if ($webhookSecret !== '') {
    $providedSecret = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if ($providedSecret === '' || !hash_equals($webhookSecret, $providedSecret)) {
        http_response_code(403);
        exit;
    }
}

$db = new Medoo([
    'database_type' => 'mysql',
    'server' => requiredEnv('DB_SERVER'),
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'database_name' => requiredEnv('DB_DATABASE'),
    'username' => requiredEnv('DB_USERNAME'),
    'password' => requiredEnv('DB_PASSWORD'),
]);

function isAdmin(int|string|null $chatId): bool
{
    if ($chatId === null || $chatId === false || $chatId === '') {
        return false;
    }

    return in_array((int)$chatId, ADMINS, true);
}

function bot(string $method, array $data = []): array|false
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.telegram.org/bot' . TOKEN . '/' . $method,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $result = curl_exec($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $failed = $result === false;
    curl_close($curl);

    if ($failed || $statusCode < 200 || $statusCode >= 300) {
        error_log('Telegram API request failed for method ' . $method . '.');
        return false;
    }

    $decoded = json_decode((string)$result, true);
    if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
        error_log('Telegram API returned an unsuccessful response for method ' . $method . '.');
        return false;
    }

    return $decoded;
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

function hasdb(int|string $userid): bool
{
    global $db;

    if (!$db->has('Data', ['UserId' => (string)$userid])) {
        $db->insert('Data', [
            'UserId' => (string)$userid,
            'step' => 'defult',
        ]);
        return false;
    }

    return true;
}

$rawUpdate = file_get_contents('php://input');
if (!is_string($rawUpdate) || trim($rawUpdate) === '') {
    http_response_code(200);
    exit;
}

try {
    $update = json_decode($rawUpdate, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    http_response_code(400);
    exit;
}

$text = $chatType = $chatTitle = $userName = $firstName = $callbackId = $data = $query = $fromId = false;
$text2 = $messageId2 = false;
$message = [];
$photo = $document = $video = null;
$chatId = $messageId = null;

if (isset($update['message']) || isset($update['edited_message'])) {
    $message = $update['message'] ?? $update['edited_message'];
    $messageId = $message['message_id'] ?? null;
    $text = $message['text'] ?? null;
    $photo = $message['photo'] ?? null;
    $document = $message['document'] ?? null;
    $video = $message['video'] ?? null;
    $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
    $chatId = $chat['id'] ?? null;
    $chatType = $chat['type'] ?? null;
    $chatTitle = $chat['title'] ?? 'Unknown';
    $from = is_array($message['from'] ?? null) ? $message['from'] : [];
    $fromId = $from['id'] ?? null;
    $firstName = $from['first_name'] ?? '';
    $userName = $from['username'] ?? 'Empty';
} elseif (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
    $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
    $chatId = $chat['id'] ?? null;
    $text2 = $message['text'] ?? null;
    $messageId = $message['message_id'] ?? null;
    $data = $callback['data'] ?? null;
    $callbackId = $callback['id'] ?? null;
    $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];
    $fromId = $from['id'] ?? null;
    $firstName = $from['first_name'] ?? '';
    $userName = $from['username'] ?? 'Empty';
    $messageId2 = $messageId;

    if (isSensitiveAdminCallback($data) && !isAdmin($fromId)) {
        if (is_string($callbackId) && $callbackId !== '') {
            bot('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => 'این عملیات فقط برای ادمین مجاز است.',
                'show_alert' => true,
            ]);
        }
        $data = false;
    }
}
