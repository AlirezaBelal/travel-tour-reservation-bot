<?php

declare(strict_types=1);

/** Tour discovery, editing and category conversation, separate from booking/review UI. */
final class TourController
{
    public function __construct(
        private TourCatalogService $catalogue,
        private TourService $tours,
        private TourInventory $inventory,
        private TourCategoryService $categoriesService,
        private AccessControl $access,
        private BotConversation $conversation
    ) {
    }

    public function callback(string $action, int $actor, int $id, int $arg): bool
    {
        switch ($action) {
            case 'tours':
                $this->listing($id);
                break;
            case 'search':
                $this->conversation->state('search', []);
                $this->conversation->reply('عنوان یا مقصد را وارد کنید:');
                break;
            case 'cats':
                $this->categories($id, $arg);
                break;
            case 'cat_tours':
                $this->listing($arg, $id);
                break;
            case 'tour':
                $this->showTour($id);
                break;
            case 'reserve':
                $this->showTour($id);
                $this->conversation->state('quantity', ['tour' => $id]);
                $this->conversation->reply('چند نفر هستید؟ عددی بین ۱ تا ۲۰ وارد کنید. ظرفیت شامل سرپرست و همراهان است.');
                break;
            default:
                return $this->adminCallback($action, $actor, $id) || $this->categoryCallback($action, $actor, $id);
        }
        return true;
    }

    private function categoryCallback(string $action, int $actor, int $id): bool
    {
        switch ($action) {
            case 'category_new':
                $this->access->require($actor, 'tours');
                $this->conversation->state('category_parent', []);
                $this->conversation->reply('شناسه دسته والد را وارد کنید؛ صفر یعنی دسته اصلی. شناسه‌ها در «دسته‌ها» نمایش داده می‌شوند.');
                break;
            case 'category_delete':
                $this->access->require($actor, 'tours');
                $this->conversation->state('category_delete', []);
                $this->conversation->reply('شناسه دسته‌ای را که باید حذف شود وارد کنید:');
                break;
            case 'cat_delete_yes':
                $confirmation = $this->conversation->requireSession('category_delete_confirm');
                if ((int)$confirmation['category'] !== $id) {
                    throw new RuleViolation('تأیید حذف دسته معتبر نیست.');
                }
                $this->conversation->clear();
                $count = $this->categoriesService->delete($actor, $id);
                $this->conversation->reply("تعداد دسته‌های حذف‌شده: $count");
                break;
            default:
                return false;
        }
        return true;
    }

    public function input(string $state, array $payload, string $text): bool
    {
        return $this->handleSearchAndQuantityInput($state, $payload, $text)
            || $this->handleTourFormInput($state, $payload, $text)
            || $this->categoryInput($state, $payload, $text);
    }

    private function categoryInput(string $state, array $payload, string $text): bool
    {
        if ($state === 'category_parent') {
            $this->access->require($this->conversation->actor(), 'tours');
            $this->conversation->state('category_name', ['parent' => $this->conversation->number($text)]);
            $this->conversation->reply('نام دسته را وارد کنید:');
            return true;
        }
        if ($state === 'category_name') {
            $this->access->require($this->conversation->actor(), 'tours');
            $id = $this->categoriesService->create($this->conversation->actor(), $text, (int)$payload['parent']);
            $this->conversation->clear();
            $this->conversation->reply("دسته شماره $id ایجاد شد.");
            return true;
        }
        if ($state === 'category_delete') {
            $this->access->require($this->conversation->actor(), 'tours');
            $id = $this->conversation->number($text);
            $this->conversation->state('category_delete_confirm', ['category' => $id]);
            $this->conversation->reply('این دسته و تمام زیرمجموعه‌ها و عضویت‌های قدیمی مرتبط حذف می‌شوند. دسته دارای تور قابل حذف نیست. تأیید می‌کنید؟', [[['text' => 'تأیید حذف','callback_data' => "p:cat_delete_yes:$id"]],[['text' => 'انصراف','callback_data' => 'p:admin:0']]]);
            return true;
        }
        return false;
    }

