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
	var modeToggle = document.querySelector( '[data-jluxe-sticky-cta-mode-toggle]' );
	var modeToggleLabel = modeToggle && modeToggle.querySelector( '[data-jluxe-sticky-cta-mode-label]' );
	var activeMode = 'navigation';
	var alwaysVisible = bar.hasAttribute( 'data-jluxe-sticky-cta-always' );

	var navSelector = '#bottom-navigation, .jluxe-mobile-nav';
	var navHiddenStates = new WeakMap();
	var navGap = 10;
	var resizeObserver = typeof window.ResizeObserver === 'function' ? new window.ResizeObserver( layout ) : null;
	var observedElements = new WeakSet();

	function isMobileViewport() {
		return window.matchMedia
			? window.matchMedia( '(max-width: 767.98px)' ).matches
			: ( window.innerWidth || document.documentElement.clientWidth || 0 ) < 768;
	}

	function viewportHeight() {
		/* Fixed docks and getBoundingClientRect share layout-viewport coordinates.
		 * visualViewport.height can shrink for the keyboard/browser chrome and
		 * mixing the two coordinate systems makes the switch jump. */
		return window.innerHeight || document.documentElement.clientHeight || 0;
	}

	function positionToggleAwayFromAssistant() {
		if ( ! modeToggle ) {
			return;
		}
		var assistant = window.JLuxeThemeSettings && window.JLuxeThemeSettings.aiAssistant;
		var assistantSide = assistant && 'start' === assistant.position ? 'start' : 'end';
		var toggleSide = 'start' === assistantSide ? 'end' : 'start';
		var direction = window.getComputedStyle( document.documentElement ).direction || document.documentElement.dir || 'ltr';
		var rtl = 'rtl' === direction;
		var safeStartSide = rtl ? 'right' : 'left';
		var safeEndSide = rtl ? 'left' : 'right';
		modeToggle.style.setProperty(
			'inset-inline-start',
			'start' === toggleSide ? 'max(12px, env(safe-area-inset-' + safeStartSide + ', 0px))' : 'auto'
		);
		modeToggle.style.setProperty(
			'inset-inline-end',
			'end' === toggleSide ? 'max(12px, env(safe-area-inset-' + safeEndSide + ', 0px))' : 'auto'
		);
	}

	function syncNavigationVisibility() {
		var shouldHideNavigation = 'purchase' === activeMode && bar.classList.contains( 'is-visible' ) && isMobileViewport();
		var candidates = document.querySelectorAll( navSelector );
		for ( var index = 0; index < candidates.length; index++ ) {
			var candidate = candidates[ index ];
			if ( shouldHideNavigation ) {
				if ( ! navHiddenStates.has( candidate ) ) {
					navHiddenStates.set( candidate, candidate.hidden );
				}
				candidate.hidden = true;
			} else if ( navHiddenStates.has( candidate ) ) {
				candidate.hidden = navHiddenStates.get( candidate );
				navHiddenStates.delete( candidate );
			}
		}
	}

	function hasUsableNavigation( candidates ) {
		for ( var index = 0; index < candidates.length; index++ ) {
			var candidate = candidates[ index ];
			var originallyHidden = navHiddenStates.has( candidate ) ? navHiddenStates.get( candidate ) : candidate.hidden;
			if ( ! originallyHidden ) {
				return true;
			}
		}
		return false;
	}

	function setMode( mode ) {
		activeMode = 'purchase' === mode ? 'purchase' : 'navigation';
		if ( bar.classList.contains( 'is-visible' ) && isMobileViewport() ) {
			body.setAttribute( 'data-jluxe-sticky-cta-mode', activeMode );
		} else {
			body.removeAttribute( 'data-jluxe-sticky-cta-mode' );
		}
		syncNavigationVisibility();
		if ( modeToggle ) {
			var targetLabel = 'purchase' === activeMode ? 'منوی موبایل' : 'نوار خرید';
			modeToggle.setAttribute( 'aria-label', 'نمایش ' + targetLabel );
			modeToggle.setAttribute( 'aria-pressed', 'purchase' === activeMode ? 'true' : 'false' );
			if ( modeToggleLabel ) {
				modeToggleLabel.textContent = 'purchase' === activeMode ? 'منو' : 'خرید';
			}
		}
	}

	function focusAfterToggleHides( navCandidates ) {
		if ( ! modeToggle || document.activeElement !== modeToggle ) {
			return;
		}
		var target = realButton && ! realButton.disabled ? realButton : null;
		for ( var index = 0; ! target && index < navCandidates.length; index++ ) {
			var navigation = navCandidates[ index ];
			var navigationWasHidden = navHiddenStates.has( navigation ) ? navHiddenStates.get( navigation ) : navigation.hidden;
			if ( ! navigationWasHidden ) {
				target = navigation.querySelector( 'a, button, [tabindex]:not([tabindex="-1"])' );
			}
		}
		if ( target && typeof target.focus === 'function' ) {
			try { target.focus( { preventScroll: true } ); } catch ( error ) { target.focus(); }
		}
	}

	function syncModeToggle( viewportH, dockTop, navCandidates ) {
		if ( ! modeToggle ) {
			return;
		}
		positionToggleAwayFromAssistant();
		var shouldShow = isMobileViewport() && bar.classList.contains( 'is-visible' ) && hasUsableNavigation( navCandidates );
		if ( ! shouldShow ) {
			focusAfterToggleHides( navCandidates );
			modeToggle.hidden = true;
			return;
		}

		var anchorTop = null;
		if ( 'purchase' === activeMode ) {
			/* getBoundingClientRect().top follows the CTA's slide-in transform; derive
			 * its resting edge from the fixed bottom inset and intrinsic height instead. */
			var barRect = bar.getBoundingClientRect();
			var barHeight = bar.offsetHeight || barRect.height;
			var barBottom = parseFloat( bar.style.bottom );
			if ( ! isFinite( barBottom ) ) {
				barBottom = 0;
			}
			if ( barHeight > 0 ) {
				anchorTop = viewportH - barBottom - barHeight;
			}
		}
		if ( null === anchorTop && null !== dockTop ) {
			anchorTop = dockTop;
		}
		if ( null !== anchorTop ) {
			var toggleBottom = Math.max( 0, Math.ceil( viewportH - anchorTop + 8 ) ) + 'px';
			if ( modeToggle.style.bottom !== toggleBottom ) {
				modeToggle.style.bottom = toggleBottom;
			}
		}
		modeToggle.hidden = false;
	}

	function observeDockGeometry() {
		if ( ! resizeObserver ) {
			return;
		}
		if ( ! observedElements.has( bar ) ) {
			resizeObserver.observe( bar );
			observedElements.add( bar );
		}
		var candidates = document.querySelectorAll( navSelector );
		for ( var index = 0; index < candidates.length; index++ ) {
			if ( ! observedElements.has( candidates[ index ] ) ) {
				resizeObserver.observe( candidates[ index ] );
				observedElements.add( candidates[ index ] );
			}
		}
	}

	/*
	 * وقتی نوار خرید فعال است، dock به‌طور موقت پنهان می‌شود؛ در حالتِ منو
	 * نوار خرید کنار می‌رود. جابه‌جاییِ سوئیچر همیشه از لبهٔ واقعیِ نوارِ فعال
	 * اندازه‌گیری می‌شود، نه از ارتفاع‌هایِ فرضیِ منوهای مختلف.
	 */
	function layout() {
		var viewportH = viewportHeight();
		syncNavigationVisibility();
		var navCandidates = document.querySelectorAll( navSelector );
		var dockTop = null;
		for ( var navIndex = 0; navIndex < navCandidates.length; navIndex++ ) {
			var candidate = navCandidates[ navIndex ];
			var candidateRect = candidate.getBoundingClientRect();
			var candidateStyle = window.getComputedStyle( candidate );
			if (
				candidateRect.height > 0 &&
				candidateRect.bottom > 0 &&
				candidateRect.top < viewportH &&
				candidateStyle.display !== 'none' &&
				candidateStyle.visibility !== 'hidden'
			) {
				dockTop = null === dockTop ? candidateRect.top : Math.min( dockTop, candidateRect.top );
			}
		}

		var hasDock = null !== dockTop;
		var navSpace = hasDock ? Math.max( 0, viewportH - dockTop ) : 0;
		var gap = hasDock ? navGap : 0;
		var bottom = navSpace + gap;
		if ( hasDock ) {
			bar.setAttribute( 'data-jluxe-bottom-dock', '' );
		} else {
			bar.removeAttribute( 'data-jluxe-bottom-dock' );
		}
		if ( bar.style.bottom !== bottom + 'px' ) {
			bar.style.bottom = bottom + 'px';
		}

		syncModeToggle( viewportH, dockTop, navCandidates );
		if ( bar.classList.contains( 'is-visible' ) ) {
			var toggleClearance = modeToggle && ! modeToggle.hidden ? Math.max( modeToggle.offsetHeight || 0, 44 ) + 8 : 0;
			var activeDockSpace = navSpace;
			if ( 'purchase' === activeMode ) {
				var purchaseBottom = parseFloat( bar.style.bottom );
				if ( ! isFinite( purchaseBottom ) ) {
					purchaseBottom = 0;
				}
				activeDockSpace = ( bar.offsetHeight || bar.getBoundingClientRect().height || 0 ) + purchaseBottom;
			}
			var pageClearance = Math.ceil( activeDockSpace + toggleClearance + 12 ) + 'px';
			if ( body.style.paddingBottom !== pageClearance ) {
				body.style.paddingBottom = pageClearance;
			}
		} else if ( '' !== body.style.paddingBottom ) {
			body.style.paddingBottom = '';
		}
		observeDockGeometry();
	}

	/* نمایش پس از عبورِ فرمِ اصلی از دید — بدونِ IntersectionObserver (سازگار). */
	function onScroll() {
		if ( ! isMobileViewport() ) {
			bar.classList.remove( 'is-visible' );
			body.classList.remove( 'jluxe-has-sticky-cta' );
			body.style.paddingBottom = '';
			setMode( 'navigation' );
			layout();
			return;
		}

		var wasVisible = bar.classList.contains( 'is-visible' );
		var visible;
		if ( alwaysVisible || ! form ) {
			visible = true;
			bar.classList.add( 'is-visible' );
		} else {
			var rect = form.getBoundingClientRect();
			var threshold = viewportHeight() * ( wasVisible ? 0.92 : 0.75 );
			visible = bar.classList.toggle( 'is-visible', rect.bottom < threshold );
		}
		body.classList.toggle( 'jluxe-has-sticky-cta', visible );
		var modeNeedsSync = visible
			? ! body.hasAttribute( 'data-jluxe-sticky-cta-mode' )
			: body.hasAttribute( 'data-jluxe-sticky-cta-mode' );
		if ( wasVisible !== visible || modeNeedsSync ) {
			setMode( visible ? 'purchase' : 'navigation' );
			layout();
		}
	}

	var layoutFramePending = false;
	function scheduleLayout() {
		if ( layoutFramePending ) {
			return;
		}
		layoutFramePending = true;
		var update = function () {
			layoutFramePending = false;
			layout();
		};
		if ( typeof window.requestAnimationFrame === 'function' ) {
			window.requestAnimationFrame( update );
		} else {
			window.setTimeout( update, 0 );
		}
	}

	bar.addEventListener( 'transitionend', function ( event ) {
		if ( event.target === bar && ( ! event.propertyName || 'transform' === event.propertyName ) ) {
			scheduleLayout();
		}
	} );

	if ( modeToggle ) {
		modeToggle.addEventListener( 'click', function () {
			if ( ! bar.classList.contains( 'is-visible' ) || ! isMobileViewport() ) {
				return;
			}
			setMode( 'purchase' === activeMode ? 'navigation' : 'purchase' );
			layout();
		} );
	}

	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'scroll', scheduleLayout, { passive: true } );
	window.addEventListener( 'resize', onScroll, { passive: true } );
	window.addEventListener( 'resize', scheduleLayout, { passive: true } );
	window.addEventListener( 'orientationchange', scheduleLayout, { passive: true } );
	window.addEventListener( 'pageshow', scheduleLayout, { passive: true } );
	if ( window.visualViewport ) {
		window.visualViewport.addEventListener( 'resize', onScroll, { passive: true } );
		window.visualViewport.addEventListener( 'resize', scheduleLayout, { passive: true } );
		window.visualViewport.addEventListener( 'scroll', scheduleLayout, { passive: true } );
	}

	/* React/افزونه ممکن است dock را بعد از اجرای اسکریپت به DOM اضافه کند. */
	if ( typeof window.MutationObserver === 'function' && body ) {
		var dockMutationObserver = new window.MutationObserver( function ( mutations ) {
			var dockChanged = false;
			for ( var mutationIndex = 0; mutationIndex < mutations.length && ! dockChanged; mutationIndex++ ) {
				var changedNodes = Array.prototype.slice.call( mutations[ mutationIndex ].addedNodes ).concat(
					Array.prototype.slice.call( mutations[ mutationIndex ].removedNodes )
				);
				for ( var nodeIndex = 0; nodeIndex < changedNodes.length; nodeIndex++ ) {
					var node = changedNodes[ nodeIndex ];
					if ( 1 === node.nodeType && ( node.matches( navSelector ) || node.querySelector( navSelector ) ) ) {
						dockChanged = true;
						break;
					}
				}
			}
			if ( dockChanged ) {
				layout();
			}
		} );
		dockMutationObserver.observe( body, { childList: true, subtree: true } );
	}

	layout();
	onScroll();

	function productVariationForm() {
		return document.querySelector( 'form.variations_form.cart' ) || ( form && form.matches( 'form.variations_form' ) ? form : null );
	}

	function firstVariationChoice( variationForm ) {
		var choice = variationForm.querySelector( '[data-cp3-pills] .cp3-pill:not(:disabled), [data-jluxe-variation-swatches] button:not(:disabled)' );
		if ( choice ) {
			return choice;
		}
		var selects = variationForm.querySelectorAll( '.variations select:not(:disabled)' );
		for ( var index = 0; index < selects.length; index++ ) {
			var select = selects[ index ];
			var style = window.getComputedStyle( select );
			if ( ! select.hasAttribute( 'data-cp3-select' ) && style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' ) {
				return select;
			}
		}
		return null;
	}

	function guideToVariationChoice() {
		var variationForm = productVariationForm();
		if ( ! variationForm ) {
			return;
		}
		/* فرود روی کنترلِ قابل‌مشاهده؛ فرمِ کلاسیک تمامِ کارت و گالری را می‌پوشاند. */
		var target = variationForm.querySelector( '[data-cp3-pills], [data-jluxe-variation-group]' ) || variationForm.querySelector( '.variations' ) || variationForm;
		var reducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var behavior = reducedMotion ? 'auto' : 'smooth';
		if ( typeof target.scrollIntoView === 'function' ) {
			target.scrollIntoView( { behavior: behavior, block: 'center' } );
		} else if ( typeof window.scrollTo === 'function' && typeof target.getBoundingClientRect === 'function' ) {
			var targetTop = target.getBoundingClientRect().top + ( window.pageYOffset || 0 ) - 120;
			window.scrollTo( { top: Math.max( 0, targetTop ), behavior: behavior } );
		}
		var choice = firstVariationChoice( variationForm );
		if ( choice ) {
			window.setTimeout( function () {
				if ( ! choice.isConnected ) {
					return;
				}
				try {
					choice.focus( { preventScroll: true } );
				} catch ( error ) {
					choice.focus();
				}
			}, 350 );
		}
	}

	var add = bar.querySelector( '[data-jluxe-sticky-add]' );
	if ( add ) {
		add.addEventListener( 'click', function () {
			var mode = add.getAttribute( 'data-jluxe-sticky-mode' ) || 'add';
			var variationForm = productVariationForm();
			if ( 'scroll' === mode || ( variationForm && ( ! realButton || realButton.disabled ) ) ) {
				guideToVariationChoice();
				return;
			}
			/* همیشه دکمهٔ رسمی ووکامرس را می‌زنیم؛ form.submit اعتبارسنجی تنوع را دور می‌زد. */
			if ( realButton && typeof realButton.click === 'function' && ! realButton.disabled ) {
				realButton.click();
			}
		} );
	}
}() );

