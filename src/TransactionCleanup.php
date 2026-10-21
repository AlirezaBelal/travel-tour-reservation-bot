<?php
declare(strict_types=1);

/** Re-read live PDO state: deadlocks can end a transaction before catch/cleanup. */
final class TransactionCleanup
{
    public static function rollbackIfActive(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
