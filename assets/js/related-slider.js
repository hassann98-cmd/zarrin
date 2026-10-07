/*
 * JLUXE — اسلایدرِ «محصولات مرتبط» (R85).
 *
 * چرا: پیش‌تر هر تعداد محصولِ مرتبط به‌صورت گرید رندر می‌شد و کارتِ پنجم به خطِ
 * بعد می‌رفت. حالا ردیف یک خطِ افقی است و این فایل فقط دو کار می‌کند:
 *   ۱) وقتی همهٔ کارت‌ها جا می‌شوند، فلش‌ها را پنهان می‌کند (بدونِ جاوااسکریپت
 *      هم اسکرولِ لمسی/تاچ‌پد با scroll-snap کار می‌کند — فلش‌ها پیش‌فرض پنهان‌اند).
 *   ۲) فلشِ قبلی/بعدی را «یک کارت» جابه‌جا می‌کند. جهتِ حرکت از روی خودِ ردیف
 *      خوانده می‌شود (rtl/ltr)، چون در RTL مقدارِ scrollLeft منفی می‌شود و
 *      جهتِ حرکت برعکس است. مرورگر خودش جابه‌جایی را به بازهٔ مجاز محدود
 *      می‌کند، پس در پایانِ ردیف فضای خالی نمی‌ماند.
 *
 * بودجه: بدونِ کتابخانه؛ یک شنوندهٔ scroll با requestAnimationFrame؛ با
 * prefers-reduced-motion حرکتِ نرم خاموش می‌شود.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-cp3-related]');
	if (!root) { return; }
	var track = root.querySelector('ul.products');
	var nav   = root.querySelector('[data-cp3-rel-nav]');
	var prev  = root.querySelector('[data-cp3-rel-prev]');
	var next  = root.querySelector('[data-cp3-rel-next]');
	if (!track || !nav || !prev || !next) { return; }

	var reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
	function motion() { return reduced && reduced.matches ? 'auto' : 'smooth'; }
	function cards() { return track.querySelectorAll('li.product'); }
	function maxScroll() { return Math.max(0, track.scrollWidth - track.clientWidth); }
	// در RTL مرورگرهای امروزی scrollLeft از صفر به سمتِ منفی می‌رود؛ قدرِ مطلق می‌گیریم.
	function position() { return Math.abs(track.scrollLeft); }
	function isRTL() {
		var style = window.getComputedStyle ? window.getComputedStyle(track) : null;
		return !!style && 'rtl' === style.direction;
	}
	// فاصلهٔ افقیِ دو کارتِ پشت‌سرهم (عرضِ کارت + فاصله) — مستقل از LTR/RTL.
	function step() {
		var list = cards();
		if (!list.length) { return 0; }
		if (list.length < 2) { return list[0].getBoundingClientRect().width; }
		var first = list[0].getBoundingClientRect();
		var second = list[1].getBoundingClientRect();
		return Math.abs(second.left - first.left) || first.width;
	}
	function isScrollable() { return maxScroll() > 2 && cards().length > 1; }

	function sync() {
		var scrollable = isScrollable();
		nav.hidden = !scrollable;
		if (!scrollable) { return; }
		var pos = position();
		prev.disabled = pos <= 2;
		next.disabled = pos >= maxScroll() - 2;
	}

	function move(direction) {
		var distance = step() * direction;
		if (isRTL()) { distance = -distance; } // در RTL، «بعدی» یعنی scrollLeft منفی‌تر
		track.scrollBy({ left: distance, behavior: motion() });
	}

	prev.addEventListener('click', function () { move(-1); });
	next.addEventListener('click', function () { move(1); });

	var queued = false;
	track.addEventListener('scroll', function () {
		if (queued) { return; }
		queued = true;
		window.requestAnimationFrame(function () { queued = false; sync(); });
	}, { passive: true });
	if ('onscrollend' in window) { track.addEventListener('scrollend', sync); }
	window.addEventListener('resize', sync, { passive: true });

	sync();
})();
