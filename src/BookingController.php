<?php

/** Booking requests, reviews, payment confirmation and related message/report presentation. */
final class BookingController
{
    public function __construct(
        private BookingService $bookings,
        private PaymentReviewService $paymentReview,
        private BookingReportService $reports,
        private BookingQueryService $queries,
        private AccessControl $access,
        private BotConversation $conversation
    ) {
    }

    public function callback(string $action, int $id, int $arg, int $version): bool
    {
        $actor = $this->conversation->actor();
        return $this->handleTravelerCallback($action, $actor, $id, $arg, $version, $this->conversation->update())
            || $this->handleBookingAdminCallback($action, $actor, $id, $arg);
    }

    public function input(string $state, array $payload, string $text): bool
    {
        return $this->handleConversationInput($state, $payload, $text) || $this->reviewInput($state, $payload, $text);
    }

    private function reviewInput(string $state, array $payload, string $text): bool
    {
        if ($state === 'reject') {
            $this->access->require($this->conversation->actor(), 'review');
            $this->bookings->transition($this->conversation->actor(), (int)$payload['booking'], 'rejected', $text === '-' ? null : $text, true);
            $this->conversation->clear();
            $this->showBooking((int)$payload['booking'], true);
            return true;
        }
        if ($state === 'pay_reference') {
            $this->access->require($this->conversation->actor(), 'payments');
            if (!PaymentRules::isManualReference($text)) {
                throw new RuleViolation('شناسه پرداخت معتبر نیست.');
            }
            $payload['reference'] = $text;
            $this->conversation->state('pay_confirm', $payload);
            $booking = $this->bookings->booking($this->conversation->actor(), (int)$payload['booking'], true);
            $this->conversation->reply("تأیید دستی پرداخت {$booking['amount']} ریال برای رزرو {$booking['id']} با شناسه {$text}؟", [[['text' => 'تأیید واریز بررسی‌شده','callback_data' => 'p:pay_yes:' . $payload['booking']]],[['text' => 'انصراف','callback_data' => 'p:admin:0']]]);
            return true;
        }
        if ($state === 'report') {
            $this->conversation->clear();
            $this->report($this->conversation->number($text));
            return true;
        }
        return false;
    }

    private function handleTravelerCallback(string $action, int $actor, int $id, int $arg, int $version, int $update): bool
    {
        switch ($action) {
            case 'reserve_yes':
                $booking = $this->bookings->reserve($actor, $id, $arg, "update:$update", $version);
                $this->conversation->clear();
                $this->showBooking($booking);
                break;
            case 'mine':
                $this->mine($id);
                break;
            case 'booking':
                $this->showBooking($id);
                break;
            case 'history':
                $this->history($id, false);
                break;
            case 'contact':
                $this->bookings->booking($actor, $id);
                $this->conversation->state('support_message', ['booking' => $id]);
                $this->conversation->reply('پیام مربوط به این رزرو را برای مدیریت بنویسید. رمز، اطلاعات کارت یا کد ملی نفرستید. /cancel برای انصراف.');
                break;
            case 'message':
                $this->access->require($actor, 'notify');
                $this->bookings->booking($actor, $id, true);
                $this->conversation->state('admin_message', ['booking' => $id]);
                $this->conversation->reply('متن راهنمایی یا پاسخ به سرپرست این رزرو را بنویسید. پیام از طریق همین ربات ارسال می‌شود. /cancel برای انصراف.');
                break;
            case 'cancel_booking':
                $this->bookings->booking($actor, $id);
                $this->conversation->state('cancel_confirm', ['booking' => $id]);
                $this->conversation->reply('لغو رزرو باعث آزادشدن ظرفیت می‌شود. استرداد وجه خودکار نیست و باید با مدیر هماهنگ شود. لغو می‌کنید؟', [[['text' => 'تأیید لغو','callback_data' => "p:cancel_yes:$id"]],[['text' => 'بازگشت','callback_data' => "p:booking:$id"]]]);
                break;
            case 'cancel_yes':
                $confirmation = $this->conversation->requireSession('cancel_confirm');
                if ((int)$confirmation['booking'] !== $id) {
                    throw new RuleViolation('تأیید لغو معتبر نیست.');
                }
                $this->conversation->clear();
                $this->bookings->transition($actor, $id, 'cancelled');
                $this->showBooking($id);
                break;
            default:
                return false;
        }
        return true;
    }

