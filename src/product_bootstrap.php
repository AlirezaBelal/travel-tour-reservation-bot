<?php
require_once __DIR__ . '/validators.php';
foreach (['Database','AccessControl','Outbox','BookingService','NotificationWorker','Payments','PrivacyService','Scheduler','TopicCatalogue','TravelerIdentity','ProductBot','WebhookProcessor'] as $class) {
    require_once __DIR__ . "/$class.php";
}
