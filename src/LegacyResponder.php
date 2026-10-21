<?php

declare(strict_types=1);

/** Explicit Telegram response adapter; tests replace only the external delivery closure. */
final class LegacyResponder
{
    /**
     * @param Closure(string, array<string, mixed>): (array<string, mixed>|false) $send
     */
    public function __construct(private Closure $send)
    {
    }
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|false
     */
    public function bot(string $method, array $payload = []): array|false
    {
        return ($this->send)($method, $payload);
    }
    /**
     * @return array<string, mixed>|false
     */
    public function sendMessage(int|string $chat, string $text): array|false
    {
        return $this->bot('sendMessage', ['chat_id' => $chat, 'text' => $text]);
    }
    /**
     * @return array<string, mixed>|false
     */
    public function sendkeyboard(int|string $chat, string $text, string $keyboard): array|false
    {
        return $this->bot('sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => $keyboard]);
    }
    /**
     * @return array<string, mixed>|false
     */
    public function editMessageText(int|string $chat, int|string $id, string $text): array|false
    {
        return $this->bot('editMessageText', ['chat_id' => $chat, 'message_id' => $id, 'text' => $text]);
    }
    /**
     * @return array<string, mixed>|false
     */
    public function editMessageKeyboard(int|string $chat, int|string $id, string $text, string $keyboard, mixed $caption = null): array|false
    {
        return $this->bot('editMessageText', ['chat_id' => $chat, 'message_id' => $id, 'text' => $text, 'reply_markup' => $keyboard]);
    }
    /**
     * @return array<string, mixed>|false
     */
    public function copymessage(int|string $chat, int|string $from, int|string $id): array|false
    {
        return $this->bot('copyMessage', ['chat_id' => $chat, 'from_chat_id' => $from, 'message_id' => $id]);
    }
}
