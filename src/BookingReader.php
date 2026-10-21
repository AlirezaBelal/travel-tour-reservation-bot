<?php

declare(strict_types=1);

/** Ownership/role checked booking snapshot, independent of write use cases. */
final class BookingReader
{
    public function __construct(private PDO $pdo, private AccessControl $access)
    {
    }
    public function booking(int $actor, int $id, bool $administrative = false): array
    {
        if ($administrative) {
            $this->access->require($actor, 'review');
        }
        $booking = Database::run($this->pdo, 'SELECT b.*,t.title,t.starts_at,t.ends_at,p.status AS payment_status,p.amount,p.order_ref,p.method FROM Bookings b JOIN Tours t ON t.id=b.tour_id JOIN Payments p ON p.booking_id=b.id WHERE b.id=?' . ($administrative ? '' : ' AND b.user_id=?'), $administrative ? [$id] : [$id,$actor])->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            throw new RuleViolation('رزرو پیدا نشد یا به حساب شما تعلق ندارد.');
        }
        return $booking;
    }
}
