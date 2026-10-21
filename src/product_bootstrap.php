<?php
require_once __DIR__ . '/validators.php';
foreach (['Database','AccessControl','Outbox','PaymentRules','PaymentReviewService','TourRules','TourInventory','TourService','BookingService','BookingServiceFactory','BookingReportService','NotificationWorker','Payments','PrivacyService','Scheduler','TopicCatalogue','LegacyTopicService','TravelerIdentity','BotSessionStore','NotificationAdminService','TourCatalogService','TravelerProfileService','BookingQueryService','ProductBot','ProductBotFactory','WebhookProcessor'] as $class) {
    require_once __DIR__ . "/$class.php";
}
