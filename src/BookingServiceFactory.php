<?php
declare(strict_types=1);

/** Composition for current runtime; BookingService's old constructor remains compatible. */
final class BookingServiceFactory
{
    public static function create(PDO $pdo, AccessControl $access, Outbox $outbox, PaymentReviewService $payments): BookingService
    {
        $inventory = new TourInventory($pdo);
        $tours = new TourService($pdo, $inventory, $access, $outbox);
        return new BookingService($pdo, $access, $outbox, $payments, $inventory, $tours);
    }
}
