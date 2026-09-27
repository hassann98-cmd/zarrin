# نقشهٔ راه زرین — از 1.56

**منبع:** اولویت‌بندیِ مالک پروژه (۲۰۲۶-۰۹-۲۷) · **اصلِ حاکم:** «اضافه‌کردن امکانات زیاد بدون کنترل هزینهٔ JS/PHP دوباره سرعت را خراب می‌کند» — پس هر آیتم با هزینهٔ عملکردی‌اش سنجیده می‌شود، نه فقط ارزشش.

**انتخابِ پنج‌گانهٔ مالک برای مرحلهٔ بعد:** Asset Loading ← Instant Search ← Quick Add/Sticky Cart ← AI RAG ← Product-page UX

**قاعدهٔ راستی‌آزمایی (همیشگی):** هر تغییر code-verified است؛ ادعایی دربارهٔ فروشگاه/مرورگر واقعی بدون اسکرین‌شات/DOM کاربر نمی‌رود. کلید API فقط سمت سرور؛ هیچ کلیدی در ZIP/مستندات.

## فازها

| فاز | نسخه | محتوا | وضعیت |
|---|---|---|---|
| ۱ — Performance | 1.56 | Asset manager، کش مگامنو، resource hints، image pipeline، کاهش JS | **R62-R63 ✓** (registry/dedupe هم اضافه شد؛ تولیدِ AVIF سمتِ میزبان باقی است) |
| ۲ — خرید | 1.57 | Quick Add، مودال پیشنهاد تکمیل‌تر، Sticky Cart، Variable UX، مینی‌کارت | **R63 ✓** (فوترِ سبد + تیکِ quick-add؛ بقیه از قبل بود) |
| ۳ — Search | 1.58 | Instant Search کامل، نرمال‌سازی فارسی، typo tolerance | **R63: نرمال‌سازی ✓** (ی/ک/ة/اعراب/نیم‌فاصله)؛ typo tolerance کامل باز |
| ۴ — AI | 1.59 | GapGPT gateway، RAG محصولات، پشتیبانی، AI Review Summary | **بازبینی شد: هسته از قبل برقرار** (tools + گیت سفارش/موبایل + خلاصهٔ نظرات) |
| ۵ — UI | 1.60 | Design System، هدر، کارت‌ها، فوتر، میکرواینترکشن | **R63: توکن‌ها + hover guard + هدر فشرده ✓**؛ بازطراحی کامل باز |
| ۶ — SEO/A11y | 1.61 | Semantic audit، WCAG، CWV، محتوای ساخت‌یافته، FAQ | **R63: skip-link + focus trap ✓**؛ WCAG کامل باز |

## آیتم‌های فاز ۱ و وضعیت کد

| # | آیتم | وضعیت | یادداشت |
|---|---|---|---|
| 1 | کش مگامنو (Object Cache + Transient fallback + invalidation روی `product_cat` + خروجی آرایه) | **R62 ✓** | `jluxe_get_mega_menu_categories` دو لایه شد؛ invalidation هر دو لایه را می‌گیرد |
| 2 | Asset Loading per page (سیستماتیک، جدول صفحه→asset) | **R62 ✓** | `inc/assets.php`: `jluxe_page_context` + برنامهٔ فیلترپذیر `jluxe_asset_plan` + dequeue مرکزی؛ `wc-cart-fragments` همیشه خاموش (مینی‌کارت REST-based است) |
| 3 | API deduplication در SPA (request واحد برای posts/categories/brands/products + data registry) | باز | جزو فاز ۱؛ نیازمند بازبینی island ها — ریسک کم، ولی دامنه‌اش با مالک چک شود |
| 4 | Resource hints هوشمند (فقط LCP/فونت/preconnect) | بخشاً ✓ | preload فونت‌ها (`functions.php`) و preload دو-مدیاییِ هیرو با `fetchpriority` از قبل هست؛ preconnect برای REST بی‌معناست (same-origin) — چیزِ بی‌دلیل اضافه نشد |
| 5 | Image pipeline | بخشاً ✓ | srcset/sizes واقعی + eager/LCP اولین کارت از قبل هست (R56)؛ تولیدِ WebP/AVIF سمتِ میزبان باقی است |

