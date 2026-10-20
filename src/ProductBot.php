<?php

final class ProductBot
{
    private int $sequence=0;
    private int $actor=0;
    private int $update=0;
    public function __construct(private PDO $pdo,private BookingService $bookings,private AccessControl $access,private Outbox $outbox,private string $timezone='Asia/Tehran') {}

    public function dispatch(int $actor,?string $text,?string $callback,int $update,?array $contact=null):bool
    {
        $this->actor=$actor; $this->update=$update; $this->sequence=0;
        $session=$this->session();
        if($text!==null&&preg_match('/^\/(start|help|tours|bookings|profile|admin|cancel|privacy|search)(?:@[A-Za-z0-9_]+)?(?:\s.*)?$/u',$text,$match)) {
            $command=$match[1];
            $this->clear();
            Database::run($this->pdo,"UPDATE Data SET step='defult' WHERE UserId=?",[$actor]);
            if($command==='start'||$command==='help'||$command==='cancel') {
                $this->reply($command==='cancel'?'عملیات جاری متوقف شد.':'خوش آمدید. برای تکمیل پروفایل، نام سرپرست، شماره اشتراک‌گذاشته‌شده از حساب خودتان و کد ملی معتبر را ثبت کنید. قیمت‌ها ریال و تاریخ‌ها میلادی با ساعت تهران هستند.\nاز دکمه‌ها استفاده کنید؛ /cancel در هر مرحله عملیات را متوقف می‌کند.',$this->menu());
            } elseif($command==='tours') $this->listing(0);
            elseif($command==='bookings') $this->mine(0);
            elseif($command==='profile') $this->profile();
            elseif($command==='admin') $this->admin();
            elseif($command==='privacy') $this->privacy();
            elseif($command==='search') { $this->state('search',[]);$this->reply('عنوان یا مقصد تور را وارد کنید. برای خروج /cancel بفرستید.'); }
            return true;
        }
        if($callback!==null&&str_starts_with($callback,'p:')) {
            if(!preg_match('/^p:([a-z_]{1,24}):([0-9]{1,16})(?::([0-9]{1,16}))?(?::([0-9]{1,16}))?$/D',$callback,$parts)) throw new RuleViolation('دکمه معتبر نیست. از منوی جدید استفاده کنید.');
            $action=$parts[1];$id=(int)$parts[2];$arg=(int)($parts[3]??0);$version=(int)($parts[4]??0);
            switch($action) {
                case 'home': $this->clear();$this->reply('انتخاب کنید:',$this->menu());break;
                case 'help': $this->reply('راهنما: پروفایل ← ثبت نام سرپرست؛ تورها ← جزئیات ← تعداد نفرات ← تأیید درخواست.\nدرخواست منتظر بررسی ظرفیت را نگه می‌دارد. تور پولی به تأیید دستی پرداخت و سپس تأیید رزرو نیاز دارد. لغو تا پیش از شروع تور مجاز است.\n/cancel برای خروج از فرم؛ /privacy برای حریم خصوصی.',$this->menu());break;
                case 'profile': $this->profile();break;
                case 'name': $this->state('name',[]);$this->reply('نام سرپرست گروه را وارد کنید (حداکثر ۵۰ حرف).');break;
                case 'phone':
                    $name=Database::run($this->pdo,'SELECT Name FROM Data WHERE UserId=?',[$actor])->fetchColumn();
                    if(!is_string($name)||trim($name)==='')throw new RuleViolation('ابتدا نام سرپرست را در پروفایل ثبت کنید.');
                    $this->state('phone',[]);$this->contactPrompt();break;
                case 'national':
                    if(!(new TravelerIdentity($this->pdo))->status($actor)['mobile'])throw new RuleViolation('ابتدا شماره خود را با دکمه اشتراک مخاطب تلگرام ثبت کنید.');
                    $this->state('national',[]);$this->reply('کد ملی ۱۰ رقمی سرپرست را وارد کنید. کد ملی فقط برای هماهنگی هویت ذخیره می‌شود و در پیام‌ها یا خروجی عمومی نمایش داده نمی‌شود.');break;
                case 'privacy': $this->privacy();break;
                case 'erase': $this->state('erase_confirm',[]);$this->reply('حذف حساب، نام و پروفایل قدیمی و اطلاعات همراهان را پاک و رزروهای بسته‌شده را بی‌نام می‌کند. ابتدا تمام رزروهای فعال را لغو کنید. شمارنده امنیتی موقت تا ۲۴ ساعت باقی می‌ماند؛ پشتیبان‌ها و پیام‌های قبلی تلگرام تابع سیاست جداگانه‌اند. تأیید می‌کنید؟',[[['text'=>'بله، حذف حساب','callback_data'=>'p:erase_yes:0']],[['text'=>'انصراف','callback_data'=>'p:home:0']]]);break;
                case 'erase_yes': $this->requireSession('erase_confirm');(new PrivacyService($this->pdo))->erase($actor);$this->reply('حساب و اطلاعات قابل حذف پاک شد. با /start می‌توانید دوباره شروع کنید.');break;
                case 'tours': $this->listing($id);break;
                case 'search': $this->state('search',[]);$this->reply('عنوان یا مقصد را وارد کنید:');break;
                case 'cats': $this->categories($id,$arg);break;
                case 'cat_tours': $this->listing($arg,$id);break;
                case 'tour': $this->showTour($id);break;
                case 'reserve': $this->showTour($id);$this->state('quantity',['tour'=>$id]);$this->reply('چند نفر هستید؟ عددی بین ۱ تا ۲۰ وارد کنید. ظرفیت شامل سرپرست و همراهان است.');break;
                case 'reserve_yes':
                    $booking=$this->bookings->reserve($actor,$id,$arg,"update:$update",$version);
                    $this->clear();$this->showBooking($booking);break;
                case 'mine': $this->mine($id);break;
                case 'booking': $this->showBooking($id);break;
                case 'history': $this->history($id,false);break;
                case 'contact': $this->bookings->booking($actor,$id);$this->state('support_message',['booking'=>$id]);$this->reply('پیام مربوط به این رزرو را برای مدیریت بنویسید. رمز، اطلاعات کارت یا کد ملی نفرستید. /cancel برای انصراف.');break;
                case 'message': $this->access->require($actor,'notify');$this->bookings->booking($actor,$id,true);$this->state('admin_message',['booking'=>$id]);$this->reply('متن راهنمایی یا پاسخ به سرپرست این رزرو را بنویسید. پیام از طریق همین ربات ارسال می‌شود. /cancel برای انصراف.');break;
                case 'cancel_booking': $this->bookings->booking($actor,$id);$this->state('cancel_confirm',['booking'=>$id]);$this->reply('لغو رزرو باعث آزادشدن ظرفیت می‌شود. استرداد وجه خودکار نیست و باید با مدیر هماهنگ شود. لغو می‌کنید؟',[[['text'=>'تأیید لغو','callback_data'=>"p:cancel_yes:$id"]],[['text'=>'بازگشت','callback_data'=>"p:booking:$id"]]]);break;
                case 'cancel_yes': $confirmation=$this->requireSession('cancel_confirm');if((int)$confirmation['booking']!==$id)throw new RuleViolation('تأیید لغو معتبر نیست.');$this->clear();$this->bookings->transition($actor,$id,'cancelled');$this->showBooking($id);break;
                case 'admin': $this->admin();break;
                case 'manage_tours': $this->listing($id,null,true);break;
                case 'new': $this->access->require($actor,'tours');$this->state('tour_title',['publication'=>'draft']);$this->reply('عنوان تور جدید را وارد کنید:');break;
                case 'edit': $this->access->require($actor,'tours');$editTour=$this->bookings->tour($id);$this->state('tour_title',['id'=>$id,'publication'=>$editTour['publication'],'expected_version'=>(int)$editTour['version']]);$this->reply('ویرایش کامل تور: عنوان جدید را وارد کنید. قیمت رزروهای قبلی ثابت می‌ماند. /cancel برای انصراف.');break;
                case 'publish': case 'inactive':
                    $this->bookings->publication($actor,$id,$action==='publish'?'published':'inactive');$this->showTour($id);break;
                case 'archive': $this->access->require($actor,'tours');$this->state('archive_confirm',['tour'=>$id]);$this->reply('تور از دسترس ثبت درخواست خارج و سوابق رزرو حفظ می‌شود. رزروهای فعال خودکار لغو نمی‌شوند. تأیید می‌کنید؟',[[['text'=>'تأیید بایگانی','callback_data'=>"p:archive_yes:$id"]],[['text'=>'انصراف','callback_data'=>"p:tour:$id"]]]);break;
                case 'archive_yes':
                    $confirmation=$this->requireSession('archive_confirm');if((int)$confirmation['tour']!==$id)throw new RuleViolation('تأیید بایگانی معتبر نیست.');$this->clear();
                    $this->bookings->publication($actor,$id,'archived');$this->reply('تور بایگانی شد.',$this->menu());break;
                case 'category_new': $this->access->require($actor,'tours');$this->state('category_parent',[]);$this->reply('شناسه دسته والد را وارد کنید؛ صفر یعنی دسته اصلی. شناسه‌ها در «دسته‌ها» نمایش داده می‌شوند.');break;
                case 'category_delete': $this->access->require($actor,'tours');$this->state('category_delete',[]);$this->reply('شناسه دسته‌ای را که باید حذف شود وارد کنید:');break;
                case 'cat_delete_yes':
                    $confirmation=$this->requireSession('category_delete_confirm');if((int)$confirmation['category']!==$id)throw new RuleViolation('تأیید حذف دسته معتبر نیست.');$this->clear();
                    $this->access->require($actor,'tours');
                    try { $count=(new TopicCatalogue($this->pdo))->deleteTree($id); }
                    catch(PDOException $error) { if(($error->errorInfo[1]??null)===1451)throw new RuleViolation('دسته به تور مرتبط است؛ ابتدا دسته تور را تغییر دهید.');throw $error; }
                    $this->access->audit($actor,'category.delete','category',$id);$this->reply("تعداد دسته‌های حذف‌شده: $count");break;
                case 'pending': $this->pending($id);break;
                case 'all_bookings': $this->allBookings($id);break;
                case 'tour_bookings': $this->allBookings($arg,$id);break;
                case 'review': $this->showBooking($id,true);break;
                case 'review_history': $this->history($id,true);break;
                case 'confirm': $this->bookings->transition($actor,$id,'confirmed',null,true);$this->showBooking($id,true);break;
                case 'complete': $this->bookings->transition($actor,$id,'completed',null,true);$this->showBooking($id,true);break;
                case 'reject': $this->access->require($actor,'review');$this->state('reject',['booking'=>$id]);$this->reply('دلیل رد درخواست را وارد کنید؛ برای رد بدون دلیل، علامت - بفرستید.');break;
                case 'pay': $this->access->require($actor,'payments');$this->bookings->booking($actor,$id,true);$this->state('pay_reference',['booking'=>$id]);$this->reply('پس از بررسی واقعی واریز، یک شناسه یکتا با ۳ تا ۸۰ حرف یا عدد انگلیسی وارد کنید. هرگز شماره کارت یا اطلاعات بانکی مسافر را وارد نکنید.');break;
                case 'pay_yes': $payload=$this->requireSession('pay_confirm');if((int)$payload['booking']!==$id)throw new RuleViolation('فرم منقضی شده است.');$this->bookings->manualPayment($actor,$id,$payload['reference']);$this->clear();$this->showBooking($id,true);break;
                case 'report': $this->report($id);break;
                case 'report_select': $this->access->require($actor,'reports');$this->state('report',[]);$this->reply('شناسه تور را وارد کنید:');break;
                case 'export': $this->export($id);break;
                case 'roles': $this->access->require($actor,'roles');$this->state('role_user',[]);$this->reply('شناسه عددی تلگرام مدیر را وارد کنید:');break;
                case 'role_yes': $payload=$this->requireSession('role_confirm');if((int)$payload['user']!==$id)throw new RuleViolation('فرم معتبر نیست.');$this->access->assign($actor,$id,$payload['role']);$this->clear();$this->reply('نقش مدیر به‌روز شد.');break;
                case 'notify': $this->access->require($actor,'notify');$this->notifications();break;
                case 'retry': $this->outbox->retry($actor,$id,$this->access);$this->reply('اعلان ناموفق برای تلاش مجدد صف شد.');break;
                case 'broadcast': $this->access->require($actor,'notify');$this->state('broadcast_target',[]);$this->reply('شناسه تور را برای ارسال به مسافران فعال وارد کنید؛ صفر یعنی همه حساب‌ها.');break;
                case 'broadcast_yes': $payload=$this->requireSession('broadcast_confirm');$this->broadcast($payload['tour'],$payload['text']);$this->clear();break;
                default: throw new RuleViolation('این دکمه دیگر معتبر نیست. از /start شروع کنید.');
            }
            return true;
        }
        if($text!==null && preg_match('/^\/(?:setprofile|list|send)(?:\s|$)/',$text)){$this->clear();return false;}
        if($session!==null&&$session['state']==='phone'&&$contact!==null){
            (new TravelerIdentity($this->pdo))->saveMobile($actor,$contact);
            $this->state('national',[]);
            $this->outbox->enqueue($actor,'sendMessage',['text'=>'شماره حساب شما ثبت شد. اکنون کد ملی ۱۰ رقمی سرپرست را وارد کنید.','reply_markup'=>json_encode(['remove_keyboard'=>true],JSON_THROW_ON_ERROR)],"update:{$this->update}:reply:".($this->sequence++));
            return true;
        }
        if($session!==null&&$session['state']!=='filter'&&$text!==null&&!str_starts_with($text,'/')) {
            $this->input($session['state'],json_decode($session['payload'],true,512,JSON_THROW_ON_ERROR),trim($text));return true;
        }
        // Keep the established profile/catalogue/approval commands available.
        if($callback!==null||($text!==null&&(preg_match('/^(?:\/(?:list|setprofile|send)|Topics|ping|usage)(?:\s|$)/',$text)||preg_match('/^[\/#!]?(?:AddTopic) /i',$text)))) return false;
        $legacyStep=Database::run($this->pdo,'SELECT step FROM Data WHERE UserId=?',[$actor])->fetchColumn();
        if($legacyStep&&$legacyStep!=='defult')return false;
        $this->reply('پیام را متوجه نشدم. از دکمه‌ها استفاده کنید یا /help بفرستید.',$this->menu());return true;
    }

