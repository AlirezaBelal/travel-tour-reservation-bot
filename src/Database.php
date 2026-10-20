<?php

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
                if ($pdo->inTransaction()) $pdo->rollBack();
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

class RuleViolation extends DomainException {}

final class BookingRules
{
    public static function quantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 20) {
            throw new RuleViolation('تعداد مسافران باید بین ۱ تا ۲۰ نفر باشد.');
        }
    }

    public static function transition(string $old, string $new): bool
    {
        return in_array($new, [
            'pending' => ['confirmed', 'rejected', 'cancelled'],
            'confirmed' => ['cancelled', 'completed'],
        ][$old] ?? [], true);
    }

    public static function csv(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-]|^[\x00-\x1f]/u', $value) ? "'" . $value : $value;
    }
}
