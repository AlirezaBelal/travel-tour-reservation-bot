<?php
foreach (['Database','AccessControl','Outbox','BookingService'] as $class) {
    require_once __DIR__ . "/$class.php";
}
