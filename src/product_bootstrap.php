<?php
require_once __DIR__ . '/validators.php';
foreach (['Database','AccessControl','Outbox','PaymentRules','PaymentReviewService','BookingService','BookingReportService','NotificationWorker','Payments','PrivacyService','Scheduler','TopicCatalogue','LegacyTopicService','TravelerIdentity','BotSessionStore','NotificationAdminService','TourCatalogService','ProductBot','WebhookProcessor'] as $class) {
    require_once __DIR__ . "/$class.php";
}
