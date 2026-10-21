<?php

declare(strict_types=1);

/** Authorized state changes coordinate the existing tour/booking/payment lock order. */
final class BookingLifecycleService
{
    public function __construct(
        private PDO $pdo,
        private TourInventory $inventory,
        private BookingReader $reader,
        private AccessControl $access,
        private Outbox $outbox,
        private BookingHistoryStore $history
    ) {
    }
    public function transition(int $actor, int $id, string $status, ?string $reason = null, bool $admin = false): bool
    {
        if ($admin) {
            $this->access->require($actor, 'review');
        } elseif ($status !== 'cancelled') {
            throw new RuleViolation('اجازه تغییر این وضعیت را ندارید.');
        }
        if ($reason !== null && strlen($reason) > 500) {
            throw new RuleViolation('دلیل را کوتاه‌تر وارد کنید.');
        }
        return Database::atomic($this->pdo, function () use ($actor, $id, $status, $reason, $admin) {
            $initial = $this->reader->booking($actor, $id, $admin);
            $tour = $this->inventory->tour((int)$initial['tour_id'], true);
            $row = Database::run($this->pdo, 'SELECT * FROM Bookings WHERE id=? FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$admin && (int)$row['user_id'] !== $actor) {
                throw new RuleViolation('اجازه دسترسی ندارید.');
            }
            if ($row['status'] === $status) {
                return false;
            }
            if (!BookingRules::transition($row['status'], $status)) {
                throw new RuleViolation('این تغییر وضعیت دیگر امکان‌پذیر نیست.');
            }
            if (!$admin && $tour['starts_at'] <= gmdate('Y-m-d H:i:s')) {
                throw new RuleViolation('مهلت لغو آنلاین تا پیش از شروع تور است. با مدیر تماس بگیرید.');
            }
            if ($status === 'completed' && $tour['ends_at'] > gmdate('Y-m-d H:i:s')) {
                throw new RuleViolation('تور هنوز پایان نیافته است.');
            }
            $payment = Database::run($this->pdo, 'SELECT status FROM Payments WHERE booking_id=? FOR UPDATE', [$id])->fetchColumn();
            if ($status === 'confirmed' && $payment !== 'paid') {
                throw new RuleViolation('ابتدا پرداخت دستی را بررسی و تأیید کنید.');
            }
            Database::run($this->pdo, 'UPDATE Bookings SET status=?,version=version+1 WHERE id=?', [$status,$id]);
            $this->history->append($id, $actor, $row['status'], $status, $reason);
            $this->access->audit($actor, 'booking.' . $status, 'booking', $id);
            $labels = ['confirmed' => 'تأییدشده','rejected' => 'ردشده','cancelled' => 'لغوشده','completed' => 'تکمیل‌شده'];
            if ($row['user_id'] !== null) {
                $text = "وضعیت رزرو شماره $id: " . $labels[$status];
                if ($reason !== null && $reason !== '') {
                    $text .= "\nدلیل: $reason";
                }
                if (in_array($status, ['cancelled','rejected'], true) && $payment === 'paid' && (int)$initial['amount'] > 0) {
                    $text .= "\nاسترداد خودکار فعال نیست؛ تسویه را با مدیر پیگیری کنید.";
                }
                $this->outbox->message((int)$row['user_id'], $text, "booking:$id:status:$status");
            }
            return true;
        });
    }
}
