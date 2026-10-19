<?php
require __DIR__ . '/../src/Database.php';
$checks = 0;
function unitCheck(bool $condition): void {
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException('Business rule check failed.'); }
}
foreach ([0,-1,21,PHP_INT_MAX] as $qty) {
    try { BookingRules::quantity($qty); throw new RuntimeException('Invalid quantity accepted.'); }
    catch (RuleViolation) { unitCheck(true); }
}
foreach ([1,20] as $qty) { BookingRules::quantity($qty); unitCheck(true); }
foreach (['confirmed','rejected','cancelled'] as $new) { unitCheck(BookingRules::transition('pending',$new)); }
unitCheck(BookingRules::transition('confirmed','completed'));
foreach (['rejected','cancelled','completed'] as $old) { unitCheck(!BookingRules::transition($old,'confirmed')); }
unitCheck(!BookingRules::transition('pending','completed'));
foreach (['=1+1',' +SUM(A1)','@cmd',"\tformula",'-1'] as $value) { unitCheck(str_starts_with(BookingRules::csv($value),"'")); }
unitCheck(BookingRules::csv('Synthetic Tour') === 'Synthetic Tour');
fwrite(STDOUT,"Booking unit tests passed ($checks checks).\n");
