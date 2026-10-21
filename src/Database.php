<?php
require_once __DIR__ . '/RuleViolation.php';
require_once __DIR__ . '/TransactionCleanup.php';
require_once __DIR__ . '/BookingRules.php';

final class Database
{
    public static function run(PDO $pdo, string $sql, array $args = []): PDOStatement
    {
        $query = $pdo->prepare($sql);
        $query->execute($args);
        return $query;
    }

    public static function atomic(PDO $pdo, callable $action): mixed
    {
        if ($pdo->inTransaction()) {
            return $action();
        }
        for ($attempt = 0; ; $attempt++) {
            $pdo->beginTransaction();
            try {
                $result = $action();
                $pdo->commit();
                return $result;
            } catch (Throwable $error) {
                TransactionCleanup::rollbackIfActive($pdo);
                if ($error instanceof PDOException && in_array((int)($error->errorInfo[1] ?? 0), [1205,1213], true) && $attempt < 4) {
                    usleep(random_int(10000,50000));
                    continue;
                }
                throw $error;
            }
        }
    }

    public static function configure(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET time_zone = '+00:00'");
    }
}
