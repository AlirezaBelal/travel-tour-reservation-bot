<?php
declare(strict_types=1);

// Prove that read-model annotations reject missing fields and incorrect payment types.
// The fixture is analyzed only; it never constructs a database or executes a use case.
$root = dirname(__DIR__, 2);
$directory = __DIR__ . '/cache';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('Cannot create isolated analysis fixture directory.');
}
$fixture = $directory . '/contract-probe.php';
$source = <<<'PROBE'
<?php
function validBookingTitle(BookingReader $reader): string {
    return $reader->booking(1, 1)['title'];
}
function invalidBookingField(BookingReader $reader): mixed {
    return $reader->booking(1, 1)['synthetic_missing_column'];
}
function invalidPaymentAmount(PaymentAdapter $adapter): string {
    return $adapter->verify([])['amount'];
}
PROBE;
try {
    if (file_put_contents($fixture, $source) === false) {
        throw new RuntimeException('Cannot write isolated analysis fixture.');
    }
    $command = implode(' ', array_map('escapeshellarg', [
        PHP_BINARY, __DIR__ . '/vendor/bin/phpstan', 'analyse', '-c', $root . '/phpstan.neon',
        '--no-progress', '--error-format=json', $root . '/src', $fixture,
    ]));
    exec($command, $output, $code);
    $report = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    $messages = $report['files'][$fixture]['messages'] ?? [];
    $identifiers = array_column($messages, 'identifier');
    sort($identifiers);
    if ($code !== 1 || ($report['totals']['errors'] ?? -1) !== 0
        || ($report['totals']['file_errors'] ?? -1) !== 2
        || $identifiers !== ['offsetAccess.notFound', 'return.type']) {
        throw new RuntimeException('Read-model negative probes were not rejected as expected.');
    }
    fwrite(STDOUT, "Static contracts passed: valid projection accepted; missing booking field and string payment amount rejected.\n");
} finally {
    if (is_file($fixture)) unlink($fixture);
}
