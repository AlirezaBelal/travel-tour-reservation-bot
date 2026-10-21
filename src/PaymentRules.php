<?php
declare(strict_types=1);

/** Payment input rules without persistence, Telegram or configuration dependencies. */
final class PaymentRules
{
    public static function isManualReference(string $reference): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{3,80}$/D', $reference) === 1;
    }
}
