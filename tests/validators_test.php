<?php

require_once __DIR__ . '/../src/validators.php';

function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAILED: {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

assertSameValue([123, 456], parseAdminIds('123, 456,123,invalid'), 'admin id parsing');
assertSameValue(true, isSensitiveAdminCallback('delete-12'), 'delete callback is admin-only');
assertSameValue(true, isSensitiveAdminCallback('taeed;;1;;topic2;;2|0'), 'approval callback is admin-only');
assertSameValue(false, isSensitiveAdminCallback('join-12'), 'user join callback stays available');
assertSameValue(true, isIranianMobileNumber('+989121234567'), 'Iranian mobile with country code');
assertSameValue(true, isIranianMobileNumber('09121234567'), 'Iranian mobile with local prefix');
assertSameValue(false, isIranianMobileNumber('02112345678'), 'landline rejected');
assertSameValue(false, isValidIranianNationalCode('0000000000'), 'repeated national code rejected');
assertSameValue(false, isValidIranianNationalCode('123'), 'short national code rejected');

fwrite(STDOUT, "Validator tests passed.\n");
