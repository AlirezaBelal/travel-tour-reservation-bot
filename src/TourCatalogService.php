<?php

declare(strict_types=1);

/**
 * Authorized catalogue read model, independent of Telegram and conversation state.
 * It shares the caller's connection/transaction; it never commits or sends messages.
 */
final class TourCatalogService
{
    public function __construct(
        private PDO $pdo,
        private BookingService $bookings,
        private AccessControl $access
    ) {
    }

    /** @return list<array{id: mixed, title: string}> */
    public function tours(int $actor, int $offset, ?int $category = null, bool $admin = false, ?string $query = null): array
    {
        $this->page($offset, 'شماره صفحه معتبر نیست.');
        if ($admin) {
            $this->access->require($actor, 'tours');
        }
        $args = [];
        $where = $admin ? '1=1' : "publication='published' AND starts_at>UTC_TIMESTAMP()";
        if ($category !== null) {
            $where .= ' AND category_id=?';
            $args[] = $category;
        }
        if (!$admin && $query !== null) {
            $where .= ' AND (LOCATE(?,title)>0 OR LOCATE(?,destination)>0)';
            $args[] = $query;
            $args[] = $query;
        }
        return Database::run(
            $this->pdo,
            "SELECT id,title FROM Tours WHERE $where ORDER BY starts_at,id LIMIT 10 OFFSET $offset",
            $args
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{id: mixed, Name: string}> */
    public function categories(int $parent, int $offset): array
    {
        $this->page($offset, 'صفحه معتبر نیست.');
        return Database::run(
            $this->pdo,
            "SELECT id,Name FROM Topics WHERE `Groups`=? ORDER BY id LIMIT 20 OFFSET $offset",
            [$parent]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{tour: array, remaining: int} */
    public function detail(int $actor, int $id): array
    {
        $tour = $this->bookings->tour($id);
        if ($tour['publication'] !== 'published' && !$this->access->allows($actor, 'tours')) {
            $ownsBooking = Database::run(
                $this->pdo,
                'SELECT id FROM Bookings WHERE user_id=? AND tour_id=?',
                [$actor, $id]
            )->fetchColumn();
            if (!$ownsBooking) {
                throw new RuleViolation('این تور منتشر نشده است.');
            }
        }
        return ['tour' => $tour, 'remaining' => (int)$tour['capacity'] - $this->bookings->used($id)];
    }

    private function page(int $offset, string $message): void
    {
        if ($offset < 0 || $offset > 100000) {
            throw new RuleViolation($message);
        }
    }
}
