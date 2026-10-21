<?php

/** Local signed simulator, NOT a merchant gateway or a real payment provider. */
final class SandboxPaymentAdapter implements PaymentAdapter
{
    public function __construct(private string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Sandbox signing key must contain at least 32 bytes.');
        }
    }
    public function signature(array $event): string
    {
        return hash_hmac('sha256', json_encode([$event['order_ref'] ?? null,$event['transaction'] ?? null,$event['amount'] ?? null,$event['status'] ?? null,$event['timestamp'] ?? null], JSON_THROW_ON_ERROR), $this->secret);
    }
    public function verify(array $callback): array
    {
        if (
            !is_string($callback['signature'] ?? null) || !hash_equals($this->signature($callback), $callback['signature'])
            || !is_int($callback['timestamp'] ?? null) || abs(time() - $callback['timestamp']) > 300
            || !is_int($callback['amount'] ?? null) || $callback['amount'] < 0
            || !is_string($callback['order_ref'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $callback['order_ref'])
            || !is_string($callback['transaction'] ?? null) || !preg_match('/^[A-Za-z0-9_-]{3,80}$/D', $callback['transaction'])
            || !in_array($callback['status'] ?? null, ['paid','failed'], true)
        ) {
            throw new RuleViolation('پاسخ پرداخت آزمایشی معتبر نیست.');
        }
        return $callback;
    }
}