    private function contactPrompt():void {
        $markup=['keyboard'=>[[['text'=>'اشتراک شماره خودم','request_contact'=>true]]],'resize_keyboard'=>true,'one_time_keyboard'=>true,'selective'=>true];
        $this->outbox->enqueue($this->actor,'sendMessage',['text'=>'برای ثبت شماره فقط دکمه زیر را بزنید؛ شماره تایپی یا کارت مخاطب دیگران پذیرفته نمی‌شود. از اطلاعات فقط برای هماهنگی رزرو استفاده می‌شود.','reply_markup'=>json_encode($markup,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)],"update:{$this->update}:reply:".($this->sequence++));
    }
    private function reply(string $text,array $buttons=[],?int $subject=null,array $permissions=[]):void {
        $this->outbox->message($this->actor,str_replace('\\n',"\n",$text),"update:{$this->update}:reply:".($this->sequence++),$buttons,$subject,$permissions);
    }
    private function menu():array {
        $buttons=[[['text'=>'تور‌ها','callback_data'=>'p:tours:0'],['text'=>'دسته‌ها','callback_data'=>'p:cats:0']],[['text'=>'جست‌وجوی تور','callback_data'=>'p:search:0'],['text'=>'رزروهای من','callback_data'=>'p:mine:0']],[['text'=>'پروفایل','callback_data'=>'p:profile:0'],['text'=>'راهنما','callback_data'=>'p:help:0']]];
        if($this->access->role($this->actor)!==null)$buttons[]=[['text'=>'مدیریت','callback_data'=>'p:admin:0']];
        return $buttons;
    }
    private function state(string $state,array $payload):void {
        Database::run($this->pdo,'INSERT INTO BotSessions(user_id,state,payload) VALUES (?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state),payload=VALUES(payload),updated_at=UTC_TIMESTAMP()',[$this->actor,$state,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }
    private function session():?array {
        $row=Database::run($this->pdo,'SELECT * FROM BotSessions WHERE user_id=?',[$this->actor])->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        if($row['updated_at']<gmdate('Y-m-d H:i:s',time()-1800)){$this->clear();return null;}
        return $row;
    }
    private function requireSession(string $state):array {
        $session=$this->session();if(!$session||$session['state']!==$state)throw new RuleViolation('فرم منقضی شده است؛ دوباره از منو شروع کنید.');
        return json_decode($session['payload'],true,512,JSON_THROW_ON_ERROR);
    }
    private function clear():void {Database::run($this->pdo,'DELETE FROM BotSessions WHERE user_id=?',[$this->actor]);}
    private function number(string $text,int $max=4503599627370495):int {
        $text=strtr($text,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        if(!preg_match('/^[0-9]{1,16}$/D',$text)||(float)$text>$max)throw new RuleViolation('یک عدد معتبر وارد کنید.');return(int)$text;
    }
    private function date(string $text):string {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$text,new DateTimeZone($this->timezone));
        if(!$date||$date->format('Y-m-d H:i')!==$text)throw new RuleViolation('تاریخ میلادی و ساعت را مانند 2027-04-21 08:30 وارد کنید.');
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    private function local(string $utc):string {return(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($this->timezone))->format('Y-m-d H:i');}
    private function profile():void {
        $name=Database::run($this->pdo,'SELECT Name FROM Data WHERE UserId=?',[$this->actor])->fetchColumn();
        $this->reply('نام سرپرست: '.($name?:'ثبت نشده')."\nشناسه حساب: {$this->actor}\nفقط نام برای رزرو لازم است.",[[['text'=>'ثبت یا ویرایش نام','callback_data'=>'p:name:0']],[['text'=>'حریم خصوصی و حذف حساب','callback_data'=>'p:privacy:0']],[['text'=>'بازگشت','callback_data'=>'p:home:0']]]);
    }
    private function privacy():void {
        $this->reply('برای مسیر جدید رزرو فقط نام سرپرست و تعداد همراهان جمع‌آوری می‌شود. اطلاعات قدیمی پروفایل تا حذف حساب حفظ می‌شوند. پاک‌سازی خودکار حساب غیرفعال فقط پس از فعال‌سازی سیاست توسط اپراتور و بدون رزرو فعال انجام می‌شود؛ مدت پیشنهادی ۱۸۰ روز است. سوابق عددی رزرو و پرداخت بدون اتصال به حساب برای گزارش باقی می‌مانند. شمارنده محدودیت پیام حداکثر ۲۴ ساعت می‌ماند تا حذف حساب محدودیت امنیتی را دور نزند. پیام‌های تلگرام و پشتیبان‌های اپراتور جداگانه نگهداری می‌شوند.',[[['text'=>'درخواست حذف حساب','callback_data'=>'p:erase:0']],[['text'=>'بازگشت','callback_data'=>'p:home:0']]]);
    }
    private function listing(int $offset,?int $category=null,bool $admin=false):void {
        if($offset>100000)throw new RuleViolation('شماره صفحه معتبر نیست.');
        if($admin)$this->access->require($this->actor,'tours');
        $args=[];$where=$admin?'1=1':"publication='published' AND starts_at>UTC_TIMESTAMP()";
        if($category!==null){$where.=' AND category_id=?';$args[]=$category;}
        $session=$this->session();
        if(!$admin&&$session&&$session['state']==='filter'){$filter=json_decode($session['payload'],true)['query'];$where.=' AND (LOCATE(?,title)>0 OR LOCATE(?,destination)>0)';$args[]=$filter;$args[]=$filter;}
        $rows=Database::run($this->pdo,"SELECT id,title FROM Tours WHERE $where ORDER BY starts_at,id LIMIT 10 OFFSET $offset",$args)->fetchAll(PDO::FETCH_ASSOC);
        $buttons=[];foreach($rows as$row)$buttons[]=[['text'=>$row['title'],'callback_data'=>'p:tour:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'صفحه بعد','callback_data'=>$admin?'p:manage_tours:'.($offset+10):($category!==null?'p:cat_tours:'.$category.':'.($offset+10):'p:tours:'.($offset+10))]];
        $buttons[]=[['text'=>'بازگشت به منو','callback_data'=>'p:home:0']];
        $this->reply($rows===[]?'توری مطابق این انتخاب پیدا نشد. برای پاک‌کردن جست‌وجو از منوی اصلی دوباره وارد شوید.':'تور را انتخاب کنید:',$buttons);
    }
    private function categories(int $parent,int $offset):void {
        if($offset>100000)throw new RuleViolation('صفحه معتبر نیست.');
        $rows=Database::run($this->pdo,"SELECT id,Name FROM Topics WHERE `Groups`=? ORDER BY id LIMIT 20 OFFSET $offset",[$parent])->fetchAll(PDO::FETCH_ASSOC);
        $buttons=[];foreach($rows as$row)$buttons[]=[['text'=>$row['id'].' — '.$row['Name'],'callback_data'=>'p:cats:'.$row['id']]];
        if($parent>0)$buttons[]=[['text'=>'تورهای این دسته','callback_data'=>"p:cat_tours:$parent:0"]];
        if(count($rows)===20)$buttons[]=[['text'=>'صفحه بعد','callback_data'=>"p:cats:$parent:".($offset+20)]];
        $buttons[]=[['text'=>'دسته‌های اصلی','callback_data'=>'p:cats:0'],['text'=>'منو','callback_data'=>'p:home:0']];
        $this->reply('دسته‌بندی‌ها؛ عدد کنار نام، شناسه دسته است:',$buttons);
    }
    private function showTour(int $id):void {
        $tour=$this->bookings->tour($id);
        if($tour['publication']!=='published'&&!$this->access->allows($this->actor,'tours')&&!Database::run($this->pdo,'SELECT id FROM Bookings WHERE user_id=? AND tour_id=?',[$this->actor,$id])->fetchColumn())throw new RuleViolation('این تور منتشر نشده است.');
        $remaining=(int)$tour['capacity']-$this->bookings->used($id);
        $text="تور #$id: {$tour['title']}\nمقصد: {$tour['destination']}\n{$tour['description']}\nشروع: ".$this->local($tour['starts_at'])."\nپایان: ".$this->local($tour['ends_at'])."\nمنطقه زمانی: {$this->timezone}\nقیمت هر نفر: {$tour['price']} ریال\nظرفیت کل: {$tour['capacity']}؛ باقی‌مانده: $remaining\nوضعیت انتشار: ".(['published'=>'فعال','draft'=>'پیش‌نویس','inactive'=>'غیرفعال','archived'=>'بایگانی'][$tour['publication']]??'نامشخص');
        $buttons=[];
        if($tour['publication']==='published'&&$remaining>0&&$tour['starts_at']>gmdate('Y-m-d H:i:s'))$buttons[]=[['text'=>'درخواست رزرو','callback_data'=>"p:reserve:$id"]];
        if($this->access->allows($this->actor,'tours')){
            $buttons[]=[['text'=>'ویرایش','callback_data'=>"p:edit:$id"],['text'=>'انتشار','callback_data'=>"p:publish:$id"]];
            $buttons[]=[['text'=>'غیرفعال','callback_data'=>"p:inactive:$id"],['text'=>'بایگانی','callback_data'=>"p:archive:$id"]];
        }
        if($this->access->allows($this->actor,'reports'))$buttons[]=[['text'=>'گزارش ظرفیت','callback_data'=>"p:report:$id"]];
        $buttons[]=[['text'=>'بازگشت','callback_data'=>'p:home:0']];$this->reply($text,$buttons);
    }
    private function mine(int $offset):void {
        if($offset>100000)throw new RuleViolation('صفحه معتبر نیست.');
        $rows=Database::run($this->pdo,"SELECT id,status FROM Bookings WHERE user_id=? ORDER BY id DESC LIMIT 10 OFFSET $offset",[$this->actor])->fetchAll(PDO::FETCH_ASSOC);
        $buttons=[];foreach($rows as$row)$buttons[]=[['text'=>'رزرو #'.$row['id'].' — '.$this->status($row['status']),'callback_data'=>'p:booking:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'بعدی','callback_data'=>'p:mine:'.($offset+10)]];
        $buttons[]=[['text'=>'منو','callback_data'=>'p:home:0']];$this->reply($rows?'رزروهای جاری و قبلی شما:':'هنوز رزروی ندارید.',$buttons);
    }
    private function status(string $status):string {return['pending'=>'در انتظار','confirmed'=>'تأییدشده','rejected'=>'ردشده','cancelled'=>'لغوشده','completed'=>'تکمیل‌شده','paid'=>'پرداخت‌شده','expired'=>'منقضی','failed'=>'ناموفق'][$status]??$status;}
    private function showBooking(int $id,bool $admin=false):void {
        $booking=$this->bookings->booking($this->actor,$id,$admin);
        $text="رزرو #$id\nتور: {$booking['title']}\nشروع: ".$this->local($booking['starts_at'])."\nتعداد نفرات: {$booking['quantity']}\nمبلغ کل: {$booking['amount']} ریال\nوضعیت رزرو: ".$this->status($booking['status'])."\nوضعیت پرداخت: ".$this->status($booking['payment_status']);
        if($booking['payment_status']==='pending'&&(int)$booking['amount']>0)$text.="\nپرداخت آنلاین فعال نیست. پس از هماهنگی با مدیر، واریز دستی بررسی می‌شود. پیش از هماهنگی، مبلغی واریز نکنید. مهلت بررسی اولیه پرداخت ۲۴ ساعت است.";
        $buttons=[];
        if($admin && $booking['user_id'] !== null && $this->access->allows($this->actor,'profiles')) {
            $leader = Database::run($this->pdo,'SELECT Name FROM Data WHERE UserId=?',[$booking['user_id']])->fetchColumn();
            $text .= "\nنام سرپرست: " . ($leader ?: 'ثبت نشده');
            $this->access->audit($this->actor,'profile.view','user',(int)$booking['user_id']);
        }
        if($admin){
            if($booking['status']==='pending')$buttons[]=[['text'=>'تأیید رزرو','callback_data'=>"p:confirm:$id"],['text'=>'رد با دلیل اختیاری','callback_data'=>"p:reject:$id"]];
            if($this->access->allows($this->actor,'payments')&&$booking['payment_status']!=='paid')$buttons[]=[['text'=>'بررسی پرداخت دستی','callback_data'=>"p:pay:$id"]];
            if($booking['status']==='confirmed')$buttons[]=[['text'=>'تکمیل پس از پایان تور','callback_data'=>"p:complete:$id"]];
        }elseif(in_array($booking['status'],['pending','confirmed'],true))$buttons[]=[['text'=>'لغو رزرو','callback_data'=>"p:cancel_booking:$id"]];
        if($admin && $this->access->allows($this->actor,'notify') && $booking['user_id']!==null)$buttons[]=[['text'=>'پیام به سرپرست','callback_data'=>"p:message:$id"]];
        elseif(!$admin)$buttons[]=[['text'=>'پیام به مدیریت درباره رزرو','callback_data'=>"p:contact:$id"]];
        $buttons[]=[['text'=>'جزئیات تور','callback_data'=>'p:tour:'.$booking['tour_id']]];
        $buttons[]=[['text'=>'تاریخچه وضعیت','callback_data'=>($admin?'p:review_history:':'p:history:').$id]];
        $buttons[]=[['text'=>'بازگشت','callback_data'=>$admin?'p:pending:0':'p:mine:0']];$this->reply($text,$buttons,$admin && $booking['user_id'] !== null ? (int)$booking['user_id'] : null,$admin?($this->access->allows($this->actor,'profiles')?['review','profiles']:['review']):[]);
    }
    private function history(int $id,bool $admin):void {
        $this->bookings->booking($this->actor,$id,$admin);
        $rows=Database::run($this->pdo,'SELECT new_status,created_at FROM BookingStatusHistory WHERE booking_id=? ORDER BY id DESC LIMIT 20',[$id])->fetchAll(PDO::FETCH_ASSOC);
        $lines=array_map(fn($row)=>$this->local($row['created_at']).' — '.$this->status($row['new_status']),$rows);$this->reply(implode("\n",$lines));
    }
    private function admin():void {
        if($this->access->role($this->actor)===null)throw new RuleViolation('دسترسی مدیریت ندارید.');
        $buttons=[];
        if($this->access->allows($this->actor,'tours'))$buttons=array_merge($buttons,[[['text'=>'ایجاد تور','callback_data'=>'p:new:0'],['text'=>'مدیریت تورها','callback_data'=>'p:manage_tours:0']],[['text'=>'ایجاد دسته','callback_data'=>'p:category_new:0'],['text'=>'حذف دسته','callback_data'=>'p:category_delete:0']]]);
        if($this->access->allows($this->actor,'review'))$buttons[]=[['text'=>'درخواست‌های منتظر','callback_data'=>'p:pending:0'],['text'=>'همه رزروها','callback_data'=>'p:all_bookings:0']];
        if($this->access->allows($this->actor,'reports'))$buttons[]=[['text'=>'گزارش تور','callback_data'=>'p:report_select:0']];
        if($this->access->allows($this->actor,'notify'))$buttons[]=[['text'=>'وضعیت اعلان‌ها','callback_data'=>'p:notify:0'],['text'=>'ارسال گروهی','callback_data'=>'p:broadcast:0']];
        if($this->access->allows($this->actor,'roles'))$buttons[]=[['text'=>'مدیریت نقش‌ها','callback_data'=>'p:roles:0']];
        $buttons[]=[['text'=>'منو','callback_data'=>'p:home:0']];$this->reply('مدیریت؛ نقش شما: '.(['owner'=>'مدیر اصلی','manager'=>'مدیر','reviewer'=>'بررسی درخواست‌ها','reporter'=>'گزارش‌گیر'][$this->access->role($this->actor)]??'نامشخص'),$buttons);
    }
    private function pending(int $offset):void {
        $this->access->require($this->actor,'review');if($offset>100000)throw new RuleViolation('صفحه معتبر نیست.');
        $rows=Database::run($this->pdo,"SELECT id,tour_id,quantity FROM Bookings WHERE status='pending' ORDER BY id LIMIT 10 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
        $buttons=[];foreach($rows as$row)$buttons[]=[['text'=>"رزرو {$row['id']} / تور {$row['tour_id']} / {$row['quantity']} نفر",'callback_data'=>'p:review:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'بعدی','callback_data'=>'p:pending:'.($offset+10)]];
        $buttons[]=[['text'=>'مدیریت','callback_data'=>'p:admin:0']];$this->reply($rows?'درخواست‌های منتظر بررسی:':'درخواست منتظری وجود ندارد.',$buttons);
    }
    private function allBookings(int $offset,?int $tour=null):void {
        $this->access->require($this->actor,'review');if($offset>100000)throw new RuleViolation('صفحه معتبر نیست.');
        $where=$tour===null?'1=1':'tour_id=?';$args=$tour===null?[]:[$tour];
        $rows=Database::run($this->pdo,"SELECT id,status,quantity FROM Bookings WHERE $where ORDER BY id DESC LIMIT 10 OFFSET $offset",$args)->fetchAll(PDO::FETCH_ASSOC);
        $buttons=[];foreach($rows as$row)$buttons[]=[['text'=>'رزرو #'.$row['id'].' / '.$this->status($row['status']).' / '.$row['quantity'].' نفر','callback_data'=>'p:review:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'صفحه بعد','callback_data'=>$tour===null?'p:all_bookings:'.($offset+10):"p:tour_bookings:$tour:".($offset+10)]];
        $buttons[]=[['text'=>'مدیریت','callback_data'=>'p:admin:0']];$this->reply('رزروها با وضعیت جاری و قبلی:',$buttons);
    }
    private function report(int $id):void {
        $this->access->require($this->actor,'reports');$tour=$this->bookings->tour($id);
        $rows=Database::run($this->pdo,'SELECT status,COUNT(*) requests,SUM(quantity) seats FROM Bookings WHERE tour_id=? GROUP BY status',[$id])->fetchAll(PDO::FETCH_ASSOC);
        $text="گزارش تور #$id\nظرفیت: {$tour['capacity']}\nباقی‌مانده: ".($tour['capacity']-$this->bookings->used($id));
        foreach($rows as$row)$text.="\n".$this->status($row['status']).": {$row['requests']} درخواست / {$row['seats']} نفر";
        $buttons=[];if($this->access->allows($this->actor,'review'))$buttons[]=[['text'=>'رزروهای این تور','callback_data'=>"p:tour_bookings:$id:0"]];if($this->access->allows($this->actor,'export'))$buttons[]=[['text'=>'خروجی CSV بدون اطلاعات هویتی','callback_data'=>"p:export:$id"]];$buttons[]=[['text'=>'مدیریت','callback_data'=>'p:admin:0']];$this->reply($text,$buttons,null,['reports']);
    }
    private function export(int $id):void {
        $this->access->require($this->actor,'export');$this->bookings->tour($id);
        $rows=Database::run($this->pdo,'SELECT b.id,t.title,b.quantity,b.status,p.amount,p.status AS payment_status FROM Bookings b JOIN Tours t ON t.id=b.tour_id JOIN Payments p ON p.booking_id=b.id WHERE b.tour_id=? ORDER BY b.id LIMIT 5001',[$id])->fetchAll(PDO::FETCH_NUM);
        if(count($rows)>5000)throw new RuleViolation('گزارش بیش از ۵۰۰۰ ردیف دارد؛ با اپراتور برای خروجی محدود هماهنگ کنید.');
        $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['booking_id','tour','quantity','status','amount_IRR','payment_status'], ',', '"', '');
        foreach($rows as$row)fputcsv($stream,array_map(fn($value)=>BookingRules::csv((string)$value),$row), ',', '"', '');rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
        $this->outbox->enqueue($this->actor,'sendDocument',['caption'=>'گزارش رزرو بدون نام، موبایل یا کد ملی','_csv'=>$csv,'_export_actor'=>$this->actor],"export:{$this->update}:$id");$this->access->audit($this->actor,'booking.export','tour',$id);$this->reply('فایل در صف ارسال امن قرار گرفت.');
    }
    private function notifications():void {
        $rows=Database::run($this->pdo,'SELECT status,COUNT(*) total FROM NotificationOutbox GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
        $text='وضعیت صف اعلان‌ها:';foreach($rows as$row)$text.="\n{$row['status']}: {$row['total']}";
        $failed=Database::run($this->pdo,"SELECT id,error_code FROM NotificationOutbox WHERE status='failed' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);$buttons=[];
        foreach($failed as$row)$buttons[]=[['text'=>"تلاش مجدد #{$row['id']} ({$row['error_code']})",'callback_data'=>'p:retry:'.$row['id']]];
        $this->reply($text,$buttons);
    }
    private function broadcast(int $tour,string $text):void {
        $this->access->require($this->actor,'notify');$after=0;$count=0;
        do{
            $rows=$tour===0?Database::run($this->pdo,'SELECT UserId AS id FROM Data WHERE UserId>? ORDER BY UserId LIMIT 200',[$after])->fetchAll(PDO::FETCH_ASSOC):Database::run($this->pdo,"SELECT DISTINCT user_id AS id FROM Bookings WHERE tour_id=? AND user_id>? AND status IN ('pending','confirmed') ORDER BY user_id LIMIT 200",[$tour,$after])->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as$row){
                $after=(int)$row['id'];
                $this->outbox->enqueue($after,'sendMessage',['text'=>$text,'_permission_actor'=>$this->actor,'_required_permissions'=>['notify']],"broadcast:{$this->update}:$after");
                $count++;
            }
        }while(count($rows)===200);
        $this->access->audit($this->actor,'notification.broadcast','tour',$tour);$this->reply("برای $count حساب در صف قرار گرفت؛ نتیجه ارسال را از وضعیت اعلان‌ها پیگیری کنید.");
    }
    private function input(string $state,array $payload,string $text):void {
        if($state==='phone')throw new RuleViolation('برای امنیت فقط دکمه «اشتراک شماره خودم» را بزنید. شماره تایپی پذیرفته نمی‌شود.');
        if($state==='national'){
            (new TravelerIdentity($this->pdo))->saveNational($this->actor,$text);
            $this->clear();$this->reply('کد ملی به‌صورت رمزگذاری‌شده ثبت شد. پروفایل شما تکمیل است.',$this->menu());return;
        }
        if($state==='name'){
            if($text===''||strlen($text)>150)throw new RuleViolation('نام باید کوتاه و غیرخالی باشد.');
            Database::run($this->pdo,"UPDATE Data SET Name=?,profile='true' WHERE UserId=?",[$text,$this->actor]);$this->state('phone',[]);$this->contactPrompt();return;
        }
        if($state==='support_message'||$state==='admin_message'){
            if($text===''||strlen($text)>2000)throw new RuleViolation('پیام را کوتاه‌تر وارد کنید.');
            $id=(int)$payload['booking'];
            if($state==='support_message'){
                $this->bookings->booking($this->actor,$id);
                foreach($this->access->reviewers()as$admin){
                    if(!$this->access->allows($admin,'notify'))continue;
                    $this->outbox->message($admin,"پیام مسافر درباره رزرو #$id:\n$text","support:{$this->update}:$admin",[[['text'=>'پاسخ به سرپرست','callback_data'=>"p:message:$id"]]],$this->actor,['notify','review']);
                }
            }else{
                $this->access->require($this->actor,'notify');$booking=$this->bookings->booking($this->actor,$id,true);
                if($booking['user_id']===null)throw new RuleViolation('حساب این مسافر حذف شده است.');
                $this->outbox->enqueue((int)$booking['user_id'],'sendMessage',['text'=>"پیام مدیریت درباره رزرو #$id:\n$text",'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'پاسخ به مدیریت','callback_data'=>"p:contact:$id"]]]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'_subject_user'=>(int)$booking['user_id'],'_permission_actor'=>$this->actor,'_required_permissions'=>['notify','review']],"staff-message:{$this->update}:$id");
                $this->access->audit($this->actor,'booking.message','booking',$id);
            }
            $this->clear();$this->reply('پیام در صف ارسال قرار گرفت. پاسخ از طریق همین ربات به شما می‌رسد.');return;
        }
        if($state==='search'){if($text===''||strlen($text)>200)throw new RuleViolation('عبارت جست‌وجو را کوتاه وارد کنید.');$this->state('filter',['query'=>$text]);$this->listing(0);return;}
        if($state==='quantity'){
            $qty=$this->number($text,20);BookingRules::quantity($qty);$tour=$this->bookings->tour((int)$payload['tour']);
            $this->reply("درخواست {$tour['title']} برای $qty نفر؛ مبلغ کل: ".($qty*(int)$tour['price']).' ریال. ثبت می‌کنید؟',[[['text'=>'تأیید درخواست','callback_data'=>"p:reserve_yes:{$tour['id']}:$qty:{$tour['version']}"]],[['text'=>'انصراف','callback_data'=>'p:home:0']]]);return;
        }
        if(str_starts_with($state,'tour_')){
            $this->access->require($this->actor,'tours');$field=substr($state,5);
            $next=['title'=>'destination','destination'=>'description','description'=>'category_id','category_id'=>'starts_at','starts_at'=>'ends_at','ends_at'=>'price','price'=>'capacity'];
            $prompts=['destination'=>'مقصد را وارد کنید:','description'=>'توضیحات تور را وارد کنید:','category_id'=>'شناسه دسته را وارد کنید؛ صفر یعنی بدون دسته:','starts_at'=>'شروع تور؛ تاریخ میلادی و ساعت تهران، مانند 2027-04-21 08:30:','ends_at'=>'پایان تور؛ تاریخ میلادی و ساعت تهران، مانند 2027-04-22 18:00:','price'=>'قیمت هر نفر به ریال؛ صفر یعنی رایگان:','capacity'=>'ظرفیت کل تور را وارد کنید:'];
            if(!in_array($field,['title','destination','description','category_id','starts_at','ends_at','price','capacity'],true))throw new RuleViolation('فرم معتبر نیست.');
            if(in_array($field,['starts_at','ends_at'],true)){
                $payload[$field]=$this->date($text);
                if($field==='ends_at' && $payload['ends_at'] <= ($payload['starts_at'] ?? '')){
                    throw new RuleViolation('زمان پایان تور باید بعد از زمان شروع باشد؛ تاریخ و ساعت پایان را دوباره وارد کنید.');
                }
            }
            elseif(in_array($field,['price','capacity','category_id'],true)){$payload[$field]=$this->number($text,$field==='price'?1000000000000:($field==='category_id'?4503599627370495:100000));if($field==='category_id'&&$payload[$field]===0)$payload[$field]=null;}
            else {if($text===''||strlen($text)>($field==='description'?4000:600))throw new RuleViolation('متن خالی یا بیش از حد طولانی است.');$payload[$field]=$text;}
            if($field==='capacity'){$id=$this->bookings->saveTour($this->actor,$payload,isset($payload['id'])?(int)$payload['id']:null);$this->clear();$this->reply('مشخصات ذخیره شد. تور جدید پیش‌نویس است؛ برای نمایش به مسافر «انتشار» را بزنید.');$this->showTour($id);}
            else{$this->state('tour_'.$next[$field],$payload);$this->reply($prompts[$next[$field]]);}return;
        }
        if($state==='reject'){$this->access->require($this->actor,'review');$this->bookings->transition($this->actor,(int)$payload['booking'],'rejected',$text==='-'?null:$text,true);$this->clear();$this->showBooking((int)$payload['booking'],true);return;}
        if($state==='pay_reference'){$this->access->require($this->actor,'payments');if(!preg_match('/^[A-Za-z0-9_-]{3,80}$/D',$text))throw new RuleViolation('شناسه پرداخت معتبر نیست.');$payload['reference']=$text;$this->state('pay_confirm',$payload);$booking=$this->bookings->booking($this->actor,(int)$payload['booking'],true);$this->reply("تأیید دستی پرداخت {$booking['amount']} ریال برای رزرو {$booking['id']} با شناسه {$text}؟",[[['text'=>'تأیید واریز بررسی‌شده','callback_data'=>'p:pay_yes:'.$payload['booking']]],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);return;}
        if($state==='report'){$this->clear();$this->report($this->number($text));return;}
        if($state==='category_parent'){$this->access->require($this->actor,'tours');$this->state('category_name',['parent'=>$this->number($text)]);$this->reply('نام دسته را وارد کنید:');return;}
        if($state==='category_name'){$this->access->require($this->actor,'tours');$id=(new TopicCatalogue($this->pdo))->create($text,(int)$payload['parent']);$this->access->audit($this->actor,'category.create','category',$id);$this->clear();$this->reply("دسته شماره $id ایجاد شد.");return;}
        if($state==='category_delete'){$this->access->require($this->actor,'tours');$id=$this->number($text);$this->state('category_delete_confirm',['category'=>$id]);$this->reply('این دسته و تمام زیرمجموعه‌ها و عضویت‌های قدیمی مرتبط حذف می‌شوند. دسته دارای تور قابل حذف نیست. تأیید می‌کنید؟',[[['text'=>'تأیید حذف','callback_data'=>"p:cat_delete_yes:$id"]],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);return;}
        if($state==='role_user'){$this->access->require($this->actor,'roles');$this->state('role_name',['user'=>$this->number($text,4503599627370495)]);$this->reply('نقش را بنویسید: مدیر، بررسی، گزارش، حذف. مدیر تور و پرداخت و اعلان را مدیریت می‌کند؛ بررسی فقط درخواست‌ها؛ گزارش فقط آمار.');return;}
        if($state==='role_name'){$this->access->require($this->actor,'roles');$role=['مدیر'=>'manager','بررسی'=>'reviewer','گزارش'=>'reporter','حذف'=>'none'][$text]??null;if(!$role)throw new RuleViolation('یکی از نقش‌های نمایش‌داده‌شده را وارد کنید.');$payload['role']=$role;$this->state('role_confirm',$payload);$this->reply("تغییر نقش حساب {$payload['user']} به {$text}؟",[[['text'=>'تأیید تغییر نقش','callback_data'=>'p:role_yes:'.$payload['user']]],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);return;}
        if($state==='broadcast_target'){$this->access->require($this->actor,'notify');$tour=$this->number($text);if($tour!==0)$this->bookings->tour($tour);$this->state('broadcast_text',['tour'=>$tour]);$this->reply('متن پیام را وارد کنید (کوتاه‌تر از ۲۰۰۰ حرف):');return;}
        if($state==='broadcast_text'){$this->access->require($this->actor,'notify');if($text===''||strlen($text)>6000)throw new RuleViolation('متن را کوتاه‌تر وارد کنید.');$payload['text']=$text;$this->state('broadcast_confirm',$payload);$this->reply("پیام زیر برای مخاطبان انتخاب‌شده صف می‌شود؛ تأیید می‌کنید؟\n$text",[[['text'=>'تأیید ارسال گروهی','callback_data'=>'p:broadcast_yes:0']],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);return;}
        throw new RuleViolation('برای ادامه از دکمه تأیید استفاده کنید یا /cancel بفرستید.');
    }
}
