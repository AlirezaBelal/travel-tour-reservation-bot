<?php

declare(strict_types=1);

/** Append-only status event persistence within the caller's transaction. */
final class BookingHistoryStore
{
    public function __construct(private PDO $pdo)
    {
    }
    public function append(int $id, int $actor, ?string $old, string $new, ?string $reason): void
    {
        Database::run($this->pdo, 'INSERT INTO BookingStatusHistory(booking_id,actor_id,old_status,new_status,reason) VALUES (?,?,?,?,?)', [$id,$actor,$old,$new,$reason]);
    }
    /** Compatibility entry point for existing callers; new use cases inject payment review. */
}
