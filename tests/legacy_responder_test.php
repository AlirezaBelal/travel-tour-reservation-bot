<?php
declare(strict_types=1);
require __DIR__ . '/../src/LegacyResponder.php';
$calls = [];
$responder = new LegacyResponder(static function (string $method, array $payload) use (&$calls): array {
    $calls[] = [$method, $payload];
    return ['ok' => true];
});
$responder->sendMessage(1, 'Synthetic');
$responder->sendkeyboard(1, 'Synthetic', '{}');
$responder->editMessageText(1, 2, 'Synthetic');
$responder->editMessageKeyboard(1, 2, 'Synthetic', '{}');
$responder->copymessage(1, 2, 3);
$responder->bot('answerCallbackQuery', ['callback_query_id' => 'synthetic']);
if ($calls !== [
    ['sendMessage', ['chat_id' => 1, 'text' => 'Synthetic']],
    ['sendMessage', ['chat_id' => 1, 'text' => 'Synthetic', 'reply_markup' => '{}']],
    ['editMessageText', ['chat_id' => 1, 'message_id' => 2, 'text' => 'Synthetic']],
    ['editMessageText', ['chat_id' => 1, 'message_id' => 2, 'text' => 'Synthetic', 'reply_markup' => '{}']],
    ['copyMessage', ['chat_id' => 1, 'from_chat_id' => 2, 'message_id' => 3]],
    ['answerCallbackQuery', ['callback_query_id' => 'synthetic']],
]) throw new RuntimeException('Legacy delivery adapter changed its payload contract.');
fwrite(STDOUT, "Legacy responder passed: six delivery contracts, no global helpers.\n");
