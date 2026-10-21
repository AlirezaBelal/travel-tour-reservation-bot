<?php
declare(strict_types=1);
require __DIR__ . '/support/ArchitectureRules.php';
$sources = [];
foreach (glob(__DIR__ . '/../src/*.php') as $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Cannot read production source.');
    }
    $sources[basename($path)] = $source;
}
if (count($sources) < 25 || !isset($sources['ProductBot.php'], $sources['TravelerProfileService.php'], $sources['BookingQueryService.php'])) {
    throw new RuntimeException('Architecture scan is incomplete.');
}
$errors = ArchitectureRules::violations($sources);
if ($errors !== []) {
    throw new RuntimeException('Architecture violations: ' . implode(', ', $errors));
}
$mutations = [
    ['ProductBot.php', "\nDatabase::run(\$pdo, 'SELECT 1');", 'presentation.persistence'],
    ['ProductBot.php', "\nnew PrivacyService(\$pdo);", 'presentation.construction'],
    ['handler.php', "\n\$db->select('Data', '*');", 'presentation.persistence'],
    ['TravelerProfileService.php', "\nglobal \$pdo;", 'application.global'],
    ['PaymentRules.php', "\nPDO::class;", 'domain.infrastructure'],
];
foreach ($mutations as [$path, $injection, $expected]) {
    $mutated = $sources;
    $mutated[$path] .= $injection;
    if (!in_array($expected, ArchitectureRules::violations($mutated), true)) {
        throw new RuntimeException('Architecture mutation escaped detection: ' . $expected);
    }
}
$mutated = $sources;
$mutated['TravelerProfileService.php'] .= "\nBookingQueryService::class;";
$mutated['BookingQueryService.php'] .= "\nTravelerProfileService::class;";
if (!in_array('dependency.cycle', ArchitectureRules::violations($mutated), true)) {
    throw new RuntimeException('Synthetic dependency cycle escaped detection.');
}
fwrite(STDOUT, 'Application architecture passed: ' . count($sources) . " production files, six detected mutations.\n");
