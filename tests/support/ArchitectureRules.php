<?php
declare(strict_types=1);

/** Source-only fitness checks; never executes the scanned code. */
final class ArchitectureRules
{
    /** @param array<string, string> $sources @return list<string> */
    public static function violations(array $sources): array
    {
        $errors = [];
        $dependencies = [];
        $names = array_map(static fn ($path) => pathinfo($path, PATHINFO_FILENAME), array_keys($sources));
        foreach ($sources as $path => $source) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            $dependencies[$name] = [];
            $tokens = token_get_all($source);
            foreach ($tokens as $index => $token) {
                if (!is_array($token)) {
                    continue;
                }
                [$type, $value] = $token;
                if ($type === T_STRING && $value !== $name && in_array($value, $names, true)) {
                    $dependencies[$name][] = $value;
                }
                if ($name === 'ProductBot' && $type === T_STRING
                    && in_array(strtolower($value), ['database', 'pdo', 'mysqli'], true)) {
                    $errors[] = 'presentation.persistence';
                }
                if ($name === 'ProductBot' && $type === T_NEW) {
                    for ($next = $index + 1; isset($tokens[$next]); $next++) {
                        if (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                            continue;
                        }
                        $class = is_array($tokens[$next]) ? $tokens[$next][1] : $tokens[$next];
                        if (!in_array($class, ['RuleViolation', 'DateTimeImmutable', 'DateTimeZone'], true)) {
                            $errors[] = 'presentation.construction';
                        }
                        break;
                    }
                }
                if ($name === 'PaymentRules' && $type === T_STRING
                    && in_array(strtolower($value), ['pdo', 'database', 'outbox', 'productbot', 'curl_init', 'getenv'], true)) {
                    $errors[] = 'domain.infrastructure';
                }
                if (in_array($name, ['TravelerProfileService', 'BookingQueryService', 'LegacyTopicService',
                    'PaymentReviewService', 'TourCatalogService'], true)
                    && ($type === T_GLOBAL || ($type === T_VARIABLE
                        && in_array($value, ['$GLOBALS', '$_ENV', '$_SERVER', '$_POST'], true)))) {
                    $errors[] = 'application.global';
                }
            }
        }
        foreach (array_keys($dependencies) as $name) {
            if (self::cyclic($name, $dependencies, [])) {
                $errors[] = 'dependency.cycle';
                break;
            }
        }
        return array_values(array_unique($errors));
    }

    private static function cyclic(string $name, array $dependencies, array $visiting): bool
    {
        if (isset($visiting[$name])) {
            return true;
        }
        $visiting[$name] = true;
        foreach ($dependencies[$name] ?? [] as $next) {
            if (self::cyclic($next, $dependencies, $visiting)) {
                return true;
            }
        }
        return false;
    }
}
