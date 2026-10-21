<?php

declare(strict_types=1);

/** Per-update Telegram response keys, menu, formatting and persisted form interaction. */
final class BotConversation
{
    private int $actor = 0;
    private int $update = 0;
    private int $sequence = 0;

    public function __construct(
        private Outbox $outbox,
        private BotSessionStore $sessions,
        private AccessControl $access,
        private string $timezone
    ) {
    }

    public function begin(int $actor, int $update): void
    {
        $this->actor = $actor;
        $this->update = $update;
        $this->sequence = 0;
    }
    public function actor(): int
    {
        return $this->actor;
    }
    public function update(): int
    {
        return $this->update;
    }
    public function timezone(): string
    {
        return $this->timezone;
    }
    public function nextReplyKey(): string
    {
        return "update:{$this->update}:reply:" . $this->sequence++;
    }
    public function enqueue(int $chat, string $method, array $payload, string $key): void
    {
        $this->outbox->enqueue($chat, $method, $payload, $key);
    }
    public function message(int $chat, string $text, string $key, array $buttons = [], ?int $subject = null, array $permissions = []): void
    {
        $this->outbox->message($chat, $text, $key, $buttons, $subject, $permissions);
    }

    public function helpText(): string
    {
        return "راهنما\n۱. از «پروفایل» نام سرپرست را ثبت کنید. برگزارکننده می‌تواند تکمیل شماره و کد ملی را الزامی کند.\n۲. از «تورها» یا «جست‌وجوی تور»، جزئیات و ظرفیت را ببینید و برای ۱ تا ۲۰ نفر درخواست بدهید.\n۳. در «رزروهای من»، وضعیت را پیگیری کنید یا پیش از شروع تور لغو کنید.\n\nثبت درخواست به معنی تأیید رزرو نیست. پرداخت پولی به بررسی دستی برگزارکننده نیاز دارد؛ پرداخت آنلاین و صدور بلیت خودکار نداریم.\nقیمت‌ها ریال و تاریخ‌ها میلادی‌اند. منطقه زمانی نمایش: {$this->timezone}.\n/cancel توقف فرم جاری؛ /privacy حریم خصوصی و حذف حساب.";
    }

    public function reply(string $text, array $buttons = [], ?int $subject = null, array $permissions = []): void
    {
        $this->outbox->message($this->actor, str_replace('\\n', "\n", $text), "update:{$this->update}:reply:" . ($this->sequence++), $buttons, $subject, $permissions);
    }

    public function menu(): array
    {
        $buttons = [[['text' => 'تورها','callback_data' => 'p:tours:0'],['text' => 'دسته‌ها','callback_data' => 'p:cats:0']],[['text' => 'جست‌وجوی تور','callback_data' => 'p:search:0'],['text' => 'رزروهای من','callback_data' => 'p:mine:0']],[['text' => 'پروفایل','callback_data' => 'p:profile:0'],['text' => 'راهنما','callback_data' => 'p:help:0']]];
        if ($this->access->role($this->actor) !== null) {
            $buttons[] = [['text' => 'مدیریت','callback_data' => 'p:admin:0']];
        }
        return $buttons;
    }

    public function state(string $state, array $payload): void
    {
        $this->sessions->put($this->actor, $state, $payload);
    }

    public function session(): ?array
    {
        return $this->sessions->get($this->actor);
    }

    public function requireSession(string $state): array
    {
        return $this->sessions->require($this->actor, $state);
    }

    public function clear(): void
    {
        $this->sessions->clear($this->actor);
    }

    public function number(string $text, int $max = 4503599627370495): int
    {
        $text = strtr($text, ['۰' => '0','۱' => '1','۲' => '2','۳' => '3','۴' => '4','۵' => '5','۶' => '6','۷' => '7','۸' => '8','۹' => '9']);
        if (!preg_match('/^[0-9]{1,16}$/D', $text) || (float)$text > $max) {
            throw new RuleViolation('یک عدد معتبر وارد کنید.');
        }
        return(int)$text;
    }

    public function date(string $text): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $text, new DateTimeZone($this->timezone));
        if (!$date || $date->format('Y-m-d H:i') !== $text) {
            throw new RuleViolation('تاریخ میلادی و ساعت را مانند 2027-04-21 08:30 وارد کنید.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function local(string $utc): string
    {
        return(new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($this->timezone))->format('Y-m-d H:i');
    }
}
