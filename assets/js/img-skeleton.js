/**
 * اسکلتون/شیمرِ لودینگِ عکس — سراسریِ کاملِ سایت (نه فقط ووکامرس یا یک
 * کامپوننتِ خاص)، طبقِ درخواستِ صریحِ کاربر: «رو تک‌تکِ عکس‌ها، همه‌جای
 * سایت». حالتِ پیش‌فرضِ شیمر خودش با CSS (globals.css: img:not(.jluxe-img-loaded))
 * برقراره — این فایل فقط کلاسِ jluxe-img-loaded رو بعدِ لودِ واقعیِ هر
 * <img> اضافه می‌کنه.
 *
 * چون خیلی از عکس‌های سایت React (آیلندها) یا AJAX (مینی‌کارت، مودالِ
 * تنوع، پاپ‌آپِ پیشنهادی) هستن و بعد از لودِ اولیه‌ی صفحه اضافه می‌شن،
 * فقط querySelectorAll یک‌بار در ابتدا کافی نیست — یک MutationObserver
 * رو کلِ <html> هر <img> تازه‌اضافه‌شده (هرجوری که اضافه شده باشه، حتی
 * innerHTML/dangerouslySetInnerHTML) رو هم می‌گیره.
 *
 * شبکه‌ی ایمنی: یک setTimeout مستقلِ ۸ثانیه‌ای برای هر عکس، تا اگه به هر
 * دلیلِ نادری (باگِ مرورگر، رویدادی که فایر نشد) رویدادِ load/error هیچ‌وقت
 * اجرا نشه، عکس تا ابد پشتِ شیمر گیر نکنه — نمایشِ عکسِ خام بهتر از یک
 * شیمرِ همیشگیه.
 *
 * بازنگریِ نسخه‌ی قبلی («Skeleton هوشمندتر»، طبقِ ریویوِ خودِ کاربر): نسخه‌ی
 * قبلیِ این فایل یک MIN_VISIBLE_MS=400 داشت که هر عکسی رو حداقل ۴۰۰
 * میلی‌ثانیه پشتِ شیمر نگه می‌داشت، حتی اگه خودِ عکس زودتر آماده بود —
 * یعنی یک تأخیرِ مصنوعی، فقط برای اینکه کاربر «افکت رو ببینه». طبقِ
 * نقدِ درستِ خودِ کاربر («Skeleton نباید سرعتِ واقعیِ سایت رو کند کنه فقط
 * برای اینکه کاربر افکت ببینه») این منطق برداشته شد.
 *
 * نکته‌ی فنیِ مهم که این تصمیم رو توجیه می‌کنه: چون <img> یک «عنصرِ
 * جایگزین»ه، همین که پیکسل‌های واقعیِ عکس آماده بشن، خودِ مرورگر (کاملاً
 * مستقل از این اسکریپت یا کلاسِ jluxe-img-loaded) اون‌ها رو به‌جایِ
 * پس‌زمینه/شیمرِ CSS رسم می‌کنه — یعنی شیمر دقیقاً به‌اندازه‌ی زمانِ واقعیِ
 * دانلود دیده می‌شه، نه بیشتر نه کمتر، بدونِ نیاز به هیچ منطقِ تایمینگی
 * تو JS. کارِ این اسکریپت فقط این‌جاست: بعدِ لودِ واقعی، انیمیشن رو متوقف و
 * پس‌زمینه رو پاک کنه (برای عملکرد/تمیزی، نه برای «مخفی‌کردنِ» چیزی که
 * از قبل مرورگر خودش پوشونده). نتیجه: عکسِ سریع (روی یک سایتِ بهینه‌شده)
 * اصلاً شیمرِ محسوسی نمی‌گیره — نه چون مخفی‌اش کردیم، بلکه چون واقعاً
 * سریع لود شده؛ عکسِ کند همچنان کاملاً و به‌درستی شیمر رو نشون می‌ده.
 */
(function () {
	"use strict";

	function markLoaded(img) {
		img.classList.add("jluxe-img-loaded");
	}

	function setup(img) {
		if (img.hasAttribute("data-jluxe-no-skeleton") || img.classList.contains("jluxe-img-loaded")) {
			return;
		}
		if (img.complete) {
			markLoaded(img);
			return;
		}
		img.addEventListener("load", function () { markLoaded(img); }, { once: true });
		img.addEventListener("error", function () { markLoaded(img); }, { once: true });
		window.setTimeout(function () { markLoaded(img); }, 8000);
	}

	function setupAll(root) {
		if (root.tagName === "IMG") {
			setup(root);
			return;
		}
		if (!root.querySelectorAll) {
			return;
		}
		root.querySelectorAll("img").forEach(setup);
	}

	function init() {
		setupAll(document);

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) {
						return;
					}
					setupAll(node);
				});
			});
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
