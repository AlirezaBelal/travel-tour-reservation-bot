<?php

require_once __DIR__ . '/../src/webhook.php';
require_once __DIR__ . '/../src/WebhookRegistration.php';

$checks = 0;
function checkWebhook(bool $expected, bool $actual, string $label): void
{
    global $checks;
    $checks++;
    if ($expected !== $actual) {
        fwrite(STDERR, "FAILED: {$label}\n");
        exit(1);
    }
}

$secret = str_repeat('a', 32);
checkWebhook(true, webhookSecretIsValid($secret, $secret), 'matching secret');
foreach (['', null, [], str_repeat('b', 32)] as $provided) {
    checkWebhook(false, webhookSecretIsValid($secret, $provided), 'invalid secret');
}
checkWebhook(false, webhookSecretIsValid('', ''), 'empty configuration fails closed');
checkWebhook(false, webhookSecretIsValid('short', 'short'), 'weak configuration fails closed');

$message = ['message_id' => 1, 'from' => ['id' => 123, 'is_bot' => false],
    'chat' => ['id' => 123, 'type' => 'private'], 'text' => '/start'];
$update = ['update_id' => 0, 'message' => $message];
checkWebhook(true, isSupportedPrivateUpdate($update), 'private start preserved');
foreach ([null, false, 1, '123', [], ['update_id' => 1], ['update_id' => '1', 'message' => $message],
    ['update_id' => 1, 'message' => 'bad'], ['update_id' => 1, 'edited_message' => $message]] as $bad) {
    checkWebhook(false, isSupportedPrivateUpdate($bad), 'malformed or unsupported update');
}
foreach (['group', 'supergroup', 'channel'] as $type) {
    $bad = $update;
    $bad['message']['chat']['type'] = $type;
    checkWebhook(false, isSupportedPrivateUpdate($bad), 'shared chat rejected');
}
$bad = $update;
$bad['message']['chat']['id'] = 456;
checkWebhook(false, isSupportedPrivateUpdate($bad), 'other profile rejected');
$callback = ['update_id' => 1, 'callback_query' => ['id' => 'query',
    'from' => ['id' => 123], 'message' => $message, 'data' => 'join-2']];
checkWebhook(true, isSupportedPrivateUpdate($callback), 'private callback preserved');
foreach ([null, [], '', str_repeat('x', 65), str_repeat('ش', 33)] as $data) {
    $bad = $callback;
    $bad['callback_query']['data'] = $data;
    checkWebhook(false, isSupportedPrivateUpdate($bad), 'callback byte limit');
}
$bad = $callback;
$bad['callback_query']['from']['id'] = 456;
checkWebhook(false, isSupportedPrivateUpdate($bad), 'forwarded callback rejected');
$bad = $callback;
unset($bad['callback_query']['message']);
checkWebhook(false, isSupportedPrivateUpdate($bad), 'inline callback rejected');
foreach ([0, -1, '123', 1.5, true, 4503599627370496] as $id) {
    checkWebhook(false, isTelegramId($id), 'invalid actor ID');
}
$registration = WebhookRegistration::parameters('https://bot.example.test/index.php', $secret);
checkWebhook(true, $registration['drop_pending_updates'] === 'false', 'registration preserves updates');
checkWebhook(true, json_decode($registration['allowed_updates'], true) === ['message','callback_query'], 'registration update allowlist');
foreach (['http://bot.example.test/index.php','https://user:password@bot.example.test/index.php','https://bot.example.test/index.php?secret=value','https://bot.example.test/index.php#fragment','not a URL'] as $url) {
    try { WebhookRegistration::parameters($url,$secret); throw new RuntimeException('Unsafe webhook URL accepted.'); }
    catch (InvalidArgumentException) { checkWebhook(true,true,'unsafe registration rejected'); }
}
fwrite(STDOUT, "Webhook tests passed ({$checks} checks).\n");
