<?php

declare(strict_types=1);

/** Compatibility rendering boundary; all persistence and authority live in services. */
final class LegacyHandlerAdapter
{
    public static function dispatch(
        TopicCatalogue $catalogue,
        LegacyTopicService $topicForms,
        LegacyMembershipService $membershipFlow,
        LegacyBroadcastService $broadcasts,
        array $owners,
        LegacyUpdate $update,
        LegacyResponder $responder
    ): void {
        $fromId = $update->actor;
        $chatId = $update->chat;
        $text = $update->text;
        $data = $update->callback;
        $messageId = $update->messageId;
        $callbackId = $update->callbackId;
        $hasContent = $update->hasContent;
        require dirname(__DIR__) . '/txt.php';
        require __DIR__ . '/handler.php';
    }
}