    /** Returns false when this action belongs to another callback category. */
    private function handleBookingAdminCallback(string $action, int $actor, int $id, int $arg): bool
    {
        switch ($action) {
            case 'pending':
                $this->pending($id);
                break;
            case 'all_bookings':
                $this->allBookings($id);
                break;
            case 'tour_bookings':
                $this->allBookings($arg, $id);
                break;
            case 'review':
                $this->showBooking($id, true);
                break;
            case 'review_history':
                $this->history($id, true);
                break;
            case 'confirm':
                $this->bookings->transition($actor, $id, 'confirmed', null, true);
                $this->showBooking($id, true);
                break;
            case 'complete':
                $this->bookings->transition($actor, $id, 'completed', null, true);
                $this->showBooking($id, true);
                break;
            case 'reject':
                $this->access->require($actor, 'review');
                $this->conversation->state('reject', ['booking' => $id]);
                $this->conversation->reply('دلیل رد درخواست را وارد کنید؛ برای رد بدون دلیل، علامت - بفرستید.');
                break;
            case 'pay':
                $this->access->require($actor, 'payments');
                $this->bookings->booking($actor, $id, true);
                $this->conversation->state('pay_reference', ['booking' => $id]);
                $this->conversation->reply('پس از بررسی واقعی واریز، یک شناسه یکتا با ۳ تا ۸۰ حرف یا عدد انگلیسی وارد کنید. هرگز شماره کارت یا اطلاعات بانکی مسافر را وارد نکنید.');
                break;
            case 'pay_yes':
                $payload = $this->conversation->requireSession('pay_confirm');
                if ((int)$payload['booking'] !== $id) {
                    throw new RuleViolation('فرم منقضی شده است.');
                }
                $this->paymentReview->record($actor, $id, $payload['reference']);
                $this->conversation->clear();
                $this->showBooking($id, true);
                break;
            case 'report':
                $this->report($id);
                break;
            case 'report_select':
                $this->access->require($actor, 'reports');
                $this->conversation->state('report', []);
                $this->conversation->reply('شناسه تور را وارد کنید:');
                break;
            case 'export':
                $this->export($id);
                break;
            default:
                return false;
        }
        return true;
    }

    public function mine(int $offset): void
    {
        $rows = $this->queries->mine($this->conversation->actor(), $offset);
        $buttons = [];
        foreach ($rows as $row) {
            $buttons[] = [['text' => 'رزرو #' . $row['id'] . ' — ' . $this->status($row['status']),'callback_data' => 'p:booking:' . $row['id']]];
        }
        if (count($rows) === 10) {
            $buttons[] = [['text' => 'بعدی','callback_data' => 'p:mine:' . ($offset + 10)]];
        }
        $buttons[] = [['text' => 'منو','callback_data' => 'p:home:0']];
        $this->conversation->reply($rows ? 'رزروهای جاری و قبلی شما:' : 'هنوز رزروی ندارید.', $buttons);
    }

    private function status(string $status): string
    {
        return['pending' => 'در انتظار','confirmed' => 'تأییدشده','rejected' => 'ردشده','cancelled' => 'لغوشده','completed' => 'تکمیل‌شده','paid' => 'پرداخت‌شده','expired' => 'منقضی','failed' => 'ناموفق'][$status] ?? $status;
    }

