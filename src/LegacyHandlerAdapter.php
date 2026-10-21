<?php

/**
 * Compatibility boundary for pre-product Telegram commands.
 *
 * The old handler is an included script that depends on its lexical variables,
 * the message catalogue, and the established output helper functions. Keep that
 * coupling isolated here; the webhook entry point only calls this adapter.
 *
 * The adapter must run inside WebhookProcessor's owning database transaction so
 * the legacy writes and outbox events remain atomic with the update receipt.
 */
final class LegacyHandlerAdapter
{
    public static function dispatch(
        \Medoo\Medoo $db,
        TopicMemberships $memberships,
        TopicCatalogue $catalogue,
        int $fromId,
        int $chatId,
        mixed $text,
        mixed $data,
        mixed $messageId,
        mixed $callbackId,
        mixed $text2,
        mixed $userName,
        array $message,
        mixed $photo,
        mixed $document,
        mixed $video,
        LegacyTopicService $topicForms
    ): void {
        require dirname(__DIR__) . '/txt.php';
        require __DIR__ . '/handler.php';
    }
}
