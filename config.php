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
$request = HttpIngress::decode($webhookSecret,
    $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null,
    $_SERVER['REQUEST_METHOD'] ?? '', file_get_contents('php://input', false, null, 0, 1048577));
if ($request['status'] !== 200 || $request['update'] === null) {
    http_response_code($request['status']);
    if ($request['status'] === 405) header('Allow: POST');
    exit;
}
$update = $request['update'];

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
$bookings = BookingServiceFactory::create($db->pdo, $access, $outbox, $paymentReview, environmentValue('REQUIRE_VERIFIED_PROFILE', 'false') === 'true');
$productBot = ProductBotFactory::create($db->pdo, $bookings, $access, $outbox, $paymentReview, environmentValue('APP_TIMEZONE','Asia/Tehran'));
$processor = new WebhookProcessor($db->pdo, $bookings, $productBot, $outbox);

$memberships = new TopicMemberships($db->pdo);
$catalogue = new TopicCatalogue($db->pdo);
$topicForms = new LegacyTopicService($db->pdo, $catalogue, ADMINS);
$membershipFlow = new LegacyMembershipService($db->pdo, $memberships, $catalogue, ADMINS);
$broadcasts = new LegacyBroadcastService($db->pdo, $memberships, ADMINS);

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
        $outbox->enqueue((int)$fromId, 'answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => 'این عملیات فقط برای ادمین مجاز است.',
            'show_alert' => true,
        ], 'callback:' . $callbackId);
    }
    $data = false;
}
