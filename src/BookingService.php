<?php
final class BookingService
{
    private PaymentReviewService $paymentReview;
    private TourInventory $inventory;
    private TourService $tours;

    public function __construct(private PDO $pdo, private AccessControl $access, private Outbox $outbox,
        ?PaymentReviewService $paymentReview = null, ?TourInventory $inventory = null, ?TourService $tours = null) {
        // Keep the public three-argument constructor compatible for existing integrations.
        $this->paymentReview = $paymentReview ?? new PaymentReviewService($pdo, $access, $outbox);
        $this->inventory = $inventory ?? new TourInventory($pdo);
        $this->tours = $tours ?? new TourService($pdo, $this->inventory, $access, $outbox);
    }
    public function ensureUser(int $id): void
    {
        Database::run($this->pdo, "INSERT INTO Data(UserId,step) VALUES (?,'defult') ON DUPLICATE KEY UPDATE updated_at=UTC_TIMESTAMP()", [$id]);
    }
    public function used(int $tour): int
    {
        return $this->inventory->used($tour);
    }
    public function tour(int $id, bool $lock = false): array
    {
        return $this->inventory->tour($id, $lock);
    }
    /** Compatibility methods; tour use cases live in TourService. */
    public function saveTour(int $actor, array $values, ?int $id = null): int
    {
        return $this->tours->saveTour($actor, $values, $id);
    }
    public function publication(int $actor, int $id, string $publication): int
    {
        return $this->tours->publication($actor, $id, $publication);
    }
    public function reserve(int $actor, int $tourId, int $quantity, string $requestKey, ?int $expectedVersion = null): int
    {
        BookingRules::quantity($quantity);
        return Database::atomic($this->pdo, function () use ($actor, $tourId, $quantity, $requestKey, $expectedVersion) {
            Database::run($this->pdo, 'SELECT UserId FROM Data WHERE UserId=? FOR UPDATE', [$actor]);
            // Every capacity-changing operation takes the tour lock first.
            $tour = $this->tour($tourId, true);
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
            if (($_ENV['REQUIRE_VERIFIED_PROFILE'] ?? getenv('REQUIRE_VERIFIED_PROFILE')) === 'true' && !(new TravelerIdentity($this->pdo))->complete($actor)) {
                throw new RuleViolation('برای رزرو ابتدا شماره خود را با دکمه تلگرام به اشتراک بگذارید و کد ملی معتبر ثبت کنید.');
            }
            if ($tour['publication'] !== 'published' || $tour['starts_at'] <= gmdate('Y-m-d H:i:s')) {
                throw new RuleViolation('ثبت درخواست برای این تور فعال نیست.');
            }
            if ($this->used($tourId) + $quantity > (int)$tour['capacity']) {
                throw new RuleViolation('ظرفیت کافی نیست. تعداد کمتری انتخاب کنید یا تور دیگری را ببینید.');
            }
            Database::run($this->pdo, 'INSERT INTO Bookings(user_id,tour_id,quantity,unit_price,request_key) VALUES (?,?,?,?,?)', [$actor,$tourId,$quantity,$tour['price'],hash('sha256', "$actor:$requestKey")]);
            $id = (int)$this->pdo->lastInsertId();
            for ($i = 1; $i <= $quantity; $i++) {
                Database::run($this->pdo, 'INSERT INTO BookingParticipants(booking_id,ordinal) VALUES (?,?)', [$id,$i]);
            }
            $amount = (int)$tour['price'] * $quantity;
            Database::run($this->pdo, 'INSERT INTO Payments(booking_id,amount,method,status,order_ref,expires_at) VALUES (?,?,?,?,?,?)', [$id,$amount,$amount === 0 ? 'free' : 'manual',$amount === 0 ? 'paid' : 'pending',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s', time()+86400)]);
            $this->history($id, $actor, null, 'pending', null);
            $this->outbox->message($actor, "درخواست رزرو شماره $id برای $quantity نفر ثبت شد و در انتظار بررسی است.\nمبلغ کل: $amount ریال\nلغو پیش از شروع تور از بخش رزروهای من امکان‌پذیر است.", "booking:$id:pending");
            foreach ($this->access->reviewers() as $admin) {
                $this->outbox->message($admin, "درخواست رزرو شماره $id برای تور {$tourId}، تعداد $quantity نفر در انتظار بررسی است.", "booking:$id:review:$admin", [[['text'=>'بررسی درخواست','callback_data'=>"p:review:$id"]]], null, ['review']);
            }
            return $id;
        }
        );
    }
    public function booking(int $actor, int $id, bool $administrative = false): array
    {
        if ($administrative) {
            $this->access->require($actor, 'review');
        }
        $booking = Database::run($this->pdo, 'SELECT b.*,t.title,t.starts_at,t.ends_at,p.status AS payment_status,p.amount,p.order_ref,p.method FROM Bookings b JOIN Tours t ON t.id=b.tour_id JOIN Payments p ON p.booking_id=b.id WHERE b.id=?' . ($administrative ? '' : ' AND b.user_id=?'), $administrative ? [$id] : [$id,$actor])->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            throw new RuleViolation('رزرو پیدا نشد یا به حساب شما تعلق ندارد.');
        }
        return $booking;
    }
    public function transition(int $actor, int $id, string $status, ?string $reason = null, bool $admin = false): bool
    {
        if ($admin) {
            $this->access->require($actor, 'review');
        }
        elseif ($status !== 'cancelled') {
            throw new RuleViolation('اجازه تغییر این وضعیت را ندارید.');
        }
        if ($reason !== null && strlen($reason) > 500) {
            throw new RuleViolation('دلیل را کوتاه‌تر وارد کنید.');
        }
        return Database::atomic($this->pdo, function () use ($actor, $id, $status, $reason, $admin) {
            $initial = $this->booking($actor, $id, $admin);
            $tour = $this->tour((int)$initial['tour_id'], true);
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
            $this->history($id, $actor, $row['status'], $status, $reason);
            $this->access->audit($actor, 'booking.' . $status, 'booking', $id);
            $labels = ['confirmed'=>'تأییدشده','rejected'=>'ردشده','cancelled'=>'لغوشده','completed'=>'تکمیل‌شده'];
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
        }
        );
    }
    private function history(int $id, int $actor, ?string $old, string $new, ?string $reason): void
    {
        Database::run($this->pdo, 'INSERT INTO BookingStatusHistory(booking_id,actor_id,old_status,new_status,reason) VALUES (?,?,?,?,?)', [$id,$actor,$old,$new,$reason]);
    }
    /** Compatibility entry point for existing callers; new use cases inject payment review. */
    public function manualPayment(int $actor, int $id, string $reference): bool
    {
        return $this->paymentReview->record($actor, $id, $reference);
    }
}
