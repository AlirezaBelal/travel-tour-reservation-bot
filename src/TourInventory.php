<?php

declare(strict_types=1);

/**
 * Shared tour row/capacity locking boundary, using the caller's PDO transaction.
 * @phpstan-type Tour array{
 *     id: int|numeric-string,
 *     category_id: int|numeric-string|null,
 *     title: string,
 *     destination: string,
 *     description: string,
 *     starts_at: string,
 *     ends_at: string,
 *     price: int|numeric-string,
 *     capacity: int|numeric-string,
 *     publication: string,
 *     version: int|numeric-string,
 *     created_at: string,
 * }
 */
final class TourInventory
{
    public function __construct(private PDO $pdo)
    {
    }
    public function used(int $tour): int
    {
        return array_sum(array_map('intval', Database::run($this->pdo, "SELECT quantity FROM Bookings WHERE tour_id=? AND status IN ('pending','confirmed','completed') FOR UPDATE", [$tour])->fetchAll(PDO::FETCH_COLUMN)));
    }
    /**
     * @return Tour
     */
    public function tour(int $id, bool $lock = false): array
    {
        $tour = Database::run($this->pdo, 'SELECT * FROM Tours WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$tour) {
            throw new RuleViolation('تور موردنظر پیدا نشد.');
        }
        return $tour;
    }
}
