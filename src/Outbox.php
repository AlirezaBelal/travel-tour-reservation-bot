<?php

final class Outbox
{
    public function __construct(private PDO $pdo)
    {
    }

    public function enqueue(int $chat, string $method, array $payload, string $key): void
    {
        if (!in_array($method, ['sendMessage', 'copyMessage', 'editMessageText', 'editMessageCaption', 'answerCallbackQuery', 'sendDocument'], true)) {
            throw new InvalidArgumentException('Unsupported Telegram method.');
        }
        if ($method === 'sendMessage' && is_string($payload['text'] ?? null)) {
            $characters = preg_split('//u', $payload['text'], -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($characters) && count($characters) > 2000) {
                $chunks = array_chunk($characters, 2000);
                foreach ($chunks as $index => $chunk) {
                    $part = $payload;
                    $part['text'] = implode('', $chunk);
                    if ($index !== count($chunks) - 1) {
                        unset($part['reply_markup']);
                    }
                    $this->enqueue($chat, $method, $part, $key . ':part:' . $index);
                }
                return;
            }
        }
        $payload['chat_id'] = $chat;
        if ($method === 'answerCallbackQuery') {
            unset($payload['chat_id']);
        }
        Database::run($this->pdo, 'INSERT INTO NotificationOutbox(chat_id,method,payload,idem_key) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE idem_key=idem_key', [
            $chat, $method, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), hash('sha256', $key),
        ]);
    }

    public function message(int $chat, string $text, string $key, array $keyboard = [], ?int $subject = null, array $permissions = []): void
    {
        $payload = ['text' => $text];
        if ($subject !== null) {
            $payload['_subject_user'] = $subject;
        }
        if ($permissions !== []) {
            $payload['_required_permissions'] = $permissions;
        }
        if ($keyboard !== []) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $this->enqueue($chat, 'sendMessage', $payload, $key);
    }

    public function retry(int $actor, int $id, AccessControl $access): void
    {
        $access->require($actor, 'notify');
        Database::atomic($this->pdo, function () use ($actor, $id, $access) {
            Database::run($this->pdo, "UPDATE NotificationOutbox SET status='pending',attempts=0,available_at=UTC_TIMESTAMP(),error_code=NULL WHERE id=? AND status='failed'", [$id]);
            $access->audit($actor, 'notification.retry', 'notification', $id);
        });
    }
}
