<?php
final class ProductBot
{
    public function __construct(
        private TravelerProfileController $profileFlow,
        private BookingService $bookings,
        private AccessControl $access,
        private BotConversation $conversation,
        private TourCatalogService $catalogue,
        private PaymentReviewService $paymentReview,
        private BookingReportService $reports,
        private NotificationAdminService $notificationAdmin,
        private BookingQueryService $queries,
        private TopicCatalogue $topics
    ) {}

    public function dispatch(int $actor,?string $text,?string $callback,int $update,?array $contact=null):bool
    {
        $this->conversation->begin($actor, $update);
        $session=$this->conversation->session();
        $legacyStep=$this->profileFlow->legacyStep();
        if ($this->dispatchCommand($actor, $text, $legacyStep)) return true;
        if(isLegacyProfileStep($legacyStep)){
            $this->profileFlow->retireLegacyProfile();
            return true;
        }
        if ($this->dispatchProductCallback($actor, $callback, $update)) return true;
        if($text!==null && preg_match('/^\/(?:list|send)(?:\s|$)/',$text)){
            $this->conversation->clear();
            return false;
        }
        if($session!==null&&$session['state']==='phone'&&$contact!==null){
            $this->profileFlow->contact($contact);
            return true;
        }
        if($session!==null&&$session['state']!=='filter'&&$text!==null&&!str_starts_with($text,'/')) {
            $this->input($session['state'],json_decode($session['payload'],true,512,JSON_THROW_ON_ERROR),trim($text));
            return true;
        }
        // Keep the established profile/catalogue/approval commands available.
        if($callback!==null||($text!==null&&(preg_match('/^(?:\/(?:list|send)|Topics|ping|usage)(?:\s|$)/',$text)||preg_match('/^[\/#!]?(?:AddTopic) /i',$text)))) return false;
        if($legacyStep&&$legacyStep!=='defult')return false;
        $this->conversation->reply('پیام را متوجه نشدم. از دکمه‌ها استفاده کنید یا /help بفرستید.',$this->conversation->menu());
        return true;
    }

    /** Slash commands take priority over any persisted form state. */
    private function dispatchCommand(int $actor, ?string $text, mixed $legacyStep): bool
    {
        if($text!==null&&preg_match('/^\/(start|help|tours|bookings|profile|setprofile|admin|cancel|privacy|search)(?:@[A-Za-z0-9_]+)?(?:\s.*)?$/u',$text,$match)) {
            $command=$match[1]==='setprofile'?'profile':$match[1];
            $this->conversation->clear();
            $this->profileFlow->resetLegacyState();
            if(isLegacyProfileStep($legacyStep))$this->profileFlow->retireLegacyProfile();
            if($command==='start'||$command==='help'||$command==='cancel') {
                $this->conversation->reply($command==='cancel'?'عملیات جاری متوقف شد. از منوی زیر ادامه دهید.':($command==='help'?$this->conversation->helpText():"به ربات رزرو و مدیریت تور خوش آمدید! 🌍\n\nاز معرفی تور تا پیگیری رزرو، مستقیم در تلگرام.\nتورهای منتشرشده را ببینید، مقصد دلخواهتان را پیدا کنید و برای ۱ تا ۲۰ نفر درخواست رزرو ثبت کنید.\n\nاز منوی زیر شروع کنید. وضعیت درخواست‌ها در «رزروهای من» دیده می‌شود؛ ثبت درخواست به معنی تأیید نهایی نیست. /cancel فرم جاری را متوقف می‌کند."),$this->conversation->menu());
            }
            elseif($command==='tours') $this->listing(0);
            elseif($command==='bookings') $this->mine(0);
            elseif($command==='profile') $this->profileFlow->show();
            elseif($command==='admin') $this->admin();
            elseif($command==='privacy') $this->profileFlow->privacy();
            elseif($command==='search') {
                $this->conversation->state('search',[]);
                $this->conversation->reply('عنوان یا مقصد تور را وارد کنید. برای خروج /cancel بفرستید.');
            }
            return true;
        }
        return false;
    }

