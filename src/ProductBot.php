<?php

final class ProductBot
{
    public function __construct(
        private TravelerProfileController $profileFlow,
        private BookingController $bookingFlow,
        private AccessControl $access,
        private BotConversation $conversation,
        private TourController $tours,
        private NotificationAdminService $notificationAdmin
    ) {
    }

    public function dispatch(int $actor, ?string $text, ?string $callback, int $update, ?array $contact = null): bool
    {
        $this->conversation->begin($actor, $update);
        $session = $this->conversation->session();
        $legacyStep = $this->profileFlow->legacyStep();
        if ($this->dispatchCommand($actor, $text, $legacyStep)) {
            return true;
        }
        if (isLegacyProfileStep($legacyStep)) {
            $this->profileFlow->retireLegacyProfile();
            return true;
        }
        if ($this->dispatchProductCallback($actor, $callback, $update)) {
            return true;
        }
        if ($text !== null && preg_match('/^\/(?:list|send)(?:\s|$)/', $text)) {
            $this->conversation->clear();
            return false;
        }
        if ($session !== null && $session['state'] === 'phone' && $contact !== null) {
            $this->profileFlow->contact($contact);
            return true;
        }
        if ($session !== null && $session['state'] !== 'filter' && $text !== null && !str_starts_with($text, '/')) {
            $this->input($session['state'], json_decode($session['payload'], true, 512, JSON_THROW_ON_ERROR), trim($text));
            return true;
        }
        // Keep the established profile/catalogue/approval commands available.
        if ($callback !== null || ($text !== null && (preg_match('/^(?:\/(?:list|send)|Topics|ping|usage)(?:\s|$)/', $text) || preg_match('/^[\/#!]?(?:AddTopic) /i', $text)))) {
            return false;
        }
        if ($legacyStep && $legacyStep !== 'defult') {
            return false;
        }
        $this->conversation->reply('پیام را متوجه نشدم. از دکمه‌ها استفاده کنید یا /help بفرستید.', $this->conversation->menu());
        return true;
    }

    /** Slash commands take priority over any persisted form state. */
    private function dispatchCommand(int $actor, ?string $text, mixed $legacyStep): bool
    {
        if ($text !== null && preg_match('/^\/(start|help|tours|bookings|profile|setprofile|admin|cancel|privacy|search)(?:@[A-Za-z0-9_]+)?(?:\s.*)?$/u', $text, $match)) {
            $command = $match[1] === 'setprofile' ? 'profile' : $match[1];
            $this->conversation->clear();
            $this->profileFlow->resetLegacyState();
            if (isLegacyProfileStep($legacyStep)) {
                $this->profileFlow->retireLegacyProfile();
            }
            if ($command === 'start' || $command === 'help' || $command === 'cancel') {
                $this->conversation->reply($command === 'cancel' ? 'عملیات جاری متوقف شد. از منوی زیر ادامه دهید.' : ($command === 'help' ? $this->conversation->helpText() : "به ربات رزرو و مدیریت تور خوش آمدید! 🌍\n\nاز معرفی تور تا پیگیری رزرو، مستقیم در تلگرام.\nتورهای منتشرشده را ببینید، مقصد دلخواهتان را پیدا کنید و برای ۱ تا ۲۰ نفر درخواست رزرو ثبت کنید.\n\nاز منوی زیر شروع کنید. وضعیت درخواست‌ها در «رزروهای من» دیده می‌شود؛ ثبت درخواست به معنی تأیید نهایی نیست. /cancel فرم جاری را متوقف می‌کند."), $this->conversation->menu());
            } elseif ($command === 'tours') {
                $this->tours->listing(0);
            } elseif ($command === 'bookings') {
                $this->bookingFlow->mine(0);
            } elseif ($command === 'profile') {
                $this->profileFlow->show();
            } elseif ($command === 'admin') {
                $this->admin();
            } elseif ($command === 'privacy') {
                $this->profileFlow->privacy();
            } elseif ($command === 'search') {
                $this->conversation->state('search', []);
                $this->conversation->reply('عنوان یا مقصد تور را وارد کنید. برای خروج /cancel بفرستید.');
            }
            return true;
        }
        return false;
    }

