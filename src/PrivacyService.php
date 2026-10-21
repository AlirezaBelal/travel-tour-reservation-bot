<?php

final class PrivacyService
{
    public function __construct(private PDO $pdo)
    {
    }
    public function erase(int $actor): void
    {
        Database::atomic($this->pdo, function () use ($actor) {
            Database::run($this->pdo, 'SELECT UserId FROM Data WHERE UserId=? FOR UPDATE', [$actor]);
            $bookings = Database::run($this->pdo, 'SELECT id,status FROM Bookings WHERE user_id=? ORDER BY tour_id,id FOR UPDATE', [$actor])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bookings as $row) {
                if (in_array($row['status'], ['pending','confirmed'], true)) {
                    throw new RuleViolation('ابتدا رزروهای فعال را لغو کنید؛ سپس حذف حساب را دوباره انجام دهید.');
                }
            }
            foreach ($bookings as $row) {
                Database::run($this->pdo, 'DELETE FROM BookingParticipants WHERE booking_id=?', [$row['id']]);
                Database::run($this->pdo, 'UPDATE BookingStatusHistory SET reason=NULL WHERE booking_id=?', [$row['id']]);
            }
            Database::run($this->pdo, 'UPDATE BookingStatusHistory SET actor_id=NULL WHERE actor_id=?', [$actor]);
            Database::run($this->pdo, 'UPDATE AuditLog SET actor_id=NULL WHERE actor_id=?', [$actor]);
            Database::run($this->pdo, "UPDATE AuditLog SET target_id=NULL WHERE target_type='user' AND target_id=?", [$actor]);
            Database::run($this->pdo, 'DELETE FROM AdminRoles WHERE user_id=?', [$actor]);
            Database::run($this->pdo, "DELETE FROM NotificationOutbox WHERE chat_id=? OR JSON_UNQUOTE(JSON_EXTRACT(payload,'$._subject_user'))=?", [$actor,(string)$actor]);
            // Keep the short-lived ingress counter so account erasure cannot reset spam limits.
            Database::run($this->pdo, 'DELETE FROM Data WHERE UserId=?', [$actor]);
        });
    }
    public function retain(int $days = 180): int
    {
        if ($days < 30 || $days > 3650) {
            throw new InvalidArgumentException('Retention must be 30–3650 days.');
        }
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $users = Database::run($this->pdo, "SELECT UserId FROM Data WHERE updated_at<? AND NOT EXISTS(SELECT 1 FROM Bookings WHERE user_id=Data.UserId AND status IN ('pending','confirmed')) LIMIT 100", [$cutoff])->fetchAll(PDO::FETCH_COLUMN);
        $removed = 0;
        foreach ($users as $user) {
            try {
                $this->erase((int)$user);
                $removed++;
            } catch (RuleViolation) {
            }
        }
        $this->cleanup();
        return $removed;
    }
    public function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM NotificationOutbox WHERE status IN ('sent','failed') AND created_at<UTC_TIMESTAMP()-INTERVAL 7 DAY");
        $this->pdo->exec("DELETE FROM RateLimits WHERE minute_slot < FLOOR(UNIX_TIMESTAMP()/60)-1440");
        $this->pdo->exec("DELETE FROM UpdateReceipts WHERE created_at<UTC_TIMESTAMP()-INTERVAL 30 DAY");
    }
}
