/**
 * دکمه‌های +/- تعداد روی سبد خرید. مقدار input.qty رو تغییر می‌ده و بعد
 * دقیقاً همون مسیر رسمی ووکامرس رو صدا می‌زنه: کلیک روی دکمه‌ی مخفیِ
 * name="update_cart" (که assets/js/frontend/cart.js خودِ ووکامرس روش
 * گوش می‌ده و AJAX واقعی رو انجام می‌ده). صرفاً dispatch کردن رویداد
 * change کافی نیست — ووکامرس کلاسیک AJAX رو فقط با submit واقعی فرم
 * (کلیک دکمه‌ی update_cart، یا Enter داخل input.qty) اجرا می‌کنه.
 */
(function () {
	document.addEventListener("click", function (event) {
		var button = event.target.closest("[data-jluxe-qty-step]");
		if (!button) {
			return;
		}

		var wrapper = button.closest(".quantity");
		var input = wrapper && wrapper.querySelector("input.qty");
		if (!input) {
			return;
		}

		var step = parseFloat(input.step) || 1;
		var min = input.min !== "" ? parseFloat(input.min) : 0;
		var max = input.max !== "" ? parseFloat(input.max) : Infinity;
		var current = parseFloat(input.value) || 0;
		var direction = button.getAttribute("data-jluxe-qty-step") === "increase" ? 1 : -1;
		var next = Math.min(max, Math.max(min, current + direction * step));

		if (next === current) {
			return;
		}

		input.value = next;
		input.dispatchEvent(new Event("change", { bubbles: true }));

		var form = input.closest("form.woocommerce-cart-form");
		var updateCartButton = form && form.querySelector('[name="update_cart"]');
		if (updateCartButton) {
			updateCartButton.disabled = false;
			updateCartButton.click();
		}
	});
})();

/**
 * گالری تصویر صفحه‌ی محصول (woocommerce/single-product/product-image.php):
 * جابه‌جایی بین تصاویر با opacity + به‌روزرسانی شمارنده‌ی فعال. ناوبری هم با
 * دو دکمه‌ی قبلی/بعدی و هم با کلیکِ مستقیم روی هر تامبنیلِ ردیفِ زیرِ عکس
 * (data-jluxe-gallery-thumb) ممکنه — کلیکِ تامبنیل مستقیم ایندکسِ همون
 * تامبنیل رو ست می‌کنه (نه relative مثلِ prev/next).
 */
(function () {
	document.addEventListener("click", function (event) {
		var gallery = event.target.closest("[data-jluxe-gallery]");
		if (!gallery) {
			return;
		}

		var images = gallery.querySelectorAll("[data-jluxe-gallery-image]");
		if (images.length < 2) {
			return;
		}

		/*
		 * باگِ واقعیِ پیدا‌شده: قبلاً «تصویرِ فعلی» با چک‌کردنِ img.style.opacity
		 * (فقط استایلِ inline) تشخیص داده می‌شد؛ ولی رنگِ اولیه‌ی هر تصویر
		 * (opacity-100/opacity-0) با کلاسِ Tailwind تویِ خودِ PHP ست می‌شه،
		 * نه inline style — پس تا قبل از اولین کلیک، style.opacity همه‌ی
		 * عکس‌ها رشته‌ی خالیه، که "" !== "0" همیشه true می‌ده و حلقه با آخرین
		 * عکس (نه عکسِ واقعاً نمایان) تموم می‌شد. قبلاً چون کلیک روی تامبنیل
		 * ایندکس رو مستقیم از data-attribute می‌گرفت (نه از رویِ این تشخیص)
		 * دیده نمی‌شد؛ با حذفِ تامبنیل‌ها، اولین کلیکِ روی فلش همیشه به‌جای
		 * تصویرِ درست، تصویرِ آخر رو مبنا می‌گرفت. حالا ایندکسِ فعلی مستقیم
		 * رو خودِ گالری نگه‌داری می‌شه (data-jluxe-gallery-current)، نه از
		 * حدسِ رویِ استایل.
		 */
		var current = parseInt(gallery.getAttribute("data-jluxe-gallery-current"), 10);
		if (isNaN(current)) {
			current = 0;
		}

		var next = current;
		var prevButton = event.target.closest("[data-jluxe-gallery-prev]");
		var nextButton = event.target.closest("[data-jluxe-gallery-next]");
		var thumbButton = event.target.closest("[data-jluxe-gallery-thumb]");

		if (prevButton) {
			next = (current - 1 + images.length) % images.length;
		} else if (nextButton) {
			next = (current + 1) % images.length;
		} else if (thumbButton) {
			next = parseInt(thumbButton.getAttribute("data-jluxe-gallery-thumb"), 10);
			if (isNaN(next) || next === current) {
				return;
			}
		} else {
			return;
		}

		gallery.setAttribute("data-jluxe-gallery-current", String(next));

		images.forEach(function (img, i) {
			img.style.opacity = i === next ? "1" : "0";
		});

		var thumbs = gallery.querySelectorAll("[data-jluxe-gallery-thumb]");
		thumbs.forEach(function (thumb, i) {
			thumb.classList.toggle("border-primary", i === next);
			thumb.classList.toggle("border-border", i !== next);
		});

		var counter = gallery.querySelector("[data-jluxe-gallery-counter]");
		if (counter) {
			var faDigits = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
			var toFa = function (n) {
				return String(n)
					.split("")
					.map(function (d) {
						return faDigits[d] || d;
					})
					.join("");
			};
			counter.textContent = toFa(next + 1) + " / " + toFa(images.length);
		}
	});
})();

/**
 * علاقه‌مندی‌ها (localStorage، واقعاً پایدار بین بازدیدها — نه صرفاً بصری
 * برای یک لود صفحه) و اشتراک‌گذاری (Web Share API واقعی، با fallback به
 * کپی لینک در کلیپ‌بورد اگر مرورگر پشتیبانی نکنه).
 */
(function () {
	var STORAGE_KEY = "jluxe_wishlist";

	function getWishlist() {
		try {
			return JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
		} catch (e) {
			return [];
		}
	}

	function setButtonState(btn, active) {
		btn.setAttribute("aria-pressed", active ? "true" : "false");
		btn.classList.toggle("text-boom-sale", active);
		var svg = btn.querySelector("svg");
		if (svg) {
			svg.setAttribute("fill", active ? "currentColor" : "none");
		}
	}

	document.querySelectorAll("[data-jluxe-wishlist-toggle]").forEach(function (btn) {
		var id = btn.getAttribute("data-jluxe-wishlist-toggle");
		setButtonState(btn, getWishlist().indexOf(id) !== -1);
	});

	document.addEventListener("click", function (event) {
		var btn = event.target.closest("[data-jluxe-wishlist-toggle]");
		if (!btn) {
			return;
		}
		var id = btn.getAttribute("data-jluxe-wishlist-toggle");
		var list = getWishlist();
		var index = list.indexOf(id);
		if (index === -1) {
			list.push(id);
		} else {
			list.splice(index, 1);
		}
		localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
		setButtonState(btn, index === -1);
	});

	document.addEventListener("click", function (event) {
		var btn = event.target.closest("[data-jluxe-share]");
		if (!btn) {
			return;
		}
		var shareData = { title: document.title, url: window.location.href };
		if (navigator.share) {
			navigator.share(shareData).catch(function () {});
			return;
		}
		if (navigator.clipboard) {
			navigator.clipboard.writeText(shareData.url).then(function () {
				var original = btn.getAttribute("aria-label");
				btn.setAttribute("aria-label", "لینک کپی شد");
				setTimeout(function () {
					btn.setAttribute("aria-label", original);
				}, 2000);
			});
		}
	});
})();

/**
 * جعبه‌ی قیمتِ صفحه‌ی محصولِ متغیر (woocommerce/single-product/price.php):
 * چون قیمت/تخفیف/موجودیِ واقعی مالِ variation انتخاب‌شده‌ست، به رویدادهای
 * واقعیِ found_variation/reset_data که خودِ assets/js/frontend/add-to-cart-variation.js
 * ووکامرس روی .variations_form دیسپچ می‌کنه گوش می‌دیم — نه polling.
 * قیمتِ فعلی/خط‌خورده مستقیم از HTML واقعیِ ووکامرس (variation.price_html که
 * خودش با wc_price() و تنظیماتِ واقعیِ سایت — تومان/جداکننده/رقم فارسی —
 * ساخته شده) گرفته می‌شه؛ این‌جوری نیازی به بازسازیِ فرمتِ عدد در JS نیست.
 *
 * این تابع برای هر .variations_form که واقعاً وجود داره صدا زده می‌شه —
 * هم برای فرمِ ایستای صفحه‌ی محصول (رندرشده هنگام بارگذاری صفحه) هم برای
 * فرمِ مودالِ افزودنِ سریع (که با AJAX بعد از بارگذاریِ صفحه به DOM اضافه
 * می‌شه؛ قبلاً این بایندینگ فقط یک‌بار روی اولین .variations_form موجودِ
 * لحظه‌ی بارگذاریِ اسکریپت انجام می‌شد، پس فرمِ مودال که دیرتر تزریق می‌شه
 * اصلاً این listener رو نمی‌گرفت و قیمت بعد از انتخابِ سایز/رنگ در مودال
 * برای همیشه مخفی می‌موند — باگِ واقعیِ گزارش‌شده). محلِ صدا زدنش رو در
 * پایینِ همین فایل (برای فرمِ ایستا) و در بخشِ مودال (بعد از تزریقِ HTML
 * و wc_variation_form()) ببین.
 */
function jluxeBindPriceBox(form) {
	var $ = window.jQuery;
	if (!$ || !form) {
		return;
	}
	var $form = $(form);
	var $priceBox = $form.find("[data-jluxe-price-box]").first();
	if (!$form.length || !$priceBox.length || $form.data("jluxePriceBoxBound")) {
		return;
	}
	$form.data("jluxePriceBoxBound", true);

	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };
	function jluxeFaDigits(str) {
		return String(str).replace(/[0-9]/g, function (d) {
			return faDigitsMap[d];
		});
	}
	var stockIcon =
		'<svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>';

	var $discountRow = $priceBox.find("[data-jluxe-price-discount-row]");
	var $badge = $priceBox.find("[data-jluxe-price-badge]");
	var $strike = $priceBox.find("[data-jluxe-price-strike]");
	var $current = $priceBox.find("[data-jluxe-price-current]");
	var $saving = $priceBox.find("[data-jluxe-price-saving]");
	var $stock = $priceBox.find("[data-jluxe-price-stock]");
	var defaultCurrentHtml = $current.html();
	var defaultStockHtml = $stock.html();
	var priceBoxEl = $priceBox[0];

	/*
	 * وقتی قیمت/بج‌تخفیف/موجودی یهو ظاهر می‌شن، ارتفاعِ priceBoxEl یهو
	 * زیاد می‌شه و کل مودال (باگِ واقعیِ گزارش‌شده: «کادر دراز می‌شه») به‌جای
	 * جابه‌جاییِ نرم، با یک پرش دیده می‌شه. با تکنیکِ FLIP (ارتفاعِ قبل رو
	 * اندازه می‌گیریم، محتوا رو آپدیت می‌کنیم، ارتفاعِ بعد رو اندازه می‌گیریم،
	 * از قبل به بعد با transition انیمیت می‌کنیم، بعدش height رو auto
	 * برمی‌گردونیم که تغییراتِ بعدی/ریسپانسیو گیر نکنن) این پرش حذف می‌شه.
	 */
	function jluxeAnimateBoxHeight(mutate) {
		if (!priceBoxEl) {
			mutate();
			return;
		}
		// ووکامرس گاهی reset_data و found_variation رو پشت‌سرِ هم/سینکرون
		// (بدون فرصتِ paint بینشون) dispatch می‌کنه؛ اگه انیمیشنِ قبلی هنوز
		// در جریانه، callback معلقش رو لغو می‌کنیم تا فقط مقصدِ نهایی (آخرین
		// mutate) اعمال بشه، نه دو انیمیشنِ رقیب که با هم تداخل کنن.
		if (priceBoxEl._jluxeAnimCancel) {
			priceBoxEl._jluxeAnimCancel();
		}

		var startHeight = priceBoxEl.getBoundingClientRect().height;
		mutate();
		var endHeight = priceBoxEl.scrollHeight;
		if (startHeight === endHeight) {
			return;
		}

		priceBoxEl.style.height = startHeight + "px";
		priceBoxEl.style.overflow = "hidden";
		// eslint-disable-next-line no-unused-expressions
		priceBoxEl.offsetHeight;

		var cancelled = false;
		priceBoxEl._jluxeAnimCancel = function () {
			cancelled = true;
		};

		function grow() {
			if (cancelled) {
				return;
			}
			priceBoxEl.style.transition = "height 260ms ease";
			priceBoxEl.style.height = endHeight + "px";
		}
		function finish() {
			if (cancelled) {
				return;
			}
			cancelled = true;
			clearTimeout(growTimeoutId);
			clearTimeout(settleTimeoutId);
			priceBoxEl.removeEventListener("transitionend", onTransitionEnd);
			priceBoxEl.style.height = "";
			priceBoxEl.style.overflow = "";
			priceBoxEl.style.transition = "";
			priceBoxEl._jluxeAnimCancel = null;
		}
		function onTransitionEnd(event) {
			if (event.propertyName === "height") {
				finish();
			}
		}

		// هم rAF (مسیرِ نرمِ معمولی وقتی تب دیده می‌شه) هم یک fallback با
		// setTimeout (چون rAF توی تبِ پس‌زمینه/غیرِ‌قابل‌مشاهده اصلاً اجرا
		// نمی‌شه و انیمیشن برای همیشه با overflow:hidden گیر می‌کرد — تستِ
		// همین محیطِ خودکار دقیقاً همین حالت رو نشون داد، document.hidden
		// true بود و rAF هیچ‌وقت صدا زده نشد).
		requestAnimationFrame(grow);
		var growTimeoutId = setTimeout(grow, 50);
		var settleTimeoutId = setTimeout(finish, 450);
		priceBoxEl.addEventListener("transitionend", onTransitionEnd);
	}

	$form.on("found_variation", function (event, variation) {
		jluxeAnimateBoxHeight(function () {
			var $parsed = $("<div></div>").html(variation.price_html || "");
			var $del = $parsed.find("del").first();
			var $ins = $parsed.find("ins").first();

			if ($del.length && $ins.length) {
				var pct = 0;
				if (variation.display_regular_price > 0) {
					pct = Math.round(100 - (variation.display_price / variation.display_regular_price) * 100);
				}
				$badge.text(jluxeFaDigits(String(pct)));
				$strike.html($del.html());
				$current.html($ins.html());
				$discountRow.removeClass("hidden").addClass("flex");
			} else {
				$current.html(variation.price_html || "");
				$discountRow.addClass("hidden").removeClass("flex");
			}
			// طبقِ درخواستِ کاربر: قیمت تا وقتی سواچی واقعاً انتخاب/found_variation
			// نشده مخفیه (price.php: hidden پیش‌فرض روی data-jluxe-price-current) —
			// همین‌جا که یک variation واقعی پیدا شده، نمایش داده می‌شه.
			$current.removeClass("hidden");
			// خطِ سود سبز طبقِ درخواستِ صریحِ کاربر («روی محصولِ متغیر هم نشون
			// بده») حالا از jluxe_saving_html استفاده می‌کنه — یک رشته‌ی
			// آماده‌ی سرور (inc/woocommerce.php: jluxe_variation_saving_html)
			// که با wc_price() واقعیِ خودِ سایت ساخته شده، نه بازسازیِ عدد در JS.
			if ($del.length && $ins.length && variation.jluxe_saving_html) {
				$saving.html(variation.jluxe_saving_html).removeClass("hidden");
			} else {
				$saving.addClass("hidden").text("");
			}

			var stockText = $("<div></div>").html(variation.availability_html || "").text().trim();
			if (stockText) {
				$stock.html('<p class="mt-1.5 flex items-center gap-1 text-[11px] font-medium text-text-muted">' + stockIcon + jluxeFaDigits(stockText) + "</p>");
			} else {
				$stock.empty();
			}
		});
	});

	$form.on("reset_data", function () {
		jluxeAnimateBoxHeight(function () {
			$current.html(defaultCurrentHtml).addClass("hidden");
			$discountRow.addClass("hidden").removeClass("flex");
			$saving.addClass("hidden").text("");
			$stock.html(defaultStockHtml);
		});
	});
}
jluxeBindPriceBox(document.querySelector(".variations_form[data-product_variations]"));

