<?php
declare(strict_types=1);
require __DIR__ . '/../src/TransactionCleanup.php';
$pdo = new class extends PDO {
    public bool $active = false;
    public int $rollbacks = 0;
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function rollBack(): bool
    {
        if (!$this->active) throw new RuntimeException('Rollback called outside a transaction.');
        $this->active = false;
        $this->rollbacks++;
        return true;
    }
};
TransactionCleanup::rollbackIfActive($pdo);
if ($pdo->rollbacks !== 0) throw new RuntimeException('Inactive transaction was rolled back.');
$pdo->active = true;
TransactionCleanup::rollbackIfActive($pdo);
TransactionCleanup::rollbackIfActive($pdo);
if ($pdo->active || $pdo->rollbacks !== 1) throw new RuntimeException('Active transaction cleanup failed.');
fwrite(STDOUT, "Transaction cleanup passed: live state checked, no double rollback.\n");
