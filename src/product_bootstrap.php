<?php
require_once __DIR__ . '/validators.php';
foreach (['Database','AccessControl','Outbox','BookingService','BookingReportService','NotificationWorker','Payments','PrivacyService','Scheduler','TopicCatalogue','TravelerIdentity','BotSessionStore','NotificationAdminService','TourCatalogService','ProductBot','WebhookProcessor'] as $class) {
    require_once __DIR__ . "/$class.php";
}
