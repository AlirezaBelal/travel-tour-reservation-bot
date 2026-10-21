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
