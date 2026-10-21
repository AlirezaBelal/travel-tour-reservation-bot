<?php

/**
 * Role-checked notification operations, independent of Telegram menu rendering.
 * The outbox records durable send intent in the caller's owning transaction.
 */
final class NotificationAdminService
{
    public function __construct(
        private PDO $pdo,
        private AccessControl $access,
        private Outbox $outbox
    ) {
    }

    public function retry(int $actor, int $id): void
    {
        $this->outbox->retry($actor, $id, $this->access);
    }

    public function overview(int $actor): array
    {
        $this->access->require($actor, 'notify');

        $statuses = Database::run(
            $this->pdo,
            'SELECT status,COUNT(*) total FROM NotificationOutbox GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC);
        $failed = Database::run(
            $this->pdo,
            "SELECT id,error_code FROM NotificationOutbox WHERE status='failed' ORDER BY id DESC LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['statuses' => $statuses, 'failed' => $failed];
    }

    public function validateTarget(int $actor, int $tour): void
    {
        $this->access->require($actor, 'notify');
        if ($tour !== 0 && !Database::run($this->pdo, 'SELECT id FROM Tours WHERE id=?', [$tour])->fetchColumn()) {
            throw new RuleViolation('تور موردنظر پیدا نشد.');
        }
    }

    public function broadcast(int $actor, int $update, int $tour, string $text): int
    {
        $this->validateTarget($actor, $tour);
        $after = 0;
        $count = 0;

        do {
            if ($tour === 0) {
                $rows = Database::run(
                    $this->pdo,
                    'SELECT UserId AS id FROM Data WHERE UserId>? ORDER BY UserId LIMIT 200',
                    [$after]
                )->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $rows = Database::run(
                    $this->pdo,
                    "SELECT DISTINCT user_id AS id FROM Bookings " .
                        "WHERE tour_id=? AND user_id>? AND status IN ('pending','confirmed') " .
                        "ORDER BY user_id LIMIT 200",
                    [$tour, $after]
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            foreach ($rows as $row) {
                $after = (int)$row['id'];
                $this->outbox->enqueue(
                    $after,
                    'sendMessage',
                    [
                        'text' => $text,
                        '_permission_actor' => $actor,
                        '_required_permissions' => ['notify'],
                    ],
                    "broadcast:$update:$after"
                );
                $count++;
            }
        } while (count($rows) === 200);

        $this->access->audit($actor, 'notification.broadcast', 'tour', $tour);
        return $count;
    }
}
