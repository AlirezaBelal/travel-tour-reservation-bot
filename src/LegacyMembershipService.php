<?php

declare(strict_types=1);

/** Legacy membership/profile eligibility and owner approvals without Telegram helpers. */
final class LegacyMembershipService
{
    /**
     * @param list<int> $owners
     */
    public function __construct(
        private PDO $pdo,
        private TopicMemberships $memberships,
        private TopicCatalogue $catalogue,
        private array $owners
    ) {
    }

    /**
     * @return array{UserId: int|numeric-string, profile: string|null, step: string|null}|null
     */
    public function user(int $actor): ?array
    {
        $user = Database::run($this->pdo, 'SELECT UserId,profile,step FROM Data WHERE UserId=?', [$actor])->fetch(PDO::FETCH_ASSOC);
        return $user === false ? null : $user;
    }

    public function ensureUser(int $actor): void
    {
        Database::run($this->pdo, "INSERT INTO Data(UserId,step) VALUES (?,'defult') ON DUPLICATE KEY UPDATE UserId=UserId", [$actor]);
    }

    public function status(int $actor, int|string $topic): ?string
    {
        return $this->memberships->status($actor, $topic);
    }

    public function eligible(int $actor, int|string $topic): bool
    {
        return ($this->user($actor)['profile'] ?? null) === 'true' && $this->catalogue->topic((int)$topic) !== null;
    }

    public function request(int $actor, int|string $topic): bool
    {
        if (!$this->eligible($actor, $topic) || $this->catalogue->children((int)$topic) !== []) {
            return false;
        }
        return $this->memberships->request($actor, $topic);
    }

    public function path(int|string $topic): string
    {
        $names = [];
        for ($guard = 0; $guard < 100; $guard++) {
            $row = $this->catalogue->topic((int)$topic);
            if ($row === null) {
                break;
            }
            $names[] = $row['Name'];
            $topic = $row['Groups'];
            if ((int)$topic === 0) {
                break;
            }
        }
        return implode(' 👉 ', $names);
    }

    public function approve(int $actor, int|string $target, int|string $topic): ?string
    {
        if (!in_array($actor, $this->owners, true)) {
            throw new RuleViolation('اجازه انجام این عملیات را ندارید.');
        }
        if (!$this->memberships->approve($target, $topic)) {
            return null;
        }
        return (string)($this->catalogue->topic((int)$topic)['Name'] ?? '');
    }
}
