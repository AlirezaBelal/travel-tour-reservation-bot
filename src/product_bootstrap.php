<?php
require_once __DIR__ . '/validators.php';
foreach (['HttpIngress','Database','AccessControl','Outbox','PaymentRules','PaymentReviewService','TourRules','TourInventory','TourService','BookingService','BookingServiceFactory','BookingReportService','NotificationWorker','Payments','PrivacyService','Scheduler','TopicCatalogue','LegacyTopicService','LegacyMembershipService','LegacyBroadcastService','LegacyUpdate','LegacyOutboxTransport','LegacyResponder','TravelerIdentity','BotSessionStore','NotificationAdminService','TourCatalogService','TravelerProfileService','BookingQueryService','BotConversation','TravelerProfileController','ProductBot','ProductBotFactory','WebhookProcessor'] as $class) {
    require_once __DIR__ . "/$class.php";
}
