/**
 * Guided checkout presentation/state machine. WooCommerce still owns all
 * validation on final submission, calculations, rates, gateways and orders.
 */
(function () {
  "use strict";

  var form = document.querySelector('form.checkout[data-jluxe-checkout-flow="guided"]');
  var root = form && form.querySelector("[data-guided-checkout-root]");
  if (!form || !root) return;

  var $ = window.jQuery;
  var addressStage = form.querySelector('[data-guided-panel="address"]');
  var paymentStage = form.querySelector('[data-guided-panel="payment"]');
  var customerDetails = form.querySelector("#customer_details");
  var addressCard = form.querySelector("[data-guided-address-card]");
  var emptyAddress = form.querySelector("[data-guided-empty-address]");
  var nextButtons = form.querySelectorAll("[data-guided-next]");
  var cancelEdit = form.querySelector("[data-guided-cancel-edit]");
  var addressActions = form.querySelector("[data-guided-address-actions]");
  var addressScrim = form.querySelector("[data-guided-address-scrim]");
  var editorReturnFocus = null;
  var visualViewport = window.visualViewport || null;
  var baselineViewportHeight = 0;
  var lastViewportWidth = window.innerWidth || 0;
  var focusScrollPending = false;
  var paymentSubmit = form.querySelector("#jluxe-payment-submit");
  var paymentBack = paymentSubmit && paymentSubmit.querySelector("[data-jluxe-checkout-back]");
  var pageTitle = form.querySelector("[data-guided-page-title]");
  var pageDescription = form.querySelector("[data-guided-page-description]");
  var addressError = form.querySelector("[data-guided-address-error]");
  var checkoutCopy =
    (window.JLuxeThemeSettings &&
      window.JLuxeThemeSettings.cartCheckout &&
      window.JLuxeThemeSettings.cartCheckout.copy) || {};
  var hasAddress = root.getAttribute("data-guided-has-address") === "1";
  var currentStep = root.getAttribute("data-guided-step") || "address";
  var manuallySelectedGateway = null;

  function setError(element, visible) {
    if (element) element.hidden = !visible;
  }

  function isMobileCheckout() {
    return typeof window.matchMedia === "function"
      ? window.matchMedia("(max-width: 767.98px)").matches
      : window.innerWidth <= 767;
  }

  function isTextEntryElement(element) {
    if (!element || !form.contains(element) || element.disabled || element.readOnly) return false;
    if (element.tagName === "TEXTAREA" || element.tagName === "SELECT") return true;
    if (element.tagName !== "INPUT") return false;
    return !/^(button|checkbox|color|file|hidden|image|radio|range|reset|submit)$/i.test(element.type || "text");
  }

  function requestFrame(callback) {
    if (typeof window.requestAnimationFrame === "function") window.requestAnimationFrame(callback);
    else window.setTimeout(callback, 16);
  }

  function scrollFocusedCheckoutFieldIntoView() {
    focusScrollPending = false;
    var field = document.activeElement;
    if (!isTextEntryElement(field)) return;

    var viewport = window.visualViewport;
    var viewportTop = viewport ? viewport.offsetTop || 0 : 0;
    var viewportBottom = viewportTop + (viewport ? viewport.height : window.innerHeight || document.documentElement.clientHeight);
    var footer = currentStep === "payment" ? paymentSubmit : addressActions;
    if (footer && !footer.hidden) {
      var footerTop = footer.getBoundingClientRect().top;
      if (footerTop > viewportTop && footerTop < viewportBottom) viewportBottom = footerTop;
    }
    var safeTop = viewportTop + 14;
    var safeBottom = viewportBottom - 18;
    var rect = field.getBoundingClientRect();
    var delta = rect.bottom > safeBottom ? rect.bottom - safeBottom : rect.top < safeTop ? rect.top - safeTop : 0;
    if (!delta) return;

    try {
      if (customerDetails && !customerDetails.hidden && customerDetails.contains(field) && typeof customerDetails.scrollBy === "function") {
        customerDetails.scrollBy({ top: delta + (delta > 0 ? 16 : -16), behavior: "smooth" });
      } else if (typeof window.scrollBy === "function") {
        window.scrollBy({ top: delta + (delta > 0 ? 16 : -16), behavior: "smooth" });
      }
    } catch (error) {
      // Embedded browsers that lack scrollBy still retain native focus scrolling.
    }
  }

  function scheduleFocusedFieldVisibility() {
    if (focusScrollPending) return;
    focusScrollPending = true;
    requestFrame(scrollFocusedCheckoutFieldIntoView);
  }

  function updateKeyboardViewport() {
    var viewport = window.visualViewport;
    var layoutHeight = Math.max(window.innerHeight || 0, document.documentElement.clientHeight || 0);
    var viewportHeight = viewport && Number.isFinite(viewport.height) ? viewport.height : layoutHeight;
    var viewportTop = viewport && Number.isFinite(viewport.offsetTop) ? viewport.offsetTop : 0;
    var currentWidth = window.innerWidth || lastViewportWidth;
    if (!baselineViewportHeight || Math.abs(currentWidth - lastViewportWidth) > 120) {
      baselineViewportHeight = Math.max(layoutHeight, viewportHeight);
      lastViewportWidth = currentWidth;
    } else if (viewportHeight >= baselineViewportHeight - 40) {
      baselineViewportHeight = Math.max(baselineViewportHeight, layoutHeight, viewportHeight);
    }
    var keyboardInset = Math.max(0, layoutHeight - viewportHeight - viewportTop);
    var viewportReducedForKeyboard = baselineViewportHeight - viewportHeight > 100;
    var style = document.documentElement.style;
    style.setProperty("--jluxe-visual-viewport-height", Math.round(viewportHeight) + "px");
    style.setProperty("--jluxe-visual-viewport-top", Math.round(viewportTop) + "px");
    style.setProperty("--jluxe-keyboard-inset", Math.round(keyboardInset) + "px");

    var keyboardOpen = (keyboardInset > 100 || viewportReducedForKeyboard) && isTextEntryElement(document.activeElement);
    form.classList.toggle("jluxe-guided-checkout--keyboard-open", keyboardOpen);
    if (keyboardOpen) scheduleFocusedFieldVisibility();
  }

  function setAddressEditorPresentation(open) {
    var modal = Boolean(open && isMobileCheckout());
    form.classList.toggle("jluxe-guided-checkout--address-editing", modal);
    if (addressScrim) addressScrim.hidden = !modal;
    if (customerDetails) {
      if (modal) {
        customerDetails.setAttribute("role", "dialog");
        customerDetails.setAttribute("aria-modal", "true");
      } else {
        customerDetails.removeAttribute("role");
        customerDetails.removeAttribute("aria-modal");
      }
    }
    updateKeyboardViewport();
  }

  function scrollToElement(element) {
    if (element && typeof element.scrollIntoView === "function") {
      element.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  function scrollCheckoutToTop() {
    var html = document.documentElement;
    var previous = html.style.scrollBehavior;
    html.style.scrollBehavior = "auto";
    try {
      if (typeof window.scrollTo === "function") {
        window.scrollTo({ top: 0, behavior: "auto" });
      }
    } catch (error) {
      // Some embedded browsers do not implement scrollTo; stage visibility is
      // still updated and the focused heading below provides keyboard context.
    } finally {
      html.style.scrollBehavior = previous;
    }
  }

  function updateStepper(step) {
    var stepper = form.querySelector("[data-guided-stepper]");
    if (!stepper) return;
    var steps = stepper.querySelectorAll("li[data-guided-step]");
    for (var i = 0; i < steps.length; i++) {
      var id = steps[i].getAttribute("data-guided-step");
      var state = id === step ? "active" : id === "address" && step === "payment" ? "done" : "pending";
      steps[i].setAttribute("data-guided-step-state", state);
      if (state === "active") steps[i].setAttribute("aria-current", "step");
      else steps[i].removeAttribute("aria-current");
    }
  }

  function applyShippingVisibility() {
    var shipping = form.querySelector("#jluxe-shipping-section");
    if (shipping) shipping.hidden = currentStep !== "payment";
  }

  function updateStickyTotal() {
    var source = form.querySelector("#order_review [data-jluxe-order-total-value]");
    var target = form.querySelector("[data-guided-total]");
    if (source && target) target.innerHTML = source.innerHTML;
  }

  function showStage(step, shouldScroll) {
    currentStep = step === "payment" ? "payment" : "address";
    root.setAttribute("data-guided-step", currentStep);
    if (addressStage) addressStage.hidden = currentStep !== "address";
    if (paymentStage) paymentStage.hidden = currentStep !== "payment";
    if (paymentSubmit) paymentSubmit.hidden = currentStep !== "payment";
    if (addressActions) addressActions.hidden = currentStep !== "address";
    if (paymentBack) paymentBack.hidden = currentStep !== "payment";
    updateStepper(currentStep);
    applyShippingVisibility();
    updateStickyTotal();

    if (pageTitle) pageTitle.textContent = currentStep === "payment" ? "مرور سفارش و پرداخت" : "نشانی تحویل";
    if (pageDescription) {
      pageDescription.textContent = currentStep === "payment"
        ? "روش ارسال، جمع سفارش و درگاه پرداخت را بررسی کنید."
        : "اطلاعات گیرنده و نشانی نوشتاری را وارد کنید؛ هزینهٔ ارسال از روی همین اطلاعات محاسبه می‌شود.";
    }

    if (shouldScroll) {
      scrollCheckoutToTop();
      var heading = currentStep === "payment"
        ? form.querySelector("#jluxe-guided-payment-title")
        : form.querySelector("#jluxe-guided-address-title");
      if (heading) {
        heading.setAttribute("tabindex", "-1");
        heading.focus({ preventScroll: true });
      }
    }
  }

  function addressPrefix() {
    var different = form.querySelector("#ship-to-different-address-checkbox");
    return different && different.checked ? "shipping" : "billing";
  }

  function fieldValue(name) {
    var field = form.querySelector('[name="' + name + '"]');
    return field && typeof field.value === "string" ? field.value.trim() : "";
  }

  function fieldLabel(name) {
    var field = form.querySelector('[name="' + name + '"]');
    if (!field) return "";
    if (field.tagName === "SELECT") {
      var option = field.options[field.selectedIndex];
      return option && field.value ? option.textContent.trim() : "";
    }
    return typeof field.value === "string" ? field.value.trim() : "";
  }

  function updateAddressSummary() {
    var prefix = addressPrefix();
    var firstName = fieldValue(prefix + "_first_name") || fieldValue("billing_first_name");
    var lastName = fieldValue(prefix + "_last_name") || fieldValue("billing_last_name");
    var phone = fieldValue("billing_phone");
    var state = fieldLabel(prefix + "_state");
    var city = fieldLabel(prefix + "_city");
    var address = fieldValue(prefix + "_address_1");
    var postcode = fieldValue(prefix + "_postcode");
    var recipient = (firstName + " " + lastName).trim() || phone;
    var addressText = [
      [state, city].filter(Boolean).join("، "),
      address,
      postcode,
    ].filter(Boolean).join("، ");
    var recipientTargets = form.querySelectorAll("[data-guided-address-recipient], [data-guided-preview-recipient]");
    var textTargets = form.querySelectorAll("[data-guided-address-text], [data-guided-preview-text]");
    var phoneTarget = form.querySelector("[data-guided-address-phone]");

    recipientTargets.forEach(function (target) { target.textContent = recipient; });
    textTargets.forEach(function (target) { target.textContent = addressText; });
    if (phoneTarget) {
      phoneTarget.textContent = phone;
      phoneTarget.hidden = !phone || phone === recipient;
    }
    // City and province are always required by this shop. The street field
    // follows the administrator's real WooCommerce required-field setting.
    hasAddress = Boolean(city && state);
    root.setAttribute("data-guided-has-address", hasAddress ? "1" : "0");
    if (addressCard) addressCard.hidden = !hasAddress;
    if (emptyAddress) emptyAddress.hidden = hasAddress;
  }

  function openAddressForm() {
    if (document.activeElement && document.activeElement !== document.body && (!customerDetails || !customerDetails.contains(document.activeElement))) {
      editorReturnFocus = document.activeElement;
    }
    if (customerDetails) customerDetails.hidden = false;
    if (addressCard) addressCard.hidden = true;
    if (emptyAddress) emptyAddress.hidden = true;
    if (cancelEdit) cancelEdit.hidden = false;
    setAddressEditorPresentation(true);
    setError(addressError, false);
    var first = customerDetails && customerDetails.querySelector("input:not([type=hidden]), select, textarea");
    if (first && !first.value) {
      first.focus({ preventScroll: true });
      window.setTimeout(updateKeyboardViewport, 80);
    } else if (customerDetails && customerDetails.getAttribute("role") === "dialog") {
      customerDetails.focus({ preventScroll: true });
    }
    scheduleFocusedFieldVisibility();
  }

  function closeAddressForm(restoreFocus) {
    if (customerDetails) customerDetails.hidden = true;
    setAddressEditorPresentation(false);
    updateAddressSummary();
    if (cancelEdit) cancelEdit.hidden = true;
    setError(addressError, false);
    if (restoreFocus !== false && editorReturnFocus && editorReturnFocus.isConnected && !editorReturnFocus.hidden) {
      editorReturnFocus.focus({ preventScroll: true });
    }
    editorReturnFocus = null;
  }

  function findFirstInvalidRequiredField() {
    if (!customerDetails || customerDetails.hidden || !$) return null;
    var firstInvalid = null;
    $(customerDetails).find(".validate-required:visible").each(function () {
      var row = this;
      var $row = $(row);
      var $input = $row.find("input:not([type=hidden]), select, textarea").first();
      if (!$input.length) return;
      var empty = $input.is(":checkbox")
        ? !$input.is(":checked")
        : $input.val() === "" || $input.val() === null;
      if (empty) {
        $row.addClass("woocommerce-invalid woocommerce-invalid-required-field");
        if (!firstInvalid) firstInvalid = row;
      } else {
        $row.removeClass("woocommerce-invalid woocommerce-invalid-required-field");
      }
    });
    return firstInvalid;
  }

  function goToPayment() {
    if (customerDetails && customerDetails.hidden && !hasAddress) {
      openAddressForm();
      var prompt = form.querySelector("#billing_first_name, #shipping_first_name, #billing_state");
      scrollToElement(prompt && prompt.closest(".form-row"));
      return;
    }
    if (customerDetails && !customerDetails.hidden) {
      var invalid = findFirstInvalidRequiredField();
      if (invalid) {
        setError(addressError, true);
        scrollToElement(invalid);
        var control = invalid.querySelector("input:not([type=hidden]), select, textarea");
        if (control) control.focus({ preventScroll: true });
        return;
      }
      setError(addressError, false);
      updateAddressSummary();
      closeAddressForm(false);
    }
    if (!hasAddress) {
      openAddressForm();
      setError(addressError, true);
      return;
    }
    if ($) $(document.body).trigger("update_checkout");
    showStage("payment", true);
  }

  function goToAddress(edit) {
    showStage("address", true);
    if (edit) openAddressForm();
    else if (hasAddress) closeAddressForm();
    setError(form.querySelector("[data-jluxe-payment-error]"), false);
  }

  function hasShippingMethodSelected() {
    var shipping = form.querySelector("#jluxe-shipping-section");
    if (!shipping) return true;
    var radios = shipping.querySelectorAll("input.shipping_method");
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
    if (!radios.length) return true;
    return Boolean(payment.querySelector("input.payment_method:checked"));
  }

  function stopCheckoutSubmit(event) {
    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
  }

  function clearAutomaticGatewaySelection() {
    var payment = form.querySelector("#payment");
    if (!payment) return;
    var radios = payment.querySelectorAll("input.payment_method");
    if (manuallySelectedGateway) {
      var chosen = Array.prototype.find.call(radios, function (radio) {
        return radio.value === manuallySelectedGateway;
      });
      if (chosen) {
        if (!chosen.checked && $) $(chosen).trigger("click");
        return;
      }
    }
    if (radios.length !== 1 || !radios[0].checked) return;
    radios[0].checked = false;
    var box = payment.querySelector(".payment_box.payment_method_" + radios[0].value.replace(/[^a-zA-Z0-9_-]/g, ""));
    if (box) box.style.display = "none";
  }

  form.addEventListener("click", function (event) {
    var target = event.target;
    if (!target || !target.closest) return;
    if (target.closest("[data-guided-open-address]")) {
      openAddressForm();
      return;
    }
    if (target.closest("[data-guided-close-address]") || target === addressScrim) {
      closeAddressForm(true);
      return;
    }
    if (target.closest("[data-guided-cancel-edit]")) {
      closeAddressForm(true);
      return;
    }
    if (target.closest("[data-guided-next]")) {
      goToPayment();
      return;
    }
    if (target.closest("[data-guided-edit-address]")) {
      goToAddress(true);
      return;
    }
    if (target.closest("[data-jluxe-checkout-back]")) {
      goToAddress(false);
    }
  });

  form.addEventListener("focusin", function (event) {
    if (!isTextEntryElement(event.target)) return;
    updateKeyboardViewport();
    window.setTimeout(updateKeyboardViewport, 80);
    scheduleFocusedFieldVisibility();
  });

  form.addEventListener("focusout", function () {
    window.setTimeout(updateKeyboardViewport, 120);
  });

  form.addEventListener("keydown", function (event) {
    if (!form.classList.contains("jluxe-guided-checkout--address-editing") || event.key !== "Tab" || !customerDetails) return;
    var focusable = Array.prototype.filter.call(
      customerDetails.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
      function (element) { return !element.hidden && !element.closest("[hidden]"); },
    );
    if (!focusable.length) {
      event.preventDefault();
      customerDetails.focus();
      return;
    }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !customerDetails.contains(document.activeElement))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !customerDetails.contains(document.activeElement))) {
      event.preventDefault();
      first.focus();
    }
  });

  if (visualViewport) {
    visualViewport.addEventListener("resize", updateKeyboardViewport);
    visualViewport.addEventListener("scroll", updateKeyboardViewport);
  }
  window.addEventListener("resize", function () {
    if (customerDetails && !customerDetails.hidden) setAddressEditorPresentation(true);
    else updateKeyboardViewport();
  });
  window.addEventListener("orientationchange", updateKeyboardViewport);

  form.addEventListener("change", function (event) {
    var target = event.target;
    if (!target) return;
    if (target.matches("input.shipping_method")) setError(form.querySelector("[data-jluxe-shipping-error]"), false);
    if (target.matches("input.payment_method")) setError(form.querySelector("[data-jluxe-payment-error]"), false);
    if (target.matches("#ship-to-different-address-checkbox")) updateAddressSummary();
    if (target.closest && target.closest("#customer_details")) {
      var row = target.closest(".form-row");
      if (row && target.value) row.classList.remove("woocommerce-invalid", "woocommerce-invalid-required-field");
    }
  });

  form.addEventListener("submit", function (event) {
    if (currentStep === "address") {
      stopCheckoutSubmit(event);
      goToPayment();
      return;
    }
    if (!hasShippingMethodSelected()) {
      stopCheckoutSubmit(event);
      var shippingError = form.querySelector("[data-jluxe-shipping-error]");
      setError(shippingError, true);
      scrollToElement(shippingError || form.querySelector("#jluxe-shipping-section"));
      return;
    }
    if (!hasPaymentMethodSelected()) {
      stopCheckoutSubmit(event);
      var paymentError = form.querySelector("[data-jluxe-payment-error]");
      setError(paymentError, true);
      scrollToElement(paymentError || form.querySelector("#payment"));
    }
  }, true);

  form.addEventListener("change", function (event) {
    if (event.isTrusted && event.target && event.target.matches("#payment input.payment_method") && event.target.checked) {
      manuallySelectedGateway = event.target.value;
    }
  }, true);

  if ($) {
    $(document.body).on("updated_checkout.jluxeGuided", function () {
      applyShippingVisibility();
      updateStickyTotal();
      window.setTimeout(clearAutomaticGatewaySelection, 50);
    });
  }

  currentStep = root.getAttribute("data-guided-step") === "payment" ? "payment" : "address";
  if (customerDetails && !customerDetails.hidden) {
    if (cancelEdit) cancelEdit.hidden = false;
    setAddressEditorPresentation(true);
  } else {
    updateKeyboardViewport();
  }
  if (paymentBack) paymentBack.hidden = currentStep !== "payment";
  updateAddressSummary();
  showStage(currentStep, false);
  window.setTimeout(clearAutomaticGatewaySelection, 50);
})();
