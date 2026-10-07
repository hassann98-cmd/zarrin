# آزمون یکپارچهٔ دورریختنی وردپرس

**نسخهٔ پوسته: 1.7.28 — تاریخ اجرا: ۲۰۲۶-۱۰-۰۶**

این مجموعه مکمل تست‌های ایزولهٔ `tests/php` است؛ جایگزین آزمون خرید واقعی WooCommerce نیست.

## چه چیزی واقعاً اجرا شد؟

- کد واقعی **WordPress 6.9**، با پوستهٔ فعال و دیتابیس تازهٔ **SQLite**؛ APIهای وردپرس در این مجموعه mock نشده‌اند.
- **۶۳ کنترل نام‌گذاری‌شده**، یک بار روی **PHP-WASM 8.3.33** و یک بار روی **8.5.10**؛ هر دو پاس شدند. هر اجرا، fixture و دیتابیس جدا دارد.
- حساب‌ها، نقش‌ها، هش رمز، cookie ورود، nonce وردپرس، REST routing/schema، جدول options، transientها و cache داخلی وردپرس واقعی‌اند.
- درخواست‌ها از request handler وردپرس می‌گذرند؛ listener شبکه یا مرورگر واقعی راه‌اندازی نمی‌شود.
- SMS **شبیه‌سازی می‌شود**؛ هیچ کد واقعی ارسال نمی‌شود. mail و درخواست‌های خارجی از طریق APIهای HTTP وردپرس مسدودند. کلید و رمز موجود در fixture فقط مقادیر آزمایشی‌اند، نه credential فروشگاه.
- WooCommerce **در اجرای integration نصب نشده است**؛ این suite عمداً رفتارِ نبودِ افزونه را هم می‌سنجد. دانلود ZIP رسمی 11.0.0 در اجرای قبلی (۲۰۲۶-۰۹-۲۴) ناموفق بود؛ در ممیزی ۲۰۲۶-۱۰-۰۶ آرشیو source رسمی WooCommerce 11.1.2 جداگانه فقط برای **بررسی استاتیک قرارداد** استفاده شد (۶۶ تابع، ۱۱۲ متد و ۲۴۳ hook؛ بدون بوت‌کردن افزونه، checkout یا درگاه). این بررسی استاتیک جای runtime واقعی WooCommerce را نمی‌گیرد.

بنابراین تست «ووکامرس غیرفعال است و پاسخ کنترل‌شده می‌گیریم» یک تست واقعی است؛ اما آزمون مالکیت سفارش، سبد و موجودی در `tests/php` هنوز از WooCommerce شبیه‌سازی‌شده استفاده می‌کند. هیچ ادعایی دربارهٔ MySQL، HPOS، Redis، هم‌زمانی چند worker، checkout، بانک، تحویل پیامک، Safari/Chrome یا فروشگاه فعال نداریم.

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

# نسخهٔ دوم مفسر؛ داده‌ها دوباره از صفر ساخته می‌شوند:
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

job یکپارچه به `.github/workflows/quality.yml` اضافه شده، ولی اجرای remote آن در GitHub Actions در این نوبت مشاهده نشده است. نتایج بالا مربوط به اجرای محلی همین کد هستند.