/**
 * ردیفِ «تعداد»ی صفحه‌ی محصولِ متغیر (woocommerce/single-product/
 * add-to-cart/variation-add-to-cart-button.php: data-jluxe-qty-row) —
 * باگِ واقعیِ گزارش‌شده: برای تنوعی که «فروش تکی» (sold individually) یا
 * موجودیش دقیقاً ۱ عدده، خودِ اسکریپتِ هسته‌ی ووکامرس
 * (add-to-cart-variation.js: onFoundVariation) کلِ .quantity رو با
 * jQuery .hide() مخفی می‌کنه (فرضش: چیزی برای تغییر نیست) — یعنی مشتری
 * اصلاً نمی‌دید داره چند عدد می‌خره. این تابع روی همون رویدادِ
 * found_variation (بعد از هسته‌ی ووکامرس، دقیقاً همون ترتیبی که
 * jluxeBindPriceBox هم ازش استفاده می‌کنه) دوباره ردیف رو نمایان می‌کنه؛
 * اگه تنوع واقعاً ثابت روی ۱ عدده، دکمه‌های +/- رو غیرفعال می‌کنه (نه
 * پنهان) تا مشتری «۱ عدد» رو ببینه ولی نتونه زیادش کنه. اگه تنوع واقعاً
 * ناموجود/غیرقابل‌خرید باشه، کلِ ردیف با یک نشانِ «ناموجود» جایگزین می‌شه.
 */
function jluxeBindQtyAvailability(form) {
	var $ = window.jQuery;
	if (!$ || !form) {
		return;
	}
	var $form = $(form);
	var qtyRow = form.querySelector("[data-jluxe-qty-row]");
	if (!qtyRow || $form.data("jluxeQtyAvailabilityBound")) {
		return;
	}
	$form.data("jluxeQtyAvailabilityBound", true);

	var oosEl = form.querySelector("[data-jluxe-qty-oos]");
	var decBtn = qtyRow.querySelector('[data-jluxe-qty-step="decrease"]');
	var incBtn = qtyRow.querySelector('[data-jluxe-qty-step="increase"]');
	var qtyInput = qtyRow.querySelector("input.qty");
	var qtyInner = qtyRow.querySelector(".quantity");
	var faOverlay = qtyRow.querySelector("[data-jluxe-qty-fa]");

	/*
	 * باگِ واقعیِ گزارش‌شده (با تستِ زنده روی همین انگشتر پیدا شد): این‌جا
	 * از attribute واقعیِ hidden برای مخفی‌کردنِ qtyRow/oosEl استفاده
	 * می‌کردیم، ولی چون خودِ این عنصرها کلاسِ Tailwindِ flex هم دارن، قانونِ
	 * پیش‌فرضِ [hidden]{display:none} مرورگر با قانونِ .flex هم‌اختصاصیته و
	 * چون .flex در stylesheet دیرتر میاد (لایه‌ی utility بعد از base)، برنده
	 * می‌شه — یعنی با وجودِ hidden=true، عنصر همچنان به‌صورتِ flex دیده
	 * می‌شد (دقیقاً همون الگویی که .jluxe-checkout-step-actions [hidden]
	 * قبلاً برایِ همین مشکل !important گرفته بود). راه‌حل این‌جا: مستقیم
	 * style.display رو ست می‌کنیم، نه فقط attribute رو.
	 */
	function setRowVisible(el, visible) {
		if (!el) {
			return;
		}
		el.hidden = !visible;
		el.style.display = visible ? "" : "none";
	}
	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };

	function setFixedQty(value) {
		if (!qtyInput) {
			return;
		}
		qtyInput.value = value;
		qtyInput.dispatchEvent(new Event("change", { bubbles: true }));
		if (faOverlay) {
			faOverlay.textContent = String(value).replace(/[0-9]/g, function (d) {
				return faDigitsMap[d];
			});
		}
	}

	$form.on("found_variation", function (event, variation) {
		var purchasable = variation.is_purchasable && variation.is_in_stock && variation.variation_is_visible;
		if (!purchasable) {
			setRowVisible(qtyRow, false);
			setRowVisible(oosEl, true);
			return;
		}
		setRowVisible(qtyRow, true);
		setRowVisible(oosEl, false);
		// باگِ واقعیِ گزارش‌شده: برای تنوعِ «فروش تکی» خودِ ووکامرس همین‌جا
		// (add-to-cart-variation.js: onFoundVariation) .quantity رو با
		// jQuery .hide() (یعنی style.display=none) مخفی می‌کنه — چون بعد از
		// اجرای اون کد به این‌جا می‌رسیم، این‌جا صریحاً دوباره نمایانش
		// می‌کنیم؛ فقط دکمه‌های +/- (نه خودِ ردیف) غیرفعال می‌شن.
		if (qtyInner) {
			qtyInner.style.display = "";
		}

		var maxQty = parseFloat(variation.max_qty);
		var fixed = "yes" === variation.is_sold_individually || (!isNaN(maxQty) && maxQty <= 1);
		if (decBtn) {
			decBtn.disabled = fixed;
		}
		if (incBtn) {
			incBtn.disabled = fixed;
		}
		if (fixed) {
			setFixedQty(1);
		}
	});

	$form.on("reset_data", function () {
		setRowVisible(qtyRow, true);
		setRowVisible(oosEl, false);
		if (qtyInner) {
			qtyInner.style.display = "";
		}
		if (decBtn) {
			decBtn.disabled = false;
		}
		if (incBtn) {
			incBtn.disabled = false;
		}
	});
}
jluxeBindQtyAvailability(document.querySelector(".variations_form[data-product_variations]"));

/**
 * سواچ‌های pill محصول متغیر (woocommerce/single-product/add-to-cart/variable.php):
 * کلیک روی دکمه، مقدار select واقعی (مخفی، sr-only) رو ست کرده و change
 * واقعی dispatch می‌کنه تا wc-add-to-cart-variation.js خودِ ووکامرس
 * (تطبیق قیمت/موجودی/دکمه‌ی خرید) دقیقاً مثل حالت select عادی کار کنه.
 */
