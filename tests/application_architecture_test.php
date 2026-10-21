<?php
declare(strict_types=1);
require __DIR__ . '/support/ArchitectureRules.php';
$sources = [];
$root = realpath(__DIR__ . '/../src');
if ($root === false) throw new RuntimeException('Cannot resolve source root.');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $source = file_get_contents($file->getPathname());
    if ($source === false) throw new RuntimeException('Cannot read production source.');
    $sources[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = $source;
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
    ['TourController.php', "\nPDO::class;", 'presentation.persistence'],
    ['handler.php', "\n\$db->select('Data', '*');", 'presentation.persistence'],
    ['TravelerProfileService.php', "\nglobal \$pdo;", 'application.global'],
    ['PaymentRules.php', "\nPDO::class;", 'domain.infrastructure'],
    ['PaymentRules.php', "\ncurl_exec(\$request);", 'domain.infrastructure'],
    ['ReservationService.php', "\ngetenv('SYNTHETIC_SETTING');", 'application.global'],
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
fwrite(STDOUT, 'Application architecture passed: ' . count($sources) . " production files, nine detected mutations.\n");
