<?php

declare(strict_types=1);

/** Reservation use case: one atomic capacity, price, payment, history and outbox unit. */
final class ReservationService
{
    /** @param Closure(): bool $requireVerifiedProfile */
    public function __construct(
        private PDO $pdo,
        private TourInventory $inventory,
        private AccessControl $access,
        private Outbox $outbox,
        private TravelerIdentity $identity,
        private BookingHistoryStore $history,
        private Closure $requireVerifiedProfile
    ) {
    }
    public function ensureUser(int $id): void
    {
        Database::run($this->pdo, "INSERT INTO Data(UserId,step) VALUES (?,'defult') ON DUPLICATE KEY UPDATE updated_at=UTC_TIMESTAMP()", [$id]);
    }
    public function reserve(int $actor, int $tourId, int $quantity, string $requestKey, ?int $expectedVersion = null): int
    {
        BookingRules::quantity($quantity);
        return Database::atomic($this->pdo, function () use ($actor, $tourId, $quantity, $requestKey, $expectedVersion) {
            Database::run($this->pdo, 'SELECT UserId FROM Data WHERE UserId=? FOR UPDATE', [$actor]);
            // Every capacity-changing operation takes the tour lock first.
            $tour = $this->inventory->tour($tourId, true);
            $existing = Database::run($this->pdo, 'SELECT * FROM Bookings WHERE user_id=? AND tour_id=? FOR UPDATE', [$actor,$tourId])->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ((int)$existing['quantity'] !== $quantity) {
                    throw new RuleViolation('برای این تور قبلاً درخواست ثبت کرده‌اید. از «رزروهای من» پیگیری کنید.');
                }
                return (int)$existing['id'];
            }
            if ($expectedVersion !== null && (int)$tour['version'] !== $expectedVersion) {
                throw new RuleViolation('مشخصات تور تغییر کرده است؛ جزئیات جدید را ببینید و دوباره تأیید کنید.');
            }
            $name = Database::run($this->pdo, 'SELECT Name FROM Data WHERE UserId=?', [$actor])->fetchColumn();
            if (!is_string($name) || trim($name) === '') {
                throw new RuleViolation('ابتدا نام خود را از بخش پروفایل ثبت کنید.');
            }
            if (($this->requireVerifiedProfile)() && !$this->identity->complete($actor)) {
                throw new RuleViolation('برای رزرو ابتدا شماره خود را با دکمه تلگرام به اشتراک بگذارید و کد ملی معتبر ثبت کنید.');
            }
            if ($tour['publication'] !== 'published' || $tour['starts_at'] <= gmdate('Y-m-d H:i:s')) {
                throw new RuleViolation('ثبت درخواست برای این تور فعال نیست.');
            }
            if ($this->inventory->used($tourId) + $quantity > (int)$tour['capacity']) {
                throw new RuleViolation('ظرفیت کافی نیست. تعداد کمتری انتخاب کنید یا تور دیگری را ببینید.');
            }
            Database::run($this->pdo, 'INSERT INTO Bookings(user_id,tour_id,quantity,unit_price,request_key) VALUES (?,?,?,?,?)', [$actor,$tourId,$quantity,$tour['price'],hash('sha256', "$actor:$requestKey")]);
            $id = (int)$this->pdo->lastInsertId();
            for ($i = 1; $i <= $quantity; $i++) {
                Database::run($this->pdo, 'INSERT INTO BookingParticipants(booking_id,ordinal) VALUES (?,?)', [$id,$i]);
            }
            $amount = (int)$tour['price'] * $quantity;
            Database::run($this->pdo, 'INSERT INTO Payments(booking_id,amount,method,status,order_ref,expires_at) VALUES (?,?,?,?,?,?)', [$id,$amount,$amount === 0 ? 'free' : 'manual',$amount === 0 ? 'paid' : 'pending',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s', time() + 86400)]);
            $this->history->append($id, $actor, null, 'pending', null);
            $this->outbox->message($actor, "درخواست رزرو شماره $id برای $quantity نفر ثبت شد و در انتظار بررسی است.\nمبلغ کل: $amount ریال\nلغو پیش از شروع تور از بخش رزروهای من امکان‌پذیر است.", "booking:$id:pending");
            foreach ($this->access->reviewers() as $admin) {
                $this->outbox->message($admin, "درخواست رزرو شماره $id برای تور {$tourId}، تعداد $quantity نفر در انتظار بررسی است.", "booking:$id:review:$admin", [[['text' => 'بررسی درخواست','callback_data' => "p:review:$id"]]], null, ['review']);
            }
            return $id;
        });
    }
}
