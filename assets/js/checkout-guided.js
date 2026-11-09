/* One-page guided checkout: native WooCommerce fields and submission stay in charge. */
(function () {
  "use strict";

  var form = document.querySelector('form.checkout[data-jluxe-checkout-flow="guided"]');
  var content = form && form.querySelector("[data-guided-checkout-root]");
  if (!form || !content) return;

  var $ = window.jQuery;
  var customerDetails = form.querySelector("#customer_details");
  var orderReview = form.querySelector("#order_review");
  var paymentSection = form.querySelector("[data-guided-payment-section]");
  var stickyAction = form.querySelector("[data-guided-mobile-action]");
  var stickyButton = stickyAction && stickyAction.querySelector("[data-guided-continue]");
  var stickyLabel = stickyAction && stickyAction.querySelector("[data-guided-continue-label]");
  var visualViewport = window.visualViewport || null;
  var baselineViewportHeight = 0;
  var lastViewportWidth = window.innerWidth || 0;
  var focusScrollPending = false;
  var actionUpdatePending = false;

  function requestFrame(callback) {
    if (typeof window.requestAnimationFrame === "function") window.requestAnimationFrame(callback);
    else window.setTimeout(callback, 16);
  }

  function layoutViewportHeight() {
    return Math.max(window.innerHeight || 0, document.documentElement.clientHeight || 0);
  }

  function isTextEntryElement(element) {
    if (!element || !form.contains(element) || element.disabled || element.readOnly) return false;
    if (element.tagName === "TEXTAREA" || element.tagName === "SELECT") return true;
    if (element.tagName !== "INPUT") return false;
    return !/^(button|checkbox|color|file|hidden|image|radio|range|reset|submit)$/i.test(element.type || "text");
  }

  function scrollFocusedFieldIntoView() {
    focusScrollPending = false;
    var field = document.activeElement;
    if (!isTextEntryElement(field)) return;

    var viewport = window.visualViewport;
    var viewportTop = viewport && Number.isFinite(viewport.offsetTop) ? viewport.offsetTop : 0;
    var viewportBottom = viewportTop + (viewport && Number.isFinite(viewport.height) ? viewport.height : layoutViewportHeight());
    var actionTop = stickyAction && !stickyAction.hidden ? stickyAction.getBoundingClientRect().top : viewportBottom;
    if (actionTop > viewportTop && actionTop < viewportBottom) viewportBottom = actionTop;
    var safeTop = viewportTop + 14;
    var safeBottom = viewportBottom - 16;
    var rect = field.getBoundingClientRect();
    var delta = rect.bottom > safeBottom ? rect.bottom - safeBottom : rect.top < safeTop ? rect.top - safeTop : 0;
    if (!delta) return;

    try {
      window.scrollBy({ top: delta + (delta > 0 ? 18 : -18), behavior: "smooth" });
    } catch (error) {
      if (typeof field.scrollIntoView === "function") field.scrollIntoView({ block: "center" });
    }
  }

  function scheduleFieldVisibility() {
    if (focusScrollPending) return;
    focusScrollPending = true;
    requestFrame(scrollFocusedFieldIntoView);
  }

  function updateKeyboardViewport() {
    var layoutHeight = layoutViewportHeight();
    var viewportHeight = visualViewport && Number.isFinite(visualViewport.height) ? visualViewport.height : layoutHeight;
    var viewportTop = visualViewport && Number.isFinite(visualViewport.offsetTop) ? visualViewport.offsetTop : 0;
    var currentWidth = window.innerWidth || lastViewportWidth;
    if (!baselineViewportHeight || Math.abs(currentWidth - lastViewportWidth) > 120) {
      baselineViewportHeight = Math.max(layoutHeight, viewportHeight);
      lastViewportWidth = currentWidth;
    } else if (viewportHeight >= baselineViewportHeight - 40) {
      baselineViewportHeight = Math.max(baselineViewportHeight, layoutHeight, viewportHeight);
    }

    var keyboardInset = Math.max(0, layoutHeight - viewportHeight - viewportTop);
    var viewportReducedForKeyboard = baselineViewportHeight - viewportHeight > 100;
    var layoutViewportResizedForKeyboard = baselineViewportHeight - layoutHeight > 100;
    if (keyboardInset < 100 && viewportReducedForKeyboard && !layoutViewportResizedForKeyboard) {
      keyboardInset = Math.max(0, baselineViewportHeight - viewportHeight - viewportTop);
    }

    var rootStyle = document.documentElement.style;
    rootStyle.setProperty("--jluxe-visual-viewport-height", Math.round(viewportHeight) + "px");
    rootStyle.setProperty("--jluxe-visual-viewport-top", Math.round(viewportTop) + "px");
    rootStyle.setProperty("--jluxe-keyboard-inset", Math.round(keyboardInset) + "px");
    var keyboardOpen = (keyboardInset > 100 || viewportReducedForKeyboard) && isTextEntryElement(document.activeElement);
    form.classList.toggle("jluxe-guided-checkout--keyboard-open", keyboardOpen);
    if (keyboardOpen) scheduleFieldVisibility();
  }

  function isPaymentVisible() {
    var target = form.querySelector("#payment") || paymentSection;
    if (!target || target.hidden || target.closest("[hidden]")) return false;
    var rect = target.getBoundingClientRect();
    var viewport = window.visualViewport;
    var top = viewport && Number.isFinite(viewport.offsetTop) ? viewport.offsetTop : 0;
    var bottom = top + (viewport && Number.isFinite(viewport.height) ? viewport.height : layoutViewportHeight());
    var threshold = Math.min(150, Math.max(48, (bottom - top) * 0.18));
    return rect.top < bottom - threshold && rect.bottom > top + threshold;
  }

  function updateStickyAction() {
    actionUpdatePending = false;
    if (!stickyButton) return;
    var label = isPaymentVisible() ? "ثبت سفارش" : "ادامه";
    if (stickyLabel) stickyLabel.textContent = label;
    stickyButton.setAttribute("aria-label", label);
  }

  function scheduleStickyActionUpdate() {
    if (actionUpdatePending) return;
    actionUpdatePending = true;
    requestFrame(updateStickyAction);
  }

  function updateStickyTotal() {
    var source = form.querySelector("#order_review [data-jluxe-order-total-value]");
    var target = stickyAction && stickyAction.querySelector("[data-guided-total]");
    if (source && target) target.innerHTML = source.innerHTML;
  }

  function scrollToElement(element) {
    if (element && typeof element.scrollIntoView === "function") {
      element.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  function findFirstInvalidRequiredField() {
    if (!customerDetails || !$) return null;
    var firstInvalid = null;
    $(customerDetails).find(".validate-required:visible").each(function () {
      var row = this;
      var $row = $(row);
      var $input = $row.find("input:not([type=hidden]), select, textarea").first();
      if (!$input.length || $input.is(":disabled")) return;
      var empty = $input.is(":checkbox") ? !$input.is(":checked") : $input.val() === "" || $input.val() === null;
      if (empty) {
        $row.addClass("woocommerce-invalid woocommerce-invalid-required-field");
        if (!firstInvalid) firstInvalid = row;
      } else {
        $row.removeClass("woocommerce-invalid woocommerce-invalid-required-field");
      }
    });
    return firstInvalid;
  }

  function hasShippingMethodSelected() {
    var shipping = form.querySelector("#jluxe-shipping-section");
    var radios = shipping ? shipping.querySelectorAll("input.shipping_method") : [];
    if (!radios.length) return true;
    var groups = Object.create(null);
    for (var i = 0; i < radios.length; i++) {
      var name = radios[i].name || "shipping";
      if (!(name in groups)) groups[name] = false;
      if (radios[i].checked) groups[name] = true;
    }
    return Object.keys(groups).every(function (name) { return groups[name]; });
  }

  function hasPaymentMethodSelected() {
    var payment = form.querySelector("#payment");
    var radios = payment ? payment.querySelectorAll("input.payment_method") : [];
    return !radios.length || Boolean(payment.querySelector("input.payment_method:checked"));
  }

  function setError(element, visible) {
    if (element) element.hidden = !visible;
  }

  function continueCheckout() {
    var invalid = findFirstInvalidRequiredField();
    if (invalid) {
      scrollToElement(invalid);
      var field = invalid.querySelector("input:not([type=hidden]), select, textarea");
      if (field) field.focus({ preventScroll: true });
      return;
    }

    if (!isPaymentVisible()) {
      scrollToElement(orderReview || paymentSection);
      return;
    }

    if (!hasShippingMethodSelected()) {
      var shippingError = form.querySelector("[data-jluxe-shipping-error]");
      setError(shippingError, true);
      scrollToElement(shippingError || form.querySelector("#jluxe-shipping-section"));
      return;
    }
    setError(form.querySelector("[data-jluxe-shipping-error]"), false);

    if (!hasPaymentMethodSelected()) {
      var paymentError = form.querySelector("[data-jluxe-payment-error]");
      setError(paymentError, true);
      scrollToElement(paymentError || form.querySelector("#payment"));
      return;
    }
    setError(form.querySelector("[data-jluxe-payment-error]"), false);

    var placeOrder = form.querySelector("#place_order");
    if (placeOrder && !placeOrder.disabled) placeOrder.click();
  }

  if (stickyAction) {
    // Place the fixed action outside transformed theme wrappers so it truly
    // stays attached to the mobile viewport. Its proxy button still activates
    // WooCommerce's real #place_order button inside the checkout form.
    if (document.body && stickyAction.parentNode !== document.body) document.body.appendChild(stickyAction);
    stickyAction.hidden = false;
    document.body.classList.add("jluxe-guided-checkout-active");
    form.classList.add("jluxe-guided-checkout--enhanced");
    if (stickyButton) stickyButton.addEventListener("click", continueCheckout);
  }

  form.addEventListener("focusin", function (event) {
    if (!isTextEntryElement(event.target)) return;
    updateKeyboardViewport();
    window.setTimeout(updateKeyboardViewport, 80);
    scheduleFieldVisibility();
  });

  form.addEventListener("focusout", function () {
    window.setTimeout(updateKeyboardViewport, 120);
  });

  form.addEventListener("input", function (event) {
    var row = event.target && event.target.closest ? event.target.closest(".form-row") : null;
    if (row && event.target.value) row.classList.remove("woocommerce-invalid", "woocommerce-invalid-required-field");
  });

  form.addEventListener("change", function (event) {
    var target = event.target;
    if (!target) return;
    if (target.matches("input.shipping_method")) setError(form.querySelector("[data-jluxe-shipping-error]"), false);
    if (target.matches("input.payment_method")) setError(form.querySelector("[data-jluxe-payment-error]"), false);
    scheduleStickyActionUpdate();
  });

  if (visualViewport) {
    visualViewport.addEventListener("resize", updateKeyboardViewport);
    visualViewport.addEventListener("resize", scheduleStickyActionUpdate);
    visualViewport.addEventListener("scroll", updateKeyboardViewport);
    visualViewport.addEventListener("scroll", scheduleStickyActionUpdate);
  }
  window.addEventListener("resize", function () {
    updateKeyboardViewport();
    scheduleStickyActionUpdate();
  });
  window.addEventListener("scroll", scheduleStickyActionUpdate, { passive: true });
  window.addEventListener("orientationchange", updateKeyboardViewport);

  if ($) {
    $(document.body).on("updated_checkout.jluxeGuided", function () {
      updateStickyTotal();
      scheduleStickyActionUpdate();
    });
  }

  updateStickyTotal();
  updateKeyboardViewport();
  updateStickyAction();
})();