(function () {
	document.addEventListener("click", function (event) {
		var swatch = event.target.closest("[data-jluxe-variation-value]");
		if (!swatch) {
			return;
		}

		var group = swatch.closest("[data-jluxe-variation-group]");
		if (!group) {
			return;
		}

		var select = group.querySelector("select");
		if (!select) {
			return;
		}

		var value = swatch.getAttribute("data-jluxe-variation-value");
		select.value = value;
		select.dispatchEvent(new Event("change", { bubbles: true }));

		group.querySelectorAll("[data-jluxe-variation-value]").forEach(function (btn) {
			if (btn === swatch) {
				btn.setAttribute("data-active", "");
			} else {
				btn.removeAttribute("data-active");
			}
		});

		/*
		 * برای گروه‌های رنگ (variable.php: data-jluxe-variation-label فقط
		 * روی ویژگی‌های رنگ رندر می‌شه)، اسمِ رنگِ انتخاب‌شده هم توی عنوانِ
		 * بالای سواچ‌ها («انتخاب رنگ: قهوه‌ای») و هم توی ردیفِ متناظرش در
		 * «ویژگی‌های کلیدی» (که قبلاً همیشه لیستِ کاملِ همه‌ی رنگ‌های ممکن
		 * رو نشون می‌داد، نه فقط رنگِ واقعاً انتخاب‌شده) به‌روزرسانی می‌شه.
		 */
		var selectedLabel = swatch.getAttribute("title") || swatch.getAttribute("aria-label") || value;
		var labelSpan = group.querySelector("[data-jluxe-variation-selected]");
		if (labelSpan) {
			labelSpan.textContent = selectedLabel;
		}
		var groupAttr = group.getAttribute("data-jluxe-variation-group");
		var keyspecValue = document.querySelector('[data-jluxe-keyspec-attr="' + groupAttr + '"] [data-jluxe-keyspec-value]');
		if (keyspecValue) {
			keyspecValue.textContent = selectedLabel;
		}
	});

	// «پاک کردن گزینه‌ها» (reset_variations) که خودِ ووکامرس با JS تزریق می‌کنه —
	// باید data-active همه‌ی سواچ‌ها هم پاک بشه، چون فقط select واقعی رو ریست می‌کنه؛
	// عنوانِ «انتخاب رنگ» و ردیفِ «ویژگی‌های کلیدی» هم به حالتِ پیش‌فرض (لیستِ
	// کاملِ گزینه‌ها) برمی‌گردن.
	document.addEventListener("click", function (event) {
		if (!event.target.closest(".reset_variations")) {
			return;
		}
		document.querySelectorAll("[data-jluxe-variation-value]").forEach(function (btn) {
			btn.removeAttribute("data-active");
		});
		document.querySelectorAll("[data-jluxe-variation-selected]").forEach(function (span) {
			span.textContent = "";
		});
		document.querySelectorAll("[data-jluxe-keyspec-value]").forEach(function (span) {
			span.textContent = span.getAttribute("data-jluxe-keyspec-default") || span.textContent;
		});
	});

	/**
	 * انتخاب خودکار اولین ترکیبِ واقعاً موجود در بارگذاری صفحه‌ی محصولِ
	 * متغیر — وگرنه چون هیچ variation ای پیش‌فرض انتخاب‌شده نیست، قیمت
	 * "۰ تومان" و وضعیت اشتباهاً "ناموجود" نشون داده می‌شد (باگ گزارش‌شده).
	 * اگه هیچ ترکیبی موجود نباشه دست نمی‌زنیم — همون متن "ناموجود" واقعیِ
	 * ووکامرس (get_availability) می‌مونه.
	 */
	var jluxeVariationForm = document.querySelector(".variations_form[data-product_variations]");
	if (jluxeVariationForm) {
		var jluxeVariations = [];
		try {
			jluxeVariations = JSON.parse(jluxeVariationForm.getAttribute("data-product_variations") || "[]");
		} catch (e) {
			jluxeVariations = [];
		}
		// فقط ترکیبِ کاملاً مشخص (بدون مقدار خالی/"هرکدوم") و موجود، چون باید
		// بشه هر ویژگی رو روی یک سواچِ مشخص کلیک کرد.
		var jluxeTarget = jluxeVariations.filter(function (v) {
			return v && v.is_in_stock && v.attributes && Object.keys(v.attributes).every(function (k) {
				return v.attributes[k] !== "";
			});
		})[0];
		if (jluxeTarget) {
			Object.keys(jluxeTarget.attributes).forEach(function (attrKey) {
				var select = jluxeVariationForm.querySelector('select[name="' + attrKey + '"]');
				var group = select && select.closest("[data-jluxe-variation-group]");
				if (!group) {
					return;
				}
				var value = jluxeTarget.attributes[attrKey];
				var swatch = group.querySelector('[data-jluxe-variation-value="' + value.replace(/"/g, '\\"') + '"]');
				if (swatch) {
					swatch.click();
				}
			});
		}
	}

	/**
	 * پنل فیلتر صفحه‌ی فروشگاه (inc/woocommerce.php: jluxe_render_shop_toolbar).
	 * باز/بسته‌شدنِ پنل صرفاً UI محلیه؛ اعمال واقعیِ فیلترها با ناوبری به
	 * URL جدید انجام می‌شه: انتخاب دسته/برند به permalink واقعیِ همون ترم
	 * می‌ره (data-url روی <option>)، قیمت/موجودی به‌صورت querystring
	 * (min_price/max_price بومیِ ووکامرسه، filter_stock رو خودمون در
	 * inc/woocommerce.php با pre_get_posts اضافه کردیم).
	 */
	// کلاس‌های flex/justify-start عمداً همراه با toggle شدنِ hidden اضافه/حذف
	// می‌شن (نه استاتیک روی خودِ عنصر) — چون Tailwind این‌ها رو در لایه‌ی
	// utilities می‌سازه که همیشه روی [hidden] (لایه‌ی base) برنده می‌شه؛
	// اگه استاتیک می‌بودن، panel با وجود hidden=true همچنان display:flex
	// می‌موند و پاپ‌آپ عملاً نه باز می‌شد نه بسته.
	// جلوی flex/justify-start + reflow اجباری (panel.offsetWidth خوندنِ صرف)
	// لازمه تا مرورگر حالتِ اولیه (بسته/hidden) رو واقعاً رسم کنه قبل از
	// اضافه‌شدنِ jluxe-open — وگرنه دو تغییرِ کلاس تو یک فریم ادغام می‌شن و
	// ترنزیشنِ CSS اصلاً اجرا نمی‌شه (پاپ‌آپ یهویی ظاهر می‌شه، نه اسلاید).
	function jluxeOpenFilterPanel(panel) {
		panel.hidden = false;
		panel.classList.add("flex", "justify-start");
		void panel.offsetWidth;
		panel.classList.add("jluxe-open");
		document.body.style.overflow = "hidden";
	}
	// بستن هم متقارنه: اول jluxe-open برداشته می‌شه (اسلاید/فید به بیرون
	// شروع می‌شه) و hidden فقط بعدِ تمومِ ترنزیشنِ درارو اضافه می‌شه — با یک
	// safety timeout (همون الگویِ img-skeleton.js) برای مواقعی که
	// transitionend به هر دلیلی فایر نشه.
	function jluxeCloseFilterPanel(panel) {
		panel.classList.remove("jluxe-open");
		document.body.style.overflow = "";
		var drawer = panel.querySelector("[data-jluxe-filter-drawer]");
		var finished = false;
		function finish() {
			if (finished) {
				return;
			}
			finished = true;
			panel.hidden = true;
			panel.classList.remove("flex", "justify-start");
		}
		if (drawer) {
			drawer.addEventListener("transitionend", finish, { once: true });
			window.setTimeout(finish, 400);
		} else {
			finish();
		}
	}

	document.addEventListener("click", function (event) {
		var panel = document.querySelector("[data-jluxe-filter-panel]");
		if (!panel) {
			return;
		}
		if (event.target.closest("[data-jluxe-filter-toggle]")) {
			jluxeOpenFilterPanel(panel);
			return;
		}
		if (event.target.closest("[data-jluxe-filter-close]")) {
			jluxeCloseFilterPanel(panel);
		}
	});

	document.addEventListener("keydown", function (event) {
		if (event.key !== "Escape") {
			return;
		}
		var panel = document.querySelector("[data-jluxe-filter-panel]");
		if (panel && !panel.hidden) {
			jluxeCloseFilterPanel(panel);
		}
	});

	var jluxeFilterForm = document.querySelector("[data-jluxe-filter-form]");
	if (jluxeFilterForm) {
		jluxeFilterForm.addEventListener("submit", function (event) {
			event.preventDefault();

			var catSelect = jluxeFilterForm.querySelector('[name="filter_cat"]');
			var brandSelect = jluxeFilterForm.querySelector('[name="filter_brand"]');
			var minPrice = jluxeFilterForm.querySelector('[name="min_price"]');
			var maxPrice = jluxeFilterForm.querySelector('[name="max_price"]');
			var inStock = jluxeFilterForm.querySelector('[name="filter_stock"]');

			var baseUrl = null;
			[catSelect, brandSelect].forEach(function (select) {
				if (!baseUrl && select && select.value) {
					var opt = select.options[select.selectedIndex];
					if (opt && opt.dataset.url) {
						baseUrl = opt.dataset.url;
					}
				}
			});
			if (!baseUrl) {
				baseUrl = window.JLuxeThemeSettings && window.JLuxeThemeSettings.shopUrl
					? window.JLuxeThemeSettings.shopUrl
					: "/shop/";
			}

			// قیمتِ ذخیره‌شده در ووکامرس همیشه ریاله (inc/woocommerce.php:
			// jluxe_toman_price تقسیم‌بر-۱۰ برای نمایش)؛ فیلتر native ووکامرس
			// هم روی همون مقدار ریالی مقایسه می‌کنه، پس ورودیِ تومانیِ کاربر
			// باید قبل از رفتن به querystring در ۱۰ ضرب بشه.
			var params = new URLSearchParams();
			if (minPrice && minPrice.value) {
				params.set("min_price", String(Math.round(Number(minPrice.value) * 10)));
			}
			if (maxPrice && maxPrice.value) {
				params.set("max_price", String(Math.round(Number(maxPrice.value) * 10)));
			}
			if (inStock && inStock.checked) {
				params.set("filter_stock", "instock");
			}

			var query = params.toString();
			window.location.href = baseUrl + (query ? "?" + query : "");
		});
	}
})();

/**
 * لیستِ کرکره‌ایِ مرتب‌سازی (.jluxe-shop-sort select.orderby) — خودِ
 * <option>های مرورگر (پاپ‌آپِ بومیِ سیستم‌عامل) با CSS قابلِ استایل‌دهی
 * نیستن؛ برای اینکه واقعاً «زیبا» بشه (طبقِ درخواستِ کاربر)، یک
 * لیست‌باکسِ سفارشی روی همون سلکتِ واقعی enhance می‌شه. خودِ <select>
 * تویِ DOM و کاملاً فعال می‌مونه (فقط با display:none مخفی می‌شه، نه
 * remove) — یعنی اگه به هر دلیلی این اسکریپت اجرا نشه یا خطا بده، فرمِ
 * اصلیِ ووکامرس دست‌نخورده و قابلِ استفاده‌ست (progressive enhancement).
 * انتخابِ یک گزینه‌ی سفارشی، value ی سلکتِ واقعی رو ست می‌کنه و یک
 * رویدادِ change واقعی dispatch می‌کنه — خودِ اسکریپتِ هسته‌یِ ووکامرس
 * (assets/js/frontend/woocommerce.js: تغییرِ .woocommerce-ordering
 * select.orderby) از همون رویداد برای submit خودکارِ فرم استفاده می‌کنه،
 * پس نیازی به بازنویسیِ منطقِ ارسال نیست.
 */
(function () {
	document.querySelectorAll(".jluxe-shop-sort").forEach(function (wrap) {
		var select = wrap.querySelector("select.orderby");
		if (!select) {
			return;
		}

		select.classList.add("jluxe-sort-select-native");

		var trigger = document.createElement("button");
		trigger.type = "button";
		trigger.className = "jluxe-sort-trigger";
		trigger.setAttribute("aria-haspopup", "listbox");
		trigger.setAttribute("aria-expanded", "false");

		var label = document.createElement("span");
		label.className = "jluxe-sort-trigger-label";

		var chevron = wrap.querySelector("svg");
		trigger.appendChild(label);
		if (chevron) {
			trigger.appendChild(chevron);
		}

		var listbox = document.createElement("ul");
		listbox.className = "jluxe-sort-listbox";
		listbox.setAttribute("role", "listbox");
		listbox.hidden = true;

		function currentOption() {
			return select.options[select.selectedIndex];
		}

		function buildOptions() {
			listbox.innerHTML = "";
			Array.prototype.forEach.call(select.options, function (opt) {
				var li = document.createElement("li");
				li.setAttribute("role", "option");
				li.dataset.value = opt.value;
				var selected = opt.value === select.value;
				li.setAttribute("aria-selected", selected ? "true" : "false");
				li.className = "jluxe-sort-option" + (selected ? " jluxe-sort-option-active" : "");
				li.innerHTML =
					'<svg class="jluxe-sort-option-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>' +
					'<span>' + opt.textContent + '</span>';
				li.tabIndex = -1;
				listbox.appendChild(li);
			});
		}

		function syncLabel() {
			var opt = currentOption();
			label.textContent = opt ? opt.textContent : "";
		}

		// باز/بسته‌شدنِ لیست دو‌مرحله‌ایه (دقیقاً هم‌الگو با پنلِ فیلتر بالاتر
		// در همین فایل): چون display:none (از خودِ hidden attribute) و
		// کلاسِ ترانزیشن‌دارِ jluxe-sort-listbox-open نمی‌تونن تویِ یک فریم
		// با هم عوض بشن — وگرنه مرورگر ترانزیشن رو اصلاً اجرا نمی‌کنه و
		// لیست یهو ظاهر/محو می‌شه، نه نرم.
		function closeListbox() {
			listbox.classList.remove("jluxe-sort-listbox-open");
			trigger.setAttribute("aria-expanded", "false");
			trigger.classList.remove("jluxe-sort-trigger-open");
			var finished = false;
			function finish() {
				if (finished) {
					return;
				}
				finished = true;
				listbox.hidden = true;
			}
			listbox.addEventListener("transitionend", finish, { once: true });
			window.setTimeout(finish, 250);
		}

		function openListbox() {
			buildOptions();
			listbox.hidden = false;
			void listbox.offsetWidth;
			listbox.classList.add("jluxe-sort-listbox-open");
			trigger.setAttribute("aria-expanded", "true");
			trigger.classList.add("jluxe-sort-trigger-open");
			var active = listbox.querySelector(".jluxe-sort-option-active");
			if (active) {
				active.focus();
			}
		}

		trigger.addEventListener("click", function () {
			if (listbox.hidden) {
				openListbox();
			} else {
				closeListbox();
			}
		});

		listbox.addEventListener("click", function (event) {
			var li = event.target.closest(".jluxe-sort-option");
			if (!li) {
				return;
			}
			if (select.value !== li.dataset.value) {
				select.value = li.dataset.value;
				select.dispatchEvent(new Event("change", { bubbles: true }));
			}
			syncLabel();
			closeListbox();
			trigger.focus();
		});

		listbox.addEventListener("keydown", function (event) {
			var items = Array.prototype.slice.call(listbox.querySelectorAll(".jluxe-sort-option"));
			var currentIndex = items.indexOf(document.activeElement);
			if (event.key === "ArrowDown") {
				event.preventDefault();
				var next = items[Math.min(items.length - 1, currentIndex + 1)];
				if (next) {
					next.focus();
				}
			} else if (event.key === "ArrowUp") {
				event.preventDefault();
				var prev = items[Math.max(0, currentIndex - 1)];
				if (prev) {
					prev.focus();
				}
			} else if (event.key === "Enter" || event.key === " ") {
				event.preventDefault();
				document.activeElement.click();
			} else if (event.key === "Escape") {
				event.preventDefault();
				closeListbox();
				trigger.focus();
			} else if (event.key === "Tab") {
				closeListbox();
			}
		});

		listbox.addEventListener("focusout", function () {
			// اجازه می‌ده کلیک/فوکوسِ داخلِ همون لیست (بینِ گزینه‌ها) بدونِ
			// بسته‌شدن زودهنگام اتفاق بیفته — فقط وقتی فوکوس کاملاً از کلِ
			// wrap خارج شد ببندیم.
			window.setTimeout(function () {
				if (!wrap.contains(document.activeElement)) {
					closeListbox();
				}
			}, 0);
		});

		document.addEventListener("click", function (event) {
			if (!listbox.hidden && !wrap.contains(event.target)) {
				closeListbox();
			}
		});

		syncLabel();
		wrap.appendChild(trigger);
		wrap.appendChild(listbox);
	});
})();

/**
 * شهرستان (billing_city) وابسته به استان (billing_state) — چک‌اوت و
 * ویرایش آدرس در حساب کاربری هر دو از همین دو id واقعیِ ووکامرس استفاده
 * می‌کنن. billing_city سمت سرور (inc/woocommerce.php: jluxe_billing_fields)
 * یک select ساده‌ست (نه select2)، فقط با یک گزینه‌ی جای‌گزین رندر می‌شه؛
 * فهرستِ واقعیِ شهرستان‌ها بر اساس استانِ انتخابی همین‌جا پر می‌شه — باگِ
 * گزارش‌شده («استان انتخاب می‌شه ولی شهرستان لود نمی‌شه») همینه: قبلاً اصلاً
 * چنین منطقی وجود نداشت و billing_city یک متنِ آزاد بود.
 */
(function () {
	var cities = window.JLuxeThemeSettings && window.JLuxeThemeSettings.iranCities;
	var stateSelect = document.getElementById("billing_state");
	var citySelect = document.getElementById("billing_city");
	if (!cities || !stateSelect || !citySelect) {
		return;
	}

	function rebuildCityOptions(preserveCurrent) {
		var list = cities[stateSelect.value] || [];
		var currentCity = preserveCurrent ? citySelect.value : "";

		citySelect.innerHTML = "";
		var placeholder = document.createElement("option");
		placeholder.value = "";
		placeholder.textContent = list.length ? "انتخاب شهرستان" : "ابتدا استان را انتخاب کنید";
		citySelect.appendChild(placeholder);

		list.forEach(function (city) {
			var opt = document.createElement("option");
			opt.value = city;
			opt.textContent = city;
			citySelect.appendChild(opt);
		});

		if (currentCity) {
			if (list.indexOf(currentCity) === -1) {
				// شهرِ ذخیره‌شده‌ی قبلی (هنگام ویرایش آدرس) توی فهرستِ این استان
				// نبود — گزینه‌اش رو نگه می‌داریم تا مقدار واقعیِ کاربر گم نشه.
				var keep = document.createElement("option");
				keep.value = currentCity;
				keep.textContent = currentCity;
				citySelect.insertBefore(keep, citySelect.children[1] || null);
			}
			citySelect.value = currentCity;
		}
	}

	rebuildCityOptions(true);

	/*
	 * billing_state با select2 (ووکامرس) غنی‌سازی می‌شه — انتخاب واقعیِ کاربر از
	 * منوی select2 رویدادِ change رو از طریقِ سیستمِ رویدادِ jQuery شلیک می‌کنه،
	 * نه با یک DOM change ساده؛ addEventListener خامِ قبلی هرگز صداش رو نمی‌شنید
	 * (باگِ واقعیِ گزارش‌شده «استان انتخاب می‌شه ولی شهرستان به‌روز نمی‌شه» —
	 * با تستِ زنده در کروم پیدا شد: dispatchEvent خام کار می‌کرد ولی
	 * jQuery(...).trigger('change') — دقیقاً همون کاری که select2 می‌کنه — نه).
	 * برای همین این‌جا از jQuery برای bind کردن استفاده می‌شه، نه addEventListener خام.
	 */
	if (window.jQuery) {
		window.jQuery(stateSelect).on("change", function () {
			rebuildCityOptions(false);
			citySelect.dispatchEvent(new Event("change", { bubbles: true }));
		});
	} else {
		stateSelect.addEventListener("change", function () {
			rebuildCityOptions(false);
			citySelect.dispatchEvent(new Event("change", { bubbles: true }));
		});
	}
})();

/**
 * پیش‌نمایش عکس‌های دیگرِ محصول روی کارت (woocommerce/content-product.php):
 * هاور (دسکتاپ) یا لمس طولانی (موبایل) یک ردیف تامبنیل کوچیک پایین عکس
 * اصلی کارت نشون می‌ده؛ هاور/تپ روی هرکدوم عکس اصلیِ کارت رو موقتاً عوض
 * می‌کنه (بدون رفتن به صفحه محصول) و با خروج ماوس از کارت به حالت اول برمی‌گرده.
 */
(function () {
	function getCard(el) {
		return el && el.closest ? el.closest(".product") : null;
	}

	document.addEventListener("mouseover", function (event) {
		var thumb = event.target.closest && event.target.closest("[data-jluxe-card-thumb]");
		if (!thumb) {
			return;
		}
		var card = getCard(thumb);
		var img = card && card.querySelector("[data-jluxe-card-main-img]");
		if (!img) {
			return;
		}
		if (!img.dataset.jluxeOriginalSrc) {
			img.dataset.jluxeOriginalSrc = img.src;
		}
		img.src = thumb.getAttribute("data-full");
	});

	// جلوگیری از رفتن به صفحه محصول وقتی فقط قصد پیش‌نمایش عکس رو داشتیم.
	document.addEventListener("click", function (event) {
		if (event.target.closest && event.target.closest("[data-jluxe-card-thumb]")) {
			event.preventDefault();
			event.stopPropagation();
		}
	});

	// خروج ماوس از کل کارت (نه فقط جابه‌جایی بین بچه‌هاش) → برگشت عکس اصلی + بستن ردیف تامبنیل.
	document.addEventListener("mouseout", function (event) {
		var card = getCard(event.target);
		if (!card) {
			return;
		}
		if (getCard(event.relatedTarget) === card) {
			return;
		}
		var img = card.querySelector("[data-jluxe-card-main-img]");
		if (img && img.dataset.jluxeOriginalSrc) {
			img.src = img.dataset.jluxeOriginalSrc;
		}
		card.removeAttribute("data-thumbs-active");
	});

	// لمس طولانی (موبایل، بدون :hover) برای باز کردن ردیف تامبنیل.
	var jluxePressTimer = null;
	document.addEventListener(
		"touchstart",
		function (event) {
			var card = getCard(event.target);
			if (!card || !card.querySelector("[data-jluxe-card-thumbs]")) {
				return;
			}
			jluxePressTimer = setTimeout(function () {
				card.setAttribute("data-thumbs-active", "");
			}, 450);
		},
		{ passive: true }
	);
	["touchend", "touchmove", "touchcancel"].forEach(function (evt) {
		document.addEventListener(
			evt,
			function () {
				clearTimeout(jluxePressTimer);
			},
			{ passive: true }
		);
	});
})();

/**
 * توست/اسنک‌بار «به سبد اضافه شد» — جایگزینِ نوتیس استاندارد ووکامرس که
 * قبلاً باعث می‌شد کارت محصول (وقتی داخل گرید چاپ می‌شد) بلند بشه. به
 * رویداد استاندارد jQuery ووکامرس (added_to_cart، از خودِ
 * assets/js/frontend/add-to-cart.js هسته‌ی ووکامرس، روی هر AJAX
 * add-to-cart موفق) گوش می‌ده — پس چه از کارتِ گرید، چه از مودالِ
 * انتخاب تنوع (پایین همین فایل)، چه از فرمِ ساده‌ی صفحه‌ی تکیِ محصول،
 * همه‌جا یکسان کار می‌کنه. jluxe:cart-updated هم همین‌جا dispatch
 * می‌شه تا هدر (شمارنده‌ی سبد) و دراور کشویی (اگه باز باشه) رفرش بشن.
 */
(function () {
	if (typeof window.jQuery === "undefined") {
		return;
	}

	function productNameFromButton(button) {
		if (!button) {
			return null;
		}
		/*
		 * ترتیب عمداً مهمه: اول صفحه‌ی تکیِ محصول (marker اختصاصیِ
		 * data-jluxe-product-title در content-single-product.php) چک
		 * می‌شه؛ فقط اگه اون نبود (یعنی واقعاً از کارتِ گرید/مودالِ quick-add
		 * میایم) سراغِ عنوانِ کارت می‌ریم. قبلاً برعکس بود — روی صفحه‌ی تکیِ
		 * محصول، button.closest(".product") خودِ wrapperِ کلِ صفحه رو
		 * می‌گرفت (نه یک کارتِ گرید) و anchorِ اولِ منطبق با
		 * a[href][class*='text-foreground'] داخلش لینکِ بردکرامبِ «خانه»
		 * بود، نه عنوانِ محصول — باگِ واقعیِ گزارش‌شده («خانه به سبد خرید
		 * اضافه شد»).
		 */
		var singleTitle = document.querySelector("[data-jluxe-product-title]");
		if (singleTitle && button.closest("form.cart")) {
			return singleTitle.textContent.trim();
		}
		var card = button.closest(".product");
		var titleEl = card && card.querySelector(".woocommerce-loop-product__title, a[href][class*='text-foreground']");
		return titleEl && titleEl.textContent.trim() ? titleEl.textContent.trim() : null;
	}

	function showToast(message) {
		var existing = document.querySelector(".jluxe-toast");
		if (existing) {
			existing.remove();
		}
		var toast = document.createElement("div");
		toast.className = "jluxe-toast";
		toast.innerHTML =
			'<span class="jluxe-toast-icon" aria-hidden="true">' +
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>' +
			"</span>" +
			'<span class="jluxe-toast-msg"></span>' +
			'<button type="button" class="jluxe-toast-cta">مشاهده سبد</button>';
		toast.querySelector(".jluxe-toast-msg").textContent = message;
		document.body.appendChild(toast);
		requestAnimationFrame(function () {
			toast.classList.add("jluxe-toast-visible");
		});

		var timer = window.setTimeout(dismiss, 3500);
		function dismiss() {
			window.clearTimeout(timer);
			toast.classList.remove("jluxe-toast-visible");
			window.setTimeout(function () {
				toast.remove();
			}, 250);
		}
		toast.querySelector(".jluxe-toast-cta").addEventListener("click", function () {
			window.dispatchEvent(new CustomEvent("jluxe:open-cart"));
			dismiss();
		});
	}

	/**
	 * انیمیشنِ «پرواز» یک نقطه از روی دکمه‌ی افزودن به سمتِ آیکونِ سبدِ
	 * خرید — سبک و بدونِ کتابخانه (فقط یک <span> با CSS transition روی
	 * transform/opacity، هر دو GPU-composited، بدون rAF loop). دسکتاپ و
	 * موبایل آیکونِ سبدِ متفاوتی هدف می‌گیرن، چون طبقِ درخواستِ کاربر سبدِ
	 * موبایل توی نوارِ پایینه (data-jluxe-cart-icon-mobile در
	 * MobileNav.tsx) نه هدرِ بالا (data-jluxe-cart-icon-desktop در
	 * Header.tsx) — بریک‌پوینتِ ۷۶۸px عمداً هم‌راستا با md: خودِ Tailwind
	 * توی همین پروژه‌ست.
	 */
	function animateAddToCart(button) {
		if (!button) {
			return;
		}
		var isMobile = window.innerWidth < 768;
		// توی صفحه‌ی محصول (موبایل)، MobileNav با نوارِ چسبانِ قیمت/سبد
		// (content-single-product.php، الگوی Boom) عوض می‌شه، پس
		// [data-jluxe-cart-icon-mobile] اصلاً توی DOM نیست تا وقتی کاربر
		// دستی toggle نکرده — بدونِ این fallback، پرواز به سمتِ سبد روی
		// شایع‌ترین حالتِ واقعیِ موبایل (صفحه‌ی محصول) اصلاً دیده نمی‌شد.
		var target = isMobile
			? document.querySelector("[data-jluxe-cart-icon-mobile]") || document.querySelector("[data-jluxe-mobile-price-bar]")
			: document.querySelector("[data-jluxe-cart-icon-desktop]");
		if (!target) {
			return;
		}

		var startRect = button.getBoundingClientRect();
		var endRect = target.getBoundingClientRect();
		var startX = startRect.left + startRect.width / 2;
		var startY = startRect.top + startRect.height / 2;
		var endX = endRect.left + endRect.width / 2;
		var endY = endRect.top + endRect.height / 2;
		var deltaX = endX - startX;
		var deltaY = endY - startY;
		// نقطه‌ی میانیِ مسیرِ کمانی — کمی بالاترِ خطِ راست تا حرکت به‌جای
		// خطِ صاف، یک قوسِ طبیعی داشته باشه (طبقِ درخواستِ کاربر: انیمیشنِ
		// قبلی چندان قشنگ نبود).
		var midX = deltaX / 2;
		var midY = deltaY / 2 - Math.max(50, Math.abs(deltaY) * 0.35);

		var dot = document.createElement("span");
		dot.className = "jluxe-cart-fly-dot";
		dot.style.left = startX + "px";
		dot.style.top = startY + "px";
		dot.style.setProperty("--jluxe-fly-mid-x", midX + "px");
		dot.style.setProperty("--jluxe-fly-mid-y", midY + "px");
		dot.style.setProperty("--jluxe-fly-end-x", deltaX + "px");
		dot.style.setProperty("--jluxe-fly-end-y", deltaY + "px");
		dot.innerHTML =
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>';
		document.body.appendChild(dot);

		var cleaned = false;
		function cleanup() {
			if (cleaned) {
				return;
			}
			cleaned = true;
			if (dot.parentNode) {
				dot.remove();
			}
			target.classList.add("jluxe-cart-bump");
			window.setTimeout(function () {
				target.classList.remove("jluxe-cart-bump");
			}, 380);
		}

		dot.addEventListener("animationend", cleanup, { once: true });
		// fallback: اگه animationend به هر دلیلی (مثلاً تب پس‌زمینه) فایر نشه.
		window.setTimeout(cleanup, 900);
	}

	/**
	 * محوشدنِ لحظه‌ایِ خودِ دکمه — جایگزینِ توستِ متنی روی موبایل (طبقِ
	 * درخواستِ کاربر: توی موبایل پیغامِ «به سبد اضافه شد» حذف بشه، به‌جاش
	 * خودِ دکمه یک لحظه محو بشه و دایره از روش به سمتِ سبد بپره) — فقط
	 * opacity/transform، سبک و GPU-composited.
	 */
	function fadeButtonPulse(button) {
		if (!button) {
			return;
		}
		button.classList.add("jluxe-btn-added-pulse");
		window.setTimeout(function () {
			button.classList.remove("jluxe-btn-added-pulse");
		}, 420);
	}

	window.jQuery(document.body).on("added_to_cart", function (event, fragments, cartHash, $button, cartSnapshot) {
		var button = $button && $button.length ? $button[0] : null;
		// خودِ اسکریپتِ هسته‌ی ووکامرس (assets/js/frontend/add-to-cart.js) روی
		// همین رویداد یک لینکِ خامِ «مشاهده سبد خرید» (.added_to_cart.wc-forward)
		// درست کنارِ دکمه اضافه می‌کنه — چیزی که توستِ سفارشیِ پایین جایگزینش
		// نمی‌کنه چون یک مکانیزمِ کاملاً جداست (تزریقِ DOM، نه نوتیس/session).
		// داخلِ کارتِ گرید چون فضایی براش دیده نشده، همین لینکِ اضافه باعثِ
		// بلندشدنِ ناگهانیِ کارت می‌شد (باگِ واقعیِ گزارش‌شده). چون خودمون
		// فیدبکِ معادل (توست/پالس + انیمیشنِ پرواز) رو همیشه نشون می‌دیم،
		// این لینک همه‌جا حذف می‌شه.
		//
		// نکته‌ی مهم: این هندلر (روی همین رویدادِ added_to_cart) زودتر از
		// هندلرِ خودِ ووکامرس (که واقعاً لینک رو می‌سازه — با تستِ زنده تأیید
		// شد: jQuery._data(body,'events').added_to_cart لیستِ هندلرها رو به
		// همون ترتیبِ ثبت اجرا می‌کنه) صدا زده می‌شه، پس حذفِ مستقیم این‌جا
		// همیشه یک قدم دیر بود — لینک هنوز ساخته نشده. با setTimeout صفر
		// حذف به انتهای صفِ اجرای همین Tick می‌ره، بعدِ اینکه هندلرِ ووکامرس
		// (که خودش هم روی همین رویداده) لینک رو ساخته باشه.
		window.setTimeout(function () {
			document.querySelectorAll(".added_to_cart.wc-forward").forEach(function (el) {
				el.remove();
			});
		}, 0);
		var isMobile = window.innerWidth < 768;
		// دسکتاپ: همون توستِ متنیِ قبلی. موبایل: به‌جای توست، خودِ دکمه یک
		// لحظه محو/پالس می‌شه و دایره‌ی پروازی جای پیغام رو می‌گیره — طبقِ
		// درخواستِ صریحِ کاربر (فضای کمِ موبایل برای توستِ متنی مناسب نبود).
		if (isMobile) {
			fadeButtonPulse(button);
		} else {
			var name = productNameFromButton(button);
			showToast(name ? name + " به سبد خرید اضافه شد" : "محصول به سبد خرید اضافه شد");
		}
		animateAddToCart(button);
		/*
		 * باگِ واقعیِ گزارش‌شده («لگ زیاد موقعِ افزودن به سبد، دوباره‌کلیک
		 * لگِ بیشتر»): سرورِ این سایت روی هر دورِ رفت‌وبرگشتِ AJAX ~۱.۵
		 * ثانیه کند بود (باگِ هاست/زیرساخت، نه چیزی که این تم بتونه از
		 * سمتِ کلاینت رفع کنه — با اندازه‌گیریِ زنده تأیید شد، حتی یک
		 * درخواستِ کاملاً بی‌ربط مثلِ /wp-json/ هم همین‌قدر طول می‌کشید).
		 * ولی این‌جا یک چیزِ واقعی هم بود که می‌شد بهتر کرد: قبلاً بعدِ هر
		 * افزودنِ موفق، یک CustomEvent خالی (بدونِ داده) dispatch می‌شد که
		 * use-cart.ts مجبور می‌شد بلافاصله یک درخواستِ AJAX کاملاً جداگانه‌ی
		 * op=get بفرسته تا سبد رو رفرش کنه — یعنی هر «افزودن» موفق، دو
		 * دورِ کاملِ رفت‌وبرگشتِ کند رو پشتِ‌سرِهم می‌طلبید (add، بعد get).
		 * حالا وقتی endpoint خودمون (jluxe_ajax_cart_add) پاسخ می‌ده،
		 * پاسخش از قبل خودِ jluxe_cart_snapshot() کامله — همون snapshot رو
		 * مستقیم با رویداد پاس می‌دیم تا use-cart.ts بدونِ یک fetch اضافه
		 * state رو آپدیت کنه؛ نصفِ رفت‌وبرگشتِ قبلی حذف شد. (برایِ محصولِ
		 * ساده‌ی گرید که از AJAX خامِ خودِ ووکامرس میاد، cartSnapshot خالیه
		 * و use-cart.ts طبقِ قبل fallback به fetch می‌کنه.)
		 */
		window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: cartSnapshot || null }));
		// پاپ‌آپِ «محصولات پیشنهادی» فقط وقتی باز می‌شه که خودِ دکمه‌ی اصلیِ
		// افزودنِ همین صفحه‌ی محصول کلیک شده باشه (کلاسِ single_add_to_cart_button
		// فقط مالِ اونه، نه کارت‌های گرید — چه همین صفحه چه بخشِ «محصولات
		// مرتبط»ِ زیرش که خودشون هم می‌تونن added_to_cart رو صدا بزنن). با
		// کمی تاخیر بعدِ اینکه توست/انیمیشنِ پرواز دیده بشه.
		if (button && button.classList.contains("single_add_to_cart_button")) {
			window.setTimeout(function () {
				if (typeof window.jluxeOpenSuggestedProductsModal === "function") {
					window.jluxeOpenSuggestedProductsModal();
				}
			}, isMobile ? 550 : 750);
		}
	});
})();

