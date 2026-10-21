<?php

/**
 * @phpstan-type PaymentEvent array{
 *     order_ref: string,
 *     transaction: string,
 *     amount: int,
 *     status: 'paid'|'failed',
 *     timestamp?: int,
 *     signature?: string,
 * }
 */
interface PaymentAdapter
{
    /**
     * Must verify authenticity and authoritative amount/order before returning.
     * @param array<string, mixed> $callback
     * @return PaymentEvent
     */
    public function verify(array $callback): array;
}
