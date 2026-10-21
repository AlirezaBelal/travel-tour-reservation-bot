<?php
declare(strict_types=1);
require __DIR__ . '/../src/RuleViolation.php';
require __DIR__ . '/../src/TourRules.php';
$values = ['category_id' => null, 'title' => 'Synthetic', 'destination' => 'Synthetic',
    'description' => 'Synthetic', 'starts_at' => '2028-04-21 08:00:00',
    'ends_at' => '2028-04-21 09:00:00', 'price' => 0, 'capacity' => 20, 'publication' => 'draft'];
TourRules::validate($values);
$checks = 1;
foreach ([['title' => ' '], ['description' => str_repeat('x', 4001)], ['starts_at' => '2028-02-30 08:00:00'],
    ['ends_at' => $values['starts_at']], ['price' => -1], ['price' => '100'], ['price' => 1000000000001],
    ['capacity' => 0], ['capacity' => 100001], ['publication' => 'unknown']] as $invalid) {
    try {
        TourRules::validate(array_replace($values, $invalid));
        throw new RuntimeException('Invalid tour values accepted.');
    } catch (RuleViolation $error) {
        $checks++;
    }
}
foreach (TourRules::FIELDS as $field) {
    $missing = $values;
    unset($missing[$field]);
    try {
        TourRules::validate($missing);
        throw new RuntimeException('Missing tour field accepted.');
    } catch (RuleViolation $error) {
        $checks++;
    }
}
fwrite(STDOUT, "Pure tour rules passed ($checks checks).\n");