/**
 * پاپ‌آپِ «محصولات پیشنهادی» — محتوا کاملاً سمتِ سرور رندر شده
 * (inc/woocommerce.php: jluxe_render_suggested_products_modal)، این‌جا
 * فقط باز/بسته‌کردنشه. دکمه‌های افزودنِ داخلش همون کلاس‌های استانداردِ
 * ajax_add_to_cart/data-jluxe-quick-variant رو دارن، پس بدونِ کدِ اضافه
 * توسطِ همون اسکریپت‌های موجود (خودِ ووکامرس/مودالِ انتخابِ تنوع) کار
 * می‌کنن.
 */
(function () {
	var modal = document.querySelector("[data-jluxe-suggested-modal]");
	if (!modal) {
		// محصولی که پیشنهادی براش پیدا نشد (jluxe_get_suggested_products_for_cart
		// خالی بود) اصلاً این عنصر رو تویِ صفحه نداره — window.jluxeOpenSuggestedProductsModal
		// هیچ‌وقت تعریف نمی‌شه، و فراخوانی‌اش تویِ IIFEِ بالا با typeof چک
		// می‌شه، پس امنه.
		return;
	}

	window.jluxeOpenSuggestedProductsModal = function () {
		modal.classList.remove("hidden");
		modal.setAttribute("aria-hidden", "false");
		document.body.style.overflow = "hidden";
	};

	function closeSuggestedProductsModal() {
		modal.classList.add("hidden");
		modal.setAttribute("aria-hidden", "true");
		document.body.style.overflow = "";
	}
	// روی window هم قرار می‌گیره چون مودالِ انتخابِ سریعِ تنوع (پایین‌ترِ
	// همین فایل) باید بتونه این مودال رو ببنده وقتی از داخلش (روی یک
	// محصولِ متغیرِ پیشنهادی) باز می‌شه — وگرنه دو بک‌دراپِ تیره‌ی
	// تمام‌صفحه (این‌جا bg-foreground/50 و اونجا rgb(0 0 0 / 0.5)) روی هم
	// می‌افتن و صفحه با هر کلیک تیره‌تر به‌نظر می‌رسه، بدونِ اینکه خودِ
	// مودالِ تنوع (که z-index پایین‌تری داره) اصلاً دیده بشه — باگِ واقعیِ
	// گزارش‌شده.
	window.jluxeCloseSuggestedProductsModal = closeSuggestedProductsModal;

	modal.querySelectorAll("[data-jluxe-suggested-close]").forEach(function (el) {
		el.addEventListener("click", closeSuggestedProductsModal);
	});

	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape" && !modal.classList.contains("hidden")) {
			closeSuggestedProductsModal();
		}
	});
})();

