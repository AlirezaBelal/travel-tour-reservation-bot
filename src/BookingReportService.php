<?php

/**
 * An authorized read model for aggregate bookings and identity-free CSV exports.
 * Delivery and audit ordering remain the responsibility of the Telegram UI.
 */
final class BookingReportService
{
    private const MAX_EXPORT_ROWS = 5000;

    public function __construct(
        private PDO $pdo,
        private BookingService $bookings,
        private AccessControl $access
    ) {}

    public function summary(int $actor, int $tourId): array
    {
        $this->access->require($actor, 'reports');
        $tour = $this->bookings->tour($tourId);
        $rows = Database::run(
            $this->pdo,
            'SELECT status,COUNT(*) requests,SUM(quantity) seats FROM Bookings WHERE tour_id=? GROUP BY status',
            [$tourId]
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'capacity' => (int)$tour['capacity'],
            'remaining' => (int)$tour['capacity'] - $this->bookings->used($tourId),
            'statuses' => $rows,
        ];
    }

    public function csv(int $actor, int $tourId): string
    {
        $this->access->require($actor, 'export');
        $this->bookings->tour($tourId);

        // Explicit column allowlist: never query old or encrypted identity data.
        $rows = Database::run(
            $this->pdo,
            'SELECT b.id,t.title,b.quantity,b.status,p.amount,p.status AS payment_status ' .
            'FROM Bookings b JOIN Tours t ON t.id=b.tour_id JOIN Payments p ON p.booking_id=b.id ' .
            'WHERE b.tour_id=? ORDER BY b.id LIMIT 5001',
            [$tourId]
        )->fetchAll(PDO::FETCH_NUM);

        if (count($rows) > self::MAX_EXPORT_ROWS) {
            throw new RuleViolation('گزارش بیش از ۵۰۰۰ ردیف دارد؛ با اپراتور برای خروجی محدود هماهنگ کنید.');
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Cannot open temporary CSV stream.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'booking_id', 'tour', 'quantity', 'status', 'amount_IRR', 'payment_status',
            ], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv(
                    $stream,
                    array_map(static fn ($value) => BookingRules::csv((string)$value), $row),
                    ',', '"', ''
                );
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if ($csv === false) {
                throw new RuntimeException('Cannot read generated CSV.');
            }
            return $csv;
        } finally {
            fclose($stream);
        }
    }
}
