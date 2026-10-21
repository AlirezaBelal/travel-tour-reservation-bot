<?php

/** Persistent per-user form state; independent from legacy Data.step. */
final class BotSessionStore
{
    private const TTL_SECONDS = 1800;

    public function __construct(private PDO $pdo)
    {
    }

    public function put(int $actor, string $state, array $payload): void
    {
        Database::run(
            $this->pdo,
            'INSERT INTO BotSessions(user_id,state,payload) VALUES (?,?,?) ' .
                'ON DUPLICATE KEY UPDATE state=VALUES(state),payload=VALUES(payload),updated_at=UTC_TIMESTAMP()',
            [$actor, $state, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]
        );
    }

    public function get(int $actor): ?array
    {
        $row = Database::run(
            $this->pdo,
            'SELECT * FROM BotSessions WHERE user_id=?',
            [$actor]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ($row['updated_at'] < gmdate('Y-m-d H:i:s', time() - self::TTL_SECONDS)) {
            $this->clear($actor);
            return null;
        }
        return $row;
    }

    public function require(int $actor, string $state): array
    {
        $session = $this->get($actor);
        if (!$session || $session['state'] !== $state) {
            throw new RuleViolation('فرم منقضی شده است؛ دوباره از منو شروع کنید.');
        }
        return json_decode($session['payload'], true, 512, JSON_THROW_ON_ERROR);
    }

    public function clear(int $actor): void
    {
        Database::run($this->pdo, 'DELETE FROM BotSessions WHERE user_id=?', [$actor]);
    }
}
