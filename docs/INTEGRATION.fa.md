# آزمون یکپارچهٔ دورریختنی وردپرس

**نسخهٔ پوستهٔ منتشرشده: 1.7.56 · کد `acfed03` · CI انتشار: موفق (۲۰۲۶-۱۰-۱۰)**

این سند دو مسیر دورریختنی را جدا گزارش می‌کند: آزمون پایهٔ WordPress بدون WooCommerce برای کنترل رفتار در نبود افزونه، و آزمون همراه WooCommerce واقعی برای سبد و رندر checkout. هیچ‌کدام جای آزمون نهایی فروشگاه روی staging را نمی‌گیرند. برای `1.7.56`، `npm run check` با ۲۲۰ تست JavaScript و ۱٬۰۶۹ assertion PHP-WASM گذشت؛ GitHub Actions همان commit، checkهای PHP 8.0/8.3/8.5، WordPress/SQLite را روی PHP 8.3 و 8.5 و WooCommerce 11.2.0 واقعی با WordPress 7.0 را نیز گذراند. هیچ سفارشی ثبت یا پرداخت/درگاه زنده‌ای اجرا نشد.

## چه چیزی واقعاً اجرا شد؟

- کد واقعی **WordPress 6.9**، با پوستهٔ فعال و دیتابیس تازهٔ **SQLite**؛ APIهای وردپرس در این مجموعه mock نشده‌اند.
- **۶۳ کنترل نام‌گذاری‌شده**، یک بار روی **PHP-WASM 8.3.33** و یک بار روی **8.5.10**؛ هر دو پاس شدند. هر اجرا، fixture و دیتابیس جدا دارد.
- حساب‌ها، نقش‌ها، هش رمز، cookie ورود، nonce وردپرس، REST routing/schema، جدول options، transientها و cache داخلی وردپرس واقعی‌اند.
- درخواست‌ها از request handler وردپرس می‌گذرند؛ listener شبکه یا مرورگر واقعی راه‌اندازی نمی‌شود.
- SMS **شبیه‌سازی می‌شود**؛ هیچ کد واقعی ارسال نمی‌شود. mail و درخواست‌های خارجی از طریق APIهای HTTP وردپرس مسدودند. کلید و رمز موجود در fixture فقط مقادیر آزمایشی‌اند، نه credential فروشگاه.
- اجرای پایهٔ `npm run test:integration` WooCommerce **نصب نمی‌کند** و مسیر نبودِ افزونه را می‌سنجد. اجرای جداگانهٔ `npm run test:integration:woo` در GitHub Actions با **WordPress 7.0 + WooCommerce 11.2.0 واقعی**، PHP-WASM 8.3 و SQLite **۱۷ کنترل** را روی رندر storefront، سبد ساده/متغیر، اعتبارسنجی موجودی، کوپن و نمایش checkout کلاسیک گذراند؛ همین job در commitهای `492a07a` و `91152cf` موفق شد. این آزمون فقط سبد را دست‌کاری و پاک می‌کند؛ هیچ order ثبت، stock واقعی فروشگاه کم، یا درگاه/پرداخت/پیامک زنده اجرا نمی‌شود. بررسی استاتیک قرارداد با آرشیو WooCommerce 11.1.2 نیز ۷۰ تابع و ۱۱۴ متد مورد استفاده را یافت؛ ۲۳ template بررسی شد که ۲۲ نسخه‌برچسب با upstream منطبق بود.
- اجرای کیفیت commit `acfed03` روی [GitHub Actions](https://github.com/hassann98-cmd/zarrin/actions/runs/37995040934) موفق بود: checkهای PHP 8.0/8.3/8.5، WordPress 6.9 + SQLite روی PHP-WASM 8.3/8.5 و WooCommerce 11.2.0 واقعی با WordPress 7.0. انتشار [v1.7.56](https://github.com/hassann98-cmd/zarrin/actions/runs/37995040735) ZIP را ساخت، به [Release عمومی](https://github.com/hassann98-cmd/zarrin/releases/tag/v1.7.56) پیوست، دانلود کرد و بایت‌به‌بایت تطبیق داد؛ هیچ order، پرداخت/درگاه یا پیامک زنده‌ای اجرا نشد.
- روی commit مستنداتیِ `1f34e80` یک اجرای `checks (8.0)` در `npm run check` با exit code 1 تمام شد؛ باقی jobها پاس شدند و دانلود log برای یافتن علت با EOF روبه‌رو شد. پس از آن، workflow کامل روی دو commit مستنداتیِ `e1b4402` و `9c041ff` همهٔ jobها را گذراند؛ شامل PHP 8.0/8.3/8.5، WordPress/SQLite و WooCommerce ([اجرای نخست](https://github.com/hassann98-cmd/zarrin/actions/runs/37995516834) · [اجرای بعدی](https://github.com/hassann98-cmd/zarrin/actions/runs/37995680866)). بین این runها فقط مستندات تغییر کرد؛ علت شکستِ تک‌مرحله‌ایِ اول نامشخص است و به تست سفارش/پرداخت مربوط نبود.
- اجرای کیفیت commit `91152cf` روی [GitHub Actions](https://github.com/hassann98-cmd/zarrin/actions/runs/37992707258) کامل و سبز بود: checkهای PHP 8.0/8.3/8.5، WordPress/SQLite روی PHP-WASM 8.3 و 8.5 و WooCommerce. `checks (8.3)` که در دو run قبلی 1.7.54 ناموفق شده بود، این بار هم موفق شد. انتشار [v1.7.55](https://github.com/hassann98-cmd/zarrin/actions/runs/37992706976) ZIP را از همان commit ساخت، در Release ضمیمه کرد و دانلود را بایت‌به‌بایت تطبیق داد.

بنابراین مسیر «ووکامرس غیرفعال است و پاسخ کنترل‌شده می‌گیریم» در WordPress 6.9 واقعی آزموده شده و مسیر cart/stock/coupon/checkout-render در WooCommerce 11.2.0 واقعی نیز در محیط جداگانه اجرا شده است. مالکیت سفارش در `tests/php` همچنان با doubles پوشش دارد؛ هیچ سفارش واقعی/fixture ثبت نمی‌شود. این نتایج MySQL، HPOS، Redis، هم‌زمانی چند worker، ارسال سفارش/پرداخت، بانک، تحویل پیامک، Safari/Chrome یا رفتار سایت فعال را تأیید نمی‌کنند.

## کنترل‌های مهم

1. فعال‌سازی پوسته، رندر عمومی، template پیگیری و enqueue فایل `assets/js/order-tracking.js` بدون WooCommerce.
2. bootstrap مهمان و واردشده، cookie واقعی پس از ورود با رمز و لزوم REST nonce برای هویت کاربر.
3. رد nonce منقضی سبد قبل از callback عملیاتی؛ پاسخ JSON با وضعیت 503 برای WooCommerce غیرفعال.
4. حذف trigger استاندارد `add-to-cart` پیش از اولویت ۲۰ در `wp_loaded` درخواست AJAX اختصاصی. یک **ناظر تست** حضور فیلد را بررسی می‌کند؛ این کنترل، اجرای سبد واقعی WooCommerce نیست.
5. مسیر REST ساده و pretty در نصب `/store/`، رد GET پیگیری، اندازه/نوع ورودی و `private, no-store` برای موفقیت و خطاهای خود هسته؛ تطبیق namespace با حروف بزرگ نیز پوشش دارد.
6. درخواست OTP با ارقام فارسی، ثبت HMAC، تأیید و ایجاد cookie واقعی، رد مصرف دوباره، پنج خطای متوالی، رد OTP مدیر و جلوگیری از ورود با `billing_phone` تنها.
7. اتصال صریح شماره با cookie + nonce + OTP آزمایشی، شکست provider شبیه‌سازی‌شده و مصرف بودجهٔ ارسال ناموفق.
8. claim قفل و جایگزینی مالک منقضی، مصرف شرطی options، پاک‌شدن cache گرم‌شده و شمارش واقعی transient؛ **به‌صورت ترتیبی، نه اثبات رقابت MySQL**.
9. جلوگیری از import کد برای مشتری با capability واقعی وردپرس، و round-trip تنظیمات مدیر با لیست خالی و بک‌اسلش.
10. خالی‌بودن debug log از خطای PHP/DB، Warning، Notice و Deprecated در سناریوهای اجراشده.

## اجرا در محیط توسعه

ابزارهای اختیاری: **Node >=24.18، npm >=11.16، Python 3 و `gh` قابل‌استفاده**. برای build معمول فرانت‌اند همچنان Node 22.12+ کافی است. این ابزارها روی هاست فروشگاه لازم نیستند.

از ریشهٔ مخزن، با Node و npm بالا:

```sh
npm ci
npm run build
npm ci --prefix tests/integration --ignore-scripts
npm --prefix tests/integration run prepare:fixtures
npm run test:integration

# مسیر جدا با WooCommerce 11.2.0 رسمی؛ فایل را پیشاپیش طبق workflow و checksum pin تهیه کنید:
ZARRIN_WOOCOMMERCE_ARCHIVE=/absolute/path/to/woocommerce-11.2.0.zip npm run test:integration:woo

# نسخهٔ دوم مفسر برای suite پایه؛ داده‌ها دوباره از صفر ساخته می‌شوند:
ZARRIN_TEST_PHP=8.5 npm run test:integration
```

وابستگی‌های SDK فقط در `tests/integration/node_modules` نصب می‌شوند. نصب پاک با lifecycle scriptهای غیرفعال هم اجرا و آزمایش شد؛ فایل‌سیستم میزبان را به PHP mount نمی‌کنیم و برای این آزمون نیازی به اجرای اسکریپت build افزونهٔ native نبود.

- آماده‌سازی از API خواندنی GitHub، فایل‌های upstream قفل‌شده را می‌گیرد. در Arena اتصال GitHub از قبل تنظیم شده است؛ این ابزار هیچ رمز یا token از چت نمی‌گیرد و در فایل نمی‌نویسد.
- مسیر پیش‌فرض آرشیوها: `.cache/integration/archives`، خارج از Git و ZIP انتشار. برای مسیر موجود، `ZARRIN_INTEGRATION_DOWNLOADS=/absolute/path` را **هم در آماده‌سازی و هم در اجرا** تنظیم کنید.
- اجرای خود تست دانلود ندارد. SHA-256 آرشیوها پیش از راه‌اندازی بررسی می‌شود؛ شکست checksum باید بررسی شود، نه اینکه کنترل آن حذف شود.
- دیتابیس و نسخهٔ اجرایی پوسته داخل فایل‌سیستم موقت WASM ساخته و در پایان dispose می‌شوند. `.git`، source تست، credentialها، uploads واقعی یا دیتابیس فروشگاه به محیط PHP mount نمی‌شوند.
- مقدار `ZARRIN_INTEGRATION_TEST` فقط در runtime آزمون تعریف می‌شود؛ آن را به `wp-config.php` فروشگاه اضافه نکنید. `safety.php` افزونهٔ قابل‌نصب روی فروشگاه نیست و بدون این ثابت با 404 متوقف می‌شود.
- runner روی exit code تنها تکیه نمی‌کند؛ خروجی PHP باید marker کامل داشته باشد. پایان موفق: `WORDPRESS_INTEGRATION_PASSED: 63`.

## منشأ fixtureها و ابزارها

| جزء | pin |
|---|---|
| WordPress | `WordPress/WordPress`، commit `ec24ee6087dad52052c7d8a11d50c24c9ba89a3b`، نسخهٔ 6.9 |
| SQLite integration | `WordPress/sqlite-database-integration`، commit `f3ea1a43ba525be382c7a9c17735b6b4d4b11d49`، tag `v2.2.23` |
| SDK | `@php-wasm/node`، `@php-wasm/universal`، `@wp-playground/wordpress` همگی `3.1.55`، با lockfile مستقل |

آرشیو source پروژهٔ SQLite به‌علت `export-ignore`، runtime لازم را ندارد. `download.mjs` مطابق ساختار بسته‌بندی upstream، فایل‌های plugin و `mysql-on-sqlite/src` را کنار هم قرار می‌دهد و symlink منبع را با فایل‌های واقعی جایگزین می‌کند. **۴۶ فایل** با hash blob ثبت‌شده در `sqlite-files.json` کنترل می‌شوند؛ ZIP مشتقِ آزمون با timestamp ثابت و ورودی‌های بدون فشرده‌سازی ساخته می‌شود تا تفاوت نسخهٔ zlib نتیجه را عوض نکند. هیچ فایل PHP پایگاه‌داده برای تطبیق با پوسته patch نشده است.

این نسخه‌ها pin بازتولید آزمون‌اند، نه توصیهٔ نصب همین نسخهٔ WordPress در production. ارتقای dependencyها و تست امنیت/سازگاری نسخهٔ انتخابی هاست، کار جداگانه است.

## شاهد خطاها و مرز نتیجه

در اولین اجرای واقعی، پیگیری بدون WooCommerce با `Call to undefined function wc_get_order()` به 500 می‌رسید؛ پس از اصلاح، همان درخواست 503 کنترل‌شده و غیرقابل‌کش دارد. همچنین ورودی آرایه به فیلد متنی دارای sanitizer، پیش از اصلاح از schema عبور می‌کرد؛ callback صریح اعتبارسنجی این مسیر را بست. پاسخ موفق ورود نیز پیش از اصلاح هدر صریح `private, no-store` نداشت.

ریسک اجرای دو مسیر افزودن به سبد با بررسی [فرم‌هندلر WooCommerce 11.0.0](https://github.com/woocommerce/woocommerce/blob/11.0.0/plugins/woocommerce/includes/class-wc-form-handler.php) و payload فرم‌های خود پوسته مشخص شد. حذف فیلد فقط از payload AJAX، حفظ فرم استاندارد و ترتیب hook وردپرس تست دارند؛ شمارش واقعی اقلام بعد از خرید/افزودن هنوز باید روی WooCommerce نصب‌شده تأیید شود.

در commit `6d5e550`، اجرای GitHub Actions با نتیجهٔ success کامل شد: jobهای build/test روی PHP 8.0/8.3/8.5، WordPress 6.9 روی PHP-WASM 8.3/8.5، و WooCommerce 11.2.0 واقعی همگی پاس شدند ([run 37959765669](https://github.com/hassann98-cmd/zarrin/actions/runs/37959765669)). workflow انتشار نیز ZIP نسخهٔ 1.7.53 را ساخت، به Release عمومی پیوست و دانلود asset را بایت‌به‌بایت با خروجی build تطبیق داد ([run 37959765805](https://github.com/hassann98-cmd/zarrin/actions/runs/37959765805)). این CI همچنان staging فروشگاه واقعی را جایگزین نمی‌کند.
