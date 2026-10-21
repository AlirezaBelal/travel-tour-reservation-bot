<?php

final class WebhookRegistration
{
    /**
     * @return array{url: string, secret_token: string, allowed_updates: string, drop_pending_updates: string}
     */
    public static function parameters(string $url, string $secret): array
    {
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !webhookSecretIsValid($secret, $secret)) {
            throw new InvalidArgumentException('Use a clean HTTPS endpoint and the configured strong webhook secret.');
        }
        return ['url' => $url,'secret_token' => $secret,'allowed_updates' => json_encode(['message','callback_query'], JSON_THROW_ON_ERROR),'drop_pending_updates' => 'false'];
    }
}