/**
 * افزودن به سبد در فرمِ صفحه‌ی تکیِ محصول (woocommerce/single-product/add-to-cart/{simple,variation-add-to-cart-button}.php)
 * پیش‌فرضِ ووکامرس submit واقعیِ فرم (رفرش کامل صفحه) بود — بعدش همون نوتیسِ
 * رنگیِ قدیمیِ خودِ ووکامرس (wc_print_notices، از هوکِ woocommerce_before_single_product)
 * بالای صفحه ظاهر می‌شد که با توستِ سفارشیِ بالای همین فایل ناهماهنگه (باگ
 * گزارش‌شده — گاهی حتی همزمان با پیامِ موفقیتِ تکراری). اینجا submit رو
 * می‌گیریم و AJAX می‌فرستیم.
 *
 * باگِ واقعیِ بعدی (کاربر دقیقاً همین رو گزارش کرد — با یک سایتِ دیگه‌ای که
 * همین تمِ زرین رو داره، ولی چون کدش عیناً همینه، خودِ jluxe.ir هم موقعِ
 * لانچ می‌گرفتش): این‌جا قبلاً مستقیم به endpoint خامِ ووکامرس
 * (wc-ajax=add_to_cart) می‌زد و برای محصولِ متغیر، product_id رو با
 * variation_id عوض می‌کرد (استدلال: «endpoint خودش تشخیص می‌ده»). آیتم
 * واقعاً درست به سبد اضافه می‌شد (session سمتِ سرور mutate می‌شد) ولی
 * WC_AJAX::add_to_cart بعدش status رو هنوز روی همون ID (که تنوعه، نه
 * والد) چک می‌کرد و {error:true} برمی‌گردوند — یعنی کاربر توستِ خطا
 * («لطفاً گزینه‌های محصول را انتخاب کنید») می‌دید ولی با رفتن به صفحه‌ی
 * دیگه می‌دید واقعاً به سبد اضافه شده. راه‌حل: به‌جایِ endpoint خامِ
 * ووکامرس، از endpoint خودمون (inc/cart-ux.php: jluxe_ajax_cart_add،
 * op=add) استفاده می‌کنیم که product_id (والد) و variation_id رو جدا جدا
 * می‌فرسته و دقیقاً امضای ۴تاییِ استانداردِ WC()->cart->add_to_cart() رو
 * صدا می‌زنه — بدونِ حدس‌زدن.
 */
(function () {
	var form = document.querySelector("form.cart");
	var cartCfg = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
	if (!form || !cartCfg || !cartCfg.ajaxUrl || !window.jQuery) {
		return;
	}
	/*
	 * باگِ واقعیِ دیگه‌ای که همینِ‌جا کشف شد (گزارشِ کاربر: «پاپ‌آپِ
	 * محصولاتِ پیشنهادی فقط برایِ محصولِ ساده میاد، برایِ متغیر نه»):
	 * قبلاً این‌جا form.querySelector('[name="add-to-cart"]') رو یک‌بار،
	 * همین اول (موقعِ لودِ صفحه) می‌خوند و اگه null بود (خیلی معمول برایِ
	 * محصولِ متغیر — دکمه‌ی افزودن اصلاً توی DOM نیست تا وقتی کاربر یک
	 * تنوعِ معتبر انتخاب کنه؛ فقط اگه ووکامرس مقدارِ پیش‌فرضی برایِ
	 * ویژگی‌ها تنظیم کرده باشه از اول توی DOMه) کلِ IIFE (بدونِ اتصالِ هیچ
	 * listenerی) return می‌کرد — یعنی برایِ اکثرِ محصولاتِ متغیر، این
	 * فایل هیچ‌وقت submit رو نمی‌گرفت، حتی بعدِ اینکه کاربر بعداً یک
	 * تنوع انتخاب می‌کرد؛ فرم با submit خامِ خودِ مرورگر (رفرشِ کامل
	 * صفحه) می‌رفت، پس نه AJAX ما نه توست نه پاپ‌آپِ پیشنهادی هیچ‌کدوم
	 * اجرا نمی‌شدن (هرچند خودِ افزودن به سبد از راهِ رفرشِ کامل درست
	 * کار می‌کرد — برای همین کاربر خطایی نمی‌دید، فقط پاپ‌آپ رو نمی‌دید).
	 * الان listener مستقیم رو خودِ form وصل می‌شه (که همیشه توی DOMه)،
	 * و دکمه/product_id هر بار توی خودِ submit (وقتی حتماً یک تنوعِ
	 * معتبر انتخاب شده و دکمه حتماً ساخته شده) دوباره خونده می‌شن.
	 */

	// پیام‌های خطای خودِ ووکامرس (مثلاً «...&mdash; ما مجموعا...») نویسه‌ی
	// HTML خام (نه یک خط‌تیره‌ی واقعی) دارن — چون این متن‌ها برای چاپِ مستقیمِ
	// HTML (wc_print_notices) نوشته شدن. با textContent decode نمی‌شن (باگِ
	// واقعیِ گزارش‌شده: کاربر عیناً «&mdash;» رو روی توست می‌دید)، پس این‌جا
	// یک بار از مرورگر خودش decode گرفته می‌شه.
	function decodeHtmlEntities(text) {
		var el = document.createElement("textarea");
		el.innerHTML = text;
		return el.value;
	}

	function showErrorToast(message) {
		var existing = document.querySelector(".jluxe-toast");
		if (existing) {
			existing.remove();
		}
		var toast = document.createElement("div");
		toast.className = "jluxe-toast jluxe-toast-error";
		toast.innerHTML =
			'<span class="jluxe-toast-icon" aria-hidden="true">' +
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>' +
			"</span>" +
			'<span class="jluxe-toast-msg"></span>';
		toast.querySelector(".jluxe-toast-msg").textContent = decodeHtmlEntities(message);
		document.body.appendChild(toast);
		requestAnimationFrame(function () {
			toast.classList.add("jluxe-toast-visible");
		});
		window.setTimeout(function () {
			toast.classList.remove("jluxe-toast-visible");
			window.setTimeout(function () {
				toast.remove();
			}, 250);
		}, 4500);
	}

	form.addEventListener("submit", function (event) {
		var button = form.querySelector('[name="add-to-cart"], .single_add_to_cart_button');
		if (button && button.disabled) {
			event.preventDefault();
			return;
		}
		event.preventDefault();
		if (button) {
			button.disabled = true;
			button.classList.add("opacity-60", "pointer-events-none");
		}

		function restoreButton() {
			if (button) {
				button.disabled = false;
				button.classList.remove("opacity-60", "pointer-events-none");
			}
		}

		// آیدیِ والدِ محصول: تا این‌جا (داخلِ submit) حتماً یک تنوعِ معتبر
		// انتخاب شده، پس button (که همین بالا از رویِ خودِ فرم دوباره
		// خونده شد) حتماً وجود داره و button.value همیشه آیدیِ واقعیِ
		// محصولِ والده (چه ساده چه متغیر — قالبِ ووکامرس این‌جوری
		// می‌سازتش). form.dataset.product_id فقط fallback برایِ حالتِ
		// نادرِ نبودِ دکمه‌ست (content-single-product.php: data-product_id
		// رویِ خودِ <form> — نامِ attribute زیرخط داره نه خط‌تیره، پس
		// dataset اسمش رو camelCase نمی‌کنه، دقیقاً product_id می‌مونه).
		// variation_id (اگه محصول متغیره) از فیلدِ جداگونه‌ی خودِ فرم میاد،
		// همون فیلدی که اسکریپتِ سواچِ ووکامرس (wc-add-to-cart-variation)
		// موقعِ انتخابِ یک تنوعِ معتبر مقدارش رو ست می‌کنه.
		var formData = new FormData(form);
		formData.set("action", "jluxe_cart");
		formData.set("nonce", cartCfg.nonce);
		formData.set("op", "add");
		formData.set("product_id", (button && button.value) || form.dataset.product_id || "");

		fetch(cartCfg.ajaxUrl, { method: "POST", body: formData, credentials: "same-origin" })
			.then(function (res) {
				return res.json();
			})
			.then(function (response) {
				restoreButton();
				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? response.data.message : "لطفاً گزینه‌های محصول را انتخاب کنید.";
					showErrorToast(message);
					return;
				}
				window.jQuery(document.body).trigger("added_to_cart", [null, null, window.jQuery(button), response.data]);
			})
			.catch(function () {
				restoreButton();
				showErrorToast("خطا در افزودن به سبد خرید. دوباره تلاش کنید.");
			});
	});
})();

/**
 * مودالِ انتخاب سریعِ تنوع — کلیک روی [data-jluxe-quick-variant] (دکمه‌ی
 * افزودن سریع/دکمه‌ی پایین کارت برای محصولِ متغیر، woocommerce/
 * content-product.php) به‌جای رفتن به صفحه‌ی محصول، سواچ‌ها + جعبه‌ی
 * خرید همون محصول رو (inc/cart-ux.php: jluxe_ajax_variation_picker) در
 * یک مودال باز می‌کنه. فرمِ داخلِ مودال با submit عادی navigate می‌کنه
 * (single_add_to_cart_button کلاسِ ajax_add_to_cart نداره)، پس submit
 * دستی گرفته و با fetch به همون endpoint واقعیِ AJAX افزودنِ ووکامرس
 * (wc-ajax=add_to_cart) فرستاده می‌شه.
 */
