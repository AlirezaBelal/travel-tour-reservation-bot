<?php
declare(strict_types=1);

/** Profile, consent and erasure conversation; persistence is delegated to one application service. */
final class TravelerProfileController
{
    public function __construct(private TravelerProfileService $profiles, private BotConversation $conversation) {}

    public function legacyStep(): ?string { return $this->profiles->legacyStep($this->conversation->actor()); }
    public function resetLegacyState(): void { $this->profiles->resetLegacyState($this->conversation->actor()); }
    public function contact(array $contact): void
    {
        $this->profiles->saveMobile($this->conversation->actor(), $contact);
        $this->conversation->state('national', []);
        $this->conversation->enqueue($this->conversation->actor(), 'sendMessage',
            ['text' => 'شماره همراه شما به‌صورت رمزگذاری‌شده ثبت شد. اکنون کد ملی ۱۰ رقمی سرپرست را وارد کنید؛ فقط ساختار و رقم کنترل بررسی می‌شود.',
                'reply_markup' => json_encode(['remove_keyboard' => true], JSON_THROW_ON_ERROR)],
            $this->conversation->nextReplyKey());
    }

    public function callback(string $action, int $actor): bool
    {
        switch ($action) {
            case 'home':
                $this->conversation->clear();
                $this->conversation->reply('انتخاب کنید:',$this->conversation->menu());
                break;
            case 'help':
                $this->conversation->reply($this->conversation->helpText(),$this->conversation->menu());
                break;
            case 'profile':
                $this->show();
                break;
            case 'name':
                $this->conversation->state('name',[]);
                $this->conversation->reply('نام سرپرست گروه را وارد کنید (حداکثر ۵۰ حرف).');
                break;
            case 'phone':
                $name=$this->profiles->name($actor);
                if(!is_string($name)||trim($name)==='')throw new RuleViolation('ابتدا نام سرپرست را در پروفایل ثبت کنید.');
                $this->conversation->state('phone',[]);
                $this->contactPrompt();
                break;
            case 'national':
                if(!$this->profiles->status($actor)['mobile'])throw new RuleViolation('ابتدا شماره خود را با دکمه اشتراک مخاطب تلگرام ثبت کنید.');
                $this->conversation->state('national',[]);
                $this->conversation->reply('کد ملی ۱۰ رقمی سرپرست را وارد کنید. کد ملی فقط برای هماهنگی هویت ذخیره می‌شود و در پیام‌ها یا خروجی عمومی نمایش داده نمی‌شود.');
                break;
            case 'privacy':
                $this->privacy();
                break;
            case 'erase':
                $this->conversation->state('erase_confirm',[]);
                $this->conversation->reply('حذف حساب، نام و پروفایل قدیمی و اطلاعات همراهان را پاک و رزروهای بسته‌شده را بی‌نام می‌کند. ابتدا تمام رزروهای فعال را لغو کنید. شمارنده امنیتی موقت تا ۲۴ ساعت باقی می‌ماند؛ پشتیبان‌ها و پیام‌های قبلی تلگرام تابع سیاست جداگانه‌اند. تأیید می‌کنید؟',[[['text'=>'بله، حذف حساب','callback_data'=>'p:erase_yes:0']],[['text'=>'انصراف','callback_data'=>'p:home:0']]]);
                break;
            case 'erase_yes':
                $this->conversation->requireSession('erase_confirm');
                $this->profiles->erase($actor);
                $this->conversation->reply('حساب و اطلاعات قابل حذف پاک شد. با /start می‌توانید دوباره شروع کنید.');
                break;
            default:
                return false;
        }
        return true;
    }

    /** Returns false when this action belongs to another callback category. */
    public function retireLegacyProfile():void {
        $this->conversation->clear();
        $this->profiles->resetLegacyState($this->conversation->actor());
        $this->conversation->enqueue($this->conversation->actor(),'sendMessage',['text'=>'فرم قدیمی پروفایل بسته شد؛ اطلاعات این پیام ذخیره نشد. برای ثبت امن اطلاعات، /profile را بفرستید.','reply_markup'=>json_encode(['remove_keyboard'=>true],JSON_THROW_ON_ERROR)],$this->conversation->nextReplyKey());
    }

