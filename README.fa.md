# ربات متن‌باز رزرو تور

[English](README.md) · [وضعیت CI](https://github.com/AlirezaBelal/travel-tour-reservation-bot/actions/workflows/ci.yml) · [GPL-3.0-only](LICENSE)

ربات رزرو گروهی تور با رابط فارسی، نوشته‌شده با PHP و MySQL. **کد برای نصب روی سرور خودتان است؛ ربات میزبانی‌شده یا گواهی آماده‌بودن Production ارائه نمی‌شود.**

## امکانات

- معرفی، جست‌وجو و مدیریت تور؛ رزرو **۱ تا ۲۰ نفر** با ظرفیت و قیمت ثبت‌شده در تراکنش.
- پیگیری و تأیید/رد/لغو درخواست؛ **بدون درگاه پرداخت واقعی و استرداد خودکار**.
- نقش‌های مدیریتی، گزارش CSV بدون شناسه مسافر، اعلان‌های صف‌شده و تلاش مجدد.
- پروفایل با شماره اشتراک‌گذاری‌شده از حساب خود مسافر و کد ملی دارای رقم کنترل؛ اطلاعات هویتی رمزگذاری می‌شوند، اما استعلام رسمی هویت انجام نمی‌شود.
- سازگاری دستورات قدیمی، Docker و تست‌های خودکار.

## نصب از صفر

به Docker Compose، ربات تلگرام متعلق به خودتان، دامنه HTTPS و اطلاعات محرمانه نیاز دارید.

~~~bash
git clone https://github.com/AlirezaBelal/travel-tour-reservation-bot.git
cd travel-tour-reservation-bot
cp .env.example .env
# فایل .env را خصوصی تکمیل کنید؛ شناسه مدیر و گذرواژه‌های مجزای دیتابیس.
# TELEGRAM_WEBHOOK_SECRET: openssl rand -hex 32
# PROFILE_ENCRYPTION_KEY: openssl rand -base64 32
docker compose build app
docker compose run --rm migrate php bin/install.php
docker compose run --rm migrate php bin/restrict-runtime.php --confirm
docker compose up -d
curl -f http://127.0.0.1:8080/health.php
~~~

آدرس `127.0.0.1:8080` را پشت Reverse Proxy امن با HTTPS قرار دهید. پس از آماده‌شدن دامنه، وبهوک **ربات مجاز خودتان** را ثبت کنید:

~~~bash
docker compose run --rm app php bin/set-webhook.php https://YOUR_DOMAIN/index.php --check
# دستور بعدی واقعاً به تلگرام متصل می‌شود:
docker compose run --rm app php bin/set-webhook.php https://YOUR_DOMAIN/index.php --confirm
~~~

سرویس `worker` برای ارسال پیام و وظایف زمان‌بندی ضروری است. هرگز `.env`، توکن، کلید رمزگذاری یا اطلاعات واقعی مسافر را Commit نکنید. دستور `docker compose down -v` داده‌های Volume را حذف می‌کند. برای دیتابیس موجود حتماً [مهاجرت](docs/MIGRATION.md) و [پشتیبان‌گیری](docs/DEPLOYMENT.md) را بخوانید.

## استفاده مسافر

`/start` → پروفایل → تورها/جست‌وجو → انتخاب تعداد نفرات → ثبت درخواست → «رزروهای من» برای مشاهده وضعیت یا لغو. فقط شماره تماس متعلق به همان حساب از دکمه تلگرام پذیرفته می‌شود؛ `/privacy` برای حریم خصوصی و حذف حساب است. **درخواست به‌معنای تأیید نهایی نیست.**

قیمت‌ها **ریال** و تاریخ ورودی **میلادی** است؛ تاریخ‌ها در دیتابیس UTC ذخیره می‌شوند. درخواست‌های منتظر ظرفیت را نگه می‌دارند؛ رزرو مجدد همان تور پس از لغو فعلاً خودکار نیست. `REQUIRE_VERIFIED_PROFILE` به‌طور پیش‌فرض `false` است و فعال‌سازی آن به سیاست جمع‌آوری داده نیاز دارد. داده هویتی قدیمی خودکار منتقل یا حذف نمی‌شود.

## مدیریت

شناسه مدیر اصلی را در `TELEGRAM_ADMIN_IDS` تنظیم و `/admin` را باز کنید. تور بسازید و منتشر کنید، پرداخت دستی را **خارج از ربات واقعاً بررسی** کنید، درخواست‌ها را تأیید/رد و نقش‌ها، گزارش‌ها و اعلان‌ها را مدیریت کنید. از مسافر اطلاعات کارت یا رمز بانکی نخواهید.

پرداخت آنلاین و استرداد خودکار وجود ندارد؛ شبیه‌ساز پرداخت فقط برای آزمایش محلی است. ارسال تلگرام «**حداقل یک‌بار**» است و در موارد خطای تأیید ارسال ممکن است تکرار شود. سیاست حذف داده، نگهداری کلید، بکاپ و امنیت سرور بر عهده اپراتور است.

## تست و توسعه

~~~bash
composer install
composer test:unit
# فقط دیتابیس آزمایشی دورریختنی با نام travel_tour_test
composer test:integration
composer install --working-dir=tools/quality
composer analyse
composer lint:style
~~~

CI نسخه‌های PHP 8.0–8.4، دو موتور دیتابیس و بازیابی Docker را تست می‌کند؛ نه تحویل واقعی تلگرام یا تسویه بانکی.

**راهنماها:** [فهرست مستندات](docs/README.md) · [معماری](docs/ARCHITECTURE.md) · [محدودیت محصول](docs/PRODUCT.md) · [نصب و بازیابی](docs/DEPLOYMENT.md) · [امنیت](SECURITY.md) · [مشارکت](CONTRIBUTING.md).
