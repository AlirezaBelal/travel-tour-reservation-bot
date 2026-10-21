<?php
declare(strict_types=1);

/** Manual payment review: authorization, locked payment writes, audit and outbox. */
final class PaymentReviewService
{
    public function __construct(private PDO $pdo, private AccessControl $access, private Outbox $outbox) {}

    public function record(int $actor, int $id, string $reference): bool
    {
        $this->access->require($actor, 'payments');
        if (!PaymentRules::isManualReference($reference)) {
            throw new RuleViolation('شناسه تأیید پرداخت باید ۳ تا ۸۰ حرف یا عدد انگلیسی باشد.');
        }
        return Database::atomic($this->pdo, function () use ($actor,$id,$reference) {
            $booking = Database::run($this->pdo, 'SELECT * FROM Bookings WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                throw new RuleViolation('رزرو پیدا نشد.');
            }
            if (!Database::run($this->pdo, 'SELECT * FROM Tours WHERE id=? FOR UPDATE',
                [(int)$booking['tour_id']])->fetch(PDO::FETCH_ASSOC)) {
                throw new RuleViolation('تور موردنظر پیدا نشد.');
            }
            $booking = Database::run($this->pdo, 'SELECT * FROM Bookings WHERE id=? FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            $payment = Database::run($this->pdo, 'SELECT * FROM Payments WHERE booking_id=? FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            if ($payment['status'] === 'paid') {
                return false;
            }
            if (!in_array($booking['status'], ['pending','confirmed'], true)) {
                throw new RuleViolation('پرداخت رزرو بسته‌شده قابل تأیید نیست.');
            }
            try {
                Database::run($this->pdo, "UPDATE Payments SET status='paid',method='manual',provider_tx=? WHERE booking_id=?", ['manual:' . hash('sha256', $reference),$id]);
            }
            catch (PDOException $error) {
                if (($error->errorInfo[1] ?? null) === 1062) {
                    throw new RuleViolation('این شناسه پرداخت قبلاً استفاده شده است.');
                }
                throw $error;
            }
            $this->access->audit($actor, 'payment.manual', 'booking', $id);
            if ($booking['user_id'] !== null) {
                $this->outbox->message((int)$booking['user_id'], "پرداخت رزرو شماره $id توسط مدیر تأیید شد. وضعیت رزرو جداگانه بررسی می‌شود.", "payment:$id:paid");
            }
            return true;
        }
        );
    }
}