    /** Product callbacks remain separate from the legacy compatibility handler. */
    private function dispatchProductCallback(int $actor, ?string $callback, int $update): bool
    {
        if($callback!==null&&str_starts_with($callback,'p:')) {
            if(!preg_match('/^p:([a-z_]{1,24}):([0-9]{1,16})(?::([0-9]{1,16}))?(?::([0-9]{1,16}))?$/D',$callback,$parts)) throw new RuleViolation('دکمه معتبر نیست. از منوی جدید استفاده کنید.');
            $action=$parts[1];
            $id=(int)$parts[2];
            $arg=(int)($parts[3]??0);
            $version=(int)($parts[4]??0);
            if ($this->profileFlow->callback($action, $actor)) {
                return true;
            }
            if ($this->handleTravelerCallback($action, $actor, $id, $arg, $version, $update)) {
                return true;
            }
            if ($this->handleTourAdminCallback($action, $actor, $id)) {
                return true;
            }
            if ($this->handleBookingAdminCallback($action, $actor, $id, $arg)) {
                return true;
            }
            throw new RuleViolation('این دکمه دیگر معتبر نیست. از /start شروع کنید.');
        }
        return false;
    }

    /** Returns false when this action belongs to another callback category. */
    private function handleTravelerCallback(string $action, int $actor, int $id, int $arg, int $version, int $update): bool
    {
        switch ($action) {
            case 'tours':
                $this->listing($id);
                break;
            case 'search':
                $this->conversation->state('search',[]);
                $this->conversation->reply('عنوان یا مقصد را وارد کنید:');
                break;
            case 'cats':
                $this->categories($id,$arg);
                break;
            case 'cat_tours':
                $this->listing($arg,$id);
                break;
            case 'tour':
                $this->showTour($id);
                break;
            case 'reserve':
                $this->showTour($id);
                $this->conversation->state('quantity',['tour'=>$id]);
                $this->conversation->reply('چند نفر هستید؟ عددی بین ۱ تا ۲۰ وارد کنید. ظرفیت شامل سرپرست و همراهان است.');
                break;
            case 'reserve_yes':
                $booking=$this->bookings->reserve($actor,$id,$arg,"update:$update",$version);
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
                $this->history($id,false);
                break;
            case 'contact':
                $this->bookings->booking($actor,$id);
                $this->conversation->state('support_message',['booking'=>$id]);
                $this->conversation->reply('پیام مربوط به این رزرو را برای مدیریت بنویسید. رمز، اطلاعات کارت یا کد ملی نفرستید. /cancel برای انصراف.');
                break;
            case 'message':
                $this->access->require($actor,'notify');
                $this->bookings->booking($actor,$id,true);
                $this->conversation->state('admin_message',['booking'=>$id]);
                $this->conversation->reply('متن راهنمایی یا پاسخ به سرپرست این رزرو را بنویسید. پیام از طریق همین ربات ارسال می‌شود. /cancel برای انصراف.');
                break;
            case 'cancel_booking':
                $this->bookings->booking($actor,$id);
                $this->conversation->state('cancel_confirm',['booking'=>$id]);
                $this->conversation->reply('لغو رزرو باعث آزادشدن ظرفیت می‌شود. استرداد وجه خودکار نیست و باید با مدیر هماهنگ شود. لغو می‌کنید؟',[[['text'=>'تأیید لغو','callback_data'=>"p:cancel_yes:$id"]],[['text'=>'بازگشت','callback_data'=>"p:booking:$id"]]]);
                break;
            case 'cancel_yes':
                $confirmation=$this->conversation->requireSession('cancel_confirm');
                if((int)$confirmation['booking']!==$id)throw new RuleViolation('تأیید لغو معتبر نیست.');
                $this->conversation->clear();
                $this->bookings->transition($actor,$id,'cancelled');
                $this->showBooking($id);
                break;
            default:
                return false;
        }
        return true;
    }

