<?php

require_once __DIR__ . '/TelegramTransport.php';
final class NotificationWorker
{
    public function __construct(private PDO $pdo, private AccessControl $access)
    {
    }
    public static function delay(int $attempt, int $retryAfter = 0): int
    {
        return max($retryAfter, min(3600, 2 ** min(12, max(1, $attempt))));
    }
    /** Transport returns {ok, code, retry_after?}; never persists raw API errors. */
    public function tick(callable $transport, int $limit = 20, bool $throttle = true): int
    {
        if ((int)$this->pdo->query("SELECT GET_LOCK('travel_tour_outbox_worker',0)")->fetchColumn() !== 1) {
            return 0;
        }
        $processed = 0;
        try {
            if (Database::run($this->pdo, 'SELECT id FROM NotificationDeliveryState WHERE id=1 AND blocked_until>UTC_TIMESTAMP()')->fetchColumn() !== false) {
                return 0;
            }
            $this->pdo->exec("UPDATE NotificationOutbox SET status='pending',locked_at=NULL WHERE status='processing' AND locked_at < UTC_TIMESTAMP()-INTERVAL 5 MINUTE");
            for ($i = 0; $i < $limit; $i++) {
                $row = Database::atomic($this->pdo, function () use ($throttle) {
                    $condition = $throttle ? " AND (o.method='answerCallbackQuery' OR NOT EXISTS (SELECT 1 FROM NotificationOutbox recent WHERE recent.chat_id=o.chat_id AND recent.method<>'answerCallbackQuery' AND recent.sent_at >= UTC_TIMESTAMP()-INTERVAL 1 SECOND))" : '';
                    $row = Database::run($this->pdo, "SELECT o.* FROM NotificationOutbox o WHERE o.status='pending' AND o.available_at<=UTC_TIMESTAMP() $condition ORDER BY (o.method='answerCallbackQuery') DESC,o.id LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        Database::run($this->pdo, "UPDATE NotificationOutbox SET status='processing',attempts=attempts+1,locked_at=UTC_TIMESTAMP() WHERE id=?", [$row['id']]);
                    }
                    return $row;
                });
                if (!$row) {
                    break;
                }
                try {
                    $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($payload)) {
                        throw new UnexpectedValueException('Outbox payload must be an object.');
                    }
                } catch (JsonException | UnexpectedValueException) {
                    Database::run($this->pdo, "UPDATE NotificationOutbox SET status='failed',error_code='invalid_payload',locked_at=NULL WHERE id=?", [$row['id']]);
                    continue;
                }
                $obsolete = isset($payload['_expires_at']) && $payload['_expires_at'] <= gmdate('Y-m-d H:i:s');
                $authorized = true;
                foreach ((array)($payload['_required_permissions'] ?? []) as $permission) {
                    if (!is_string($permission) || !$this->access->allows((int)($payload['_permission_actor'] ?? $row['chat_id']), $permission)) {
                        $authorized = false;
                    }
                }
                if (!$authorized) {
                    Database::run($this->pdo, "UPDATE NotificationOutbox SET status='failed',error_code='permission_revoked',locked_at=NULL WHERE id=?", [$row['id']]);
                    continue;
                }
                if (isset($payload['_export_actor']) && !$this->access->allows((int)$payload['_export_actor'], 'export')) {
                    Database::run($this->pdo, "UPDATE NotificationOutbox SET status='failed',error_code='permission_revoked' WHERE id=?", [$row['id']]);
                    continue;
                }
                if (isset($payload['_reminder_booking'])) {
                    $booking = Database::run($this->pdo, "SELECT b.status,t.version,t.starts_at FROM Bookings b JOIN Tours t ON t.id=b.tour_id WHERE b.id=?", [$payload['_reminder_booking']])->fetch(PDO::FETCH_ASSOC);
                    $obsolete = !$booking || $booking['status'] !== 'confirmed' || (int)$booking['version'] !== (int)$payload['_tour_version'] || $booking['starts_at'] <= gmdate('Y-m-d H:i:s');
                }
                foreach (array_keys($payload) as $key) {
                    if (str_starts_with($key, '_') && $key !== '_csv') {
                        unset($payload[$key]);
                    }
                }
                try {
                    $result = $obsolete ? ['ok' => true,'code' => 0] : $transport($row['method'], $payload);
                } catch (Throwable) {
                    $result = ['ok' => false,'code' => 0];
                }
                $processed++;
                $code = (int)($result['code'] ?? 0);
                if (($result['ok'] ?? false) === true) {
                    Database::run($this->pdo, "UPDATE NotificationOutbox SET status='sent',sent_at=UTC_TIMESTAMP(),locked_at=NULL,error_code=? WHERE id=?", [$obsolete ? 'obsolete' : null,$row['id']]);
                } else {
                    $attempt = (int)$row['attempts'] + 1;
                    $retry = ($code === 0 || $code === 429 || $code >= 500) && $attempt < 8;
                    $delay = self::delay($attempt, max(0, min(86400, (int)($result['retry_after'] ?? 0))));
                    $available = gmdate('Y-m-d H:i:s', time() + $delay);
                    Database::atomic($this->pdo, function () use ($retry, $available, $code, $row) {
                        Database::run($this->pdo, 'UPDATE NotificationOutbox SET status=?,available_at=?,locked_at=NULL,error_code=? WHERE id=?', [$retry ? 'pending' : 'failed', $available, $code === 0 ? 'transport' : "telegram_$code", $row['id']]);
                        if ($code === 429) {
                            Database::run($this->pdo, 'INSERT INTO NotificationDeliveryState(id,blocked_until) VALUES (1,?) ON DUPLICATE KEY UPDATE blocked_until=GREATEST(blocked_until,VALUES(blocked_until))', [$available]);
                        }
                    });
                    if ($code === 429) {
                        break;
                    }
                }
                if ($throttle) {
                    usleep(50000);
                }
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('travel_tour_outbox_worker')");
        }
        return $processed;
    }
}
