<?php

declare(strict_types=1);

/** Composition for current runtime; BookingService's old constructor remains compatible. */
final class BookingServiceFactory
{
    public static function create(PDO $pdo, AccessControl $access, Outbox $outbox, PaymentReviewService $payments, bool $requireVerifiedProfile): BookingService
    {
        $inventory = new TourInventory($pdo);
        $tours = new TourService($pdo, $inventory, $access, $outbox);
        $reader = new BookingReader($pdo, $access);
        $history = new BookingHistoryStore($pdo);
        $reservations = new ReservationService(
            $pdo,
            $inventory,
            $access,
            $outbox,
            new TravelerIdentity($pdo),
            $history,
            static fn (): bool => $requireVerifiedProfile
        );
        $lifecycle = new BookingLifecycleService($pdo, $inventory, $reader, $access, $outbox, $history);
        return new BookingService($pdo, $access, $outbox, $payments, $inventory, $tours, $reader, $reservations, $lifecycle);
    }
}