    /** Returns false when this action belongs to another callback category. */
    private function handleTourAdminCallback(string $action, int $actor, int $id): bool
    {
        switch ($action) {
            case 'admin':
                $this->admin();
                break;
            case 'manage_tours':
                $this->listing($id,null,true);
                break;
            case 'new':
                $this->access->require($actor,'tours');
                $this->conversation->state('tour_title',['publication'=>'draft']);
                $this->conversation->reply('عنوان تور جدید را وارد کنید:');
                break;
            case 'edit':
                $this->access->require($actor,'tours');
                $editTour=$this->bookings->tour($id);
                $this->conversation->state('tour_title',['id'=>$id,'publication'=>$editTour['publication'],'expected_version'=>(int)$editTour['version']]);
                $this->conversation->reply('ویرایش کامل تور: عنوان جدید را وارد کنید. قیمت رزروهای قبلی ثابت می‌ماند. /cancel برای انصراف.');
                break;
            case 'publish':
                case 'inactive':
                $this->bookings->publication($actor,$id,$action==='publish'?'published':'inactive');
                $this->showTour($id);
                break;
            case 'archive':
                $this->access->require($actor,'tours');
                $this->conversation->state('archive_confirm',['tour'=>$id]);
                $this->conversation->reply('تور از دسترس ثبت درخواست خارج و سوابق رزرو حفظ می‌شود. رزروهای فعال خودکار لغو نمی‌شوند. تأیید می‌کنید؟',[[['text'=>'تأیید بایگانی','callback_data'=>"p:archive_yes:$id"]],[['text'=>'انصراف','callback_data'=>"p:tour:$id"]]]);
                break;
            case 'archive_yes':
                $confirmation=$this->conversation->requireSession('archive_confirm');
                if((int)$confirmation['tour']!==$id)throw new RuleViolation('تأیید بایگانی معتبر نیست.');
                $this->conversation->clear();
                $this->bookings->publication($actor,$id,'archived');
                $this->conversation->reply('تور بایگانی شد.',$this->conversation->menu());
                break;
            case 'category_new':
                $this->access->require($actor,'tours');
                $this->conversation->state('category_parent',[]);
                $this->conversation->reply('شناسه دسته والد را وارد کنید؛ صفر یعنی دسته اصلی. شناسه‌ها در «دسته‌ها» نمایش داده می‌شوند.');
                break;
            case 'category_delete':
                $this->access->require($actor,'tours');
                $this->conversation->state('category_delete',[]);
                $this->conversation->reply('شناسه دسته‌ای را که باید حذف شود وارد کنید:');
                break;
            case 'cat_delete_yes':
                $confirmation=$this->conversation->requireSession('category_delete_confirm');
                if((int)$confirmation['category']!==$id)throw new RuleViolation('تأیید حذف دسته معتبر نیست.');
                $this->conversation->clear();
                $this->access->require($actor,'tours');
                try {
                    $count=$this->topics->deleteTree($id);
                }
                catch(PDOException $error) {
                    if(($error->errorInfo[1]??null)===1451)throw new RuleViolation('دسته به تور مرتبط است؛ ابتدا دسته تور را تغییر دهید.');
                    throw $error;
                }
                $this->access->audit($actor,'category.delete','category',$id);
                $this->conversation->reply("تعداد دسته‌های حذف‌شده: $count");
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
                $this->allBookings($arg,$id);
                break;
            case 'review':
                $this->showBooking($id,true);
                break;
            case 'review_history':
                $this->history($id,true);
                break;
            case 'confirm':
                $this->bookings->transition($actor,$id,'confirmed',null,true);
                $this->showBooking($id,true);
                break;
            case 'complete':
                $this->bookings->transition($actor,$id,'completed',null,true);
                $this->showBooking($id,true);
                break;
            case 'reject':
                $this->access->require($actor,'review');
                $this->conversation->state('reject',['booking'=>$id]);
                $this->conversation->reply('دلیل رد درخواست را وارد کنید؛ برای رد بدون دلیل، علامت - بفرستید.');
                break;
            case 'pay':
                $this->access->require($actor,'payments');
                $this->bookings->booking($actor,$id,true);
                $this->conversation->state('pay_reference',['booking'=>$id]);
                $this->conversation->reply('پس از بررسی واقعی واریز، یک شناسه یکتا با ۳ تا ۸۰ حرف یا عدد انگلیسی وارد کنید. هرگز شماره کارت یا اطلاعات بانکی مسافر را وارد نکنید.');
                break;
            case 'pay_yes':
                $payload=$this->conversation->requireSession('pay_confirm');
                if((int)$payload['booking']!==$id)throw new RuleViolation('فرم منقضی شده است.');
                $this->paymentReview->record($actor,$id,$payload['reference']);
                $this->conversation->clear();
                $this->showBooking($id,true);
                break;
            case 'report':
                $this->report($id);
                break;
            case 'report_select':
                $this->access->require($actor,'reports');
                $this->conversation->state('report',[]);
                $this->conversation->reply('شناسه تور را وارد کنید:');
                break;
            case 'export':
                $this->export($id);
                break;
            case 'roles':
                $this->access->require($actor,'roles');
                $this->conversation->state('role_user',[]);
                $this->conversation->reply('شناسه عددی تلگرام مدیر را وارد کنید:');
                break;
            case 'role_yes':
                $payload=$this->conversation->requireSession('role_confirm');
                if((int)$payload['user']!==$id)throw new RuleViolation('فرم معتبر نیست.');
                $this->access->assign($actor,$id,$payload['role']);
                $this->conversation->clear();
                $this->conversation->reply('نقش مدیر به‌روز شد.');
                break;
            case 'notify':
                $this->access->require($actor,'notify');
                $this->notifications();
                break;
            case 'retry':
                $this->notificationAdmin->retry($actor,$id);
                $this->conversation->reply('اعلان ناموفق برای تلاش مجدد صف شد.');
                break;
            case 'broadcast':
                $this->access->require($actor,'notify');
                $this->conversation->state('broadcast_target',[]);
                $this->conversation->reply('شناسه تور را برای ارسال به مسافران فعال وارد کنید؛ صفر یعنی همه حساب‌ها.');
                break;
            case 'broadcast_yes':
                $payload=$this->conversation->requireSession('broadcast_confirm');
                $this->broadcast($payload['tour'],$payload['text']);
                $this->conversation->clear();
                break;
            default:
                return false;
        }
        return true;
    }

