<?php
declare(strict_types=1);
require __DIR__ . '/../src/PaymentRules.php';
$checks = 0;
foreach (['abc', 'SYNTHETIC-RECEIPT_01', str_repeat('x', 80)] as $valid) {
    if (!PaymentRules::isManualReference($valid)) {
        throw new RuntimeException('Valid synthetic payment reference rejected.');
    }
    $checks++;
}
foreach (['', 'ab', str_repeat('x', 81), 'a b', "abc\n", 'abc/def', "abc'", '۱۲۳'] as $invalid) {
    if (PaymentRules::isManualReference($invalid)) {
        throw new RuntimeException('Invalid synthetic payment reference accepted.');
    }
    $checks++;
}
fwrite(STDOUT, "Payment rules passed ($checks checks).\n");
