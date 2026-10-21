<?php

declare(strict_types=1);

/** Compatibility rendering boundary; all persistence and authority live in services. */
final class LegacyHandlerAdapter
{
    /**
     * @param list<int> $owners
     */
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
        require_once __DIR__ . '/LegacyKeyboard.php';
        require dirname(__DIR__) . '/txt.php';
        require __DIR__ . '/handler.php';
    }

    /** Shared command/back rendering; identity and membership rules stay in services. */
    public static function membershipMenu(
        TopicCatalogue $catalogue,
        LegacyMembershipService $memberships,
        LegacyResponder $responder,
        int $chat,
        int|string|null $message,
        string $list,
        string $start,
        string $profileFalse
    ): void {
        $user = $memberships->user($chat);
        if ($user === null) {
            $memberships->ensureUser($chat);
            $responder->sendMessage($chat, $start);
            return;
        }
        if (($user['profile'] ?? null) !== 'true') {
            $responder->sendMessage($chat, $profileFalse);
            return;
        }
        $rows = LegacyKeyboard::membership(
            $catalogue->children(0),
            static fn (int|string $id): string => LegacyKeyboard::statusIcon($memberships->status($chat, $id))
        );
        $keyboard = LegacyKeyboard::encode($rows);
        if ($message === null) {
            $responder->sendkeyboard($chat, $list, $keyboard);
        } else {
            $responder->editMessageKeyboard($chat, $message, 'عضویت‌های قدیمی دسته‌ها', $keyboard);
        }
    }
}