    private function adminCallback(string $action, int $actor, int $id): bool
    {
        switch ($action) {
            case 'manage_tours':
                $this->listing($id, null, true);
                break;
            case 'new':
                $this->access->require($actor, 'tours');
                $this->conversation->state('tour_title', ['publication' => 'draft']);
                $this->conversation->reply('عنوان تور جدید را وارد کنید:');
                break;
            case 'edit':
                $this->access->require($actor, 'tours');
                $editTour = $this->inventory->tour($id);
                $this->conversation->state('tour_title', ['id' => $id,'publication' => $editTour['publication'],'expected_version' => (int)$editTour['version']]);
                $this->conversation->reply('ویرایش کامل تور: عنوان جدید را وارد کنید. قیمت رزروهای قبلی ثابت می‌ماند. /cancel برای انصراف.');
                break;
            case 'publish':
            case 'inactive':
                $this->tours->publication($actor, $id, $action === 'publish' ? 'published' : 'inactive');
                $this->showTour($id);
                break;
            case 'archive':
                $this->access->require($actor, 'tours');
                $this->conversation->state('archive_confirm', ['tour' => $id]);
                $this->conversation->reply('تور از دسترس ثبت درخواست خارج و سوابق رزرو حفظ می‌شود. رزروهای فعال خودکار لغو نمی‌شوند. تأیید می‌کنید؟', [[['text' => 'تأیید بایگانی','callback_data' => "p:archive_yes:$id"]],[['text' => 'انصراف','callback_data' => "p:tour:$id"]]]);
                break;
            case 'archive_yes':
                $confirmation = $this->conversation->requireSession('archive_confirm');
                if ((int)$confirmation['tour'] !== $id) {
                    throw new RuleViolation('تأیید بایگانی معتبر نیست.');
                }
                $this->conversation->clear();
                $this->tours->publication($actor, $id, 'archived');
                $this->conversation->reply('تور بایگانی شد.', $this->conversation->menu());
                break;
            default:
                return false;
        }
        return true;
    }

    /** Returns false when this action belongs to another callback category. */
    public function listing(int $offset, ?int $category = null, bool $admin = false): void
    {
        $session = $this->conversation->session();
        $query = !$admin && $session && $session['state'] === 'filter'
            ? json_decode($session['payload'], true, 512, JSON_THROW_ON_ERROR)['query'] : null;
        $rows = $this->catalogue->tours($this->conversation->actor(), $offset, $category, $admin, $query);
        $buttons = [];
        foreach ($rows as $row) {
            $buttons[] = [['text' => $row['title'],'callback_data' => 'p:tour:' . $row['id']]];
        }
        if (count($rows) === 10) {
            $buttons[] = [['text' => 'صفحه بعد','callback_data' => $admin ? 'p:manage_tours:' . ($offset + 10) : ($category !== null ? 'p:cat_tours:' . $category . ':' . ($offset + 10) : 'p:tours:' . ($offset + 10))]];
        }
        $buttons[] = [['text' => 'بازگشت به منو','callback_data' => 'p:home:0']];
        $this->conversation->reply($rows === [] ? 'توری مطابق این انتخاب پیدا نشد. برای پاک‌کردن جست‌وجو از منوی اصلی دوباره وارد شوید.' : 'تور را انتخاب کنید:', $buttons);
    }

    private function categories(int $parent, int $offset): void
    {
        $rows = $this->catalogue->categories($parent, $offset);
        $buttons = [];
        foreach ($rows as $row) {
            $buttons[] = [['text' => $row['id'] . ' — ' . $row['Name'],'callback_data' => 'p:cats:' . $row['id']]];
        }
        if ($parent > 0) {
            $buttons[] = [['text' => 'تورهای این دسته','callback_data' => "p:cat_tours:$parent:0"]];
        }
        if (count($rows) === 20) {
            $buttons[] = [['text' => 'صفحه بعد','callback_data' => "p:cats:$parent:" . ($offset + 20)]];
        }
        $buttons[] = [['text' => 'دسته‌های اصلی','callback_data' => 'p:cats:0'],['text' => 'منو','callback_data' => 'p:home:0']];
        $this->conversation->reply('دسته‌بندی‌ها؛ عدد کنار نام، شناسه دسته است:', $buttons);
    }

    private function showTour(int $id): void
    {
        $detail = $this->catalogue->detail($this->conversation->actor(), $id);
        $tour = $detail['tour'];
        $remaining = $detail['remaining'];
        $text = "تور #$id: {$tour['title']}\nمقصد: {$tour['destination']}\n{$tour['description']}\nشروع: " . $this->conversation->local($tour['starts_at']) . "\nپایان: " . $this->conversation->local($tour['ends_at']) . "\nمنطقه زمانی: {$this->conversation->timezone()}\nقیمت هر نفر: {$tour['price']} ریال\nظرفیت کل: {$tour['capacity']}؛ باقی‌مانده: $remaining\nوضعیت انتشار: " . (['published' => 'فعال','draft' => 'پیش‌نویس','inactive' => 'غیرفعال','archived' => 'بایگانی'][$tour['publication']] ?? 'نامشخص');
        $buttons = [];
        if ($tour['publication'] === 'published' && $remaining > 0 && $tour['starts_at'] > gmdate('Y-m-d H:i:s')) {
            $buttons[] = [['text' => 'درخواست رزرو','callback_data' => "p:reserve:$id"]];
        }
        if ($this->access->allows($this->conversation->actor(), 'tours')) {
            $buttons[] = [['text' => 'ویرایش','callback_data' => "p:edit:$id"],['text' => 'انتشار','callback_data' => "p:publish:$id"]];
            $buttons[] = [['text' => 'غیرفعال','callback_data' => "p:inactive:$id"],['text' => 'بایگانی','callback_data' => "p:archive:$id"]];
        }
        if ($this->access->allows($this->conversation->actor(), 'reports')) {
            $buttons[] = [['text' => 'گزارش ظرفیت','callback_data' => "p:report:$id"]];
        }
        $buttons[] = [['text' => 'بازگشت','callback_data' => 'p:home:0']];
        $this->conversation->reply($text, $buttons);
    }