## آیتم‌های فاز ۲ (خرید) — وضعیت فعلی

- **مودال پیشنهاد (R47-R61):** مسیر add → پاسخ endpoint → snapshot → مودال → پیشنهاد یکپارچه شد؛ خروجیِ خالی = بدون مودال؛ ردیفِ variable = quick-variant. باقی‌مانده از پیشنهادِ مالک: فوترِ مودال با «سبد شما: N کالا / مبلغ» و دکمه‌های [مشاهده سبد] [ادامه خرید] بدون redirect.
- **Quick Add در کارت محصول:** باز — loading/checkmark/«به سبد اضافه شد»/badge/mini-cart update.
- **Variable UX (انتخاب رنگ/مدل، سواچ، قیمت و موجودی variation):** پایه‌اش هست (`attribute-swatches.php` + quick-variant)؛ گسترش به کارتِ محصول باز است.
- **Sticky Cart موبایل:** از R43 هست — نکتهٔ مالک «فقط بعد از خروج CTA اصلی از viewport» رعایت است (IntersectionObserver).

## آیتم‌های فاز ۳ (Search) — وضعیت فعلی

- endpoint جستجوی زنده (محصول/دسته/برند + debounce) هست (`inc/search.php` + هدر).
- باقی‌مانده: پیشنهادِ جستجو، شمارش «۲۴ محصول» زیر دسته، نرمال‌سازی فارسی (ي/ی، ك/ک، نیم‌فاصله، فاصله اضافی)، typo tolerance.

## آیتم‌های فاز ۴ (AI) — وضعیت و قواعد

- Gateway فعلی: provider `gapgpt` طبق مستندات رسمی؛ فقط سمت سرور.
- RAG محصولات (Woo search ← چند محصول واقعی ← GapGPT) برای کاهش هزینه و hallucination.
- اطلاعات سفارش فقط پس از احراز هویتِ مناسب (سفارش + موبایل) و با حداقلِ داده.
- AI Review Summary (نقاط قوت/ضعف/مناسب برای چه کسی + ⭐ میانگین).

## آیتم‌های فاز ۵ و ۶ (خلاصه)

- Design System مرکزی (`--jluxe-*` برای رنگ/ردیوس/سایه) — از R44 پیش‌نمونه هست (توکن‌های panel)؛ تعمیم باز است.
- کارت premium: سفید/cream، border مویی، radius ۱۶-۲۰، shadow خیلی نرم، hover کوتاه، فضای سفید.
- hover فقط با `@media (hover:hover)`؛ skeleton فقط برای بخشِ واقعاً async.
- هدر دو مرحله‌ای (دسکتاپ/موبایل) + کوچک‌شدنِ ظریف با اسکرول؛ مگامنو با ستون پرفروش‌ها lazy.
- محصول above-the-fold کامل (نام/برند/قیمت/تخفیف/موجودی/variation/ارسال/ضمانت/CTA) + «ارسال به …» کنار قیمت.
- Trust Bar پنج‌گانه + نمادها جدا؛ Review UX با عکس/ویژگی/«پیشنهاد می‌کنم».
- A11y سراسری: button/a واقعی، aria-label برای icon-only، focus-visible، کیبورد، contrast، alt واقعی، سلسله‌مراتب heading، focus trap مودال، Escape.
- SEO: Rank Math صاحبِ Schema می‌ماند — تم فقط semantic HTML؛ پاسخ‌دوست بودنِ صفحه محصول + FAQ داینامیک (`inc/product-faq.php` هست؛ گسترش باز).

## بک‌لاگ ارزش‌دار (به ترتیب ارزش مالک)

Wishlist ← اطلاع‌رسانی موجودی ← هشدار کاهش قیمت ← مقایسه ← جستجوی سریع ← مشاهده اخیر ← پیگیری سفارش ← AI Support ← AI Review Summary ← پیشنهاد مکمل هوشمند ← فاکتور در Account ← PWA سبک

## صریحاً اضافه نمی‌کنیم (تصمیم مالک)

انیمیشن زیاد، video background، 3D، carouselهای متعدد، particle، preload همه تصاویر، JS framework جدید، API اضافه برای هر Section، Schema موازی Rank Math — «هزینهٔ عملکردی بیشتر از ارزش است.»
