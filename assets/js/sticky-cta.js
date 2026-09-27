/**
 * نوار چسبانِ «افزودن به سبد» موبایل (jluxe_render_sticky_add_to_cart).
 * طراحی: هیچ فرمِ موازی‌ای نمی‌سازد — کلیکِ کاربر دقیقاً همان کلیکِ دکمهٔ
 * رسمیِ ووکامرس است (همان nonce/اجکشن/اعتبارسنجی). برای محصولِ متغیر،
 * کاربر به فرمِ انتخابِ تنوع هدایت می‌شود. نمایشِ نوار همیشه با
 * prefers-reduced-motion محترم شمرده می‌شود.
 */
( function () {
	'use strict';
	var bar = document.querySelector( '[data-jluxe-sticky-cta]' );
	if ( ! bar ) {
		return;
	}
	var form = document.querySelector( 'form.cart' );
	var realButton = document.querySelector( 'form.cart .single_add_to_cart_button' );
	var body = document.body;

	/* نمایش پس از عبورِ فرمِ اصلی از دید — بدونِ IntersectionObserver (سازگار). */
	function onScroll() {
		if ( ! form ) {
			bar.classList.add( 'is-visible' );
			return;
		}
		var rect = form.getBoundingClientRect();
		var passed = rect.bottom < ( window.innerHeight || document.documentElement.clientHeight ) * 0.75;
		var visible = bar.classList.toggle( 'is-visible', passed );
		body.classList.toggle( 'jluxe-has-sticky-cta', visible );
	}
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', onScroll, { passive: true } );

	/* حسِ اپ: نوار بالای ناوبریِ پایینِ موبایل می‌نشیند، نه روی آن. */
	function layout() {
		var nav = document.querySelector( '.jluxe-mobile-nav' );
		var navH = nav ? nav.getBoundingClientRect().height : 0;
		var safe = nav ? ( parseFloat( window.getComputedStyle( nav ).paddingBottom ) || 0 ) : 0;
		bar.style.bottom = ( navH ? ( navH - safe ) : 0 ) + 'px';
		if ( bar.classList.contains( 'is-visible' ) ) {
			body.style.paddingBottom = Math.ceil( bar.offsetHeight + ( navH ? navH : 0 ) + 12 ) + 'px';
		} else {
			body.style.paddingBottom = '';
		}
	}
	window.addEventListener( 'resize', layout, { passive: true } );
	layout();
	onScroll();

	var add = bar.querySelector( '[data-jluxe-sticky-add]' );
	if ( add ) {
		add.addEventListener( 'click', function () {
			var mode = add.getAttribute( 'data-jluxe-sticky-mode' );
			if ( 'scroll' === mode && form ) {
				/* R58: فرود روی خودِ قرص‌های انتخابِ تنویع، نه وسطِ کلِ کارت —
				فرمِ classic کلِ گرید (شامل گالری) را دربر می‌گیرد و
				scrollIntoView روی آن کاربر را وسطِ کارت رها می‌کرد. */
				var pillsTarget = document.querySelector( '[data-cp3-pills]' );
				var scrollTarget = pillsTarget || form;
				if ( typeof scrollTarget.scrollIntoView === 'function' ) {
					scrollTarget.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}
				var firstSelect = form.querySelector( 'select' );
				if ( firstSelect ) {
					window.setTimeout( function () { firstSelect.focus( { preventScroll: true } ); }, 350 );
				}
				return;
			}
			/* محصولِ ساده: خودِ دکمهٔ رسمی ووکامرس کلیک می‌شود (submit واقعی فرم). */
			if ( realButton && typeof realButton.click === 'function' && ! realButton.disabled ) {
				realButton.click();
			} else if ( form && typeof form.submit === 'function' ) {
				form.submit();
			}
		} );
	}
}() );
