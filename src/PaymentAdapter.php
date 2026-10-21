<?php
interface PaymentAdapter
{
    /** Must verify authenticity and authoritative amount/order before returning. */
    public function verify(array $callback): array;
}
