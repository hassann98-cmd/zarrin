/**
 * R89 — اسلایدرِ هیروی صفحهٔ اصلی (inc/theme-settings-homepage.php:
 * jluxe_render_homepage_hero). بدونِ کتابخانه؛ رفتار مثلِ Swiper:
 *
 *   effect  slide | fade | zoom | vertical | none   (data-effect)
 *   loop    حلقهٔ بی‌پایان با دو اسلایدِ کلون (فقط slide/vertical)
 *   swipe   کشیدنِ انگشت/ماوس؛ در slide اسلاید همراهِ انگشت حرکت می‌کند
 *   autoplay با توقف روی hover/فوکوس/لمس، تبِ پنهان و بیرون از دید
 *   a11y    اسلایدهای غیرفعال inert + aria-hidden؛ کلیدهای ← → ؛
 *           prefers-reduced-motion ⇒ بدونِ انیمیشن و بدونِ چرخشِ خودکار
 *
 * جهت: صفحه RTL است؛ مثلِ Swiperِ RTL «بعدی» از سمتِ چپ وارد می‌شود و
 * کشیدنِ انگشت به راست یعنی بعدی.
 */
(function (root, factory) {
	var api = factory();
	if (typeof module === "object" && module.exports) module.exports = api;
	else root.JLuxeHeroSlider = api;
	if (typeof document !== "undefined" && typeof window !== "undefined" && !window.__JLUXE_HERO_MANUAL__) {
		var start = function () { api.initAll(document); };
		if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
		else start();
	}
})(typeof self !== "undefined" ? self : globalThis, function () {
	"use strict";

	function initHeroSlider(el) {
		if (!el || el.__jluxeHero) return el && el.__jluxeHero;
		var win = el.ownerDocument.defaultView;
		var doc = el.ownerDocument;
		var track = el.querySelector("[data-jluxe-hero-track]");
		var viewport = track && track.parentElement;
		var slides = track ? Array.prototype.slice.call(track.querySelectorAll("[data-jluxe-hero-slide]")) : [];
		var count = slides.length;
		if (!track || count < 2) return null;

		var effect = el.getAttribute("data-effect") || "slide";
		var reduced = !!(win.matchMedia && win.matchMedia("(prefers-reduced-motion: reduce)").matches);
		var moving = effect === "slide" || effect === "vertical";
		var vertical = effect === "vertical";
		var loop = moving && el.getAttribute("data-loop") === "1";
		var wrap = el.getAttribute("data-loop") === "1";
		var speedAttr = parseInt(el.getAttribute("data-speed"), 10);
		var speed = reduced || effect === "none" ? 0 : isNaN(speedAttr) ? 500 : Math.max(0, speedAttr);
		var autoplay = el.getAttribute("data-autoplay") === "1" && !reduced;
		var delay = Math.max(2000, parseInt(el.getAttribute("data-autoplay-ms"), 10) || 5000);
		var dots = Array.prototype.slice.call(el.querySelectorAll("[data-jluxe-hero-dot]"));
		var prevBtn = el.querySelector("[data-jluxe-hero-prev]");
		var nextBtn = el.querySelector("[data-jluxe-hero-next]");
		var rtl = win.getComputedStyle(el).direction === "rtl" || doc.documentElement.dir === "rtl";

		var index = 0; // اسلایدِ واقعی
		var pos = 0; // جایگاه در track (با کلون‌ها)
		var timer = 0;
		var holds = {}; // دلایلِ توقف: hover, focus, touch, hidden, offscreen
		var busy = false;
		var jumpTimer = 0;

		if (reduced) el.style.setProperty("--jh-speed", "0ms");

		if (loop) {
			var headClone = slides[count - 1].cloneNode(true);
			var tailClone = slides[0].cloneNode(true);
			[headClone, tailClone].forEach(function (clone) {
				clone.removeAttribute("data-jluxe-hero-slide");
				clone.setAttribute("data-jluxe-hero-clone", "");
				clone.setAttribute("aria-hidden", "true");
				clone.setAttribute("inert", "");
				clone.setAttribute("data-active", "false");
				clone.querySelectorAll("img").forEach(function (img) {
					img.setAttribute("loading", "lazy");
					img.setAttribute("fetchpriority", "low");
				});
			});
			track.insertBefore(headClone, slides[0]);
			track.appendChild(tailClone);
			pos = 1;
		}

		function offsetFor(p, dragPx) {
			if (!moving) return "";
			var gap = "var(--jh-gap, 0px)";
			if (vertical) {
				return "translate3d(0, calc(" + -p + " * (100% + " + gap + ") + " + (dragPx || 0) + "px), 0)";
			}
			// RTL: اسلایدها از راست چیده می‌شوند ⇒ برای رفتن جلو track به راست می‌رود.
			var sign = rtl ? 1 : -1;
			return "translate3d(calc(" + sign * p + " * (100% + " + gap + ") + " + (dragPx || 0) + "px), 0, 0)";
		}

		function apply(animate) {
			if (!moving) return;
			if (!animate) track.classList.add("is-jumping");
			track.style.transform = offsetFor(pos, 0);
			if (!animate) {
				void track.offsetWidth; // eslint-disable-line no-void
				track.classList.remove("is-jumping");
			}
		}

		function markActive() {
			slides.forEach(function (slide, i) {
				var active = i === index;
				slide.setAttribute("data-active", active ? "true" : "false");
				if (active) {
					slide.removeAttribute("aria-hidden");
					slide.removeAttribute("inert");
				} else {
					slide.setAttribute("aria-hidden", "true");
					slide.setAttribute("inert", "");
				}
			});
			dots.forEach(function (dot, i) {
				dot.setAttribute("aria-current", i === index ? "true" : "false");
				var fill = dot.querySelector("[data-jluxe-hero-fill]");
				if (!fill) return;
				fill.style.transition = "none";
				fill.style.width = i < index ? "100%" : "0%";
				if (i === index && autoplay && !paused()) {
					void fill.offsetWidth; // eslint-disable-line no-void
					fill.style.transition = "width " + delay + "ms linear";
					fill.style.width = "100%";
				} else if (i === index && !autoplay) {
					fill.style.width = "100%";
				}
			});
		}

		function settleLoop() {
			busy = false;
			if (!loop) return;
			if (pos === 0) {
				pos = count;
				apply(false);
			} else if (pos === count + 1) {
				pos = 1;
				apply(false);
			}
		}

		function goTo(target, opts) {
			opts = opts || {};
			index = ((target % count) + count) % count;
			pos = loop ? (typeof opts.pos === "number" ? opts.pos : index + 1) : index;
			markActive();
			if (moving) {
				busy = speed > 0;
				apply(speed > 0);
				win.clearTimeout(jumpTimer);
				if (speed > 0) jumpTimer = win.setTimeout(settleLoop, speed + 60); // پشتیبان اگر transitionend نیاید
				else settleLoop();
			}
			schedule();
		}

		// بدونِ حلقه: فلش/کشیدن در دو سرِ لیست می‌ایستد؛ چرخشِ خودکار به اولی برمی‌گردد (rewind).
		function next(auto) {
			if (busy) settleLoop();
			if (loop) return goTo(index + 1, { pos: pos + 1 });
			if (index + 1 < count) return goTo(index + 1);
			if (auto === true || wrap) return goTo(0);
			apply(true);
			schedule();
		}
		function prev() {
			if (busy) settleLoop();
			if (loop) return goTo(index - 1, { pos: pos - 1 });
			if (index > 0) return goTo(index - 1);
			if (wrap) return goTo(count - 1);
			apply(true);
			schedule();
		}

		function paused() {
			for (var k in holds) if (holds[k]) return true;
			return false;
		}
		function schedule() {
			win.clearTimeout(timer);
			if (!autoplay || paused()) return;
			timer = win.setTimeout(function () { next(true); }, delay);
		}
		function hold(reason, on) {
			var was = paused();
			holds[reason] = on;
			var now = paused();
			if (was === now) return;
			if (now) {
				win.clearTimeout(timer);
				dots.forEach(function (dot) {
					var fill = dot.querySelector("[data-jluxe-hero-fill]");
					if (fill && dot.getAttribute("aria-current") === "true") {
						var w = win.getComputedStyle(fill).width;
						fill.style.transition = "none";
						fill.style.width = w;
					}
				});
			} else {
				markActive();
				schedule();
			}
		}

		track.addEventListener("transitionend", function (e) {
			if (e.target === track && e.propertyName === "transform") {
				win.clearTimeout(jumpTimer);
				settleLoop();
			}
		});

		if (prevBtn) prevBtn.addEventListener("click", function () { prev(); });
		if (nextBtn) nextBtn.addEventListener("click", function () { next(); });
		dots.forEach(function (dot, i) {
			dot.addEventListener("click", function () { goTo(i); });
		});
		el.addEventListener("keydown", function (e) {
			if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
			var forward = (e.key === "ArrowLeft") === rtl;
			e.preventDefault();
			if (forward) next();
			else prev();
		});
		el.addEventListener("mouseenter", function () { hold("hover", true); });
		el.addEventListener("mouseleave", function () { hold("hover", false); });
		el.addEventListener("focusin", function () { hold("focus", true); });
		el.addEventListener("focusout", function (e) {
			if (!el.contains(e.relatedTarget)) hold("focus", false);
		});
		doc.addEventListener("visibilitychange", function () { hold("hidden", doc.hidden); });
		if (win.IntersectionObserver) {
			new win.IntersectionObserver(function (entries) {
				hold("offscreen", !entries[entries.length - 1].isIntersecting);
			}).observe(el);
		}

		// ---- کشیدن (touch/mouse) ----
		var drag = null;
		var suppressClick = false;
		viewport.style.touchAction = "pan-y";
		track.querySelectorAll("a, img").forEach(function (node) { node.setAttribute("draggable", "false"); });
		viewport.addEventListener("dragstart", function (e) { e.preventDefault(); });
		viewport.addEventListener("pointerdown", function (e) {
			if (e.button !== 0 || !e.isPrimary) return;
			drag = { x: e.clientX, y: e.clientY, t: Date.now(), dx: 0, active: false, id: e.pointerId };
			if (e.pointerType === "touch") hold("touch", true);
		});
		viewport.addEventListener("pointermove", function (e) {
			if (!drag || e.pointerId !== drag.id) return;
			var dx = e.clientX - drag.x;
			var dy = e.clientY - drag.y;
			if (!drag.active) {
				if (Math.abs(dx) < 8 || Math.abs(dx) < Math.abs(dy)) return;
				drag.active = true;
				if (busy) settleLoop();
				if (viewport.setPointerCapture) {
					try { viewport.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
				}
			}
			drag.dx = dx;
			if (effect === "slide") {
				track.classList.add("is-dragging");
				var edge = !loop && ((index === 0 && (rtl ? dx < 0 : dx > 0)) || (index === count - 1 && (rtl ? dx > 0 : dx < 0)));
				track.style.transform = offsetFor(pos, edge ? dx / 3 : dx);
			}
		});
		function endDrag(e) {
			if (!drag || (e && e.pointerId !== drag.id)) return;
			var d = drag;
			drag = null;
			if (e && e.pointerType === "touch") hold("touch", false);
			track.classList.remove("is-dragging");
			if (!d.active) return;
			suppressClick = true;
			win.setTimeout(function () { suppressClick = false; }, 0);
			var width = viewport.getBoundingClientRect().width || 1;
			var fast = Math.abs(d.dx) > 30 && Date.now() - d.t < 250;
			if (Math.abs(d.dx) > width * 0.15 || fast) {
				var forward = rtl ? d.dx > 0 : d.dx < 0;
				if (forward) next();
				else prev();
			} else {
				apply(true);
			}
		}
		viewport.addEventListener("pointerup", endDrag);
		viewport.addEventListener("pointercancel", endDrag);
		viewport.addEventListener(
			"click",
			function (e) {
				if (suppressClick) {
					e.preventDefault();
					e.stopPropagation();
				}
			},
			true
		);

		apply(false);
		markActive();
		schedule();

		var api = {
			next: function () { next(); },
			prev: function () { prev(); },
			goTo: function (i) { goTo(i); },
			get index() { return index; },
			get position() { return pos; },
			settle: settleLoop,
		};
		el.__jluxeHero = api;
		el.setAttribute("data-ready", "true");
		return api;
	}

	function initAll(scope) {
		return Array.prototype.slice
			.call((scope || document).querySelectorAll("[data-jluxe-hero-slider]"))
			.map(initHeroSlider);
	}

	return { initHeroSlider: initHeroSlider, initAll: initAll };
});
