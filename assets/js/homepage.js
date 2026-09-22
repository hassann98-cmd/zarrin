/**
 * رفتار سمت کاربر برای بخش‌های تازه‌ی صفحه‌ی اصلی (استوری، اسلایدر بنر،
 * پنل‌های پیشنهادی). فقط روی front-page لود می‌شه (ببین
 * jluxe_enqueue_homepage_assets در inc/theme-settings-homepage.php).
 */
(function () {
	"use strict";

	/* ---------------------------------------------------------------
	 * استوری‌ها — کلیک روی آواتار، ویوئر تمام‌صفحه (عکس یا ویدیو) رو باز می‌کنه.
	 * ------------------------------------------------------------- */
	function initStories() {
		var bars = document.querySelectorAll("[data-jluxe-stories]");
		if (!bars.length) return;

		bars.forEach(function (bar) {
			var viewer = bar.querySelector("[data-jluxe-story-viewer]");
			var media = viewer ? viewer.querySelector("[data-jluxe-story-media]") : null;
			var titleEl = viewer ? viewer.querySelector("[data-jluxe-story-title]") : null;
			var closeBtn = viewer ? viewer.querySelector("[data-jluxe-story-close]") : null;

			function close() {
				if (!viewer) return;
				viewer.setAttribute("hidden", "");
				viewer.classList.remove("grid", "place-items-center");
				media.innerHTML = "";
				document.body.style.overflow = "";
			}

			function open(item) {
				if (!viewer || !media) return;
				media.innerHTML = "";
				if (item.type === "video") {
					var video = document.createElement("video");
					video.src = item.src;
					video.controls = true;
					video.autoplay = true;
					video.playsInline = true;
					video.className = "max-h-[80vh] max-w-full rounded-xl";
					media.appendChild(video);
				} else {
					var img = document.createElement("img");
					img.src = item.src;
					img.alt = item.title || "";
					img.className = "max-h-[80vh] max-w-full rounded-xl object-contain";
					media.appendChild(img);
				}
				if (titleEl) titleEl.textContent = item.title || "";
				viewer.removeAttribute("hidden");
				viewer.classList.add("grid", "place-items-center");
				document.body.style.overflow = "hidden";
			}

			bar.querySelectorAll("[data-jluxe-story-avatar]").forEach(function (btn) {
				btn.addEventListener("click", function () {
					open({
						src: btn.getAttribute("data-src"),
						type: btn.getAttribute("data-media-type"),
						title: btn.getAttribute("data-title"),
					});
				});
			});

			if (closeBtn) closeBtn.addEventListener("click", close);
			if (viewer) {
				viewer.addEventListener("click", function (e) {
					if (e.target === viewer) close();
				});
			}
			document.addEventListener("keydown", function (e) {
				if (e.key === "Escape") close();
			});
		});
	}

	/* ---------------------------------------------------------------
	 * اسلایدر بنر + اسلایدر هیرو — چرخش خودکار + دکمه قبلی/بعدی + نقطه‌ها.
	 * هر دو از یک ساختار markup مشترک (data-jluxe-bs-*) استفاده می‌کنن؛
	 * فقط سلکتور ریشه فرق داره. مدت چرخش خودکار از data-autoplay-ms خودِ
	 * هر ریشه خونده می‌شه (پیش‌فرض ۵۰۰۰ اگه ست نشده بود).
	 * ------------------------------------------------------------- */
	function initSliders(selector) {
		document.querySelectorAll(selector).forEach(function (root) {
			var slides = Array.prototype.slice.call(root.querySelectorAll("[data-jluxe-bs-slide]"));
			if (slides.length < 2) return;

			var autoplayMs = parseInt(root.getAttribute("data-autoplay-ms"), 10) || 5000;
			var dots = Array.prototype.slice.call(root.querySelectorAll("[data-jluxe-bs-dot]"));
			var prevBtn = root.querySelector("[data-jluxe-bs-prev]");
			var nextBtn = root.querySelector("[data-jluxe-bs-next]");
			var index = 0;
			var timer = null;
			var paused = false;
			var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

			// اسلایدرِ هیرو (inc/theme-settings-homepage.php: jluxe_render_homepage_hero)
			// طبقِ درخواستِ صریحِ کاربر («بنرها با یک حالتِ نرمِ اسلاید بخورن»)
			// به یک trackِ flex واقعی (data-jluxe-bs-track) با transform:translateX
			// تغییر کرد؛ ولی همین تابع برای بنرِ اسلایدرِ دیگه‌ای هم استفاده
			// می‌شه که هنوز ساختارِ قدیمیِ absolute+opacity رو داره — پس این‌جا
			// اول چک می‌شه کدوم ساختاره، تا تغییرِ هیرو چیزِ دیگه‌ای رو نشکنه.
			var track = root.querySelector("[data-jluxe-bs-track]");

			function show(i) {
				index = (i + slides.length) % slides.length;
				if (track) {
					// track خودش dir="ltr" داره (نوشته‌شده تو PHP)، پس همیشه
					// index بیشتر = برو چپ، بدونِ توجه به جهتِ کلیِ صفحه.
					track.style.transform = "translateX(" + -index * 100 + "%)";
				} else {
					slides.forEach(function (s, si) {
						s.style.opacity = si === index ? "1" : "0";
						s.style.pointerEvents = si === index ? "auto" : "none";
					});
				}
				// وضعیت فعال را هم روی DOM و هم روی transform خود تصویر صریحاً
				// ریست می‌کنیم. این مهم است چون با جابه‌جایی سریع بین اسلایدها،
				// transform اسلاید قبلی نباید باقی بماند؛ و وقتی zoom در تنظیمات
				// خاموش است هیچ اسلایدی نباید scale بگیرد.
				var zoomEnabled = root.hasAttribute("data-jluxe-hero-zoom") && root.getAttribute("data-jluxe-hero-zoom") === "1";
				slides.forEach(function (s, si) {
					var active = si === index;
					s.setAttribute("data-active", active ? "true" : "false");
					s.querySelectorAll("[data-jluxe-bs-kenburns]").forEach(function (img) {
						img.setAttribute("data-jluxe-hero-zoom-item", zoomEnabled ? "1" : "0");
						img.style.transform = zoomEnabled && active ? "scale(1.045)" : "scale(1)";
					});
				});
				dots.forEach(function (d, di) {
					d.setAttribute("data-active", di === index ? "true" : "false");
					// نوارِ پیشرفتِ داخلِ هر نشانگر (اگه وجود داشته باشه — فقط
					// اسلایدرِ هیرو این ساختار رو داره، بنرِ اسلایدرِ قدیمی نه):
					// اسلایدهای گذشته پر، اسلایدِ فعال با همون مدتِ واقعیِ
					// autoplay پر می‌شه، اسلایدهای بعدی خالی.
					var fill = d.querySelector("[data-jluxe-bs-fill]");
					if (!fill) return;
					fill.style.transition = "none";
					fill.style.width = di < index ? "100%" : "0%";
					if (di === index) {
						// force reflow تا transition زیر واقعاً از نو شروع بشه،
						// نه اینکه مرورگر تغییرِ width رو با transition قبلی قاطی کنه.
						void fill.offsetWidth; // eslint-disable-line no-unused-expressions
						fill.style.transition = "width " + autoplayMs + "ms linear";
						fill.style.width = "100%";
					}
				});
			}

			function restart() {
				if (timer) clearInterval(timer);
				if (reducedMotion) {
					timer = null;
					return;
				}
				timer = setInterval(function () {
					if (!paused && !document.hidden) show(index + 1);
				}, autoplayMs);
			}

			if (prevBtn) prevBtn.addEventListener("click", function () { show(index - 1); restart(); });
			if (nextBtn) nextBtn.addEventListener("click", function () { show(index + 1); restart(); });
			dots.forEach(function (d, di) {
				d.addEventListener("click", function () { show(di); restart(); });
			});
			root.addEventListener("mouseenter", function () { paused = true; });
			root.addEventListener("mouseleave", function () { paused = false; });
			root.addEventListener("touchstart", function () { paused = true; }, { passive: true });
			root.addEventListener("touchend", function () { paused = false; });

			show(0);
			restart();
		});
	}

	/* ---------------------------------------------------------------
	 * دکمه‌ی قبلی/بعدی برای کاروسل‌های native-scroll (گرید محصولات با
	 * چیدمان «کاروسل»، نوار دسته‌بندی). باگ واقعی که پیدا شد، دو لایه بود:
	 * ۱) بعد از مخفی‌کردن اسکرول‌بار پیش‌فرض (globals.css → .jluxe-scroll-x)،
	 *    با ماوس معمولی (بدون تاچ‌پد/لمس) هیچ راهی برای اسکرول نمونده بود.
	 * ۲) اسکرول با مقدار دلخواه (درصدی از عرض کانتینر) روی یک کاروسلِ
	 *    scroll-snap-type:proximity گاهی توی «منطقه‌ی مرده»ی بین دو نقطه‌ی
	 *    snap فرود می‌اومد و مرورگر نامتناسب/غیرقابل‌پیش‌بینی به عقب برمی‌گردوندش
	 *    (تکرار می‌شد ولی همیشه نه — دقیقاً حس «گاهی تکون نمی‌خوره»ی گزارش‌شده).
	 *    فیکس: به‌جای درصد دلخواه، دقیقاً به اندازه‌ی «یک کارت + gap واقعی»
	 *    اسکرول می‌کنیم تا همیشه دقیقاً روی نقطه‌ی snap بعدی فرود بیاد.
	 * جهت RTL-aware: scrollLeft از ۰ شروع می‌شه و برای دیدنِ آیتم‌های بعدی
	 * منفی‌تر می‌شه.
	 * ------------------------------------------------------------- */
	function initScrollArrows() {
		function stepWidth(scroller) {
			var first = scroller.children[0];
			if (!first) return scroller.clientWidth * 0.8;
			var gap = parseFloat(getComputedStyle(scroller).columnGap || getComputedStyle(scroller).gap) || 0;
			return first.getBoundingClientRect().width + gap;
		}
		function bind(selector, direction) {
			document.querySelectorAll(selector).forEach(function (btn) {
				var wrapper = btn.closest(".relative");
				var scroller = wrapper ? wrapper.querySelector("[data-jluxe-scroller]") : null;
				if (!scroller) return;
				btn.addEventListener("click", function () {
					var amount = stepWidth(scroller) * direction;
					// «smooth» این‌جا عمداً استفاده نشد: در تست زنده رفتار ناپایدار
					// داشت (گاهی روی موقعیت ۰ باقی می‌موند، انگار انیمیشن نصفه قطع
					// می‌شد) — «instant» صددرصد قابل‌اعتماد بود. اسکرول دستی با
					// تاچ‌پد/لمس همچنان به‌خاطر scroll-smooth (CSS) نرمه؛ فقط کلیکِ
					// دکمه instant شد.
					scroller.scrollBy({ left: amount, behavior: "instant" });
				});
			});
		}
		// دکمه‌ی «قبلی» همیشه سمت چپ کاروسل قرار داره (end-1 در RTL) و «بعدی»
		// سمت راست (start-1) — طبق درخواست کاربر، جهتِ اسکرول باید دقیقاً با
		// همون سمتِ بصریِ دکمه یکی باشه: کلیک روی دکمه‌ی چپ لیست رو به چپ ببره
		// (scrollLeft منفی‌تر)، کلیک روی دکمه‌ی راست لیست رو به راست ببره.
		bind("[data-jluxe-scroll-prev]", -1);
		bind("[data-jluxe-scroll-next]", 1);
	}

	/* ---------------------------------------------------------------
	 * نمایشِ هوشمندِ همون فلش‌های بالا — طبقِ درخواستِ صریحِ کاربر، هر دو
	 * دکمه با opacity-0/pointer-events-none شروع می‌شن (کلاسِ پایه توی
	 * jluxe_scroll_arrows، inc/theme-settings-homepage.php) و اینجا فقط
	 * سمتی که واقعاً چیزی برایِ رفتن داره ظاهر می‌شه: اولِ کار فقط دکمه‌ی
	 * چپ («قبلی» — طبقِ قراردادِ بالا، منفی‌ترکنندهٔ scrollLeft)، و وقتی
	 * کاربر (با کشیدن یا کلیکِ خودِ دکمه) به انتها رسید، اون محو و دکمه‌ی
	 * راست («بعدی») ظاهر می‌شه.
	 * ------------------------------------------------------------- */
	function initScrollArrowVisibility() {
		document.querySelectorAll("[data-jluxe-scroller]").forEach(function (scroller) {
			var wrapper = scroller.closest(".relative");
			if (!wrapper) return;
			var prevBtn = wrapper.querySelector("[data-jluxe-scroll-prev]");
			var nextBtn = wrapper.querySelector("[data-jluxe-scroll-next]");
			if (!prevBtn && !nextBtn) return;

			function setVisible(btn, visible) {
				if (!btn) return;
				btn.classList.toggle("opacity-0", !visible);
				btn.classList.toggle("pointer-events-none", !visible);
			}

			function update() {
				var max = scroller.scrollWidth - scroller.clientWidth;
				if (max < 20) {
					setVisible(prevBtn, false);
					setVisible(nextBtn, false);
					return;
				}
				var scrolled = Math.abs(scroller.scrollLeft);
				setVisible(prevBtn, scrolled < max - 4);
				setVisible(nextBtn, scrolled > 4);
			}

			scroller.addEventListener("scroll", update, { passive: true });
			window.addEventListener("resize", update);
			update();
		});
	}

	/* ---------------------------------------------------------------
	 * درگ‌کردنِ کاروسل‌های [data-jluxe-scroller] با ماوس — طبق درخواستِ
	 * کاربر، قبلاً فقط با فلش‌ها یا لمس/تراک‌پد می‌شد چپ/راستش کرد، با
	 * ماوسِ معمولیِ دسکتاپ (که رویداد wheel افقی نداره) راهی نبود.
	 * mousemove/mouseup روی document (نه خودِ اسکرولر) بایده که اگه ماوس
	 * حینِ درگ سریع از روی اسکرولر رد بشه/بیرون بره، درگ قطع نشه.
	 * wasDragged: بعدِ یک درگِ واقعی (جابه‌جاییِ بیشتر از چند پیکسل)، رویدادِ
	 * click ای که مرورگر خودکار بعدِ mouseup می‌فرسته کنسل می‌شه — وگرنه هر
	 * درگِ ساده روی یک کارت باعثِ بازشدنِ لینکِ محصولِ زیرِ ماوس می‌شد.
	 * ------------------------------------------------------------- */
	function initDragScroll() {
		var drag = null;
		var wasDragged = false;

		document.querySelectorAll("[data-jluxe-scroller]").forEach(function (scroller) {
			scroller.addEventListener("mousedown", function (event) {
				if (event.button !== 0) {
					return;
				}
				drag = { scroller: scroller, startX: event.clientX, startScrollLeft: scroller.scrollLeft, moved: false };
				scroller.classList.add("jluxe-dragging");
			});
			scroller.addEventListener(
				"click",
				function (event) {
					if (wasDragged) {
						event.preventDefault();
						event.stopPropagation();
					}
				},
				true
			);
		});

		document.addEventListener("mousemove", function (event) {
			if (!drag) {
				return;
			}
			var dx = event.clientX - drag.startX;
			if (Math.abs(dx) > 4) {
				drag.moved = true;
			}
			// طبقِ تستِ زنده‌ی کاربر: درگِ ماوس به راست باید لیست رو به راست ببره
			// (نه با همون علامتِ دکمه‌های قبلی/بعدی که فرضِ اولیه‌ی اشتباه بود) —
			// یعنی جهتِ محتوا باید دقیقاً مخالفِ dx حرکت کنه، مثلِ درگِ استانداردِ
			// «گرفتنِ محتوا و کشیدنش» در بیشترِ سایت‌ها.
			drag.scroller.scrollLeft = drag.startScrollLeft - dx;
		});

		document.addEventListener("mouseup", function () {
			if (!drag) {
				return;
			}
			drag.scroller.classList.remove("jluxe-dragging");
			wasDragged = drag.moved;
			drag = null;
			setTimeout(function () {
				wasDragged = false;
			}, 0);
		});
	}

	/* ---------------------------------------------------------------
	 * کاروسلِ «محصولات فروش ویژه» — تا آخرین محصولِ واقعی (نه محتوای
	 * کپی‌شده) با سرعتِ ملایم جلو می‌ره، بعد سریع (با easing، نه اسکرولِ
	 * بومیِ مرورگر که مدتش قابل‌کنترل نیست) به اول برمی‌گرده. فقط وقتی
	 * سکشن واقعاً توی دیدِ کاربره (IntersectionObserver) فعاله — نه از
	 * همون بارگذاریِ صفحه.
	 *
	 * جهت: طبقِ همون قراردادِ initScrollArrows بالا (scrollLeft از ۰ برای
	 * دیدنِ آیتم‌های بعدی منفی‌تر می‌شه) — نسخه‌ی قبلی برعکس این بود
	 * (scrollLeft += ...) که باگِ واقعیِ گزارش‌شده‌ی «جهتش اشتباهه» رو
	 * ساخته بود.
	 *
	 * فلش‌های قبلی/بعدی (data-jluxe-scroll-prev/next) بیرونِ خودِ scroller
	 * (فرزندِ مستقیمِ wrapper) هستن، پس هاور/لمسِ scroller پوششِ کلیکِ
	 * رویِ فلش‌ها رو نمی‌داد — هر کلیکِ فلش بلافاصله با فریمِ بعدیِ
	 * اسکرولِ خودکار override می‌شد (باگِ واقعیِ گزارش‌شده‌ی «فلش‌ها کار
	 * نمی‌کنن»)؛ الان کلیکِ فلش هم چند ثانیه موقتاً متوقفش می‌کنه.
	 * ------------------------------------------------------------- */
	function initAutoScroll() {
		if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
		var FORWARD_SPEED = 1.1; // پیکسل در هر فریم — کمی سریع‌تر از حالتِ قبلی طبقِ درخواست.
		var REWIND_MS = 650; // مدتِ برگشتِ سریع به اول.
		var ARROW_PAUSE_MS = 3000;

		document.querySelectorAll("[data-jluxe-autoscroll]").forEach(function (scroller) {
			var wrapper = scroller.closest(".relative") || scroller.parentElement;
			var paused = false;
			var inView = false;
			var rewinding = false;
			var resumeTimer = null;

			function maxNegativeScroll() {
				return -(scroller.scrollWidth - scroller.clientWidth);
			}

			function easedScrollTo(target, duration, done) {
				var start = scroller.scrollLeft;
				var change = target - start;
				var startTime = null;
				function step(ts) {
					if (!startTime) {
						startTime = ts;
					}
					var t = Math.min((ts - startTime) / duration, 1);
					var eased = 1 - Math.pow(1 - t, 3); // ease-out cubic
					scroller.scrollLeft = start + change * eased;
					if (t < 1) {
						requestAnimationFrame(step);
					} else if (done) {
						done();
					}
				}
				requestAnimationFrame(step);
			}

			function tick() {
				if (!paused && inView && !rewinding && !scroller.classList.contains("jluxe-dragging")) {
					var max = maxNegativeScroll();
					if (max < -1) {
						if (scroller.scrollLeft > max) {
							scroller.scrollLeft = Math.max(max, scroller.scrollLeft - FORWARD_SPEED);
						} else {
							rewinding = true;
							easedScrollTo(0, REWIND_MS, function () {
								rewinding = false;
							});
						}
					}
				}
				requestAnimationFrame(tick);
			}
			requestAnimationFrame(tick);

			if ("IntersectionObserver" in window) {
				var observer = new IntersectionObserver(
					function (entries) {
						entries.forEach(function (entry) {
							inView = entry.isIntersecting;
						});
					},
					{ threshold: 0.2 }
				);
				observer.observe(wrapper);
			} else {
				inView = true;
			}

			function pause() {
				paused = true;
			}
			function resume() {
				paused = false;
			}
			scroller.addEventListener("mouseenter", pause);
			scroller.addEventListener("mouseleave", resume);
			scroller.addEventListener("touchstart", pause, { passive: true });
			scroller.addEventListener("touchend", resume, { passive: true });

			wrapper.querySelectorAll("[data-jluxe-scroll-prev], [data-jluxe-scroll-next]").forEach(function (btn) {
				btn.addEventListener("click", function () {
					rewinding = false;
					pause();
					window.clearTimeout(resumeTimer);
					resumeTimer = window.setTimeout(resume, ARROW_PAUSE_MS);
				});
			});
		});
	}

	/* ---------------------------------------------------------------
	 * «جهشِ» ملایمِ کشفِ کاروسل (peek hint) — باگِ واقعیِ گزارش‌شده: توی
	 * پنل‌های پیشنهادیِ دسته‌بندی، هر پنل روی موبایل/تبلت دقیقاً basis-full
	 * می‌گیره (کاملِ عرض، snap-mandatory) — یعنی هیچ لبه‌ای از پنلِ بعدی
	 * دیده نمی‌شه و کاربر هیچ نشونه‌ی بصری‌ای نداره که این ردیف اصلاً
	 * قابلِ‌اسکرول‌کردنه. فقط یک‌بار، وقتی کاروسل برای اولین‌بار وارد دیدِ
	 * کاربر می‌شه (IntersectionObserver)، یک جهشِ کوتاه به چپ (منفی‌تر شدنِ
	 * scrollLeft، طبقِ همون قراردادِ RTL بالا) و برگشتِ نرم به صفر — صرفاً
	 * یک اشاره‌ی بصری، نه ناوبریِ واقعی. دسکتاپ (lg+) خودش overflow-visible/
	 * غیرقابل‌اسکرول‌ه (scrollWidth تقریباً برابرِ clientWidth)، پس خودکار رد
	 * می‌شه — نیازی به چک‌کردنِ عرضِ ویوپورت نیست.
	 * ------------------------------------------------------------- */
	function initCarouselPeekHint() {
		if (!("IntersectionObserver" in window)) return;

		function easedScrollTo(scroller, target, duration, done) {
			var start = scroller.scrollLeft;
			var change = target - start;
			var startTime = null;
			function step(ts) {
				if (!startTime) startTime = ts;
				var t = Math.min((ts - startTime) / duration, 1);
				var eased = 1 - Math.pow(1 - t, 3); // ease-out cubic
				scroller.scrollLeft = start + change * eased;
				if (t < 1) {
					requestAnimationFrame(step);
				} else if (done) {
					done();
				}
			}
			requestAnimationFrame(step);
		}

		document.querySelectorAll("[data-jluxe-peek-hint]").forEach(function (scroller) {
			var done = false;
			var observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (done || !entry.isIntersecting) return;
						if (scroller.scrollWidth - scroller.clientWidth < 20) return;
						done = true;
						observer.disconnect();
						window.setTimeout(function () {
							var peek = Math.min(56, scroller.clientWidth * 0.16);
							easedScrollTo(scroller, -peek, 420, function () {
								window.setTimeout(function () {
									easedScrollTo(scroller, 0, 420);
								}, 260);
							});
						}, 400);
					});
				},
				{ threshold: 0.4 }
			);
			observer.observe(scroller);
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		initStories();
		initSliders("[data-jluxe-banner-slider]");
		initSliders("[data-jluxe-hero-slider]");
		initScrollArrows();
		initScrollArrowVisibility();
		initDragScroll();
		initAutoScroll();
		initCarouselPeekHint();
	});
})();
