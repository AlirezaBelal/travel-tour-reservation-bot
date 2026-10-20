<?php
foreach (['Database','AccessControl','Outbox','BookingService','NotificationWorker','Payments','PrivacyService','Scheduler','TopicCatalogue','ProductBot','WebhookProcessor'] as $class) {
    require_once __DIR__ . "/$class.php";
}