(function () {
	var modalRoot = null;
	var lastFocused = null;

	function closeModal() {
		if (!modalRoot) {
			return;
		}
		modalRoot.remove();
		modalRoot = null;
		document.body.style.overflow = "";
		if (lastFocused && lastFocused.focus) {
			lastFocused.focus();
		}
	}

	function openModal(productId, triggerEl) {
		var cart = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
		if (!cart || !cart.ajaxUrl) {
			return;
		}
		// جلوگیری از باگِ واقعیِ گزارش‌شده («صفحه هی مشکی و تیره‌تر می‌شه»):
		// اگه از قبل یک نمونه از همین مودال باز مونده (مثلاً کاربر دوبار
		// پشتِ‌هم روی [data-jluxe-quick-variant] زده)، اول بسته می‌شه — وگرنه
		// modalRoot قبلی هیچ‌وقت remove نمی‌شد و بک‌دراپِ ۵۰٪-سیاهش زیرِ
		// نمونه‌ی جدید می‌موند، با هر کلیک یک لایه‌ی تیره‌ی دیگه روی هم.
		if (modalRoot) {
			closeModal();
		}
		// اگه از داخلِ مودالِ «محصولات پیشنهادی» باز شده (یک محصولِ متغیرِ
		// پیشنهادی)، اون مودال هم باید بسته بشه — وگرنه دو بک‌دراپِ
		// تمام‌صفحه روی هم می‌افتن و چون z-index این مودال (۶۰) از اون
		// (۷۰) پایین‌تره، مودالِ تنوع اصلاً دیده نمی‌شد؛ کاربر فقط تیره‌تر
		// شدنِ صفحه رو می‌دید، نه خودِ فرمِ انتخابِ تنوع رو.
		if (typeof window.jluxeCloseSuggestedProductsModal === "function") {
			window.jluxeCloseSuggestedProductsModal();
		}

		lastFocused = triggerEl || document.activeElement;
		modalRoot = document.createElement("div");
		modalRoot.className = "jluxe-variant-modal-backdrop";
		modalRoot.innerHTML =
			'<div class="jluxe-variant-modal" role="dialog" aria-modal="true">' +
			'<button type="button" class="jluxe-variant-modal-close" aria-label="بستن">' +
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
			"</button>" +
			'<div class="jluxe-variant-modal-head"></div>' +
			'<div class="jluxe-variant-modal-body"><div class="jluxe-variant-modal-loading">در حال بارگذاری...</div></div>' +
			"</div>";
		document.body.appendChild(modalRoot);
		document.body.style.overflow = "hidden";

		modalRoot.addEventListener("click", function (event) {
			if (event.target === modalRoot || event.target.closest(".jluxe-variant-modal-close")) {
				closeModal();
			}
		});

		var body = new URLSearchParams({ action: "jluxe_variation_picker", nonce: cart.nonce, product_id: String(productId) });
		fetch(cart.ajaxUrl, { method: "POST", body: body, credentials: "same-origin" })
			.then(function (res) {
				return res.json();
			})
			.then(function (response) {
				if (!modalRoot) {
					return;
				}
				if (!response.success) {
					modalRoot.querySelector(".jluxe-variant-modal-body").innerHTML =
						'<p class="jluxe-variant-modal-error">مشکلی پیش آمد، دوباره تلاش کنید.</p>';
					return;
				}
				var data = response.data;
				modalRoot.querySelector(".jluxe-variant-modal-head").innerHTML =
					'<img src="' + data.image + '" alt="" class="jluxe-variant-modal-img" />' +
					'<a href="' + data.url + '" class="jluxe-variant-modal-title">' + data.name + "</a>";
				modalRoot.querySelector(".jluxe-variant-modal-body").innerHTML = data.html;

				var form = modalRoot.querySelector("form.variations_form");
				if (form && window.jQuery && window.jQuery.fn.wc_variation_form) {
					window.jQuery(form).wc_variation_form();
					jluxeBindPriceBox(form);
					jluxeBindQtyAvailability(form);
				}
			})
			.catch(function () {
				if (modalRoot) {
					modalRoot.querySelector(".jluxe-variant-modal-body").innerHTML =
						'<p class="jluxe-variant-modal-error">مشکلی پیش آمد، دوباره تلاش کنید.</p>';
				}
			});
	}

	document.addEventListener("click", function (event) {
		var trigger = event.target.closest("[data-jluxe-quick-variant]");
		if (!trigger) {
			return;
		}
		event.preventDefault();
		openModal(trigger.getAttribute("data-jluxe-quick-variant"), trigger);
	});

	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape" && modalRoot) {
			closeModal();
		}
	});

	document.addEventListener("submit", function (event) {
		var form = event.target.closest(".jluxe-variant-modal form.variations_form");
		if (!form) {
			return;
		}
		event.preventDefault();

		var addButton = form.querySelector(".single_add_to_cart_button");
		if (addButton && addButton.classList.contains("disabled")) {
			return;
		}

		var cart = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
		if (!cart || !cart.ajaxUrl) {
			return;
		}

		/*
		 * همون باگِ خامِ wc-ajax=add_to_cart که در فرمِ صفحه‌ی تکیِ محصول
		 * پیدا شد این‌جا هم بود — با یک شکلِ حتی بدتر: این فرم (خروجیِ
		 * jluxe_ajax_variation_picker در inc/cart-ux.php، کپیِ ساختارِ
		 * variable.php خودِ ووکامرس) اصلاً input جدایی به نامِ product_id
		 * نداره، فقط data-product_id روی خودِ <form> (که FormData ازش چیزی
		 * نمی‌خونه، چون attribute دیتاسته نه فیلدِ فرم). یعنی $_POST['product_id']
		 * سمتِ سرور اصلاً ست نمی‌شد و WC_AJAX::add_to_cart بی‌سروصدا wp_die(0)
		 * می‌کرد — پاسخِ خام "0" هم چون هیچ‌وقت response.error نداره، کدِ قبلی
		 * این رو موفقیت حساب می‌کرد و مودال رو می‌بست، در حالی که هیچ‌چیزی
		 * واقعاً به سبد اضافه نشده بود. حالا از همون endpoint خودمون
		 * (jluxe_ajax_cart_add) استفاده می‌کنیم که product_id/variation_id
		 * رو جدا جدا و صریح می‌گیره.
		 */
		var formData = new FormData(form);
		formData.set("action", "jluxe_cart");
		formData.set("nonce", cart.nonce);
		formData.set("op", "add");
		formData.set("product_id", form.dataset.product_id || "");

		if (addButton) {
			addButton.disabled = true;
			addButton.classList.add("jluxe-loading");
		}

		fetch(cart.ajaxUrl, { method: "POST", body: formData, credentials: "same-origin" })
			.then(function (res) {
				return res.json();
			})
			.then(function (response) {
				if (addButton) {
					addButton.disabled = false;
					addButton.classList.remove("jluxe-loading");
				}
				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? response.data.message : "لطفاً یک گزینه انتخاب کنید.";
					var errorEl = modalRoot && modalRoot.querySelector(".jluxe-variant-modal-error");
					if (!errorEl && modalRoot) {
						errorEl = document.createElement("p");
						errorEl.className = "jluxe-variant-modal-error";
						form.prepend(errorEl);
					}
					if (errorEl) {
						// همون باگِ «&mdash; خام به‌جایِ خط‌تیره‌ی واقعی» که توی
						// showErrorToast هم بود — پیام‌های خطایِ ووکامرس نویسه‌ی
						// HTML دارن و با textContent decode نمی‌شن.
						var decoder = document.createElement("textarea");
						decoder.innerHTML = message;
						errorEl.textContent = decoder.value;
					}
					return;
				}
				if (window.jQuery) {
					window.jQuery(document.body).trigger("added_to_cart", [null, null, window.jQuery(addButton), response.data]);
				}
				closeModal();
			})
			.catch(function () {
				if (addButton) {
					addButton.disabled = false;
					addButton.classList.remove("jluxe-loading");
				}
			});
	});
})();

/**
 * توضیحاتِ کوتاهِ محصول (content-single-product.php، زیرِ «ویژگی‌های کلیدی»):
 * ۳ خط با CSS کلمپ می‌شه؛ این‌جا فقط اگه واقعاً بیشتر از ۳ خط باشه (scrollHeight
 * بزرگ‌تر از clientHeight) دکمه‌ی «بیشتر» نشون داده می‌شه — طبق درخواستِ کاربر
 * («اگه توضیحات کوتاه زیاد بود... از یه جایی سه‌نقطه بزنه، با کلیکِ بیشتر
 * بقیه رو نشون بده»)، نه همیشه/بی‌ربط به طول واقعیِ متن.
 */
(function () {
	document.querySelectorAll("[data-jluxe-short-desc]").forEach(function (wrap) {
		var text = wrap.querySelector("[data-jluxe-short-desc-text]");
		var toggle = wrap.querySelector("[data-jluxe-short-desc-toggle]");
		if (!text || !toggle) {
			return;
		}
		if (text.scrollHeight - text.clientHeight <= 1) {
			return;
		}
		toggle.classList.remove("hidden");
		toggle.addEventListener("click", function () {
			var expanded = text.classList.toggle("jluxe-clamp-expanded");
			toggle.textContent = expanded ? "کمتر" : "بیشتر";
		});
	});
})();

/**
 * سینکِ لایه‌ی رقمِ فارسیِ روی input[type=number].qty (تعداد قابل‌تغییر —
 * quantity-input.php + globals.css). چون ممکنه input دیرتر (مودالِ
 * افزودنِ سریع، AJAX) به DOM اضافه بشه، به‌جای querySelectorAll یک‌باره
 * روی document با delegation گوش می‌دیم — هر inputای که بعداً هم اضافه
 * بشه پوشش داده می‌شه. دو رویداد لازمه: "input" برای تایپِ دستی/اسپینرِ
 * بومیِ مرورگر، "change" برای وقتی دکمه‌های +/- بالای همین فایل مقدار رو
 * با جاوااسکریپت ست می‌کنن و change رو dispatch می‌کنن (که خودش رویدادِ
 * input رو تولید نمی‌کنه).
 */
(function () {
	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };
	function toFaDigits(value) {
		return String(value).replace(/[0-9]/g, function (d) {
			return faDigitsMap[d];
		});
	}
	function syncOverlay(input) {
		var wrap = input.closest("[data-jluxe-qty-fa-wrap]");
		var overlay = wrap && wrap.querySelector("[data-jluxe-qty-fa]");
		if (overlay) {
			overlay.textContent = toFaDigits(input.value || "0");
		}
	}
	["input", "change"].forEach(function (eventName) {
		document.addEventListener(eventName, function (event) {
			if (event.target.matches && event.target.matches(".jluxe-qty-fa-input")) {
				syncOverlay(event.target);
			}
		});
	});
})();

/**
 * نوارِ چسبانِ موبایلِ قیمت+افزودن‌به‌سبدِ صفحه‌ی محصول (الگوی Boom) —
 * woocommerce/content-single-product.php این نوار رو رندر می‌کنه؛ این‌جا
 * فقط رفتارش وصل می‌شه: دکمه‌ی افزودن دکمه‌ی واقعیِ فرم رو کلیک می‌کنه،
 * دکمه‌ی toggle یک رویدادِ jluxe:toggle-mobile-bar می‌فرسته که هم خودِ
 * این نوار (کلاسِ hidden) هم MobileNav.tsx (React، state داخلیِ خودش)
 * روش گوش می‌دن. عمداً classList روی خودِ nav.tsx دستکاری نمی‌شه — چون
 * اون کامپوننت با هر تغییرِ سبدِ خرید re-render می‌شه و className
 * دستیِ بیرونی رو پاک می‌کرد (باگِ واقعیِ کشف‌شده حینِ ساختِ همین
 * قابلیت)؛ به‌جاش خودِ MobileNav با state رفتار می‌کنه.
 */
(function () {
	var priceBar = document.querySelector("[data-jluxe-mobile-price-bar]");
	var toggleBtn = document.querySelector("[data-jluxe-mobile-bar-toggle]");
	if (!priceBar || !toggleBtn) {
		return;
	}
	var addBtn = priceBar.querySelector("[data-jluxe-mobile-bar-add]");

	addBtn.addEventListener("click", function () {
		var realBtn = document.querySelector("form.cart .single_add_to_cart_button");
		if (realBtn) {
			realBtn.click();
		}
	});

	priceBar.classList.remove("hidden");

	toggleBtn.addEventListener("click", function () {
		priceBar.classList.toggle("hidden");
		window.dispatchEvent(new CustomEvent("jluxe:toggle-mobile-bar"));
	});
})();

/**
 * سینکِ قیمتِ نوارِ چسبانِ موبایل (بالا) با تنوعِ واقعاً انتخاب‌شده —
 * دقیقاً همون رویدادهای found_variation/reset_data که jluxeBindPriceBox
 * (بالاتر در همین فایل) هم گوش می‌ده، ولی جدا نگه داشته شده که به منطقِ
 * انیمیشنِ ارتفاعِ آن تابع (که مخصوصِ جعبه‌ی اصلیِ قیمته) وابسته نباشه.
 */
(function ($) {
	if (!$) {
		return;
	}
	var priceSlot = document.querySelector("[data-jluxe-mobile-bar-price]");
	if (!priceSlot) {
		return;
	}
	var $form = $(".variations_form[data-product_variations]").first();
	if (!$form.length) {
		return;
	}
	var defaultHtml = priceSlot.innerHTML;
	$form.on("found_variation", function (event, variation) {
		priceSlot.innerHTML = variation.price_html || defaultHtml;
	});
	$form.on("reset_data", function () {
		priceSlot.innerHTML = defaultHtml;
	});
})(window.jQuery);

/**
 * آکاردئونِ سوالاتِ متداولِ محصول (inc/product-faq.php →
 * jluxe_render_product_faq_section) — با کلیک/لمس روی هر سوال، پاسخش با
 * ترانزیشنِ grid-template-rows (0fr → 1fr، همون تکنیکِ رایجِ CSS-only
 * برای انیمیشنِ ارتفاعِ نامعلوم بدون اندازه‌گیریِ JS) باز/بسته می‌شه. هر
 * سوال مستقله (باز بودنِ یکی، بقیه رو نمی‌بنده) — چون این یک لیستِ
 * اطلاعاتیه نه یک ناوبریِ تک‌انتخابی.
 */
(function () {
	document.addEventListener("click", function (event) {
		var trigger = event.target.closest(".jluxe-faq-trigger");
		if (!trigger) {
			return;
		}
		var item = trigger.closest(".jluxe-faq-accordion-item");
		if (!item) {
			return;
		}
		var panel = item.querySelector(".jluxe-faq-panel");
		if (!panel) {
			return;
		}
		var isOpen = trigger.getAttribute("aria-expanded") === "true";
		trigger.setAttribute("aria-expanded", isOpen ? "false" : "true");
		panel.classList.toggle("grid-rows-[0fr]", isOpen);
		panel.classList.toggle("grid-rows-[1fr]", !isOpen);
		var chevron = trigger.querySelector(".jluxe-faq-chevron");
		if (chevron) {
			chevron.style.transform = isOpen ? "" : "rotate(180deg)";
		}
	});
})();