    private function listing(int $offset,?int $category=null,bool $admin=false):void {
        $session = $this->conversation->session();
        $query = !$admin && $session && $session['state'] === 'filter'
            ? json_decode($session['payload'], true, 512, JSON_THROW_ON_ERROR)['query'] : null;
        $rows = $this->catalogue->tours($this->conversation->actor(), $offset, $category, $admin, $query);
        $buttons=[];
        foreach($rows as$row)$buttons[]=[['text'=>$row['title'],'callback_data'=>'p:tour:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'صفحه بعد','callback_data'=>$admin?'p:manage_tours:'.($offset+10):($category!==null?'p:cat_tours:'.$category.':'.($offset+10):'p:tours:'.($offset+10))]];
        $buttons[]=[['text'=>'بازگشت به منو','callback_data'=>'p:home:0']];
        $this->conversation->reply($rows===[]?'توری مطابق این انتخاب پیدا نشد. برای پاک‌کردن جست‌وجو از منوی اصلی دوباره وارد شوید.':'تور را انتخاب کنید:',$buttons);
    }

    private function categories(int $parent,int $offset):void {
        $rows = $this->catalogue->categories($parent, $offset);
        $buttons=[];
        foreach($rows as$row)$buttons[]=[['text'=>$row['id'].' — '.$row['Name'],'callback_data'=>'p:cats:'.$row['id']]];
        if($parent>0)$buttons[]=[['text'=>'تورهای این دسته','callback_data'=>"p:cat_tours:$parent:0"]];
        if(count($rows)===20)$buttons[]=[['text'=>'صفحه بعد','callback_data'=>"p:cats:$parent:".($offset+20)]];
        $buttons[]=[['text'=>'دسته‌های اصلی','callback_data'=>'p:cats:0'],['text'=>'منو','callback_data'=>'p:home:0']];
        $this->conversation->reply('دسته‌بندی‌ها؛ عدد کنار نام، شناسه دسته است:',$buttons);
    }

    private function showTour(int $id):void {
        $detail = $this->catalogue->detail($this->conversation->actor(), $id);
        $tour = $detail['tour'];
        $remaining = $detail['remaining'];
        $text="تور #$id: {$tour['title']}\nمقصد: {$tour['destination']}\n{$tour['description']}\nشروع: ".$this->conversation->local($tour['starts_at'])."\nپایان: ".$this->conversation->local($tour['ends_at'])."\nمنطقه زمانی: {$this->conversation->timezone()}\nقیمت هر نفر: {$tour['price']} ریال\nظرفیت کل: {$tour['capacity']}؛ باقی‌مانده: $remaining\nوضعیت انتشار: ".(['published'=>'فعال','draft'=>'پیش‌نویس','inactive'=>'غیرفعال','archived'=>'بایگانی'][$tour['publication']]??'نامشخص');
        $buttons=[];
        if($tour['publication']==='published'&&$remaining>0&&$tour['starts_at']>gmdate('Y-m-d H:i:s'))$buttons[]=[['text'=>'درخواست رزرو','callback_data'=>"p:reserve:$id"]];
        if($this->access->allows($this->conversation->actor(),'tours')){
            $buttons[]=[['text'=>'ویرایش','callback_data'=>"p:edit:$id"],['text'=>'انتشار','callback_data'=>"p:publish:$id"]];
            $buttons[]=[['text'=>'غیرفعال','callback_data'=>"p:inactive:$id"],['text'=>'بایگانی','callback_data'=>"p:archive:$id"]];
        }
        if($this->access->allows($this->conversation->actor(),'reports'))$buttons[]=[['text'=>'گزارش ظرفیت','callback_data'=>"p:report:$id"]];
        $buttons[]=[['text'=>'بازگشت','callback_data'=>'p:home:0']];
        $this->conversation->reply($text,$buttons);
    }

    private function mine(int $offset):void {
        $rows = $this->queries->mine($this->conversation->actor(), $offset);
        $buttons=[];
        foreach($rows as$row)$buttons[]=[['text'=>'رزرو #'.$row['id'].' — '.$this->status($row['status']),'callback_data'=>'p:booking:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'بعدی','callback_data'=>'p:mine:'.($offset+10)]];
        $buttons[]=[['text'=>'منو','callback_data'=>'p:home:0']];
        $this->conversation->reply($rows?'رزروهای جاری و قبلی شما:':'هنوز رزروی ندارید.',$buttons);
    }

    private function status(string $status):string {
        return['pending'=>'در انتظار','confirmed'=>'تأییدشده','rejected'=>'ردشده','cancelled'=>'لغوشده','completed'=>'تکمیل‌شده','paid'=>'پرداخت‌شده','expired'=>'منقضی','failed'=>'ناموفق'][$status]??$status;
    }

    private function showBooking(int $id,bool $admin=false):void {
        $booking=$this->queries->detail($this->conversation->actor(),$id,$admin);
        $text="رزرو #$id\nتور: {$booking['title']}\nشروع: ".$this->conversation->local($booking['starts_at'])."\nتعداد نفرات: {$booking['quantity']}\nمبلغ کل: {$booking['amount']} ریال\nوضعیت رزرو: ".$this->status($booking['status'])."\nوضعیت پرداخت: ".$this->status($booking['payment_status']);
        if($booking['payment_status']==='pending'&&(int)$booking['amount']>0)$text.="\nپرداخت آنلاین فعال نیست. پس از هماهنگی با مدیر، واریز دستی بررسی می‌شود. پیش از هماهنگی، مبلغی واریز نکنید. مهلت بررسی اولیه پرداخت ۲۴ ساعت است.";
        $buttons=[];
        if($admin && $booking['user_id'] !== null && $this->access->allows($this->conversation->actor(),'profiles')) {
            $leader = $booking['leader_name'];
            $text .= "\nنام سرپرست: " . ($leader ?: 'ثبت نشده');
        }
        if($admin){
            if($booking['status']==='pending')$buttons[]=[['text'=>'تأیید رزرو','callback_data'=>"p:confirm:$id"],['text'=>'رد با دلیل اختیاری','callback_data'=>"p:reject:$id"]];
            if($this->access->allows($this->conversation->actor(),'payments')&&$booking['payment_status']!=='paid')$buttons[]=[['text'=>'بررسی پرداخت دستی','callback_data'=>"p:pay:$id"]];
            if($booking['status']==='confirmed')$buttons[]=[['text'=>'تکمیل پس از پایان تور','callback_data'=>"p:complete:$id"]];
        }
        elseif(in_array($booking['status'],['pending','confirmed'],true))$buttons[]=[['text'=>'لغو رزرو','callback_data'=>"p:cancel_booking:$id"]];
        if($admin && $this->access->allows($this->conversation->actor(),'notify') && $booking['user_id']!==null)$buttons[]=[['text'=>'پیام به سرپرست','callback_data'=>"p:message:$id"]];
        elseif(!$admin)$buttons[]=[['text'=>'پیام به مدیریت درباره رزرو','callback_data'=>"p:contact:$id"]];
        $buttons[]=[['text'=>'جزئیات تور','callback_data'=>'p:tour:'.$booking['tour_id']]];
        $buttons[]=[['text'=>'تاریخچه وضعیت','callback_data'=>($admin?'p:review_history:':'p:history:').$id]];
        $buttons[]=[['text'=>'بازگشت','callback_data'=>$admin?'p:pending:0':'p:mine:0']];
        $this->conversation->reply($text,$buttons,$admin && $booking['user_id'] !== null ? (int)$booking['user_id'] : null,$admin?($this->access->allows($this->conversation->actor(),'profiles')?['review','profiles']:['review']):[]);
    }

    private function history(int $id,bool $admin):void {
        $rows = $this->queries->history($this->conversation->actor(), $id, $admin);
        $lines=array_map(fn($row)=>$this->conversation->local($row['created_at']).' — '.$this->status($row['new_status']),$rows);
        $this->conversation->reply(implode("\n",$lines));
    }

    private function admin():void {
        if($this->access->role($this->conversation->actor())===null)throw new RuleViolation('دسترسی مدیریت ندارید.');
        $buttons=[];
        if($this->access->allows($this->conversation->actor(),'tours'))$buttons=array_merge($buttons,[[['text'=>'ایجاد تور','callback_data'=>'p:new:0'],['text'=>'مدیریت تورها','callback_data'=>'p:manage_tours:0']],[['text'=>'ایجاد دسته','callback_data'=>'p:category_new:0'],['text'=>'حذف دسته','callback_data'=>'p:category_delete:0']]]);
        if($this->access->allows($this->conversation->actor(),'review'))$buttons[]=[['text'=>'درخواست‌های منتظر','callback_data'=>'p:pending:0'],['text'=>'همه رزروها','callback_data'=>'p:all_bookings:0']];
        if($this->access->allows($this->conversation->actor(),'reports'))$buttons[]=[['text'=>'گزارش تور','callback_data'=>'p:report_select:0']];
        if($this->access->allows($this->conversation->actor(),'notify'))$buttons[]=[['text'=>'وضعیت اعلان‌ها','callback_data'=>'p:notify:0'],['text'=>'ارسال گروهی','callback_data'=>'p:broadcast:0']];
        if($this->access->allows($this->conversation->actor(),'roles'))$buttons[]=[['text'=>'مدیریت نقش‌ها','callback_data'=>'p:roles:0']];
        $buttons[]=[['text'=>'منو','callback_data'=>'p:home:0']];
        $this->conversation->reply('مدیریت؛ نقش شما: '.(['owner'=>'مدیر اصلی','manager'=>'مدیر','reviewer'=>'بررسی درخواست‌ها','reporter'=>'گزارش‌گیر'][$this->access->role($this->conversation->actor())]??'نامشخص'),$buttons);
    }

    private function pending(int $offset):void {
        $rows = $this->queries->pending($this->conversation->actor(), $offset);
        $buttons=[];
        foreach($rows as$row)$buttons[]=[['text'=>"رزرو {$row['id']} / تور {$row['tour_id']} / {$row['quantity']} نفر",'callback_data'=>'p:review:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'بعدی','callback_data'=>'p:pending:'.($offset+10)]];
        $buttons[]=[['text'=>'مدیریت','callback_data'=>'p:admin:0']];
        $this->conversation->reply($rows?'درخواست‌های منتظر بررسی:':'درخواست منتظری وجود ندارد.',$buttons);
    }

    private function allBookings(int $offset,?int $tour=null):void {
        $rows = $this->queries->all($this->conversation->actor(), $offset, $tour);
        $buttons=[];
        foreach($rows as$row)$buttons[]=[['text'=>'رزرو #'.$row['id'].' / '.$this->status($row['status']).' / '.$row['quantity'].' نفر','callback_data'=>'p:review:'.$row['id']]];
        if(count($rows)===10)$buttons[]=[['text'=>'صفحه بعد','callback_data'=>$tour===null?'p:all_bookings:'.($offset+10):"p:tour_bookings:$tour:".($offset+10)]];
        $buttons[]=[['text'=>'مدیریت','callback_data'=>'p:admin:0']];
        $this->conversation->reply('رزروها با وضعیت جاری و قبلی:',$buttons);
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
            $this->conversation->actor(), $this->conversation->update(), $tour, $text
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
        if ($this->handleConversationInput($state, $payload, $text)) {
            return;
        }
        if ($this->handleSearchAndQuantityInput($state, $payload, $text)) {
            return;
        }
        if ($this->handleTourFormInput($state, $payload, $text)) {
            return;
        }
        $this->handleAdministrativeInput($state, $payload, $text);
    }

    private function handleConversationInput(string $state, array $payload, string $text): bool
    {
        if($state==='support_message'||$state==='admin_message'){
            if($text===''||strlen($text)>2000)throw new RuleViolation('پیام را کوتاه‌تر وارد کنید.');
            $id=(int)$payload['booking'];
            if($state==='support_message'){
                $this->bookings->booking($this->conversation->actor(),$id);
                foreach($this->access->reviewers()as$admin){
                    if(!$this->access->allows($admin,'notify'))continue;
                    $this->conversation->message($admin,"پیام مسافر درباره رزرو #$id:\n$text","support:{$this->conversation->update()}:$admin",[[['text'=>'پاسخ به سرپرست','callback_data'=>"p:message:$id"]]],$this->conversation->actor(),['notify','review']);
                }
            }
            else{
                $this->access->require($this->conversation->actor(),'notify');
                $booking=$this->bookings->booking($this->conversation->actor(),$id,true);
                if($booking['user_id']===null)throw new RuleViolation('حساب این مسافر حذف شده است.');
                $this->conversation->enqueue((int)$booking['user_id'],'sendMessage',['text'=>"پیام مدیریت درباره رزرو #$id:\n$text",'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'پاسخ به مدیریت','callback_data'=>"p:contact:$id"]]]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'_subject_user'=>(int)$booking['user_id'],'_permission_actor'=>$this->conversation->actor(),'_required_permissions'=>['notify','review']],"staff-message:{$this->conversation->update()}:$id");
                $this->access->audit($this->conversation->actor(),'booking.message','booking',$id);
            }
            $this->conversation->clear();
            $this->conversation->reply('پیام در صف ارسال قرار گرفت. پاسخ از طریق همین ربات به شما می‌رسد.');
            return true;
        }
        return false;
    }

    private function handleSearchAndQuantityInput(string $state, array $payload, string $text): bool
    {
        if($state==='search'){
            if($text===''||strlen($text)>200)throw new RuleViolation('عبارت جست‌وجو را کوتاه وارد کنید.');
            $this->conversation->state('filter',['query'=>$text]);
            $this->listing(0);
            return true;
        }
        if($state==='quantity'){
            $qty=$this->conversation->number($text,20);
            BookingRules::quantity($qty);
            $tour=$this->bookings->tour((int)$payload['tour']);
            $this->conversation->reply("درخواست {$tour['title']} برای $qty نفر؛ مبلغ کل: ".($qty*(int)$tour['price']).' ریال. ثبت می‌کنید؟',[[['text'=>'تأیید درخواست','callback_data'=>"p:reserve_yes:{$tour['id']}:$qty:{$tour['version']}"]],[['text'=>'انصراف','callback_data'=>'p:home:0']]]);
            return true;
        }
        return false;
    }

    private function handleTourFormInput(string $state, array $payload, string $text): bool
    {
        if(str_starts_with($state,'tour_')){
            $this->access->require($this->conversation->actor(),'tours');
            $field=substr($state,5);
            $next=['title'=>'destination','destination'=>'description','description'=>'category_id','category_id'=>'starts_at','starts_at'=>'ends_at','ends_at'=>'price','price'=>'capacity'];
            $prompts=['destination'=>'مقصد را وارد کنید:','description'=>'توضیحات تور را وارد کنید:','category_id'=>'شناسه دسته را وارد کنید؛ صفر یعنی بدون دسته:','starts_at'=>'شروع تور؛ تاریخ میلادی و ساعت تهران، مانند 2027-04-21 08:30:','ends_at'=>'پایان تور؛ تاریخ میلادی و ساعت تهران، مانند 2027-04-22 18:00:','price'=>'قیمت هر نفر به ریال؛ صفر یعنی رایگان:','capacity'=>'ظرفیت کل تور را وارد کنید:'];
            if(!in_array($field,['title','destination','description','category_id','starts_at','ends_at','price','capacity'],true))throw new RuleViolation('فرم معتبر نیست.');
            if(in_array($field,['starts_at','ends_at'],true)){
                $payload[$field]=$this->conversation->date($text);
                if($field==='ends_at' && $payload['ends_at'] <= ($payload['starts_at'] ?? '')){
                    throw new RuleViolation('زمان پایان تور باید بعد از زمان شروع باشد؛ تاریخ و ساعت پایان را دوباره وارد کنید.');
                }
            }
            elseif(in_array($field,['price','capacity','category_id'],true)){
                $payload[$field]=$this->conversation->number($text,$field==='price'?1000000000000:($field==='category_id'?4503599627370495:100000));
                if($field==='category_id'&&$payload[$field]===0)$payload[$field]=null;
            }
            else {
                if($text===''||strlen($text)>($field==='description'?4000:600))throw new RuleViolation('متن خالی یا بیش از حد طولانی است.');
                $payload[$field]=$text;
            }
            if($field==='capacity'){
                $id=$this->bookings->saveTour($this->conversation->actor(),$payload,isset($payload['id'])?(int)$payload['id']:null);
                $this->conversation->clear();
                $this->conversation->reply('مشخصات ذخیره شد. تور جدید پیش‌نویس است؛ برای نمایش به مسافر «انتشار» را بزنید.');
                $this->showTour($id);
            }
            else{
                $this->conversation->state('tour_'.$next[$field],$payload);
                $this->conversation->reply($prompts[$next[$field]]);
            }
            return true;
        }
        return false;
    }

    private function handleAdministrativeInput(string $state, array $payload, string $text): void
    {
        if($state==='reject'){
            $this->access->require($this->conversation->actor(),'review');
            $this->bookings->transition($this->conversation->actor(),(int)$payload['booking'],'rejected',$text==='-'?null:$text,true);
            $this->conversation->clear();
            $this->showBooking((int)$payload['booking'],true);
            return;
        }
        if($state==='pay_reference'){
            $this->access->require($this->conversation->actor(),'payments');
            if(!PaymentRules::isManualReference($text))throw new RuleViolation('شناسه پرداخت معتبر نیست.');
            $payload['reference']=$text;
            $this->conversation->state('pay_confirm',$payload);
            $booking=$this->bookings->booking($this->conversation->actor(),(int)$payload['booking'],true);
            $this->conversation->reply("تأیید دستی پرداخت {$booking['amount']} ریال برای رزرو {$booking['id']} با شناسه {$text}؟",[[['text'=>'تأیید واریز بررسی‌شده','callback_data'=>'p:pay_yes:'.$payload['booking']]],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);
            return;
        }
        if($state==='report'){
            $this->conversation->clear();
            $this->report($this->conversation->number($text));
            return;
        }
        if($state==='category_parent'){
            $this->access->require($this->conversation->actor(),'tours');
            $this->conversation->state('category_name',['parent'=>$this->conversation->number($text)]);
            $this->conversation->reply('نام دسته را وارد کنید:');
            return;
        }
        if($state==='category_name'){
            $this->access->require($this->conversation->actor(),'tours');
            $id=$this->topics->create($text,(int)$payload['parent']);
            $this->access->audit($this->conversation->actor(),'category.create','category',$id);
            $this->conversation->clear();
            $this->conversation->reply("دسته شماره $id ایجاد شد.");
            return;
        }
        if($state==='category_delete'){
            $this->access->require($this->conversation->actor(),'tours');
            $id=$this->conversation->number($text);
            $this->conversation->state('category_delete_confirm',['category'=>$id]);
            $this->conversation->reply('این دسته و تمام زیرمجموعه‌ها و عضویت‌های قدیمی مرتبط حذف می‌شوند. دسته دارای تور قابل حذف نیست. تأیید می‌کنید؟',[[['text'=>'تأیید حذف','callback_data'=>"p:cat_delete_yes:$id"]],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);
            return;
        }
        if($state==='role_user'){
            $this->access->require($this->conversation->actor(),'roles');
            $this->conversation->state('role_name',['user'=>$this->conversation->number($text,4503599627370495)]);
            $this->conversation->reply('نقش را بنویسید: مدیر، بررسی، گزارش، حذف. مدیر تور و پرداخت و اعلان را مدیریت می‌کند؛ بررسی فقط درخواست‌ها؛ گزارش فقط آمار.');
            return;
        }
        if($state==='role_name'){
            $this->access->require($this->conversation->actor(),'roles');
            $role=['مدیر'=>'manager','بررسی'=>'reviewer','گزارش'=>'reporter','حذف'=>'none'][$text]??null;
            if(!$role)throw new RuleViolation('یکی از نقش‌های نمایش‌داده‌شده را وارد کنید.');
            $payload['role']=$role;
            $this->conversation->state('role_confirm',$payload);
            $this->conversation->reply("تغییر نقش حساب {$payload['user']} به {$text}؟",[[['text'=>'تأیید تغییر نقش','callback_data'=>'p:role_yes:'.$payload['user']]],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);
            return;
        }
        if($state==='broadcast_target'){
            $this->access->require($this->conversation->actor(),'notify');
            $tour=$this->conversation->number($text);
            if($tour!==0)$this->bookings->tour($tour);
            $this->conversation->state('broadcast_text',['tour'=>$tour]);
            $this->conversation->reply('متن پیام را وارد کنید (کوتاه‌تر از ۲۰۰۰ حرف):');
            return;
        }
        if($state==='broadcast_text'){
            $this->access->require($this->conversation->actor(),'notify');
            if($text===''||strlen($text)>6000)throw new RuleViolation('متن را کوتاه‌تر وارد کنید.');
            $payload['text']=$text;
            $this->conversation->state('broadcast_confirm',$payload);
            $this->conversation->reply("پیام زیر برای مخاطبان انتخاب‌شده صف می‌شود؛ تأیید می‌کنید؟\n$text",[[['text'=>'تأیید ارسال گروهی','callback_data'=>'p:broadcast_yes:0']],[['text'=>'انصراف','callback_data'=>'p:admin:0']]]);
            return;
        }
        throw new RuleViolation('برای ادامه از دکمه تأیید استفاده کنید یا /cancel بفرستید.');
    }

}
