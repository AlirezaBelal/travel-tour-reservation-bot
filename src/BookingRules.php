<?php

declare(strict_types=1);

require_once __DIR__ . '/RuleViolation.php';

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
        return preg_match('/^[\s\p{Cf}]*[=+@\-]|^[\x00-\x1f]/u', $value) ? "'" . $value : $value;
    }
}