/**
 * گیت‌کردنِ واقعیِ مرحله‌ی «روش پرداخت» توی چک‌اوت (woocommerce/checkout/
 * form-checkout.php + inc/woocommerce.php: jluxe_render_checkout_stepper).
 *
 * طبقِ درخواستِ صریحِ کاربر، «اطلاعات ارسال» (فرم آدرس + انتخابِ روشِ
 * ارسال) و «نحوه پرداخت» (انتخابِ درگاه) باید دو مرحله‌ی کاملاً جدا حس
 * بشن — نه این‌که با زدنِ «ادامه»، پرداخت کنارِ همون فرمِ آدرس/روشِ ارسال
 * ظاهر بشه. پس این‌جا موقعِ رفتن به مرحله‌ی پرداخت، هم #customer_details
 * (فرمِ آدرس) و هم #jluxe-shipping-section (انتخابِ روشِ ارسال) مخفی
 * می‌شن — نه فقط #payment که قبلاً تنها چیزِ مخفی‌شده بود.
 *
 * همچنین طبقِ درخواستِ صریح، نه روشِ ارسال و نه روشِ پرداخت هیچ‌کدوم از
 * پیش انتخاب‌شده نیستن (woocommerce/cart/cart-shipping.php و
 * woocommerce/checkout/payment-method.php دیگه هیچ رادیویی رو pre-check
 * نمی‌کنن) — پس این‌جا هم قبلِ رفتنِ به مرحله‌ی بعد واقعاً چک می‌کنیم که
 * کاربر خودش یکی رو زده باشه، وگرنه پیغامِ خطای واقعی نشون می‌دیم.
 *
 * چون #jluxe-shipping-section و #payment هر دو داخلِ #order_review
 * هستن و با هر AJAX ووکامرس (تغییرِ آدرس/روشِ ارسال → updated_checkout)
 * کاملاً از نو رندر می‌شن، وضعیتِ نمایش/مخفی‌بودن باید رویِ همون رویداد
 * دوباره اعمال بشه. دکمه‌های «ادامه»/«بازگشت» عمداً بیرونِ #order_review
 * (بلافاصله بعدش) قرار می‌گیرن تا خودِ همون AJAX پاکشون نکنه.
 */
(function () {
	var form = document.querySelector("form.checkout");
	var stepper = document.querySelector(".jluxe-checkout-stepper");
	var orderReview = document.getElementById("order_review");
	if (!form || !stepper || !orderReview || !window.jQuery) {
		return;
	}

	var CHECK_SVG =
		'<svg class="size-4 sm:size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';
	var currentStep = "shipping";
	var customerDetails = document.getElementById("customer_details");
	// آدرسِ واقعیِ سبدِ خرید — از inc/woocommerce.php (wc_get_cart_url())
	// localize شده؛ قبلاً از یک لینکِ داخلِ #payment خونده می‌شد که دیگه
	// (طبقِ همین درخواست) اونجا رندر نمی‌شه.
	var wcCartUrl = (window.jluxeWcSettings && window.jluxeWcSettings.cartUrl) || "/cart/";

	function setStepVisual(id, state) {
		var stepEl = stepper.querySelector('[data-jluxe-step="' + id + '"]');
		if (!stepEl) {
			return;
		}
		var circle = stepEl.querySelector("[data-jluxe-step-circle]");
		var label = stepEl.querySelector("[data-jluxe-step-label]");
		if (!circle || !label) {
			return;
		}
		circle.classList.remove("border-success", "bg-success", "text-white", "border-primary", "text-primary", "border-border", "text-text-muted");
		label.classList.remove("font-medium", "text-foreground", "text-text-muted");
		if (state === "done") {
			circle.classList.add("border-success", "bg-success", "text-white");
			circle.innerHTML = CHECK_SVG;
			label.classList.add("text-text-muted");
		} else if (state === "active") {
			circle.classList.add("border-primary", "text-primary");
			circle.innerHTML = '<span class="text-caption font-bold">' + circle.getAttribute("data-jluxe-step-number") + "</span>";
			label.classList.add("font-medium", "text-foreground");
		} else {
			circle.classList.add("border-border", "text-text-muted");
			circle.innerHTML = '<span class="text-caption font-bold">' + circle.getAttribute("data-jluxe-step-number") + "</span>";
			label.classList.add("text-text-muted");
		}
	}

	// #jluxe-shipping-section/#payment داخل #order_review هستن و با هر
	// AJAX از نو ساخته می‌شن، پس هر بار از DOM واقعی (نه یک متغیرِ ذخیره‌شده)
	// خونده می‌شن.
	function applyStepVisibility() {
		var shippingSection = document.getElementById("jluxe-shipping-section");
		var payment = document.getElementById("payment");
		// wrapper عریضِ جدیدِ روشِ پرداخت (woocommerce/checkout/form-checkout.php)
		// — برخلافِ #payment، این با AJAX ووکامرس جایگزین نمی‌شه (فرگمنت فقط
		// روی خودِ .woocommerce-checkout-payment عمل می‌کنه، نه wrapperِ
		// بیرونیش)، پس همیشه قابلِ‌اعتمادتره؛ ولی هر دو رو با هم toggle
		// می‌کنیم تا هر جا #payment از نو ساخته شد هم بلافاصله وضعیتِ درست رو
		// داشته باشه.
		var paymentColumn = document.getElementById("jluxe-payment-column");
		// دکمه‌ی «پرداخت» + شرایط — حالا توی سایدبار، کنارِ «بازگشت»
		// (inc/woocommerce.php: jluxe_render_payment_submit).
		var paymentSubmit = document.getElementById("jluxe-payment-submit");
		var showShipping = "shipping" === currentStep;
		if (customerDetails) {
			customerDetails.style.display = showShipping ? "" : "none";
		}
		if (shippingSection) {
			shippingSection.style.display = showShipping ? "" : "none";
		}
		if (payment) {
			payment.style.display = showShipping ? "none" : "";
		}
		if (paymentColumn) {
			paymentColumn.hidden = showShipping;
		}
		if (paymentSubmit) {
			paymentSubmit.hidden = showShipping;
		}
	}

	function hasShippingMethodSelected() {
		var shippingSection = document.getElementById("jluxe-shipping-section");
		if (!shippingSection) {
			return true; // سبد نیازی به ارسال نداره (مثلاً کاملاً محصول دانلودی) — چیزی برای انتخاب نیست.
		}
		var radios = shippingSection.querySelectorAll("input.shipping_method");
		if (!radios.length) {
			return true;
		}
		var groups = {};
		for (var i = 0; i < radios.length; i++) {
			var name = radios[i].name;
			if (!(name in groups)) {
				groups[name] = false;
			}
			if (radios[i].checked) {
				groups[name] = true;
			}
		}
		for (var key in groups) {
			if (Object.prototype.hasOwnProperty.call(groups, key) && !groups[key]) {
				return false;
			}
		}
		return true;
	}

	function toggleError(selector, show) {
		var el = document.querySelector(selector);
		if (el) {
			el.hidden = !show;
		}
		return el;
	}

	/*
	 * actions طبقِ درخواستِ صریحِ کاربر («دکمه‌های ادامه و بازگشت باید پایین
	 * باشن نه بالا») بعدِ #order_review (پایینِ «خلاصه سفارش»، بعدِ اقلام/
	 * روشِ ارسال/جمعِ کل) می‌شینه — نه قبلش. نگرانیِ قدیمیِ «دکمه زیرِ
	 * لیستِ بلندِ درگاه‌ها گم می‌شه» دیگه صدق نمی‌کنه چون این دکمه‌ها فقط
	 * توی مرحله‌ی «اطلاعات ارسال» دیده می‌شن (نه مرحله‌ی پرداخت، جایی که
	 * لیستِ درگاه‌ها می‌تونه بلند باشه) — applyStepVisibility پایین‌تر
	 * #payment رو کاملاً جدا مدیریت می‌کنه؛ scrollIntoView هم روی هر
	 * جابه‌جاییِ مرحله همیشه این ناحیه رو در دیدِ کاربر می‌آره.
	 */
	var actions = document.createElement("div");
	actions.className = "jluxe-checkout-step-actions mt-4 flex flex-col gap-2.5";
	actions.innerHTML =
		/*
		 * برچسبِ «نهایی‌سازی سفارش» طبقِ مرجعِ تصویریِ جدیدِ کاربر برای دکمه‌ی
		 * ادامه‌ی مرحله‌ی «اطلاعات ارسال» (قبلاً «ادامه» بود).
		 */
		'<button type="button" data-jluxe-checkout-next class="flex h-12 w-full items-center justify-center rounded-xl bg-primary text-button font-bold text-primary-foreground transition-all hover:bg-primary-hover active:scale-[0.98]">نهایی‌سازی سفارش</button>' +
		'<a href="' + wcCartUrl + '" data-jluxe-checkout-to-cart class="flex h-11 w-full items-center justify-center rounded-xl border border-border text-button font-medium text-foreground transition-colors hover:bg-muted">بازگشت به سبد خرید</a>' +
		'<button type="button" data-jluxe-checkout-back hidden class="flex h-11 w-full items-center justify-center rounded-xl border border-border text-button font-medium text-foreground transition-colors hover:bg-muted">بازگشت</button>';
	orderReview.insertAdjacentElement("afterend", actions);
	var nextBtn = actions.querySelector("[data-jluxe-checkout-next]");
	var toCartBtn = actions.querySelector("[data-jluxe-checkout-to-cart]");
	var backBtn = actions.querySelector("[data-jluxe-checkout-back]");

	function goToPayment() {
		currentStep = "payment";
		setStepVisual("shipping", "done");
		setStepVisual("payment", "active");
		applyStepVisibility();
		nextBtn.hidden = true;
		toCartBtn.hidden = true;
		backBtn.hidden = false;
		actions.scrollIntoView({ behavior: "smooth", block: "start" });
	}

	function goToShipping() {
		currentStep = "shipping";
		setStepVisual("shipping", "active");
		setStepVisual("payment", "pending");
		applyStepVisibility();
		nextBtn.hidden = false;
		toCartBtn.hidden = false;
		backBtn.hidden = true;
		toggleError('[data-jluxe-payment-error]', false);
		actions.scrollIntoView({ behavior: "smooth", block: "start" });
	}

	/*
	 * باگِ واقعیِ گزارش‌شده (با تستِ زنده پیدا شد): فیلدهای الزامیِ فرمِ آدرس
	 * رو خالی می‌ذاشتیم و «نهایی‌سازی سفارش» بدونِ هیچ مانعی به مرحله‌ی
	 * پرداخت می‌رفت. علتِ واقعی: چکِ قبلی روی pseudo-class بومیِ مرورگر
	 * (:invalid) بود، ولی خودِ ووکامرس (woocommerce_form_field در
	 * wc-template-functions.php) هیچ‌وقت attribute واقعیِ HTML5
	 * required="required" رو روی input نمی‌ذاره — فقط کلاسِ CSS
	 * validate-required روی <p class="form-row"> و aria-required روی خودِ
	 * input. یعنی هیچ فیلدی هیچ‌وقت واقعاً :invalid نمی‌شد، پس اون چک همیشه
	 * بی‌اثر بود. اصلاح: همون قراردادِ خودِ ووکامرس رو پیاده می‌کنیم —
	 * دقیقاً همون چیزی که assets/frontend/checkout.js خودِ ووکامرس موقعِ
	 * submit واقعی روی .validate-required:visible انجام می‌ده (کلاسِ
	 * woocommerce-invalid/woocommerce-invalid-required-field که CSS
	 * قرمزِ globals.css روش سوار می‌شه) — نه یک اعتبارسنجیِ ابداعیِ جدید.
	 */
	function findFirstInvalidRequiredField() {
		if (!customerDetails || !window.jQuery) {
			return null;
		}
		var $ = window.jQuery;
		var $rows = $(customerDetails).find(".validate-required:visible");
		var $firstInvalid = null;
		$rows.each(function () {
			var $row = $(this);
			var $input = $row.find("input.input-text, select, textarea, input:checkbox");
			if (!$input.length) {
				return;
			}
			var isEmpty = $input.is(":checkbox") ? !$input.is(":checked") : $input.val() === "" || $input.val() === null;
			if (isEmpty) {
				$row.addClass("woocommerce-invalid woocommerce-invalid-required-field");
				if (!$firstInvalid) {
					$firstInvalid = $row;
				}
			} else {
				$row.removeClass("woocommerce-invalid woocommerce-invalid-required-field");
			}
		});
		return $firstInvalid ? $firstInvalid[0] : null;
	}

	nextBtn.addEventListener("click", function () {
		var invalid = findFirstInvalidRequiredField();
		if (invalid) {
			invalid.scrollIntoView({ behavior: "smooth", block: "center" });
			var focusable = invalid.querySelector("input, select, textarea");
			if (focusable) {
				focusable.focus();
			}
			return;
		}
		if (!hasShippingMethodSelected()) {
			var errorEl = toggleError('[data-jluxe-shipping-error]', true);
			if (errorEl) {
				errorEl.scrollIntoView({ behavior: "smooth", block: "center" });
			}
			return;
		}
		toggleError('[data-jluxe-shipping-error]', false);
		goToPayment();
	});
	backBtn.addEventListener("click", goToShipping);

	// وقتی کاربر خودش یک روشِ ارسال رو انتخاب کرد، پیغامِ خطا (اگه نشون
	// داده شده بود) خودکار مخفی می‌شه — کاربر مجبور نیست دوباره «ادامه»
	// بزنه تا خطا بره.
	document.addEventListener("change", function (event) {
		if (event.target && event.target.classList && event.target.classList.contains("shipping_method")) {
			toggleError('[data-jluxe-shipping-error]', false);
		}
		if (event.target && event.target.classList && event.target.classList.contains("payment_method")) {
			toggleError('[data-jluxe-payment-error]', false);
		}
	});

	/*
	 * روشِ پرداخت هم دیگه از پیش انتخاب نیست، پس قبلِ اجازه‌دادنِ submitِ
	 * واقعیِ فرم (که خودِ اسکریپتِ چک‌اوتِ ووکامرس روی همین رویداد گوش
	 * می‌ده و پردازشِ AJAX سفارش رو شروع می‌کنه) چک می‌کنیم یک درگاه واقعاً
	 * انتخاب شده. capture:true عمداً استفاده شده — این‌طوری این listener
	 * تضمینی، مستقل از ترتیبِ لودِ اسکریپت‌ها، زودتر از هر listenerِ
	 * دیگه‌ای (از جمله خودِ ووکامرس) روی همین submit اجرا می‌شه؛
	 * stopImmediatePropagation جلوی رسیدنِ رویداد به اون listenerها رو
	 * کاملاً می‌گیره، نه فقطِ preventDefault.
	 */
	document.addEventListener(
		"submit",
		function (event) {
			if (event.target !== form || "payment" !== currentStep) {
				return;
			}
			var payment = document.getElementById("payment");
			var methodRadios = payment ? payment.querySelectorAll("input.payment_method") : [];
			if (!methodRadios.length) {
				return; // سفارش نیاز به پرداخت نداره (مثلاً مبلغ صفر) — چیزی برای انتخاب نیست.
			}
			var chosen = payment.querySelectorAll("input.payment_method:checked").length > 0;
			if (!chosen) {
				event.preventDefault();
				event.stopImmediatePropagation();
				var errorEl = toggleError('[data-jluxe-payment-error]', true);
				if (errorEl) {
					errorEl.scrollIntoView({ behavior: "smooth", block: "center" });
				}
			}
		},
		true
	);

	/*
	 * باگِ واقعیِ گزارش‌شده (با تستِ زنده پیدا شد): با اینکه دیگه
	 * payment-method.php رادیوی درگاه رو pre-check نمی‌کنه، وقتی فقط یک
	 * درگاهِ پرداخت فعال باشه، خودِ اسکریپتِ هسته‌ی ووکامرس
	 * (assets/js/frontend/checkout.js) مستقل از تمپلیت، همون تنها رادیو
	 * رو با جاوااسکریپت خودش دوباره checked می‌کنه (رفتارِ استانداردِ
	 * خودِ ووکامرس برای «وقتی فقط یک انتخاب هست»). چون طبقِ درخواستِ صریحِ
	 * کاربر روشِ پرداخت هم نباید هیچ‌وقت از پیش انتخاب‌شده باشه، این‌جا
	 * دقیقاً همون رفتارِ خودکارِ ووکامرس رو (فقط همین یک مورد، نه هیچ
	 * منطقِ دیگه‌ای از چک‌اوت) خنثی می‌کنیم — با تأخیرِ کوتاه تا مطمئن
	 * بشیم بعد از اجرای اسکریپتِ خودِ ووکامرس اجرا می‌شه، نه قبلش.
	 */
	function clearAutoSelectedPaymentMethod() {
		var payment = document.getElementById("payment");
		if (!payment) {
			return;
		}
		var radios = payment.querySelectorAll("input.payment_method");
		if (radios.length !== 1 || !radios[0].checked) {
			return;
		}
		radios[0].checked = false;
		var box = payment.querySelector(".payment_box.payment_method_" + radios[0].value.replace(/[^a-zA-Z0-9_-]/g, ""));
		if (box) {
			box.style.display = "none";
		}
	}

	applyStepVisibility();
	window.jQuery(document.body).on("updated_checkout", function () {
		applyStepVisibility();
		setTimeout(clearAutoSelectedPaymentMethod, 50);
	});
	setTimeout(clearAutoSelectedPaymentMethod, 50);
})();