    private function showBooking(int $id, bool $admin = false): void
    {
        $booking = $this->queries->detail($this->conversation->actor(), $id, $admin);
        $text = "رزرو #$id\nتور: {$booking['title']}\nشروع: " . $this->conversation->local($booking['starts_at']) . "\nتعداد نفرات: {$booking['quantity']}\nمبلغ کل: {$booking['amount']} ریال\nوضعیت رزرو: " . $this->status($booking['status']) . "\nوضعیت پرداخت: " . $this->status($booking['payment_status']);
        if ($booking['payment_status'] === 'pending' && (int)$booking['amount'] > 0) {
            $text .= "\nپرداخت آنلاین فعال نیست. پس از هماهنگی با مدیر، واریز دستی بررسی می‌شود. پیش از هماهنگی، مبلغی واریز نکنید. مهلت بررسی اولیه پرداخت ۲۴ ساعت است.";
        }
        $buttons = [];
        if ($admin && $booking['user_id'] !== null && $this->access->allows($this->conversation->actor(), 'profiles')) {
            $leader = $booking['leader_name'];
            $text .= "\nنام سرپرست: " . ($leader ?: 'ثبت نشده');
        }
        if ($admin) {
            if ($booking['status'] === 'pending') {
                $buttons[] = [['text' => 'تأیید رزرو','callback_data' => "p:confirm:$id"],['text' => 'رد با دلیل اختیاری','callback_data' => "p:reject:$id"]];
            }
            if ($this->access->allows($this->conversation->actor(), 'payments') && $booking['payment_status'] !== 'paid') {
                $buttons[] = [['text' => 'بررسی پرداخت دستی','callback_data' => "p:pay:$id"]];
            }
            if ($booking['status'] === 'confirmed') {
                $buttons[] = [['text' => 'تکمیل پس از پایان تور','callback_data' => "p:complete:$id"]];
            }
        } elseif (in_array($booking['status'], ['pending','confirmed'], true)) {
            $buttons[] = [['text' => 'لغو رزرو','callback_data' => "p:cancel_booking:$id"]];
        }
        if ($admin && $this->access->allows($this->conversation->actor(), 'notify') && $booking['user_id'] !== null) {
            $buttons[] = [['text' => 'پیام به سرپرست','callback_data' => "p:message:$id"]];
        } elseif (!$admin) {
            $buttons[] = [['text' => 'پیام به مدیریت درباره رزرو','callback_data' => "p:contact:$id"]];
        }
        $buttons[] = [['text' => 'جزئیات تور','callback_data' => 'p:tour:' . $booking['tour_id']]];
        $buttons[] = [['text' => 'تاریخچه وضعیت','callback_data' => ($admin ? 'p:review_history:' : 'p:history:') . $id]];
        $buttons[] = [['text' => 'بازگشت','callback_data' => $admin ? 'p:pending:0' : 'p:mine:0']];
        $this->conversation->reply($text, $buttons, $admin && $booking['user_id'] !== null ? (int)$booking['user_id'] : null, $admin ? ($this->access->allows($this->conversation->actor(), 'profiles') ? ['review','profiles'] : ['review']) : []);
    }

    private function history(int $id, bool $admin): void
    {
        $rows = $this->queries->history($this->conversation->actor(), $id, $admin);
        $lines = array_map(fn($row)=>$this->conversation->local($row['created_at']) . ' — ' . $this->status($row['new_status']), $rows);
        $this->conversation->reply(implode("\n", $lines));
    }

    private function pending(int $offset): void
    {
        $rows = $this->queries->pending($this->conversation->actor(), $offset);
        $buttons = [];
        foreach ($rows as $row) {
            $buttons[] = [['text' => "رزرو {$row['id']} / تور {$row['tour_id']} / {$row['quantity']} نفر",'callback_data' => 'p:review:' . $row['id']]];
        }
        if (count($rows) === 10) {
            $buttons[] = [['text' => 'بعدی','callback_data' => 'p:pending:' . ($offset + 10)]];
        }
        $buttons[] = [['text' => 'مدیریت','callback_data' => 'p:admin:0']];
        $this->conversation->reply($rows ? 'درخواست‌های منتظر بررسی:' : 'درخواست منتظری وجود ندارد.', $buttons);
    }

