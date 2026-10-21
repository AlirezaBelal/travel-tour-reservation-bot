<?php
declare(strict_types=1);
require __DIR__ . '/../src/RuleViolation.php';
require __DIR__ . '/../src/Payments.php';
require __DIR__ . '/../src/NotificationWorker.php';
if (!interface_exists(PaymentAdapter::class) || !class_exists(SandboxPaymentAdapter::class)
    || !class_exists(PaymentService::class) || !class_exists(NotificationWorker::class)
    || !class_exists(TelegramTransport::class)) {
    throw new RuntimeException('Historical includes no longer expose their public types.');
}
$adapter = new SandboxPaymentAdapter(str_repeat('s', 40));
$event = ['order_ref' => str_repeat('a', 32), 'transaction' => 'SYNTHETIC-LOADER',
    'amount' => 0, 'status' => 'paid', 'timestamp' => time()];
$event['signature'] = $adapter->signature($event);
if ($adapter->verify($event) !== $event) {
    throw new RuntimeException('Class separation changed the local sandbox contract.');
}
fwrite(STDOUT, "Compatibility includes passed: payment and worker types, no network.\n");
