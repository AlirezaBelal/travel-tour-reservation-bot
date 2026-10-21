<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/webhook.php';
require_once __DIR__ . '/../src/IncomingUpdate.php';

$checks = 0;
function checkIncoming(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException("Incoming update contract failed: $label");
    }
}

$from = ['id' => 301, 'is_bot' => false, 'first_name' => 'Synthetic', 'username' => 'fixture'];
$message = [
    'message_id' => 45,
    'from' => $from,
    'chat' => ['id' => 301, 'type' => 'private'],
    'text' => '/profile',
    'photo' => [['file_id' => 'synthetic-photo']],
];
$incoming = IncomingUpdate::context(['update_id' => 1, 'message' => $message]);
checkIncoming($incoming['message'] === $message && $incoming['messageId'] === 45, 'message preserved');
checkIncoming($incoming['fromId'] === 301 && $incoming['chatId'] === 301, 'message actor and chat');
checkIncoming($incoming['text'] === '/profile' && $incoming['photo'] === $message['photo'], 'message content');
checkIncoming($incoming['userName'] === 'fixture' && $incoming['firstName'] === 'Synthetic', 'actor metadata');
checkIncoming($incoming['chatType'] === 'private' && $incoming['chatTitle'] === 'Unknown', 'chat metadata');
checkIncoming($incoming['data'] === false && $incoming['callbackId'] === false
    && $incoming['text2'] === false && $incoming['messageId2'] === false, 'message callback defaults');

$callbackMessage = ['message_id' => 46, 'chat' => ['id' => 301, 'type' => 'private'], 'text' => 'Old message'];
$callback = ['update_id' => 2, 'callback_query' => [
    'id' => 'synthetic-query', 'from' => $from,
    'data' => 'p:profile:0', 'message' => $callbackMessage,
]];
$incoming = IncomingUpdate::context($callback);
checkIncoming($incoming['message'] === $callbackMessage && $incoming['chatId'] === 301, 'callback message preserved');
checkIncoming($incoming['fromId'] === 301 && $incoming['messageId'] === 46
    && $incoming['messageId2'] === 46, 'callback IDs');
checkIncoming($incoming['data'] === 'p:profile:0' && $incoming['callbackId'] === 'synthetic-query'
    && $incoming['text2'] === 'Old message', 'callback payload');
checkIncoming($incoming['text'] === false && $incoming['photo'] === null
    && $incoming['chatType'] === false, 'callback legacy defaults');

$contactMessage = $message;
unset($contactMessage['text'], $contactMessage['photo']);
$contactMessage['contact'] = ['user_id' => 301, 'phone_number' => 'synthetic-phone'];
$incoming = IncomingUpdate::context(['update_id' => 3, 'message' => $contactMessage]);
checkIncoming($incoming['text'] === null && $incoming['message']['contact']['user_id'] === 301,
    'contact stays attached to original message');

foreach ([
    ['update_id' => 4, 'message' => array_replace($message, ['chat' => ['id' => 999, 'type' => 'group']])],
    ['update_id' => 5, 'edited_message' => $message],
    ['update_id' => 6, 'callback_query' => array_replace($callback['callback_query'], ['data' => str_repeat('a', 65)])],
] as $bad) {
    try {
        IncomingUpdate::context($bad);
        throw new RuntimeException('Unsupported update was accepted.');
    } catch (InvalidArgumentException) {
        $checks++;
    }
}
fwrite(STDOUT, "Incoming update tests passed ($checks checks).\n");
