<?php

require __DIR__ . '/config.php';
require_once __DIR__ . '/src/LegacyHandlerAdapter.php';
$legacyDelivery = new LegacyOutboxTransport($outbox, (int)$fromId, (int)$update['update_id']);
$legacyResponse = new LegacyResponder(Closure::fromCallable([$legacyDelivery, 'send']));
try {
    $processor->handle((int)$update['update_id'], (int)$fromId, is_string($text) ? $text : null, is_string($data) ? $data : null, function () use ($legacyResponse, $catalogue, $topicForms, $membershipFlow, $broadcasts, $fromId, $chatId, $text, $data, $messageId, $callbackId, $message, $photo, $document, $video) {
        LegacyHandlerAdapter::dispatch($catalogue, $topicForms, $membershipFlow, $broadcasts, ADMINS,
            new LegacyUpdate((int)$fromId, (int)$chatId, is_string($text) ? $text : null,
                is_string($data) ? $data : null, is_int($messageId) || is_string($messageId) ? $messageId : 0,
                is_string($callbackId) ? $callbackId : null,
                (bool)$text || isset($photo) || isset($document) || isset($video) || isset($message['contact'])), $legacyResponse);
    }, is_string($callbackId) ? $callbackId : null, is_array($message['contact'] ?? null) ? $message['contact'] : null);
} catch (InvalidArgumentException) {
    $legacyResponse->sendMessage($chatId, 'اطلاعات واردشده معتبر نیست یا دسته موردنظر دیگر وجود ندارد. لطفاً دوباره تلاش کنید.');
} catch (Throwable) {
    error_log('Webhook processing failed.');
    http_response_code(500);
}