    private function handleSearchAndQuantityInput(string $state, array $payload, string $text): bool
    {
        if ($state === 'search') {
            if ($text === '' || strlen($text) > 200) {
                throw new RuleViolation('عبارت جست‌وجو را کوتاه وارد کنید.');
            }
            $this->conversation->state('filter', ['query' => $text]);
            $this->listing(0);
            return true;
        }
        if ($state === 'quantity') {
            $qty = $this->conversation->number($text, 20);
            BookingRules::quantity($qty);
            $tour = $this->inventory->tour((int)$payload['tour']);
            $this->conversation->reply("درخواست {$tour['title']} برای $qty نفر؛ مبلغ کل: " . ($qty * (int)$tour['price']) . ' ریال. ثبت می‌کنید؟', [[['text' => 'تأیید درخواست','callback_data' => "p:reserve_yes:{$tour['id']}:$qty:{$tour['version']}"]],[['text' => 'انصراف','callback_data' => 'p:home:0']]]);
            return true;
        }
        return false;
    }

    private function handleTourFormInput(string $state, array $payload, string $text): bool
    {
        if (str_starts_with($state, 'tour_')) {
            $this->access->require($this->conversation->actor(), 'tours');
            $field = substr($state, 5);
            $next = ['title' => 'destination','destination' => 'description','description' => 'category_id','category_id' => 'starts_at','starts_at' => 'ends_at','ends_at' => 'price','price' => 'capacity'];
            $prompts = ['destination' => 'مقصد را وارد کنید:','description' => 'توضیحات تور را وارد کنید:','category_id' => 'شناسه دسته را وارد کنید؛ صفر یعنی بدون دسته:','starts_at' => 'شروع تور؛ تاریخ میلادی و ساعت تهران، مانند 2027-04-21 08:30:','ends_at' => 'پایان تور؛ تاریخ میلادی و ساعت تهران، مانند 2027-04-22 18:00:','price' => 'قیمت هر نفر به ریال؛ صفر یعنی رایگان:','capacity' => 'ظرفیت کل تور را وارد کنید:'];
            if (!in_array($field, ['title','destination','description','category_id','starts_at','ends_at','price','capacity'], true)) {
                throw new RuleViolation('فرم معتبر نیست.');
            }
            if (in_array($field, ['starts_at','ends_at'], true)) {
                $payload[$field] = $this->conversation->date($text);
                if ($field === 'ends_at' && $payload['ends_at'] <= ($payload['starts_at'] ?? '')) {
                    throw new RuleViolation('زمان پایان تور باید بعد از زمان شروع باشد؛ تاریخ و ساعت پایان را دوباره وارد کنید.');
                }
            } elseif (in_array($field, ['price','capacity','category_id'], true)) {
                $payload[$field] = $this->conversation->number($text, $field === 'price' ? 1000000000000 : ($field === 'category_id' ? 4503599627370495 : 100000));
                if ($field === 'category_id' && $payload[$field] === 0) {
                    $payload[$field] = null;
                }
            } else {
                if ($text === '' || strlen($text) > ($field === 'description' ? 4000 : 600)) {
                    throw new RuleViolation('متن خالی یا بیش از حد طولانی است.');
                }
                $payload[$field] = $text;
            }
            if ($field === 'capacity') {
                $id = $this->tours->saveTour($this->conversation->actor(), $payload, isset($payload['id']) ? (int)$payload['id'] : null);
                $this->conversation->clear();
                $this->conversation->reply('مشخصات ذخیره شد. تور جدید پیش‌نویس است؛ برای نمایش به مسافر «انتشار» را بزنید.');
                $this->showTour($id);
            } else {
                $this->conversation->state('tour_' . $next[$field], $payload);
                $this->conversation->reply($prompts[$next[$field]]);
            }
            return true;
        }
        return false;
    }
}
