<?php

declare(strict_types=1);

/**
 * Identity-free booking lists/history with authorization independent of Telegram.
 * @phpstan-import-type Booking from BookingReader
 */
final class BookingQueryService
{
    public function __construct(private PDO $pdo, private BookingReader $bookings, private AccessControl $access, private TravelerProfileService $profiles)
    {
    }

    /**
     * @return Booking
     */
    public function detail(int $actor, int $id, bool $admin = false): array
    {
        $booking = $this->bookings->booking($actor, $id, $admin);
        $booking['leader_name'] = null;
        if ($admin && $booking['user_id'] !== null && $this->access->allows($actor, 'profiles')) {
            $booking['leader_name'] = $this->profiles->leaderForReview($actor, (int)$booking['user_id']);
        }
        return $booking;
    }

    /**
     * @return list<array{id: int|numeric-string, status: string}>
     */
    public function mine(int $actor, int $offset): array
    {
        $this->page($offset);
        return Database::run(
            $this->pdo,
            "SELECT id,status FROM Bookings WHERE user_id=? ORDER BY id DESC LIMIT 10 OFFSET $offset",
            [$actor]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{new_status: string, created_at: string}>
     */
    public function history(int $actor, int $id, bool $admin = false): array
    {
        $this->bookings->booking($actor, $id, $admin);
        return Database::run(
            $this->pdo,
            'SELECT new_status,created_at FROM BookingStatusHistory WHERE booking_id=? ORDER BY id DESC LIMIT 20',
            [$id]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int|numeric-string, tour_id: int|numeric-string, quantity: int|numeric-string}>
     */
    public function pending(int $actor, int $offset): array
    {
        $this->access->require($actor, 'review');
        $this->page($offset);
        return Database::run(
            $this->pdo,
            "SELECT id,tour_id,quantity FROM Bookings WHERE status='pending' ORDER BY id LIMIT 10 OFFSET $offset"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int|numeric-string, status: string, quantity: int|numeric-string}>
     */
    public function all(int $actor, int $offset, ?int $tour = null): array
    {
        $this->access->require($actor, 'review');
        $this->page($offset);
        $where = $tour === null ? '1=1' : 'tour_id=?';
        return Database::run(
            $this->pdo,
            "SELECT id,status,quantity FROM Bookings WHERE $where ORDER BY id DESC LIMIT 10 OFFSET $offset",
            $tour === null ? [] : [$tour]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function page(int $offset): void
    {
        if ($offset < 0 || $offset > 100000) {
            throw new RuleViolation('صفحه معتبر نیست.');
        }
    }
}
