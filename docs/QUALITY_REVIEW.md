# گزارش ممیزی کیفیت مهندسی سورس

۲۱ اکتبر ۲۰۲۶ — **Travel Tour Reservation Bot**

مبنای قابل بازتولید این مرحله:
[`2c10ee7`](https://github.com/AlirezaBelal/travel-tour-reservation-bot/commit/2c10ee733ae1a4a8639895a03d12e5f45815b483).
این گزارش درباره کیفیت سورس عمومی است؛ هیچ ربات، توکن، سرور، Webhook یا دیتابیس
آنلاین برای مالک ایجاد یا فعال نشده است. آزمون‌های کانتینری فقط در CI و با داده
ساختگی اجرا می‌شوند. نتیجه نهایی باید همراه CI موفق همان Commit خوانده شود.

## نتیجه معماری

معماری **Service-Oriented Modular Monolith** حفظ شده است. Presentation دیگر SQL
یا ساخت سرویس عملیاتی ندارد. کنترل‌های مالکیت و مجوز در سرویس‌ها نیز اجرا می‌شوند.
تور، رزرو، چرخه وضعیت، پرداخت دستی، پروفایل، گزارش و اعلان مرزهای مشخص دارند؛
قواعد مستقل و Factoryهای صریح جای وابستگی‌های ضمنی را گرفته‌اند. تراکنش‌ها و
اتصال PDO مشترک مانده‌اند. Framework، Microservice یا Interface تکراری اضافه نشده است.

نمودارهای Context/Component، مسئولیت هر فایل، مسیر رزرو، قرارداد قفل‌ها و Outbox
در [معماری نهایی](ARCHITECTURE.md) ثبت شده‌اند. تصمیم سازگاری در
[ADR 0005](adr/0005-explicit-use-cases.md) توضیح داده شده است.

## مقایسه اندازه‌گیری‌شده

| شاخص | مبنای `2c10ee7` | نتیجه این مرحله |
|---|---:|---:|
| SQL مستقیم `ProductBot` | ۱۱ | **۰** |
| متدهای نام‌دار `ProductBot`، با Constructor | ۴۲ | **۱۰** |
| وابستگی‌های شیئی مستقیم Constructor در `ProductBot` | ۹ | **۶** |
| خطوط فیزیکی `ProductBot` | ۸۴۰ | **۲۶۸** |
| SQL مستقیم `BookingService` | ۱۸ | **۰**؛ API سازگاری و Delegation |
| خطوط فیزیکی `BookingService` | ۲۱۶ | **۸۲** |
| خطوط `handler.php` | ۴۶۰ | **۴۰۳**؛ Renderer بدون دیتابیس |
| خطوط `config.php` | ۲۳۲ | **۱۱۲** |
| بیشترین Cyclomatic Complexity میان مؤلفه‌های UI | ۲۳ | **۱۶** |
| خواندن Global/Environment در Application Serviceها | حداقل مسیر رزرو | **۰** در سرویس‌های عملیاتی |
| PHPStan | فاقد Gate | سطح **۵**، بدون Baseline فعال یا Ignore |
| Coding Standard | فاقد Gate | کنترل خودکار خطاهای **PSR-12** در سورس |

تعداد خطوط با احتساب Formatting گزارش شده و به‌تنهایی معیار پیشرفت نیست.
وابستگی‌های UI در میانه استخراج Queryها موقتاً به ۱۱ رسیدند؛ مقایسه نهایی، ۹ به
۶ نسبت به همان مبنای مشخص است. شمارش متد و خطوط از سورس Git انجام شده است.
پیچیدگی با Sniff رسمی `Generic.Metrics.CyclomaticComplexity` در PHPCS 3.13.6
اندازه‌گیری شده؛ گزارش نهایی **هر چهار** مؤلفه Product/Tour/Profile/Booking را
شامل می‌شود. کاهش سقف ۲۳ به ۱۶ حدود ۳۰٪ است؛ پیچیدگی با حذف کد استخراج‌شده از
گزارش پنهان نشده است. بعضی متدهای کارت و Routing هنوز چندشاخه‌اند؛ قواعد مالی و
ظرفیت در آنها تعریف نمی‌شوند.

## PRهای این مرحله

| PR | نتیجه اصلی |
|---|---|
| [۱۹](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/19) | صفرکردن SQL در ProductBot، پروفایل/Queryهای مجاز و Factory صریح |
| [۲۰](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/20) | TourService، TourRules و مرز مشترک قفل ظرفیت |
| [۲۱](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/21) | عضویت، ارسال قدیمی، حذف دسته و ورودی صریح LegacyUpdate |
| [۲۲](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/22) | ProfileController، Conversation، HttpIngress و حذف Helperهای خروجی Global |
| [۲۳](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/23) | ابزارهای قفل‌شده، PHPStan سطح ۵ و رفع تشخیص‌های واقعی بدون سرکوب |
| [۲۴](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/24) | Reservation/Lifecycle/Reader/History مستقل و سیاست پروفایل تزریق‌شده |
| [۲۵](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/25) | TourController، مجوز/اتمی‌بودن عملیات دسته و سنجش پیچیدگی |
| [۲۶](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/26) | Formatting مستقل، Gate سبک و مقایسه Tokenها |
| [۲۷](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/27) | فایل مستقل انواع پرداخت و Transport با Loaderهای سازگار |
| [۲۸](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/28) | BookingController و کاهش Router به ۶ وابستگی |
| [۲۹](https://github.com/AlirezaBelal/travel-tour-reservation-bot/pull/29) | تست قرارداد Retry، Guardهای نهایی، Pin کردن Actions و این گزارش |

PRهای قبلی ۱۶ تا ۱۸ حفظ شدند و دوباره‌نویسی نشدند. هر ادغام به موفقیت Gateهای
همان Head وابسته است؛ آخرین CI روی Merge Commit شاخه `master` نیز در تحویل بررسی
می‌شود. هیچ Force Push یا حذف تاریخچه‌ای در این مرحله انجام نشده است.

## شواهد تست و ابزارها

- Characterization کاتالوگ و ارسال قدیمی ابتدا روی پیاده‌سازی قبل از استخراج
  اجرا شد. تست‌های موجود حذف یا ضعیف نشدند.
- سرویس‌های مستقیم، مجوز، مالکیت، Projection بدون هویت، حفظ داده تاریخی، حذف
  حساب و سیاست پروفایل مستقل از Environment را بررسی می‌کنند.
- رقابت هشت پردازش برای ظرفیت، چهار پردازش برای پرداخت دستی و دو پردازش برای
  تأیید/لغو، به‌علاوه Idempotency درخواست و Webhook، روی هر دو دیتابیس حفظ شدند.
- شکست Outbox، شکست Audit دسته، Rollback بیرونی رزرو/پرداخت/تاریخچه/اعلان و
  حفظ قیمت Snapshot تست شده‌اند. Probe تراکنش، 1205/1213، Auto-abort، سقف پنج
  تلاش، خطای غیرقابل Retry و مالکیت تراکنش بیرونی را مستقل بررسی می‌کند.
- Worker واقعیِ تست، Retry/Lease، توقف پردازش، قطعی دیتابیس و بازیابی کانتینری
  را پوشش می‌دهد. Delivery خارجی، قرارداد At-least-once دارد.
- Guard معماری، سورس واقعی را بازگشتی می‌خواند و **۹ نقض مصنوعی** را باید پیدا
  کند: SQL/ساخت سرویس در Presentation، PDO/HTTP در قواعد، Global/Environment
  در سرویس و وابستگی چرخه‌ای. Include قدیمی فقط از Adapter مجاز است.
- PHPStan 2.3.1، سطح ۵، PHP هدف 8.0.30: صفر خطای فعال، بدون Include کردن Baseline
  یا Ignore. هفت تشخیص اولیه اصلاح/شفاف شدند؛ محافظ Rollback حذف نشد.
- PHPCS 3.13.6: خطاهای PSR-12 کنترل می‌شوند. نام‌های Global برای سازگاری حفظ
  شده‌اند و Warningهای طول خطوط متن، Advisory هستند. سه خطای چندکلاسی با جداسازی
  واقعی فایل‌ها رفع شدند، نه استثنای جدید در Rule.
- PR Formatting، Tokenهای ۵۷ فایل آن مقطع را با مبنا مقایسه کرد؛ فاصله/Comment،
  Braceهای کنترلی و شکل Else-if نرمال شدند. تست‌های رفتاری مکمل این بررسی بودند.
- Composer هر دو Graph برنامه و ابزارها را Audit می‌کند. Actions به SHA ثابت
  Pin شده‌اند و دسترسی Repository برابر `contents: read` است.

Gate نهایی شامل **۹ Job** است: پنج نسخه PHP 8.0–8.4، MySQL 8.0، MariaDB 10.11،
Static/Style و Docker. هر دیتابیس، Suite یکپارچه‌سازی را **دو بار** اجرا می‌کند.
Docker نصب تمیز، Migration/Seed تکراری، HTTP، حداقل دسترسی Runtime، بازیابی Worker
و Backup/Restore در Volume مستقل را بررسی می‌کند. تست‌ها از ربات واقعی استفاده
نمی‌کنند. درصد Coverage ادعا نمی‌شود؛ معیار این گزارش سناریوها و Assertionهای
اجراشده است.

اجرای محلی PHP/Docker در Workspace این مأموریت در دسترس نبود؛ اجرای معتبر از
GitHub Actions و دیتابیس‌های موقت آن دریافت شد. این محدودیت با موفقیت محلی اشتباه
گرفته نشده است.

## محدودیت‌های تحلیل و بدهی باقی‌مانده

| مورد | شدت / محل | اثر و تصمیم |
|---|---|---|
| Renderer قدیمی | P2 — `src/handler.php`، فراخوانی در `LegacyHandlerAdapter.php:25` | هنوز Include و چیدمان تکراری Keyboard دارد. بدون SQL/Medoo/Helper خروجی Global است؛ نگهداری آن از حذف رفتار قدیمی کم‌ریسک‌تر است. |
| عمق تحلیل و قراردادهای Array | P2 — `phpstan.neon:11`، Read Modelها | Template از PHPStan/Style خارج است و با Syntax/معماری/DB/HTTP کنترل می‌شود. سطح ۵ و Arrayها جای Levelهای بالاتر/Shape دقیق‌تر را نمی‌گیرند؛ Coverage درصدی هم اندازه‌گیری نشده است. |
| سازگاری بارگذاری و تنظیمات قدیمی | P2 — `product_bootstrap.php:4`، `LegacyProfileRequirement.php:10` | نام‌های Global و API/Include قبلی حفظ شده‌اند. Environment قدیمی فقط در Adapter سازگاری خوانده می‌شود؛ Runtime جدید سیاست صریح دارد. مهاجرت Namespace/PSR-4 می‌تواند مستقل انجام شود. |

در محدوده بازبینی و Gateهای این تغییرات، ایراد باز P0/P1 یا Regression شناخته‌شده
گزارش نشده است. محدودیت‌های تحلیل فوق و بررسی امنیت زیرساخت واقعی به معنی اثبات
ریاضی نبود خطا نیستند؛ این پروژه سرویس Production فعال ارائه نمی‌کند.

## امتیاز سخت‌گیرانه

امتیاز ۸٫۵ گزارش‌شده قبلی، زمینه مأموریت بود؛ نتیجه به‌طور خودکار از آن افزایش نیافته
است. امتیاز زیر قضاوت ممیزی سورس با وزن‌های درخواستی و شواهد همین گزارش است.

| معیار | وزن | امتیاز | دلیل و کسر امتیاز |
|---|---:|---:|---|
| معماری و قابلیت نگهداری | ۲۵ | **۲۳** | قواعد مستقل، Use Caseهای مجاز، کاهش Coupling و Root صریح؛ Template، Arrayها و بارگذاری قدیمی باقی‌اند. |
| امنیت و حریم خصوصی | ۲۵ | **۲۲** | حفاظت هویت، مجوز، CSV و Webhook با تست مستقل؛ ابطال اعتبارنامه تاریخی و امنیت نصب واقعی از سورس قابل تأیید نیست. |
| صحت کسب‌وکار و دیتابیس | ۲۵ | **۲۳٫۵** | ظرفیت، Snapshot، تکرار، Retry و اتمی‌بودن با تست واقعی؛ هم‌زمانی همچنان نیازمند مراقبت است. |
| تست‌ها و CI | ۱۵ | **۱۴** | Characterization، Mutation، تست منفی، رقابت و بازیابی؛ Static سطح ۵ و Coverage اندازه‌گیری‌نشده محدودیت‌اند. |
| مستندات و تجربه توسعه‌دهنده | ۱۰ | **۸٫۵** | راهنمای نصب، معماری، ADR و نصب مستقل در CI؛ سازگاری قدیمی هنوز هزینه شناخت دارد. |
| **مجموع** | **۱۰۰** | **۹۱** | **۹٫۱ از ۱۰ برای کیفیت مهندسی سورس** |

## اقدام دستی ضروری مالک

در تاریخچه Git الگوهای توکن قدیمی وجود دارد. مالک باید ابطال اعتبارنامه‌های
افشاشده را تأیید کند؛ مقدار آنها چاپ، آزمایش یا بازاستفاده نشد و تاریخچه تغییر
نکرد. در فایل‌ها و Diff جدید نباید Secret واقعی یا داده واقعی مسافر باشد.
این مأموریت به ساخت Bot، تنظیم Webhook یا راه‌اندازی زیرساخت نیاز ندارد.

## محدوده امتیاز و شاهد CI نهایی

عدد ۹٫۱ این گزارش، امتیاز **وزن‌دار کیفیت مهندسی سورس** طبق جدول بالاست؛
این عدد نباید به‌جای امتیاز مستقل Clean Code یا گواهی Production استفاده شود.
مبنای شروع اصلاحات Commit قبلی 2c10ee7 بود؛ ارزیابی تحویل روی
[Commit ef5a1c2](https://github.com/AlirezaBelal/travel-tour-reservation-bot/commit/ef5a1c2a12e0270fb98ecb926897e05fe09b43ff)
با [CI موفق ۹/۹](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/runs/39572108378)
تأیید شده است. این شاهد ثابت تاریخی است و برای Commitهای جدید باید
نتیجه CI همان Commit جداگانه بررسی شود.

## وضعیت تحویل، مشروط به CI موفق همان Commit

- CLEAN CODE: VERIFIED — در محدوده ممیزی، نه ادعای کمال
- ARCHITECTURE: VERIFIED
- SERVICE BOUNDARIES: VERIFIED
- LEGACY MODERNIZATION: PARTIAL — Renderer سازگاری حفظ شده است
- STATIC ANALYSIS: PASS — سطح ۵ و محدودیت مشخص
- SECURITY REGRESSION: NONE FOUND — تأیید ابطال تاریخی مستقل است
- MASTER CI: PASS — شاهد ۹/۹ مربوط به Commit ef5a1c2 در بالا؛ برای Commit جدید باید جدا تأیید شود
- REPOSITORY: PUBLIC — نام و GPL-3.0-only حفظ می‌شوند
- HOSTED INFRASTRUCTURE: NOT REQUIRED
