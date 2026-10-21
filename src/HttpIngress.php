<?php
declare(strict_types=1);

/** Pure HTTP webhook decision: no database/bootstrap, output headers or input I/O. */
final class HttpIngress
{
    /** @return array{status: int, update: array|null} */
    public static function decode(string $secret, mixed $suppliedSecret, string $method, mixed $body): array
    {
        if (!webhookSecretIsValid($secret, $suppliedSecret)) return ['status' => 403, 'update' => null];
        if ($method !== 'POST') return ['status' => 405, 'update' => null];
        if (!is_string($body) || trim($body) === '') return ['status' => 200, 'update' => null];
        if (strlen($body) > 1048576) return ['status' => 413, 'update' => null];
        try {
            $update = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return ['status' => 400, 'update' => null];
        }
        return ['status' => 200, 'update' => isSupportedPrivateUpdate($update) ? $update : null];
    }
}
