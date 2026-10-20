<?php

final class BookingService
{
    public function __construct(private PDO $pdo, private AccessControl $access, private Outbox $outbox) {}

    public function ensureUser(int $id): void
    {
        Database::run($this->pdo, "INSERT INTO Data(UserId,step) VALUES (?,'defult') ON DUPLICATE KEY UPDATE updated_at=UTC_TIMESTAMP()", [$id]);
    }

    public function used(int $tour): int
    {
        return array_sum(array_map('intval', Database::run($this->pdo, "SELECT quantity FROM Bookings WHERE tour_id=? AND status IN ('pending','confirmed','completed') FOR UPDATE", [$tour])->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function tour(int $id, bool $lock = false): array
    {
        $tour = Database::run($this->pdo, 'SELECT * FROM Tours WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$tour) {
            throw new RuleViolation('تور موردنظر پیدا نشد.');
        }
        return $tour;
    }

    public function saveTour(int $actor, array $values, ?int $id = null): int
    {
        $this->access->require($actor, 'tours');
        $fields = ['category_id','title','destination','description','starts_at','ends_at','price','capacity','publication'];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                throw new RuleViolation('مشخصات تور کامل نیست.');
            }
        }
        foreach (['title','destination','description'] as $field) {
            if (!is_string($values[$field]) || trim($values[$field]) === '' || strlen($values[$field]) > ($field === 'description' ? 4000 : 600)) {
                throw new RuleViolation('عنوان، مقصد و توضیحات تور را به‌درستی وارد کنید.');
            }
        }
        foreach (['starts_at','ends_at'] as $field) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string)$values[$field], new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d H:i:s') !== $values[$field]) {
                throw new RuleViolation('تاریخ یا ساعت معتبر نیست.');
            }
        }
        if ($values['ends_at'] <= $values['starts_at']) {
            throw new RuleViolation('زمان پایان تور باید بعد از زمان شروع باشد.');
        }
        if (!is_int($values['price']) || $values['price'] < 0 || $values['price'] > 1000000000000
            || !is_int($values['capacity']) || $values['capacity'] < 1 || $values['capacity'] > 100000
            || !in_array($values['publication'], ['draft','published','inactive','archived'], true)) {
            throw new RuleViolation('قیمت، ظرفیت، تاریخ یا وضعیت تور معتبر نیست.');
        }
        return Database::atomic($this->pdo, function () use ($actor, $values, $id, $fields) {
            if ($values['category_id'] !== null && !Database::run($this->pdo, 'SELECT id FROM Topics WHERE id=? FOR UPDATE', [$values['category_id']])->fetchColumn()) {
                throw new RuleViolation('دسته انتخاب‌شده وجود ندارد.');
            }
            if ($id !== null) {
                $previous = $this->tour($id, true);
                if (isset($values['expected_version']) && (int)$values['expected_version'] !== (int)$previous['version']) {
                    throw new RuleViolation('تور توسط مدیر دیگری تغییر کرده است؛ ویرایش را از ابتدا باز کنید.');
                }
                if ($values['capacity'] < $this->used($id)) {
                    throw new RuleViolation('ظرفیت جدید از تعداد صندلی‌های رزروشده کمتر است.');
                }
                Database::run($this->pdo, 'UPDATE Tours SET ' . implode(',', array_map(fn($f) => "$f=?", $fields)) . ',version=version+1 WHERE id=?', array_merge(array_map(fn($f) => $values[$f], $fields), [$id]));
                $users = Database::run($this->pdo, "SELECT user_id FROM Bookings WHERE tour_id=? AND user_id IS NOT NULL AND status IN ('pending','confirmed')", [$id])->fetchAll(PDO::FETCH_COLUMN);
                foreach ($users as $user) {
                    $this->outbox->message((int)$user, "مشخصات تور شماره $id تغییر کرد. لطفاً جزئیات جدید را از فهرست تورها ببینید.", "tour:$id:version:" . ($previous['version'] + 1) . ":user:$user");
                }
            } else {
                Database::run($this->pdo, 'INSERT INTO Tours(' . implode(',', $fields) . ') VALUES (?,?,?,?,?,?,?,?,?)', array_map(fn($f) => $values[$f], $fields));
                $id = (int)$this->pdo->lastInsertId();
            }
            $this->access->audit($actor, 'tour.save', 'tour', $id);
            return $id;
        });
    }

    public function publication(int $actor,int $id,string $publication):int
    {
        $this->access->require($actor,'tours');
        if(!in_array($publication,['draft','published','inactive','archived'],true))throw new RuleViolation('وضعیت انتشار معتبر نیست.');
        return Database::atomic($this->pdo,function()use($actor,$id,$publication){
            // Read current locked values; a stale UI snapshot must not undo another edit.
            $tour=$this->tour($id,true);
            if($tour['publication']===$publication)return$id;
            $tour['price']=(int)$tour['price'];$tour['capacity']=(int)$tour['capacity'];
            $tour['publication']=$publication;$tour['expected_version']=(int)$tour['version'];
            $this->saveTour($actor,$tour,$id);
            $this->access->audit($actor,'tour.publication.'.$publication,'tour',$id);
            return$id;
        });
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
            if (getenv('REQUIRE_VERIFIED_PROFILE') === 'true' && !(new TravelerIdentity($this->pdo))->complete($actor)) {
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
        });
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
        } elseif ($status !== 'cancelled') {
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
        });
    }

    private function history(int $id, int $actor, ?string $old, string $new, ?string $reason): void
    {
        Database::run($this->pdo, 'INSERT INTO BookingStatusHistory(booking_id,actor_id,old_status,new_status,reason) VALUES (?,?,?,?,?)', [$id,$actor,$old,$new,$reason]);
    }

    public function manualPayment(int $actor, int $id, string $reference): bool
    {
        $this->access->require($actor, 'payments');
        if (!preg_match('/^[A-Za-z0-9_-]{3,80}$/D', $reference)) {
            throw new RuleViolation('شناسه تأیید پرداخت باید ۳ تا ۸۰ حرف یا عدد انگلیسی باشد.');
        }
        return Database::atomic($this->pdo, function () use ($actor,$id,$reference) {
            $booking = Database::run($this->pdo, 'SELECT * FROM Bookings WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                throw new RuleViolation('رزرو پیدا نشد.');
            }
            $this->tour((int)$booking['tour_id'], true);
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
            } catch (PDOException $error) {
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
        });
    }
}
