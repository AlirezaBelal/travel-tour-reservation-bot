<?php

require_once __DIR__ . '/Database.php';

/**
 * @phpstan-type Topic array{
 *     id: int|numeric-string,
 *     Name: string,
 *     Groups: int|numeric-string,
 *     caption: string|null,
 * }
 */
final class TopicCatalogue
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<Topic>
     */
    public function all(): array
    {
        $query = $this->pdo->query('SELECT * FROM Topics');
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<Topic>
     */
    public function children(int $parent): array
    {
        $query = $this->pdo->prepare('SELECT id,Name,`Groups`,caption FROM Topics WHERE `Groups`=?');
        $query->execute([$parent]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return Topic|null
     */
    public function topic(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT id,Name,`Groups`,caption FROM Topics WHERE id=?');
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function create(string $name, int $parentId): int
    {
        if (trim($name) === '' || strlen($name) > 300 || $parentId < 0) {
            throw new InvalidArgumentException('Invalid topic.');
        }
        return Database::atomic($this->pdo, function () use ($name, $parentId): int {
            if ($parentId !== 0) {
                $parent = $this->pdo->prepare('SELECT id FROM Topics WHERE id = ? FOR UPDATE');
                $parent->execute([$parentId]);
                if ($parent->fetchColumn() === false) {
                    throw new InvalidArgumentException('Parent topic does not exist.');
                }
            }
            $insert = $this->pdo->prepare('INSERT INTO Topics (Name, `Groups`) VALUES (?, ?)');
            $insert->execute([trim($name), $parentId]);
            return (int)$this->pdo->lastInsertId();
        });
    }

    /** Deletes arbitrary-depth descendants atomically; never executes ALTER TABLE. */
    public function deleteTree(int $topicId): int
    {
        return Database::atomic($this->pdo, function () use ($topicId): int {
            $topics = $this->pdo->query('SELECT id, `Groups` FROM Topics ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            $children = [];
            $exists = false;
            foreach ($topics as $topic) {
                $children[(int)$topic['Groups']][] = (int)$topic['id'];
                $exists = $exists || (int)$topic['id'] === $topicId;
            }
            $pending = $exists ? [$topicId] : [];
            $visited = [];
            while ($pending !== []) {
                $id = array_pop($pending);
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                foreach ($children[$id] ?? [] as $child) {
                    $pending[] = $child;
                }
            }
            $delete = $this->pdo->prepare('DELETE FROM Topics WHERE id = ?');
            foreach (array_keys($visited) as $id) {
                $delete->execute([$id]);
            }
            return count($visited);
        });
    }
}