    private function contactPrompt():void {
        $markup=['keyboard'=>[[['text'=>'اشتراک شماره خودم','request_contact'=>true]]],'resize_keyboard'=>true,'one_time_keyboard'=>true,'selective'=>true];
        $this->conversation->enqueue($this->conversation->actor(),'sendMessage',['text'=>'برای ثبت شماره فقط دکمه زیر را بزنید؛ شماره تایپی یا کارت مخاطب دیگران پذیرفته نمی‌شود. از اطلاعات فقط برای هماهنگی رزرو استفاده می‌شود.','reply_markup'=>json_encode($markup,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)],$this->conversation->nextReplyKey());
    }

    public function show():void {
        $name=$this->profiles->name($this->conversation->actor());
        $status=$this->profiles->status($this->conversation->actor());
        $mobile=$status['mobile']?'ثبت شد':'ثبت نشده';
        $national=$status['national']?'ثبت شد':'ثبت نشده';
        $this->conversation->reply('نام سرپرست: '.($name?:'ثبت نشده')."\nشماره تلگرام: $mobile\nکد ملی: $national\nشناسه حساب: {$this->conversation->actor()}\nشماره و کد ملی در این صفحه نمایش داده نمی‌شوند.",[[['text'=>'ثبت یا ویرایش نام','callback_data'=>'p:name:0']],[['text'=>'اشتراک شماره خودم','callback_data'=>'p:phone:0']],[['text'=>'ثبت یا ویرایش کد ملی','callback_data'=>'p:national:0']],[['text'=>'حریم خصوصی و حذف حساب','callback_data'=>'p:privacy:0']],[['text'=>'بازگشت','callback_data'=>'p:home:0']]]);
    }

    public function privacy():void {
        $this->conversation->reply('در پروفایل جدید نام سرپرست، شماره همراهی که با دکمه تلگرام به اشتراک گذاشته‌اید و کد ملی دارای رقم کنترل معتبر ذخیره می‌شود. شماره و کد ملی در پایگاه داده رمزگذاری شده و در گزارش‌های عمومی یا پیام‌های مدیر نمایش داده نمی‌شوند. بررسی رقم کنترل به معنی استعلام هویت از ثبت احوال نیست. حذف حساب پس از بستن رزروهای فعال، اطلاعات رمزگذاری‌شده را نیز پاک می‌کند. اطلاعات قدیمی پروفایل تا حذف حساب محفوظ‌اند. پاک‌سازی خودکار حساب غیرفعال فقط با سیاست مصوب اپراتور فعال می‌شود؛ پیشنهاد ۱۸۰ روز است. پیام‌های تلگرام و نسخه‌های پشتیبان قواعد نگهداری جداگانه دارند.',[[['text'=>'درخواست حذف حساب','callback_data'=>'p:erase:0']],[['text'=>'بازگشت','callback_data'=>'p:home:0']]]);
    }

    public function input(string $state, array $payload, string $text): bool
    {
        if($state==='phone')throw new RuleViolation('برای امنیت فقط دکمه «اشتراک شماره خودم» را بزنید. شماره تایپی پذیرفته نمی‌شود.');
        if($state==='national'){
            $this->profiles->saveNational($this->conversation->actor(),$text);
            $this->conversation->clear();
            $this->conversation->reply('کد ملی به‌صورت رمزگذاری‌شده ثبت شد. پروفایل شما تکمیل است.',$this->conversation->menu());
            return true;
        }
        if($state==='name'){
            $this->profiles->saveName($this->conversation->actor(), $text);
            $this->conversation->state('phone',[]);
            $this->contactPrompt();
            return true;
        }
        return false;
    }

}
