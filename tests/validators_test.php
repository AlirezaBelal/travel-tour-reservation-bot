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
assertSameValue(true, isIranianMobileNumber('+98 (912) 123-4567'), 'formatted mobile accepted');
assertSameValue('+989121234567', normalizeIranianMobile('+98 (912) 123-4567'), 'formatted mobile canonicalized');
foreach (['prefix09121234567', '09121234567suffix', "09121234567\n", "0912\r1234567", '09121234567<script>', '+98+9121234567', str_repeat(' ', 25) . '09121234567'] as $malformedPhone) {
    assertSameValue(false, isIranianMobileNumber($malformedPhone), 'phone with injected text/control or excessive length rejected');
    try {
        normalizeIranianMobile($malformedPhone);
        throw new RuntimeException('Invalid mobile normalized unexpectedly.');
    } catch (InvalidArgumentException) {
        // A normalization helper must share the exact phone validation contract.
    }
}
assertSameValue(false, isVerifiedSharedContact(['phone_number'=>'prefix09121234567','user_id'=>123], 123), 'own contact with injected text rejected');
assertSameValue(false, isVerifiedSharedContact(['phone_number'=>"09121234567\n",'user_id'=>123], 123), 'own contact with newline rejected');

assertSameValue(true, isVerifiedSharedContact(['phone_number'=>'+989121234567','user_id'=>123],123), 'own shared contact accepted');
assertSameValue(false, isVerifiedSharedContact(['phone_number'=>'09121234567','user_id'=>124],123), 'foreign contact rejected');
assertSameValue(false, isVerifiedSharedContact(['phone_number'=>'09121234567'],123), 'unattributed contact rejected');
assertSameValue(false, isVerifiedSharedContact(['phone_number'=>'02112345678','user_id'=>123],123), 'non mobile contact rejected');
assertSameValue('+989121234567',normalizeIranianMobile('09121234567'),'mobile canonicalized');
assertSameValue(true,isValidIranianNationalCode('0000000019'),'synthetic checksum-valid national code');
assertSameValue(false, isValidIranianNationalCode('0000000000'), 'repeated national code rejected');
assertSameValue(false, isValidIranianNationalCode('123'), 'short national code rejected');
foreach (["0000000019\n", "0000000019\r", "0000000019extra", " 0000000019", "0000000019 "] as $malformedNationalCode) {
    assertSameValue(false, isValidIranianNationalCode($malformedNationalCode), 'national code rejects trailing newline, whitespace or appended text');
}


assertSameValue(true, isSensitiveAdminCallback('approve:101:4'), 'short approval is admin-only');
assertSameValue(true, isSensitiveAdminCallback('confirmDelete-4'), 'deletion confirmation is admin-only');

assertSameValue([123], parseAdminIds('0,-1,999999999999999999999,123'), 'reject unusable administrator IDs');

foreach (['name','contact','NationalCode','sex','birthday'] as $step) assertSameValue(true, isLegacyProfileStep($step), 'retired profile state');
foreach ([null,false,'defult','tour_title','send'] as $step) assertSameValue(false, isLegacyProfileStep($step), 'healthy state preserved');

fwrite(STDOUT, "Validator tests passed.\n");
