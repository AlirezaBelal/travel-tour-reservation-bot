<?php
declare(strict_types=1);

/** Legacy category forms with explicit persistence and configured-owner authority. */
final class LegacyTopicService
{
    public function __construct(private PDO $pdo, private TopicCatalogue $catalogue, private array $owners) {}

    public function createRoot(int $actor, int $chat, string $name): void
    {
        $this->requireOwner($actor);
        Database::atomic($this->pdo, function () use ($chat, $name): void {
            $this->catalogue->create($name, 0);
            $this->step($chat, 'defult');
        });
    }

    public function begin(int $actor, int $chat, int $parent): void
    {
        $this->requireOwner($actor);
        if ($parent < 0) {
            throw new InvalidArgumentException('Invalid topic parent.');
        }
        $this->step($chat, "AddTopic-$parent");
    }

    /** Null means another flow; other outcomes are rendered by the Telegram handler. */
    public function submit(int $actor, int $chat, ?string $text): ?string
    {
        return Database::atomic($this->pdo, function () use ($actor, $chat, $text): ?string {
            $step = (string)Database::run($this->pdo,
                'SELECT step FROM Data WHERE UserId=? FOR UPDATE', [$chat])->fetchColumn();
            if (!str_starts_with($step, 'AddTopic-') && !str_starts_with($step, 'caption-')) {
                return null;
            }
            if (!in_array($actor, $this->owners, true) || $text === null) {
                return 'ignored';
            }
            if (str_starts_with($step, 'AddTopic-')) {
                return $this->createChild($chat, $step, $text);
            }
            $id = substr($step, strlen('caption-'));
            if (!ctype_digit($id) || !Database::run($this->pdo,
                'SELECT id FROM Topics WHERE id=?', [$id])->fetchColumn()) {
                return 'missing';
            }
            $this->step($chat, 'defult');
            Database::run($this->pdo, 'UPDATE Topics SET caption=? WHERE id=?', [$text, $id]);
            return 'saved';
        });
    }

    public function reset(int $chat): void
    {
        $this->step($chat, 'defult');
    }

    public function beginDelete(int $actor, int $chat, int $topic): void
    {
        $this->requireOwner($actor);
        $this->step($chat, "deleteConfirm-$topic");
    }

    public function confirmDelete(int $actor, int $chat, int $topic): ?int
    {
        $this->requireOwner($actor);
        return Database::atomic($this->pdo, function () use ($chat, $topic): ?int {
            $step = Database::run($this->pdo, 'SELECT step FROM Data WHERE UserId=? FOR UPDATE', [$chat])->fetchColumn();
            if ($step !== "deleteConfirm-$topic") return null;
            $this->reset($chat);
            try {
                return $this->catalogue->deleteTree($topic);
            } catch (PDOException $error) {
                if (($error->errorInfo[1] ?? null) === 1451) {
                    throw new RuleViolation('این دسته یا زیرمجموعه آن به تور مرتبط است؛ ابتدا دسته تور را تغییر دهید.');
                }
                throw $error;
            }
        });
    }

    private function createChild(int $chat, string $step, string $text): string
    {
        if (trim($text) === '') {
            return 'ignored';
        }
        $parent = substr($step, strlen('AddTopic-'));
        if (!ctype_digit($parent)) {
            $this->step($chat, 'defult');
            return 'ignored';
        }
        $id = $this->catalogue->create(trim($text), (int)$parent);
        $this->step($chat, "caption-$id");
        return 'caption';
    }

    private function step(int $chat, string $step): void
    {
        Database::run($this->pdo, 'UPDATE Data SET step=? WHERE UserId=?', [$step, $chat]);
    }

    private function requireOwner(int $actor): void
    {
        if (!in_array($actor, $this->owners, true)) {
            throw new RuleViolation('اجازه انجام این عملیات را ندارید.');
        }
    }
}
