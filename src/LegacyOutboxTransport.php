<?php
declare(strict_types=1);

/** Per-update legacy delivery context; no process-global actor, update or sequence. */
final class LegacyOutboxTransport
{
    private int $sequence = 0;
    public function __construct(private Outbox $outbox, private int $actor, private int $update) {}

    public function send(string $method, array $payload = []): array
    {
        $chat = (int)($payload['chat_id'] ?? $this->actor);
        $key = $method === 'answerCallbackQuery' && isset($payload['callback_query_id'])
            ? 'callback:' . $payload['callback_query_id'] : 'legacy:' . $this->update . ':' . $this->sequence++;
        $this->outbox->enqueue($chat, $method, $payload, $key);
        return ['ok' => true, 'queued' => true];
    }
}
