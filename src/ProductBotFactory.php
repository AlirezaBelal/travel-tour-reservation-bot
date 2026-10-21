<?php
declare(strict_types=1);

/** Composition only: no request handling, service lookup or global configuration. */
final class ProductBotFactory
{
    public static function create(PDO $pdo, BookingService $bookings, AccessControl $access, Outbox $outbox,
        PaymentReviewService $payments, string $timezone = 'Asia/Tehran'): ProductBot
    {
        return new ProductBot(
            new TravelerProfileService($pdo, new TravelerIdentity($pdo), new PrivacyService($pdo), $access),
            $bookings, $access, $outbox, new TourCatalogService($pdo, $bookings, $access), $payments,
            new BookingReportService($pdo, $bookings, $access), new BotSessionStore($pdo),
            new NotificationAdminService($pdo, $access, $outbox),
            new BookingQueryService($pdo, $bookings, $access), new TopicCatalogue($pdo), $timezone
        );
    }
}
