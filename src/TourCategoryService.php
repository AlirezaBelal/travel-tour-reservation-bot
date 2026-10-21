<?php

declare(strict_types=1);

/** Current product category mutations, authorized/audited independently of the UI. */
final class TourCategoryService
{
    public function __construct(private PDO $pdo, private TopicCatalogue $catalogue, private AccessControl $access)
    {
    }

    public function create(int $actor, string $name, int $parent): int
    {
        $this->access->require($actor, 'tours');
        return Database::atomic($this->pdo, function () use ($actor, $name, $parent): int {
            $id = $this->catalogue->create($name, $parent);
            $this->access->audit($actor, 'category.create', 'category', $id);
            return $id;
        });
    }

    public function delete(int $actor, int $id): int
    {
        $this->access->require($actor, 'tours');
        return Database::atomic($this->pdo, function () use ($actor, $id): int {
            try {
                $count = $this->catalogue->deleteTree($id);
            } catch (PDOException $error) {
                if (($error->errorInfo[1] ?? null) === 1451) {
                    throw new RuleViolation('دسته به تور مرتبط است؛ ابتدا دسته تور را تغییر دهید.');
                }
                throw $error;
            }
            $this->access->audit($actor, 'category.delete', 'category', $id);
            return $count;
        });
    }
}
