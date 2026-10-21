<?php
declare(strict_types=1);
require __DIR__ . '/../src/Database.php';

final class TransactionProbe extends PDO
{
    public bool $active = false;
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool
    {
        if ($this->active) throw new RuntimeException('Unexpected nested transaction.');
        $this->active = true;
        $this->begins++;
        return true;
    }
    public function commit(): bool
    {
        if (!$this->active) throw new RuntimeException('Commit without an active transaction.');
        $this->active = false;
        $this->commits++;
        return true;
    }
    public function rollBack(): bool
    {
        if (!$this->active) throw new RuntimeException('Rollback after database auto-abort.');
        $this->active = false;
        $this->rollbacks++;
        return true;
    }
}
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($label);
};
$failure = static function (int $code): PDOException {
    $error = new PDOException('Synthetic transaction failure');
    $error->errorInfo = ['40001', $code, 'Synthetic transaction failure'];
    return $error;
};
foreach ([1205, 1213] as $code) {
    $pdo = new TransactionProbe();
    $attempts = 0;
    $result = Database::atomic($pdo, static function () use (&$attempts, $code, $failure): string {
        if (++$attempts === 1) throw $failure($code);
        return 'synthetic-result';
    });
    $check($result === 'synthetic-result' && $attempts === 2, 'retryable database error reruns complete action');
    $check($pdo->begins === 2 && $pdo->rollbacks === 1 && $pdo->commits === 1 && !$pdo->active,
        'retry cleans failed transaction before committing next attempt');
}
$pdo = new TransactionProbe();
$attempts = 0;
Database::atomic($pdo, static function () use ($pdo, &$attempts, $failure): void {
    if (++$attempts === 1) {
        $pdo->active = false; // InnoDB can abort the transaction itself after a deadlock.
        throw $failure(1213);
    }
});
$check($attempts === 2 && $pdo->rollbacks === 0 && $pdo->commits === 1,
    'auto-aborted transaction is retried without an invalid rollback');
$pdo = new TransactionProbe();
$attempts = 0;
try {
    Database::atomic($pdo, static function () use (&$attempts, $failure): void {
        $attempts++;
        throw $failure(1213);
    });
    throw new RuntimeException('Expected retry exhaustion.');
} catch (PDOException $error) {
    $check($attempts === 5 && $pdo->begins === 5 && $pdo->rollbacks === 5 && $pdo->commits === 0,
        'deadlock retry is bounded and preserves the failure');
}
$pdo = new TransactionProbe();
$attempts = 0;
try {
    Database::atomic($pdo, static function () use (&$attempts, $failure): void {
        $attempts++;
        throw $failure(1062);
    });
    throw new RuntimeException('Expected non-retryable error.');
} catch (PDOException $error) {
    $check($attempts === 1 && $pdo->rollbacks === 1, 'duplicate-key failures are not retried');
}
$pdo = new TransactionProbe();
$pdo->active = true;
$attempts = 0;
try {
    Database::atomic($pdo, static function () use (&$attempts, $failure): void {
        $attempts++;
        throw $failure(1213);
    });
    throw new RuntimeException('Expected error to reach transaction owner.');
} catch (PDOException $error) {
    $check($attempts === 1 && $pdo->active && $pdo->begins === 0 && $pdo->rollbacks === 0 && $pdo->commits === 0,
        'nested atomic action does not retry or finish its caller transaction');
}
fwrite(STDOUT, "Transaction retry contract passed ($checks checks).\n");