    /** Product callbacks remain separate from the legacy compatibility handler. */
    private function dispatchProductCallback(int $actor, ?string $callback, int $update): bool
    {
        if ($callback !== null && str_starts_with($callback, 'p:')) {
            if (!preg_match('/^p:([a-z_]{1,24}):([0-9]{1,16})(?::([0-9]{1,16}))?(?::([0-9]{1,16}))?$/D', $callback, $parts)) {
                throw new RuleViolation('دکمه معتبر نیست. از منوی جدید استفاده کنید.');
            }
            $action = $parts[1];
            $id = (int)$parts[2];
            $arg = (int)($parts[3] ?? 0);
            $version = (int)($parts[4] ?? 0);
            if ($action === 'admin') {
                $this->admin();
                return true;
            }
            if ($this->profileFlow->callback($action, $actor)) {
                return true;
            }
            if ($this->bookingFlow->callback($action, $id, $arg, $version)) {
                return true;
            }
            if ($this->tours->callback($action, $actor, $id, $arg)) {
                return true;
            }
            if ($this->handleAdministrationCallback($action, $actor, $id)) {
                return true;
            }
            throw new RuleViolation('این دکمه دیگر معتبر نیست. از /start شروع کنید.');
        }
        return false;
    }

    /** Returns false when this action belongs to another callback category. */
    private function handleAdministrationCallback(string $action, int $actor, int $id): bool
    {
        switch ($action) {
            case 'roles':
                $this->access->require($actor, 'roles');
                $this->conversation->state('role_user', []);
                $this->conversation->reply('شناسه عددی تلگرام مدیر را وارد کنید:');
                break;
            case 'role_yes':
                $payload = $this->conversation->requireSession('role_confirm');
                if ((int)$payload['user'] !== $id) {
                    throw new RuleViolation('فرم معتبر نیست.');
                }
                $this->access->assign($actor, $id, $payload['role']);
                $this->conversation->clear();
                $this->conversation->reply('نقش مدیر به‌روز شد.');
                break;
            case 'notify':
                $this->access->require($actor, 'notify');
                $this->notifications();
                break;
            case 'retry':
                $this->notificationAdmin->retry($actor, $id);
                $this->conversation->reply('اعلان ناموفق برای تلاش مجدد صف شد.');
                break;
            case 'broadcast':
                $this->access->require($actor, 'notify');
                $this->conversation->state('broadcast_target', []);
                $this->conversation->reply('شناسه تور را برای ارسال به مسافران فعال وارد کنید؛ صفر یعنی همه حساب‌ها.');
                break;
            case 'broadcast_yes':
                $payload = $this->conversation->requireSession('broadcast_confirm');
                $this->broadcast($payload['tour'], $payload['text']);
                $this->conversation->clear();
                break;
            default:
                return false;
        }
        return true;
    }

    private function admin(): void
    {
        if ($this->access->role($this->conversation->actor()) === null) {
            throw new RuleViolation('دسترسی مدیریت ندارید.');
        }
        $buttons = [];
        if ($this->access->allows($this->conversation->actor(), 'tours')) {
            $buttons = array_merge($buttons, [[['text' => 'ایجاد تور','callback_data' => 'p:new:0'],['text' => 'مدیریت تورها','callback_data' => 'p:manage_tours:0']],[['text' => 'ایجاد دسته','callback_data' => 'p:category_new:0'],['text' => 'حذف دسته','callback_data' => 'p:category_delete:0']]]);
        }
        if ($this->access->allows($this->conversation->actor(), 'review')) {
            $buttons[] = [['text' => 'درخواست‌های منتظر','callback_data' => 'p:pending:0'],['text' => 'همه رزروها','callback_data' => 'p:all_bookings:0']];
        }
        if ($this->access->allows($this->conversation->actor(), 'reports')) {
            $buttons[] = [['text' => 'گزارش تور','callback_data' => 'p:report_select:0']];
        }
        if ($this->access->allows($this->conversation->actor(), 'notify')) {
            $buttons[] = [['text' => 'وضعیت اعلان‌ها','callback_data' => 'p:notify:0'],['text' => 'ارسال گروهی','callback_data' => 'p:broadcast:0']];
        }
        if ($this->access->allows($this->conversation->actor(), 'roles')) {
            $buttons[] = [['text' => 'مدیریت نقش‌ها','callback_data' => 'p:roles:0']];
        }
        $buttons[] = [['text' => 'منو','callback_data' => 'p:home:0']];
        $this->conversation->reply('مدیریت؛ نقش شما: ' . (['owner' => 'مدیر اصلی','manager' => 'مدیر','reviewer' => 'بررسی درخواست‌ها','reporter' => 'گزارش‌گیر'][$this->access->role($this->conversation->actor())] ?? 'نامشخص'), $buttons);
    }

