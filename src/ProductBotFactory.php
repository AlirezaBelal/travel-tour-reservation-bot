<?php
declare(strict_types=1);

/** Composition only: no request handling, service lookup or global configuration. */
final class ProductBotFactory
{
    public static function create(PDO $pdo, BookingService $bookings, AccessControl $access, Outbox $outbox,
        PaymentReviewService $payments, string $timezone = 'Asia/Tehran'): ProductBot
    {
        $profiles = new TravelerProfileService($pdo, new TravelerIdentity($pdo), new PrivacyService($pdo), $access);
        $conversation = new BotConversation($outbox, new BotSessionStore($pdo), $access, $timezone);
        return new ProductBot(
            new TravelerProfileController($profiles, $conversation), $bookings, $access, $conversation,
            new TourCatalogService($pdo, $bookings, $access), $payments,
            new BookingReportService($pdo, $bookings, $access), new NotificationAdminService($pdo, $access, $outbox),
            new BookingQueryService($pdo, $bookings, $access, $profiles), new TopicCatalogue($pdo)
        );
    }
}