    private function allBookings(int $offset, ?int $tour = null): void
    {
        $rows = $this->queries->all($this->conversation->actor(), $offset, $tour);
        $buttons = [];
        foreach ($rows as $row) {
            $buttons[] = [['text' => 'رزرو #' . $row['id'] . ' / ' . $this->status($row['status']) . ' / ' . $row['quantity'] . ' نفر','callback_data' => 'p:review:' . $row['id']]];
        }
        if (count($rows) === 10) {
            $buttons[] = [['text' => 'صفحه بعد','callback_data' => $tour === null ? 'p:all_bookings:' . ($offset + 10) : "p:tour_bookings:$tour:" . ($offset + 10)]];
        }
        $buttons[] = [['text' => 'مدیریت','callback_data' => 'p:admin:0']];
        $this->conversation->reply('رزروها با وضعیت جاری و قبلی:', $buttons);
    }

    private function report(int $id): void
    {
        $summary = $this->reports->summary($this->conversation->actor(), $id);
        $text = "گزارش تور #$id\nظرفیت: {$summary['capacity']}\nباقی‌مانده: {$summary['remaining']}";
        foreach ($summary['statuses'] as $row) {
            $text .= "\n" . $this->status($row['status']) .
            ": {$row['requests']} درخواست / {$row['seats']} نفر";
        }
        $buttons = [];
        if ($this->access->allows($this->conversation->actor(), 'review')) {
            $buttons[] = [['text' => 'رزروهای این تور', 'callback_data' => "p:tour_bookings:$id:0"]];
        }
        if ($this->access->allows($this->conversation->actor(), 'export')) {
            $buttons[] = [['text' => 'خروجی CSV بدون اطلاعات هویتی', 'callback_data' => "p:export:$id"]];
        }
        $buttons[] = [['text' => 'مدیریت', 'callback_data' => 'p:admin:0']];
        $this->conversation->reply($text, $buttons, null, ['reports']);
    }

    private function export(int $id): void
    {
        $csv = $this->reports->csv($this->conversation->actor(), $id);
        $this->conversation->enqueue(
            $this->conversation->actor(),
            'sendDocument',
            [
            'caption' => 'گزارش رزرو بدون نام، موبایل یا کد ملی',
            '_csv' => $csv,
            '_export_actor' => $this->conversation->actor(),
            ],
            "export:{$this->conversation->update()}:$id"
        );
        $this->access->audit($this->conversation->actor(), 'booking.export', 'tour', $id);
        $this->conversation->reply('فایل در صف ارسال امن قرار گرفت.');
    }

    private function handleConversationInput(string $state, array $payload, string $text): bool
    {
        if ($state === 'support_message' || $state === 'admin_message') {
            if ($text === '' || strlen($text) > 2000) {
                throw new RuleViolation('پیام را کوتاه‌تر وارد کنید.');
            }
            $id = (int)$payload['booking'];
            if ($state === 'support_message') {
                $this->bookings->booking($this->conversation->actor(), $id);
                foreach ($this->access->reviewers() as $admin) {
                    if (!$this->access->allows($admin, 'notify')) {
                        continue;
                    }
                    $this->conversation->message($admin, "پیام مسافر درباره رزرو #$id:\n$text", "support:{$this->conversation->update()}:$admin", [[['text' => 'پاسخ به سرپرست','callback_data' => "p:message:$id"]]], $this->conversation->actor(), ['notify','review']);
                }
            } else {
                $this->access->require($this->conversation->actor(), 'notify');
                $booking = $this->bookings->booking($this->conversation->actor(), $id, true);
                if ($booking['user_id'] === null) {
                    throw new RuleViolation('حساب این مسافر حذف شده است.');
                }
                $this->conversation->enqueue((int)$booking['user_id'], 'sendMessage', ['text' => "پیام مدیریت درباره رزرو #$id:\n$text",'reply_markup' => json_encode(['inline_keyboard' => [[['text' => 'پاسخ به مدیریت','callback_data' => "p:contact:$id"]]]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),'_subject_user' => (int)$booking['user_id'],'_permission_actor' => $this->conversation->actor(),'_required_permissions' => ['notify','review']], "staff-message:{$this->conversation->update()}:$id");
                $this->access->audit($this->conversation->actor(), 'booking.message', 'booking', $id);
            }
            $this->conversation->clear();
            $this->conversation->reply('پیام در صف ارسال قرار گرفت. پاسخ از طریق همین ربات به شما می‌رسد.');
            return true;
        }
        return false;
    }}
