<?php
declare(strict_types=1);

/** Compatibility only for the pre-DI BookingService constructor; runtime factories never use it. */
final class LegacyProfileRequirement
{
    public static function policy(): Closure
    {
        return static fn (): bool => ($_ENV['REQUIRE_VERIFIED_PROFILE'] ?? getenv('REQUIRE_VERIFIED_PROFILE')) === 'true';
    }
}
