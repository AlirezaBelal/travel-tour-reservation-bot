<?php

declare(strict_types=1);

/**
 * Backward-compatible API facade; the current runtime injects all write use cases explicitly.
 * @phpstan-import-type Tour from TourInventory
 * @phpstan-import-type Booking from BookingReader
 */
final class BookingService
{
    private PaymentReviewService $paymentReview;
    private TourInventory $inventory;
    private TourService $tours;
    private BookingReader $reader;
    private ReservationService $reservations;
    private BookingLifecycleService $lifecycle;

    public function __construct(
        PDO $pdo,
        AccessControl $access,
        Outbox $outbox,
        ?PaymentReviewService $paymentReview = null,
        ?TourInventory $inventory = null,
        ?TourService $tours = null,
        ?BookingReader $reader = null,
        ?ReservationService $reservations = null,
        ?BookingLifecycleService $lifecycle = null
    ) {
        $this->paymentReview = $paymentReview ?? new PaymentReviewService($pdo, $access, $outbox);
        $this->inventory = $inventory ?? new TourInventory($pdo);
        $this->tours = $tours ?? new TourService($pdo, $this->inventory, $access, $outbox);
        $this->reader = $reader ?? new BookingReader($pdo, $access);
        if ($reservations === null || $lifecycle === null) {
            $history = new BookingHistoryStore($pdo);
            $reservations ??= new ReservationService(
                $pdo,
                $this->inventory,
                $access,
                $outbox,
                new TravelerIdentity($pdo),
                $history,
                LegacyProfileRequirement::policy()
            );
            $lifecycle ??= new BookingLifecycleService($pdo, $this->inventory, $this->reader, $access, $outbox, $history);
        }
        $this->reservations = $reservations;
        $this->lifecycle = $lifecycle;
    }
    public function ensureUser(int $id): void
    {
        $this->reservations->ensureUser($id);
    }
    public function used(int $tour): int
    {
        return $this->inventory->used($tour);
    }
    /**
     * @return Tour
     */
    public function tour(int $id, bool $lock = false): array
    {
        return $this->inventory->tour($id, $lock);
    }
    /**
     * @param array<string, mixed> $values
     */
    public function saveTour(int $actor, array $values, ?int $id = null): int
    {
        return $this->tours->saveTour($actor, $values, $id);
    }
    public function publication(int $actor, int $id, string $publication): int
    {
        return $this->tours->publication($actor, $id, $publication);
    }
    public function reserve(int $actor, int $tourId, int $quantity, string $requestKey, ?int $expectedVersion = null): int
    {
        return $this->reservations->reserve($actor, $tourId, $quantity, $requestKey, $expectedVersion);
    }
    /**
     * @return Booking
     */
    public function booking(int $actor, int $id, bool $administrative = false): array
    {
        return $this->reader->booking($actor, $id, $administrative);
    }
    public function transition(int $actor, int $id, string $status, ?string $reason = null, bool $admin = false): bool
    {
        return $this->lifecycle->transition($actor, $id, $status, $reason, $admin);
    }
    public function manualPayment(int $actor, int $id, string $reference): bool
    {
        return $this->paymentReview->record($actor, $id, $reference);
    }
}