/* Keep the original WooCommerce buy form in place, but let its visual surface
 * follow the product details column after the summary card has scrolled away. */
( function () {
	'use strict';

	var range = document.querySelector( '[data-jluxe-buybox-range]' );
	var holder = document.querySelector( '[data-jluxe-product-buybox]' );
	var surface = holder && holder.querySelector( '[data-jluxe-buybox-surface]' );
	var rail = range && range.querySelector( '[data-jluxe-buybox-rail]' );
	if ( ! range || ! holder || ! surface || ! rail ) {
		return;
	}

	var desktopQuery = window.matchMedia ? window.matchMedia( '(min-width: 1200px)' ) : null;
	var ready = false;
	var pinned = false;
	var natural = null;
	var frame = 0;
	var lastViewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
	var originalHolderMinHeight = holder.style.minHeight;
	var originalSurfaceStyles = {
		top: surface.style.top,
		left: surface.style.left,
		width: surface.style.width,
		maxHeight: surface.style.maxHeight,
		overflowY: surface.style.overflowY,
	};

	function isDesktop() {
		return desktopQuery ? desktopQuery.matches : ( window.innerWidth || 0 ) >= 1200;
	}

	function setReady( value ) {
		if ( ready === value ) {
			return;
		}
		ready = value;
		if ( ready ) {
			range.setAttribute( 'data-jluxe-buybox-sticky-ready', '' );
		} else {
			range.removeAttribute( 'data-jluxe-buybox-sticky-ready' );
		}
	}

	function resetPinned() {
		if ( ! pinned ) {
			return;
		}
		pinned = false;
		surface.classList.remove( 'is-pinned-to-details' );
		surface.style.top = originalSurfaceStyles.top;
		surface.style.left = originalSurfaceStyles.left;
		surface.style.width = originalSurfaceStyles.width;
		surface.style.maxHeight = originalSurfaceStyles.maxHeight;
		surface.style.overflowY = originalSurfaceStyles.overflowY;
		holder.style.minHeight = originalHolderMinHeight;
		natural = null;
	}

	function measureNaturalBox() {
		var holderRect = holder.getBoundingClientRect();
		var surfaceRect = surface.getBoundingClientRect();
		return {
			holderHeight: Math.max( holder.offsetHeight || 0, holderRect.height || 0 ),
			width: surfaceRect.width || surface.offsetWidth || 0,
			height: Math.max( surfaceRect.height || 0, surface.scrollHeight || 0 ),
			leftInset: Math.max( 0, surfaceRect.left - holderRect.left ),
			rightInset: Math.max( 0, holderRect.right - surfaceRect.right ),
		};
	}

	function stickyOffsets() {
		var nav = range.querySelector( '[data-cp3-nav], .jluxe-product-section-nav' );
		var navTop = 90;
		var navMargin = 32;
		if ( nav && window.getComputedStyle ) {
			var navStyle = window.getComputedStyle( nav );
			var parsedTop = parseFloat( navStyle.top );
			var parsedMargin = parseFloat( navStyle.marginTop );
			if ( isFinite( parsedTop ) ) {
				navTop = parsedTop;
			} else if ( nav.matches( '[data-cp3-nav]' ) ) {
				navTop = 96;
			}
			if ( isFinite( parsedMargin ) ) {
				navMargin = Math.max( 0, parsedMargin );
			}
		}
		var top = Math.max( 0, navTop ) + 8;
		return { top: top, start: Math.max( 0, top - navMargin ) };
	}

	function update() {
		frame = 0;
		if ( ! isDesktop() ) {
			resetPinned();
			setReady( false );
			return;
		}
		setReady( true );

		var rangeRect = range.getBoundingClientRect();
		var railRect = rail.getBoundingClientRect();
		var offsets = stickyOffsets();
		if ( rangeRect.bottom <= 0 || rangeRect.top > offsets.start || railRect.width <= 0 ) {
			resetPinned();
			return;
		}

		if ( ! pinned ) {
			natural = measureNaturalBox();
			if ( ! natural.width || ! natural.height ) {
				return;
			}
			holder.style.minHeight = Math.ceil( natural.holderHeight ) + 'px';
			pinned = true;
			surface.classList.add( 'is-pinned-to-details' );
		}

		var railWidth = Math.max( 0, railRect.width - natural.leftInset - natural.rightInset );
		var width = Math.min( natural.width, railWidth );
		if ( width <= 0 ) {
			resetPinned();
			return;
		}
		var contentHeight = surface.scrollHeight || natural.height;
		natural.height = Math.max( surface.getBoundingClientRect().height || 0, contentHeight );
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 800;
		var maxHeight = Math.max( 160, viewportHeight - offsets.top - 12 );
		var visibleHeight = Math.min( natural.height, maxHeight );
		var top = Math.min( offsets.top, rangeRect.bottom - visibleHeight );
		var left = railRect.left + natural.leftInset;
		var maxHolderHeight = Math.max( natural.holderHeight, natural.height );

		if ( holder.style.minHeight !== Math.ceil( maxHolderHeight ) + 'px' ) {
			holder.style.minHeight = Math.ceil( maxHolderHeight ) + 'px';
		}
		surface.style.top = Math.round( top ) + 'px';
		surface.style.left = Math.round( left ) + 'px';
		surface.style.width = Math.floor( width ) + 'px';
		surface.style.maxHeight = Math.floor( maxHeight ) + 'px';
		surface.style.overflowY = natural.height > maxHeight ? 'auto' : 'visible';
	}

	function schedule() {
		if ( frame ) {
			return;
		}
		if ( window.requestAnimationFrame ) {
			frame = window.requestAnimationFrame( update );
		} else {
			frame = window.setTimeout( update, 16 );
		}
	}

	function handleResize() {
		var width = window.innerWidth || document.documentElement.clientWidth || 0;
		if ( width !== lastViewportWidth ) {
			lastViewportWidth = width;
			resetPinned();
		}
		schedule();
	}

	window.addEventListener( 'scroll', schedule, { passive: true } );
	window.addEventListener( 'resize', handleResize, { passive: true } );
	window.addEventListener( 'pageshow', schedule, { passive: true } );
	if ( desktopQuery ) {
		if ( desktopQuery.addEventListener ) {
			desktopQuery.addEventListener( 'change', schedule );
		} else if ( desktopQuery.addListener ) {
			desktopQuery.addListener( schedule );
		}
	}
	if ( typeof window.ResizeObserver === 'function' ) {
		var geometryObserver = new window.ResizeObserver( schedule );
		geometryObserver.observe( range );
		geometryObserver.observe( rail );
		geometryObserver.observe( surface );
	}

	lastViewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
	schedule();
}() );
