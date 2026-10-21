<?php

final class WebhookProcessor
{
    public function __construct(private PDO $pdo, private BookingService $bookings, private ProductBot $bot, private Outbox $outbox)
    {
    }
    public function handle(int $update, int $actor, ?string $text, ?string $callback, ?callable $legacy = null, ?string $callbackId = null, ?array $contact = null): void
    {
        if (Database::run($this->pdo, 'SELECT update_id FROM UpdateReceipts WHERE update_id=?', [$update])->fetchColumn() !== false) {
            return;
        }
        $slot = intdiv(time(), 60);
        Database::run($this->pdo, 'INSERT INTO RateLimits(user_id,minute_slot,hits) VALUES (?,?,1) ON DUPLICATE KEY UPDATE hits=hits+1', [$actor,$slot]);
        $hits = (int)Database::run($this->pdo, 'SELECT hits FROM RateLimits WHERE user_id=? AND minute_slot=?', [$actor,$slot])->fetchColumn();
        if ($hits > 30) {
            // Persist exactly one warning per minute; delivery still goes through the worker.
            $this->outbox->message($actor, 'پیام‌های زیادی ارسال شده است؛ یک دقیقه صبر کنید.', "rate:$actor:$slot");
            return;
        }
        try {
            Database::atomic($this->pdo, function () use ($update, $actor, $text, $callback, $legacy, $callbackId, $contact) {
                if (!$this->receipt($update)) {
                    return;
                }
                $this->bookings->ensureUser($actor);
                if (!$this->bot->dispatch($actor, $text, $callback, $update, $contact) && $legacy !== null) {
                    $legacy();
                }
                $this->acknowledge($actor, $callbackId);
            });
        } catch (DomainException | InvalidArgumentException $error) {
            Database::atomic($this->pdo, function () use ($update, $actor, $error, $callbackId) {
                if (!$this->receipt($update)) {
                    return;
                }
                $this->acknowledge($actor, $callbackId);
                $this->outbox->message($actor, $error instanceof RuleViolation ? $error->getMessage() : 'اطلاعات واردشده معتبر نیست؛ دوباره تلاش کنید یا /cancel بفرستید.', "update:$update:error");
            });
        }
    }
    private function acknowledge(int $actor, ?string $id): void
    {
        if ($id !== null && $id !== '') {
            $this->outbox->enqueue($actor, 'answerCallbackQuery', ['callback_query_id' => $id,'_expires_at' => gmdate('Y-m-d H:i:s', time() + 12)], 'callback:' . $id);
        }
    }
    private function receipt(int $id): bool
    {
        try {
            Database::run($this->pdo, 'INSERT INTO UpdateReceipts(update_id) VALUES (?)', [$id]);
            return true;
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                return false;
            }throw $error;
        }
    }
}