    private function notifications(): void
    {
        $overview = $this->notificationAdmin->overview($this->conversation->actor());
        $text = 'وضعیت صف اعلان‌ها:';
        foreach ($overview['statuses'] as $row) {
            $text .= "\n{$row['status']}: {$row['total']}";
        }

        $buttons = [];
        foreach ($overview['failed'] as $row) {
            $buttons[] = [[
                'text' => "تلاش مجدد #{$row['id']} ({$row['error_code']})",
                'callback_data' => 'p:retry:' . $row['id'],
            ]];
        }
        $this->conversation->reply($text, $buttons);
    }

    private function broadcast(int $tour, string $text): void
    {
        $count = $this->notificationAdmin->broadcast(
            $this->conversation->actor(),
            $this->conversation->update(),
            $tour,
            $text
        );
        $this->conversation->reply("برای $count حساب در صف قرار گرفت؛ نتیجه ارسال را از وضعیت اعلان‌ها پیگیری کنید.");
    }

    /**
     * Dispatch stored form input by responsibility; unknown states fail closed.
     */
    private function input(string $state, array $payload, string $text): void
    {
        if ($this->profileFlow->input($state, $payload, $text)) {
            return;
        }
        if ($this->bookingFlow->input($state, $payload, $text)) {
            return;
        }
        if ($this->tours->input($state, $payload, $text)) {
            return;
        }
        $this->handleAdministrativeInput($state, $payload, $text);
    }

    private function handleAdministrativeInput(string $state, array $payload, string $text): void
    {
        if ($state === 'role_user') {
            $this->access->require($this->conversation->actor(), 'roles');
            $this->conversation->state('role_name', ['user' => $this->conversation->number($text, 4503599627370495)]);
            $this->conversation->reply('نقش را بنویسید: مدیر، بررسی، گزارش، حذف. مدیر تور و پرداخت و اعلان را مدیریت می‌کند؛ بررسی فقط درخواست‌ها؛ گزارش فقط آمار.');
            return;
        }
        if ($state === 'role_name') {
            $this->access->require($this->conversation->actor(), 'roles');
            $role = ['مدیر' => 'manager','بررسی' => 'reviewer','گزارش' => 'reporter','حذف' => 'none'][$text] ?? null;
            if (!$role) {
                throw new RuleViolation('یکی از نقش‌های نمایش‌داده‌شده را وارد کنید.');
            }
            $payload['role'] = $role;
            $this->conversation->state('role_confirm', $payload);
            $this->conversation->reply("تغییر نقش حساب {$payload['user']} به {$text}؟", [[['text' => 'تأیید تغییر نقش','callback_data' => 'p:role_yes:' . $payload['user']]],[['text' => 'انصراف','callback_data' => 'p:admin:0']]]);
            return;
        }
        if ($state === 'broadcast_target') {
            $this->access->require($this->conversation->actor(), 'notify');
            $tour = $this->conversation->number($text);
            $this->notificationAdmin->validateTarget($this->conversation->actor(), $tour);
            $this->conversation->state('broadcast_text', ['tour' => $tour]);
            $this->conversation->reply('متن پیام را وارد کنید (کوتاه‌تر از ۲۰۰۰ حرف):');
            return;
        }
        if ($state === 'broadcast_text') {
            $this->access->require($this->conversation->actor(), 'notify');
            if ($text === '' || strlen($text) > 6000) {
                throw new RuleViolation('متن را کوتاه‌تر وارد کنید.');
            }
            $payload['text'] = $text;
            $this->conversation->state('broadcast_confirm', $payload);
            $this->conversation->reply("پیام زیر برای مخاطبان انتخاب‌شده صف می‌شود؛ تأیید می‌کنید؟\n$text", [[['text' => 'تأیید ارسال گروهی','callback_data' => 'p:broadcast_yes:0']],[['text' => 'انصراف','callback_data' => 'p:admin:0']]]);
            return;
        }
        throw new RuleViolation('برای ادامه از دکمه تأیید استفاده کنید یا /cancel بفرستید.');
    }
}
