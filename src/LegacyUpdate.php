<?php
declare(strict_types=1);

/** Explicit compatibility input after private-update authentication/normalization. */
final class LegacyUpdate
{
    public function __construct(public int $actor, public int $chat, public ?string $text,
        public ?string $callback, public int|string $messageId, public ?string $callbackId,
        public bool $hasContent) {}
}
