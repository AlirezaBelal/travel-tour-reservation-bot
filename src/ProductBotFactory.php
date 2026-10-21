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
        $inventory = new TourInventory($pdo);
        $tourFlow = new TourController(new TourCatalogService($pdo, $bookings, $access),
            new TourService($pdo, $inventory, $access, $outbox), $inventory,
            new TourCategoryService($pdo, new TopicCatalogue($pdo), $access), $access, $conversation);
        return new ProductBot(
            new TravelerProfileController($profiles, $conversation), $bookings, $access, $conversation,
            $tourFlow, $payments,
            new BookingReportService($pdo, $bookings, $access), new NotificationAdminService($pdo, $access, $outbox),
            new BookingQueryService($pdo, new BookingReader($pdo, $access), $access, $profiles)
        );
    }
}
