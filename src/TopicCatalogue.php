<?php
require_once __DIR__ . '/TransactionCleanup.php';

final class TopicCatalogue
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        $query = $this->pdo->query('SELECT * FROM Topics');
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function children(int $parent): array
    {
        $query = $this->pdo->prepare('SELECT id,Name,`Groups`,caption FROM Topics WHERE `Groups`=?');
        $query->execute([$parent]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

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
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) $this->pdo->beginTransaction();
        try {
            if ($parentId !== 0) {
                $parent = $this->pdo->prepare('SELECT id FROM Topics WHERE id = ? FOR UPDATE');
                $parent->execute([$parentId]);
                if ($parent->fetchColumn() === false) {
                    throw new InvalidArgumentException('Parent topic does not exist.');
                }
            }
            $insert = $this->pdo->prepare('INSERT INTO Topics (Name, `Groups`) VALUES (?, ?)');
            $insert->execute([trim($name), $parentId]);
            $id = (int)$this->pdo->lastInsertId();
            if ($ownTransaction) $this->pdo->commit();
            return $id;
        } catch (Throwable $error) {
            if ($ownTransaction) {
                TransactionCleanup::rollbackIfActive($this->pdo);
            }
            throw $error;
        }
    }

    /** Deletes arbitrary-depth descendants atomically; never executes ALTER TABLE. */
    public function deleteTree(int $topicId): int
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) $this->pdo->beginTransaction();
        try {
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
            if ($ownTransaction) $this->pdo->commit();
            return count($visited);
        } catch (Throwable $error) {
            if ($ownTransaction) {
                TransactionCleanup::rollbackIfActive($this->pdo);
            }
            throw $error;
        }
    }
}