/**
 * اینپوتِ پرشِ صفحه در پاژینیشنِ آرشیوِ محصولات (woocommerce/loop/pagination.php).
 * data-jluxe-pagination-base همون رشته‌ی URLِ الگو با placeholderِ %#% رو
 * داره (خروجیِ خودِ get_pagenum_link ووکامرس/وردپرس، دست‌نخورده) — این‌جا
 * فقط شماره‌ی واردشده رو (چه فارسی چه لاتین) به همون جای خالی می‌ذاریم و
 * ناوبری می‌کنیم؛ هیچ منطقِ کوئری/صفحه‌بندیِ جدیدی ساخته نمی‌شه.
 */
(function () {
	function faToEnDigits(str) {
		var fa = "۰۱۲۳۴۵۶۷۸۹";
		return String(str).replace(/[۰-۹]/g, function (ch) {
			return String(fa.indexOf(ch));
		});
	}

	function goToPage(nav, input) {
		var total = parseInt(nav.getAttribute("data-jluxe-pagination-total"), 10) || 1;
		var base = nav.getAttribute("data-jluxe-pagination-base") || "";
		var n = parseInt(faToEnDigits(input.value), 10);
		if (!n || n < 1) {
			n = 1;
		}
		if (n > total) {
			n = total;
		}
		if (!base) {
			return;
		}
		window.location.href = base.replace("%#%", String(n));
	}

	document.addEventListener("keydown", function (event) {
		if (event.key !== "Enter") {
			return;
		}
		var input = event.target.closest("[data-jluxe-pagination-input]");
		if (!input) {
			return;
		}
		event.preventDefault();
		goToPage(input.closest(".jluxe-shop-pagination"), input);
	});

	document.addEventListener(
		"blur",
		function (event) {
			var input = event.target.closest && event.target.closest("[data-jluxe-pagination-input]");
			if (!input) {
				return;
			}
			var nav = input.closest(".jluxe-shop-pagination");
			var total = parseInt(nav.getAttribute("data-jluxe-pagination-total"), 10) || 1;
			var current = parseInt(faToEnDigits(input.value), 10);
			// اگه کاربر واقعاً عددِ متفاوتی وارد کرده، ناوبری کن؛ وگرنه (فقط
			// روی اینپوت کلیک کرده و بدونِ تغییر ازش خارج شده) کاری نکن.
			var originalMatch = input.defaultValue && parseInt(faToEnDigits(input.defaultValue), 10);
			if (current && current !== originalMatch && current >= 1 && current <= total) {
				goToPage(nav, input);
			}
		},
		true
	);
})();

/**
 * پاپ‌آپِ آلبومِ تصاویرِ محصول (woocommerce/single-product/product-image.php):
 * کلیک روی هر تامبنیلِ ردیفِ ثابتِ زیرِ عکس (data-jluxe-gallery-open) این
 * پاپ‌آپِ تمام‌صفحه رو دقیقاً از همون ایندکس باز می‌کنه. ناوبری با دکمه‌های
 * قبلی/بعدی، کلیدِ Escape، کلیک رویِ پس‌زمینه، و کشیدنِ لمسی (swipe) — همه
 * روی یک منبعِ حقیقتِ واحد (data-jluxe-gallery-modal-current رویِ خودِ
 * مودال) کار می‌کنن.
 */
(function () {
	/*
	 * باگِ واقعیِ گزارش‌شده (با اسکرین‌شاتِ کاربر تأیید شد): پاپ‌آپ چون
	 * همون‌جایی که تویِ HTML نوشته شده (داخلِ گریدِ ۳ستونیِ فرمِ محصول)
	 * می‌مونه، z-index:100اش فقط در برابرِ خواهروبرادرهایِ همون گرید
	 * مقایسه می‌شه، نه کلِ صفحه — عناصرِ خارج از اون گرید (نوارِ چسبانِ
	 * «معرفی/سوالات متداول/...» زیرِ گالری، حتی خودِ باکسِ قیمت) می‌تونن
	 * روش بیفتن. دقیقاً همون مشکلی که مینی‌کارت/دراورِ دسته‌بندی (کامپوننتِ
	 * React) با createPortal(..., document.body) حلش کردن؛ این‌جا چون
	 * PHP خام و JSِ ساده‌ست، همون کار رو دستی انجام می‌دیم: یک‌بار موقعِ
	 * لودِ صفحه پاپ‌آپ رو از جایِ اصلیش می‌کَنیم و مستقیم زیرِ <body>
	 * می‌ذاریم — دیگه هیچ ancestor‌ای نمی‌تونه stacking context بشکنه.
	 */
	function relocateModalsToBody() {
		document.querySelectorAll("[data-jluxe-gallery-modal]").forEach(function (modal) {
			if (modal.parentElement !== document.body) {
				document.body.appendChild(modal);
			}
		});
	}
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", relocateModalsToBody);
	} else {
		relocateModalsToBody();
	}

	function faDigits(n) {
		var map = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
		return String(n)
			.split("")
			.map(function (d) {
				return map[d] || d;
			})
			.join("");
	}

	function showImage(modal, index) {
		var images = modal.querySelectorAll("[data-jluxe-gallery-modal-image]");
		if (index < 0 || index >= images.length) {
			return;
		}
		modal.setAttribute("data-jluxe-gallery-modal-current", String(index));
		images.forEach(function (img, i) {
			var active = i === index;
			img.style.opacity = active ? "1" : "0";
			img.style.transform = active ? "scale(1)" : "scale(0.95)";
			img.classList.toggle("pointer-events-none", !active);
		});
		var counter = modal.querySelector("[data-jluxe-gallery-modal-counter]");
		if (counter) {
			counter.textContent = faDigits(index + 1) + " / " + faDigits(images.length);
		}
	}

	/*
	 * انیمیشنِ نرمِ باز/بسته‌شدن: صرفِ برداشتنِ کلاسِ hidden کافی نیست —
	 * چون transition روی opacity تعریف شده، مرورگر باید بینِ «حالتِ ۰
	 * (هنوز مخفی)» و «حالتِ ۱ (نمایان)» یک فریمِ واقعیِ paint‌شده ببینه، وگرنه
	 * مستقیم به حالتِ نهایی می‌پره (دقیقاً همون باگی که قبلاً برایِ
	 * بازوبسته‌شدنِ مینی‌کارت هم پیش اومده بود) — برای همین از double-rAF
	 * استفاده می‌شه، نه یک setTimeout ساده.
	 */
	function openModal(modal, index) {
		modal.classList.remove("hidden");
		modal.classList.add("flex");
		document.body.style.overflow = "hidden";
		showImage(modal, index);
		/*
		 * باگِ واقعیِ کشف‌شده حینِ تست: تکیه‌کردنِ صرف روی double-rAF (بدونِ
		 * fallback) باعث می‌شد پاپ‌آپ ساختاری باز بشه (hidden برداشته بشه) ولی
		 * opacity-0 هیچ‌وقت برداشته نشه — یعنی کاربر چیزی نمی‌دید، انگار اصلاً
		 * باز نشده — دقیقاً همون کلاسِ باگی که قبلاً تویِ مینی‌کارت (MiniCart.tsx)
		 * هم پیش اومده بود و اونجا با یک setTimeout پشتیبان حل شده بود. همون
		 * الگو این‌جا هم تکرار شده: هرکدوم از rAF/setTimeout زودتر برسه، برنده‌ست.
		 */
		var done = false;
		function reveal() {
			if (done) {
				return;
			}
			done = true;
			modal.classList.remove("opacity-0");
		}
		requestAnimationFrame(function () {
			requestAnimationFrame(reveal);
		});
		window.setTimeout(reveal, 60);
	}

	function closeModal(modal) {
		modal.classList.add("opacity-0");
		document.body.style.overflow = "";
		window.setTimeout(function () {
			modal.classList.add("hidden");
			modal.classList.remove("flex");
		}, 300);
	}

	function currentIndex(modal) {
		return parseInt(modal.getAttribute("data-jluxe-gallery-modal-current"), 10) || 0;
	}

	document.addEventListener("click", function (event) {
		// هم تامبنیل‌های ثابتِ زیرِ عکس (data-jluxe-gallery-open) هم دکمه‌ی
		// «مشاهده گالری» رویِ خودِ تصویرِ اصلی (data-jluxe-gallery-open-current)
		// — دومی همون ایندکسِ فعلاً نمایش‌داده‌شده رو از data-jluxe-gallery-current
		// خودِ گالری (که listener جداگانه‌ی prev/next نگه‌داری‌اش می‌کنه) می‌خونه.
		// نکته: خودِ پاپ‌آپ دیگه داخلِ [data-jluxe-gallery] نیست (relocateModalsToBody
		// اون رو مستقیم زیرِ body برده)، پس دیگه نمی‌شه با gallery.querySelector
		// پیداش کرد — چون همیشه فقط یک گالری/پاپ‌آپ رویِ صفحه‌ی محصول هست،
		// جستجویِ سراسری کافی و امنه.
		var opener = event.target.closest("[data-jluxe-gallery-open]");
		var openCurrent = event.target.closest("[data-jluxe-gallery-open-current]");
		if (opener || openCurrent) {
			var gallery = (opener || openCurrent).closest("[data-jluxe-gallery]");
			var modal = document.querySelector("[data-jluxe-gallery-modal]");
			if (modal && gallery) {
				var startIndex = opener
					? parseInt(opener.getAttribute("data-jluxe-gallery-open"), 10) || 0
					: parseInt(gallery.getAttribute("data-jluxe-gallery-current"), 10) || 0;
				openModal(modal, startIndex);
			}
			return;
		}

		var modalEl = event.target.closest("[data-jluxe-gallery-modal]");
		if (!modalEl) {
			return;
		}

		if (event.target.closest("[data-jluxe-gallery-modal-close]") || event.target === modalEl) {
			closeModal(modalEl);
			return;
		}

		var images = modalEl.querySelectorAll("[data-jluxe-gallery-modal-image]");
		if (event.target.closest("[data-jluxe-gallery-modal-prev]")) {
			showImage(modalEl, (currentIndex(modalEl) - 1 + images.length) % images.length);
		} else if (event.target.closest("[data-jluxe-gallery-modal-next]")) {
			showImage(modalEl, (currentIndex(modalEl) + 1) % images.length);
		}
	});

	document.addEventListener("keydown", function (event) {
		if ("Escape" !== event.key) {
			return;
		}
		var openModalEl = document.querySelector("[data-jluxe-gallery-modal]:not(.hidden)");
		if (openModalEl) {
			closeModal(openModalEl);
		}
	});

	// Swipe (لمسی) روی خودِ ترکِ تصویر — آستانه‌ی ۴۰px برایِ جلوگیری از
	// قاطی‌شدن با یک تپ/کلیکِ ساده.
	var touchStartX = null;
	document.addEventListener(
		"touchstart",
		function (event) {
			var track = event.target.closest("[data-jluxe-gallery-modal-track]");
			if (!track || event.touches.length !== 1) {
				return;
			}
			touchStartX = event.touches[0].clientX;
		},
		{ passive: true }
	);
	document.addEventListener(
		"touchend",
		function (event) {
			if (touchStartX === null) {
				return;
			}
			var track = event.target.closest("[data-jluxe-gallery-modal-track]");
			if (!track) {
				touchStartX = null;
				return;
			}
			var modalEl = track.closest("[data-jluxe-gallery-modal]");
			var deltaX = (event.changedTouches[0] ? event.changedTouches[0].clientX : touchStartX) - touchStartX;
			touchStartX = null;
			if (!modalEl || Math.abs(deltaX) < 40) {
				return;
			}
			var images = modalEl.querySelectorAll("[data-jluxe-gallery-modal-image]");
			// dir=rtl: کشیدن به راست (deltaX>0) یعنی «قبلی» بصری، به چپ یعنی «بعدی».
			if (deltaX > 0) {
				showImage(modalEl, (currentIndex(modalEl) - 1 + images.length) % images.length);
			} else {
				showImage(modalEl, (currentIndex(modalEl) + 1) % images.length);
			}
		},
		{ passive: true }
	);
})();
