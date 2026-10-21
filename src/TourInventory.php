<?php
declare(strict_types=1);

/** Shared tour row/capacity locking boundary, using the caller's PDO transaction. */
final class TourInventory
{
    public function __construct(private PDO $pdo) {}
    public function used(int $tour): int
    {
        return array_sum(array_map('intval', Database::run($this->pdo, "SELECT quantity FROM Bookings WHERE tour_id=? AND status IN ('pending','confirmed','completed') FOR UPDATE", [$tour])->fetchAll(PDO::FETCH_COLUMN)));
    }
    public function tour(int $id, bool $lock = false): array
    {
        $tour = Database::run($this->pdo, 'SELECT * FROM Tours WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$tour) {
            throw new RuleViolation('تور موردنظر پیدا نشد.');
        }
        return $tour;
    }
}
