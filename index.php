<?php

require __DIR__ . '/config.php';
require_once __DIR__ . '/src/LegacyHandlerAdapter.php';
try {
    $processor->handle((int)$update['update_id'], (int)$fromId, is_string($text) ? $text : null, is_string($data) ? $data : null, function () use ($db, $memberships, $catalogue, $topicForms, $fromId, $chatId, $text, $data, $messageId, $callbackId, $text2, $userName, $message, $photo, $document, $video) {
        LegacyHandlerAdapter::dispatch(
            $db, $memberships, $catalogue, (int)$fromId, (int)$chatId,
            $text, $data, $messageId, $callbackId, $text2, $userName,
            $message, $photo, $document, $video, $topicForms
        );
    }, is_string($callbackId) ? $callbackId : null, is_array($message['contact'] ?? null) ? $message['contact'] : null);
} catch (InvalidArgumentException) {
    sendMessage($chatId, 'اطلاعات واردشده معتبر نیست یا دسته موردنظر دیگر وجود ندارد. لطفاً دوباره تلاش کنید.');
} catch (Throwable) {
    error_log('Webhook processing failed.');
    http_response_code(500);
}
