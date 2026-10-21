<?php

final class AccessControl
{
    public function __construct(private PDO $pdo, private array $owners)
    {
    }

    public function role(int $actor): ?string
    {
        if (in_array($actor, $this->owners, true)) {
            return 'owner';
        }
        $role = Database::run($this->pdo, 'SELECT role FROM AdminRoles WHERE user_id = ?', [$actor])->fetchColumn();
        return $role === false ? null : $role;
    }

    public function allows(int $actor, string $permission): bool
    {
        $role = $this->role($actor);
        return $role === 'owner' || in_array($permission, [
            'manager' => ['tours', 'review', 'reports', 'export', 'notify', 'payments', 'profiles'],
            'reviewer' => ['review', 'reports'],
            'reporter' => ['reports'],
        ][$role ?? ''] ?? [], true);
    }

    public function require(int $actor, string $permission): void
    {
        if (!$this->allows($actor, $permission)) {
            throw new RuleViolation('اجازه انجام این عملیات را ندارید.');
        }
    }

    public function assign(int $actor, int $target, string $role): void
    {
        $this->require($actor, 'roles');
        if ($target < 1 || in_array($target, $this->owners, true) || !in_array($role, ['manager', 'reviewer', 'reporter', 'none'], true)) {
            throw new RuleViolation('نقش یا شناسه مدیر معتبر نیست. مدیر اصلی از تنظیمات سرور مدیریت می‌شود.');
        }
        Database::atomic($this->pdo, function () use ($actor, $target, $role) {
            Database::run($this->pdo, 'DELETE FROM AdminRoles WHERE user_id = ?', [$target]);
            if ($role !== 'none') {
                Database::run($this->pdo, 'INSERT INTO AdminRoles(user_id,role) VALUES (?,?)', [$target, $role]);
            }
            $this->audit($actor, 'role.' . $role, 'user', $target);
        });
    }

    public function audit(int $actor, string $action, string $type, int $id): void
    {
        Database::run($this->pdo, 'INSERT INTO AuditLog(actor_id,action,target_type,target_id) VALUES (?,?,?,?)', [$actor, $action, $type, $id]);
    }

    public function reviewers(): array
    {
        $ids = Database::run($this->pdo, "SELECT user_id FROM AdminRoles WHERE role IN ('manager','reviewer')")->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_merge($this->owners, array_map('intval', $ids))));
    }
}
