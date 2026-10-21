<?php
declare(strict_types=1);

/** Owner-authorized legacy recipient selection and consumption of its persisted form. */
final class LegacyBroadcastService
{
    public function __construct(private PDO $pdo, private TopicMemberships $memberships, private array $owners) {}

    public function begin(int $actor, int $chat, string $selection): void
    {
        if (!in_array($actor, $this->owners, true)) {
            throw new RuleViolation('اجازه انجام این عملیات را ندارید.');
        }
        Database::run($this->pdo, 'UPDATE Data SET step=? WHERE UserId=?', [$selection, $chat]);
    }

    /** @return array{authorized: bool, recipients: list<int>}|null */
    public function consume(int $actor, int $chat): ?array
    {
        return Database::atomic($this->pdo, function () use ($actor, $chat): ?array {
            $step = (string)Database::run($this->pdo, 'SELECT step FROM Data WHERE UserId=? FOR UPDATE', [$chat])->fetchColumn();
            if (!str_starts_with($step, 'send')) return null;
            Database::run($this->pdo, "UPDATE Data SET step='defult' WHERE UserId=?", [$chat]);
            if (!in_array($actor, $this->owners, true)) {
                return ['authorized' => false, 'recipients' => []];
            }
            $target = substr($step, strlen('send'));
            $recipients = [];
            if ($target === 'All') {
                $recipients = Database::run($this->pdo, "SELECT UserId FROM Data WHERE profile='true'")->fetchAll(PDO::FETCH_COLUMN);
            } elseif (ctype_digit($target)) {
                $recipients = $this->memberships->approvedUsers($target);
            }
            return ['authorized' => true, 'recipients' => array_map('intval', $recipients)];
        });
    }
}
