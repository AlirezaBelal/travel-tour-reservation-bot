<?php

/**
 * Pure mapping of a prevalidated private Telegram update to the historical
 * request variables. This has no database, output, or Telegram API effects.
 *
 * Keeping the legacy-compatible shape here permits a later migration away
 * from the include-based handler without changing HTTP authentication.
 */
final class IncomingUpdate
{
    /**
     * @param array<string, mixed> $update
     * @return array<string, mixed>
     */
    public static function context(array $update): array
    {
        if (!isSupportedPrivateUpdate($update)) {
            throw new InvalidArgumentException('Unsupported private Telegram update.');
        }

        $context = [
            'text' => false,
            'chatType' => false,
            'chatTitle' => false,
            'userName' => false,
            'firstName' => false,
            'callbackId' => false,
            'data' => false,
            'query' => false,
            'fromId' => false,
            'text2' => false,
            'messageId2' => false,
            'message' => [],
            'photo' => null,
            'document' => null,
            'video' => null,
            'chatId' => null,
            'messageId' => null,
        ];

        if (isset($update['message'])) {
            $message = $update['message'];
            $chat = $message['chat'];
            $from = $message['from'];

            $context['message'] = $message;
            $context['messageId'] = $message['message_id'];
            $context['text'] = $message['text'] ?? null;
            $context['photo'] = $message['photo'] ?? null;
            $context['document'] = $message['document'] ?? null;
            $context['video'] = $message['video'] ?? null;
            $context['chatId'] = $chat['id'];
            $context['chatType'] = $chat['type'];
            $context['chatTitle'] = $chat['title'] ?? 'Unknown';
        } else {
            $callback = $update['callback_query'];
            $message = $callback['message'];
            $chat = $message['chat'];
            $from = $callback['from'];

            $context['message'] = $message;
            $context['chatId'] = $chat['id'];
            $context['text2'] = $message['text'] ?? null;
            $context['messageId'] = $message['message_id'];
            $context['data'] = $callback['data'];
            $context['callbackId'] = $callback['id'];
            $context['messageId2'] = $message['message_id'];
        }

        $context['fromId'] = $from['id'];
        $context['firstName'] = $from['first_name'] ?? '';
        $context['userName'] = $from['username'] ?? 'Empty';
        return $context;
    }
}
