/* Two-step guided checkout: keep WooCommerce fields, calculations, gateways, and submission native. */
(function () {
  "use strict";

  var form = document.querySelector('form.checkout[data-jluxe-checkout-flow="guided"]');
  var root = form && form.querySelector("[data-guided-checkout-root]");
  if (!form || !root) return;

  var beforeFormNotices = document.querySelector("[data-guided-before-form-notices]");
  var $ = window.jQuery || null;
  var body = document.body;
  var customerDetails = form.querySelector("#customer_details");
  var orderReview = form.querySelector("#order_review");
  var stepper = form.querySelector("[data-guided-stepper]");
  var shippingPanel = form.querySelector('[data-guided-panel="shipping"]');
  var paymentPanel = form.querySelector('[data-guided-panel="payment"]');
  var stepStatus = form.querySelector("[data-guided-step-status]");
  var shippingErrorSummary = form.querySelector("[data-guided-shipping-error]");
  var shippingSummary = form.querySelector("[data-guided-shipping-summary]");
  var couponWidget = form.querySelector("[data-guided-coupon]");
  var couponInput = couponWidget && couponWidget.querySelector("[data-guided-coupon-input]");
  var couponButton = couponWidget && couponWidget.querySelector("[data-guided-coupon-apply]");
  var couponStatus = couponWidget && couponWidget.querySelector("[data-guided-coupon-status]");
  var nativeCouponForm = document.querySelector("form.checkout_coupon");
  var nativeCouponInput = nativeCouponForm && nativeCouponForm.querySelector('input[name="coupon_code"]');
  var pendingCoupon = "";
  var couponErrorForPending = false;
  var couponRequestTimer = null;
  var stickyAction = form.querySelector("[data-guided-mobile-action]");
  var stickyButton = stickyAction && stickyAction.querySelector("[data-guided-continue]");
  var stickyLabel = stickyAction && stickyAction.querySelector("[data-guided-continue-label]");
  var stickyBack = stickyAction && stickyAction.querySelector("[data-guided-back]");
  var visualViewport = window.visualViewport || null;
  var baselineViewportHeight = 0;
  var lastViewportWidth = window.innerWidth || 0;
  var focusScrollPending = false;
  var actionUpdatePending = false;
  var currentStep = "shipping";
  var fieldErrorId = 0;

  function hideRepeatedCheckoutNotice(element) {
    if (!element) return;
    element.classList.add("jluxe-guided-checkout__suppressed-notice");
    element.hidden = true;
    element.setAttribute("aria-hidden", "true");
  }

  function suppressDuplicateCheckoutNotices() {
    var couponToggles = document.querySelectorAll(".woocommerce-form-coupon-toggle");
    for (var i = 0; i < couponToggles.length; i++) hideRepeatedCheckoutNotice(couponToggles[i]);

    var notices = document.querySelectorAll(".woocommerce-info");
    for (var j = 0; j < notices.length; j++) {
      var content = String(notices[j].textContent || "").replace(/\s+/g, " ");
      if (content.indexOf("بازپرداخت") !== -1 && content.indexOf("کش‌بک") !== -1 && content.indexOf("کیف پول") !== -1) {
        hideRepeatedCheckoutNotice(notices[j]);
      }
    }
  }

  function requestFrame(callback) {
    if (typeof window.requestAnimationFrame === "function") window.requestAnimationFrame(callback);
    else window.setTimeout(callback, 16);
  }

  function layoutViewportHeight() {
    return Math.max(window.innerHeight || 0, document.documentElement.clientHeight || 0);
  }

  function hasHiddenAncestor(element) {
    return !!(element && element.closest && element.closest("[hidden]"));
  }

  function isElementVisible(element) {
    if (!element || hasHiddenAncestor(element)) return false;
    if ($ && typeof $(element).is === "function") return $(element).is(":visible");
    var style = window.getComputedStyle ? window.getComputedStyle(element) : null;
    return !style || (style.display !== "none" && style.visibility !== "hidden");
  }

  function isTextEntryElement(element) {
    if (!element || !form.contains(element) || hasHiddenAncestor(element) || element.disabled || element.readOnly) return false;
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

  function scrollToElement(element) {
    if (!element || typeof element.scrollIntoView !== "function") return;
    var reduceMotion = false;
    try {
      reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch (error) { /* Use smooth scrolling when the preference API is unavailable. */ }
    element.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
  }

  function focusElement(element) {
    if (!element || typeof element.focus !== "function") return;
    try { element.focus({ preventScroll: true }); }
    catch (error) { try { element.focus(); } catch (focusError) { /* The control may have been removed by a checkout fragment. */ } }
  }

  function focusField(field) {
    if (!field) return;
    var target = field;
    if (field.classList && field.classList.contains("select2-hidden-accessible")) {
      var sibling = field.nextElementSibling;
      var selection = sibling && sibling.querySelector ? sibling.querySelector(".select2-selection") : null;
      if (selection && isElementVisible(selection)) target = selection;
    }
    focusElement(target);
  }

  function fieldControls(row) {
    return Array.prototype.filter.call(
      row.querySelectorAll("input:not([type=hidden]), select, textarea"),
      function (field) { return !field.disabled; }
    );
  }

  function valueIsEmpty(field, row) {
    var type = (field.type || "").toLowerCase();
    if (type === "radio") {
      var radios = row.querySelectorAll('input[type="radio"]');
      return !Array.prototype.some.call(radios, function (radio) { return !radio.disabled && radio.checked; });
    }
    if (type === "checkbox") return !field.checked;
    if (field.tagName === "SELECT") return field.value === "" || field.value === null;
    return String(field.value == null ? "" : field.value).trim() === "";
  }

  function errorMessageFor(field, requiredMissing) {
    if (requiredMissing) return "تکمیل این فیلد الزامی است.";
    var type = (field.type || "").toLowerCase();
    if (type === "email" || field.closest(".validate-email")) return "نشانی ایمیل را به‌صورت معتبر وارد کنید.";
    if (type === "url") return "نشانی وب واردشده معتبر نیست.";
    if (field.validity && field.validity.patternMismatch) return "قالب مقدار واردشده را بررسی کنید.";
    return "مقدار واردشده را بررسی کنید.";
  }

  function addDescribedBy(field, id) {
    var ids = (field.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
    if (ids.indexOf(id) === -1) ids.push(id);
    field.setAttribute("aria-describedby", ids.join(" "));
  }

  function removeDescribedBy(field, id) {
    var ids = (field.getAttribute("aria-describedby") || "").split(/\s+/).filter(function (value) { return value && value !== id; });
    if (ids.length) field.setAttribute("aria-describedby", ids.join(" "));
    else field.removeAttribute("aria-describedby");
  }

  function setFieldInvalid(row, field, message, requiredMissing) {
    row.classList.add("woocommerce-invalid");
    if (requiredMissing) row.classList.add("woocommerce-invalid-required-field");
    else row.classList.remove("woocommerce-invalid-required-field");
    if (field) {
      field.setAttribute("aria-invalid", "true");
      var error = row.querySelector("[data-guided-field-error]");
      if (!error) {
        error = document.createElement("span");
        error.className = "jluxe-guided-field-error";
        error.setAttribute("data-guided-field-error", "");
        error.id = "jluxe-guided-field-error-" + (++fieldErrorId);
        var wrapper = row.querySelector(".woocommerce-input-wrapper") || row;
        wrapper.appendChild(error);
      }
      error.textContent = message;
      error.hidden = false;
      addDescribedBy(field, error.id);
    }
  }

  function clearFieldError(row) {
    var error = row.querySelector("[data-guided-field-error]");
    if (error) {
      fieldControls(row).forEach(function (field) { removeDescribedBy(field, error.id); });
      error.remove();
    }
    fieldControls(row).forEach(function (field) { field.removeAttribute("aria-invalid"); });
    row.classList.remove("woocommerce-invalid", "woocommerce-invalid-required-field", "woocommerce-invalid-email", "woocommerce-invalid-phone", "woocommerce-invalid-postcode");
  }

  function validateFieldRow(row) {
    if (!row || !isElementVisible(row)) return null;
    var controls = fieldControls(row);
    if (!controls.length) return null;

    var rowRequiresValue = row.classList.contains("validate-required");
    var checkable = controls.filter(function (field) { return /^(checkbox|radio)$/i.test(field.type || ""); });
    var requiredMissing = false;
    var invalidField = null;

    if (rowRequiresValue && checkable.length) {
      var checked = checkable.some(function (field) { return field.checked; });
      if (!checked) {
        invalidField = checkable[0];
        requiredMissing = true;
      }
    } else {
      for (var i = 0; i < controls.length; i++) {
        var field = controls[i];
        var required = rowRequiresValue || field.required;
        var empty = valueIsEmpty(field, row);
        if (required && empty) {
          invalidField = field;
          requiredMissing = true;
          break;
        }
        if (!empty && typeof field.checkValidity === "function" && !field.checkValidity()) {
          invalidField = field;
          break;
        }
      }
    }

    if (invalidField) {
      setFieldInvalid(row, invalidField, errorMessageFor(invalidField, requiredMissing), requiredMissing);
      return { row: row, field: invalidField };
    }
    clearFieldError(row);
    return null;
  }

  function visibleInvalidCustomerRow() {
    if (!customerDetails) return null;
    var rows = customerDetails.querySelectorAll(".form-row");
    for (var i = 0; i < rows.length; i++) {
      if (isElementVisible(rows[i]) && rows[i].classList.contains("woocommerce-invalid")) return rows[i];
    }
    return null;
  }

  function setShippingErrorSummary(visible) {
    if (!shippingErrorSummary) return;
    shippingErrorSummary.hidden = !visible;
    if (visible) shippingErrorSummary.setAttribute("aria-live", "assertive");
  }

  function findFirstInvalidRequiredField() {
    if (!customerDetails) return null;
    var rows = customerDetails.querySelectorAll(".form-row");
    var firstInvalid = null;
    for (var i = 0; i < rows.length; i++) {
      var invalid = validateFieldRow(rows[i]);
      if (invalid && !firstInvalid) firstInvalid = invalid;
    }
    return firstInvalid;
  }

  function focusInvalidField(invalid) {
    if (!invalid) return;
    scrollToElement(invalid.row);
    focusField(invalid.field);
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
    var radios = payment ? payment.querySelectorAll('input.payment_method, input[name="payment_method"]') : [];
    return !radios.length || !!payment.querySelector('input.payment_method:checked, input[name="payment_method"]:checked');
  }

  function showMethodError(selector, visible) {
    var error = form.querySelector(selector);
    if (error) error.hidden = !visible;
    return error;
  }

  function focusFirstMethod(container, selector) {
    if (!container) return;
    var radio = container.querySelector(selector);
    if (!radio) return;
    scrollToElement(radio.closest("li") || radio);
    focusField(radio);
  }

  function validatePaymentChoices() {
    if (!hasShippingMethodSelected()) {
      var shippingError = showMethodError("[data-jluxe-shipping-error]", true);
      focusFirstMethod(form.querySelector("#jluxe-shipping-section"), "input.shipping_method:not(:disabled)");
      if (!form.querySelector("#jluxe-shipping-section input.shipping_method:not(:disabled)")) scrollToElement(shippingError || form.querySelector("#jluxe-shipping-section"));
      return false;
    }
    showMethodError("[data-jluxe-shipping-error]", false);

    if (!hasPaymentMethodSelected()) {
      var paymentError = showMethodError("[data-jluxe-payment-error]", true);
      focusFirstMethod(form.querySelector("#payment"), 'input.payment_method:not(:disabled), input[name="payment_method"]:not(:disabled)');
      if (!form.querySelector('#payment input.payment_method:not(:disabled), #payment input[name="payment_method"]:not(:disabled)')) scrollToElement(paymentError || form.querySelector("#payment"));
      return false;
    }
    showMethodError("[data-jluxe-payment-error]", false);
    return true;
  }

  function fieldValue(name) {
    if (!form || !name) return "";
    var field = form.querySelector('[name="' + name + '"]');
    if (!field || field.disabled) return "";
    if (field.tagName === "SELECT") {
      var option = field.options[field.selectedIndex];
      return field.value && option ? String(option.textContent || option.label || "").trim() : "";
    }
    return String(field.value == null ? "" : field.value).trim();
  }

  function usesDifferentShippingAddress() {
    var choice = form.querySelector('input[name="ship_to_different_address"], #ship-to-different-address-checkbox');
    return !!(choice && choice.checked);
  }

  function refreshShippingSummary() {
    if (!shippingSummary) return;
    var prefix = usesDifferentShippingAddress() ? "shipping" : "billing";
    var firstName = fieldValue(prefix + "_first_name");
    var lastName = fieldValue(prefix + "_last_name");
    var company = fieldValue(prefix + "_company");
    var name = [firstName, lastName].filter(Boolean).join(" ");
    var addressParts = [
      fieldValue(prefix + "_address_1"),
      fieldValue(prefix + "_address_2"),
      [fieldValue(prefix + "_city"), fieldValue(prefix + "_state")].filter(Boolean).join("، "),
    ].filter(Boolean);
    var postcode = fieldValue(prefix + "_postcode");
    var country = fieldValue(prefix + "_country");
    if (postcode) addressParts.push("کد پستی " + postcode);
    if (country) addressParts.push(country);

    var nameNode = shippingSummary.querySelector("[data-guided-shipping-name]");
    var addressNode = shippingSummary.querySelector("[data-guided-shipping-address]");
    var companyNode = shippingSummary.querySelector("[data-guided-shipping-company]");
    var phoneNode = shippingSummary.querySelector("[data-guided-shipping-phone]");
    if (nameNode) nameNode.textContent = name || "نام گیرنده";
    if (addressNode) addressNode.textContent = addressParts.join("، ") || "نشانی پس از تکمیل اطلاعات نمایش داده می‌شود.";
    if (companyNode) {
      companyNode.textContent = company;
      companyNode.hidden = !company;
    }
    if (phoneNode) {
      var phone = prefix === "shipping" ? (fieldValue("shipping_phone") || fieldValue("billing_phone")) : fieldValue("billing_phone");
      phoneNode.textContent = phone ? "شماره تماس: " + phone : "";
      phoneNode.hidden = !phone;
    }
  }

  function setCouponStatus(message, isError) {
    if (!couponStatus) return;
    couponStatus.textContent = message;
    couponStatus.hidden = !message;
    if (message) couponStatus.setAttribute("data-status", isError ? "error" : "success");
    else couponStatus.removeAttribute("data-status");
  }

  function normalizedCouponCode(code) {
    return String(code == null ? "" : code).trim().toLocaleLowerCase();
  }

  function finishCouponRequest() {
    if (couponRequestTimer !== null) window.clearTimeout(couponRequestTimer);
    couponRequestTimer = null;
    pendingCoupon = "";
    couponErrorForPending = false;
    if (couponInput) couponInput.disabled = false;
    if (couponButton) {
      couponButton.disabled = false;
      couponButton.removeAttribute("aria-busy");
    }
  }

  function applyGuidedCoupon(event) {
    if (event) event.preventDefault();
    if (!couponInput) return;
    var code = String(couponInput.value || "").trim();
    if (!code) {
      setCouponStatus("کد تخفیف را وارد کنید.", true);
      if (typeof couponInput.focus === "function") couponInput.focus();
      return;
    }
    if (pendingCoupon) return;
    if (!$ || !nativeCouponForm || !nativeCouponInput) {
      setCouponStatus("در حال حاضر امکان اعمال کد تخفیف وجود ندارد.", true);
      return;
    }

    pendingCoupon = normalizedCouponCode(code);
    couponErrorForPending = false;
    nativeCouponInput.value = code;
    couponInput.disabled = true;
    if (couponButton) {
      couponButton.disabled = true;
      couponButton.setAttribute("aria-busy", "true");
    }
    setCouponStatus("در حال بررسی کد تخفیف…", false);
    couponRequestTimer = window.setTimeout(function () {
      if (!pendingCoupon) return;
      setCouponStatus("پاسخی دریافت نشد؛ لطفاً دوباره تلاش کنید.", true);
      finishCouponRequest();
    }, 30000);
    $(nativeCouponForm).trigger("submit");
  }

  function updateStickyTotal() {
    var source = form.querySelector("#order_review [data-jluxe-order-total-value]");
    var target = stickyAction && stickyAction.querySelector("[data-guided-total]");
    if (source && target) target.innerHTML = source.innerHTML;
  }

  function updateStickyAction() {
    actionUpdatePending = false;
    if (!stickyButton) return;
    var isPaymentStep = currentStep === "payment";
    if (stickyLabel) stickyLabel.textContent = isPaymentStep ? "ثبت سفارش" : "ادامه به پرداخت";
    stickyButton.setAttribute("aria-label", isPaymentStep ? "ثبت سفارش" : "ادامه به شیوهٔ پرداخت");
    if (stickyBack) stickyBack.hidden = !isPaymentStep;
    if (stickyAction) stickyAction.setAttribute("data-guided-mobile-step", currentStep);
  }

  function scheduleStickyActionUpdate() {
    if (actionUpdatePending) return;
    actionUpdatePending = true;
    requestFrame(updateStickyAction);
  }

  function updateStepper() {
    if (!stepper) return;
    stepper.setAttribute("data-guided-current-step", currentStep);
    var items = stepper.querySelectorAll("[data-guided-step-item]");
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var id = item.getAttribute("data-guided-step-item");
      var button = item.querySelector("[data-guided-step-target]");
      var marker = item.querySelector("[data-guided-step-marker]");
      var isCurrent = id === currentStep;
      var isComplete = id === "shipping" && currentStep === "payment";
      var state = isCurrent ? "current" : (isComplete ? "complete" : "upcoming");
      item.setAttribute("data-guided-step-state", state);
      if (button) {
        button.disabled = id === "payment" && currentStep === "shipping";
        button.setAttribute("aria-disabled", button.disabled ? "true" : "false");
        if (isCurrent) button.setAttribute("aria-current", "step");
        else button.removeAttribute("aria-current");
      }
      if (marker) {
        if (isComplete) {
          marker.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        } else {
          marker.textContent = id === "shipping" ? "۱" : "۲";
        }
      }
    }
  }

  function focusStepHeading(step) {
    var panel = step === "payment" ? paymentPanel : shippingPanel;
    var heading = panel && panel.querySelector("h2");
    if (!heading) return;
    scrollToElement(heading);
    focusElement(heading);
    window.setTimeout(updateKeyboardViewport, 80);
  }

  function refreshCheckoutTotals() {
    if (!$ || !body) return;
    // Refresh address-dependent shipping/tax totals before the customer chooses
    // a rate or gateway. WooCommerce owns the request and replaces its fragments.
    $(body).trigger("update_checkout");
  }

  function setCurrentStep(step, options) {
    options = options || {};
    if (step !== "shipping" && step !== "payment") return;
    var changed = currentStep !== step;
    currentStep = step;
    form.setAttribute("data-guided-current-step", step);
    if (shippingPanel) {
      shippingPanel.hidden = step !== "shipping";
      shippingPanel.setAttribute("aria-hidden", step !== "shipping" ? "true" : "false");
    }
    if (paymentPanel) {
      paymentPanel.hidden = step !== "payment";
      paymentPanel.setAttribute("aria-hidden", step !== "payment" ? "true" : "false");
    }
    if (beforeFormNotices) {
      beforeFormNotices.hidden = step !== "shipping";
      beforeFormNotices.setAttribute("aria-hidden", step !== "shipping" ? "true" : "false");
    }
    if (stepStatus) {
      stepStatus.textContent = step === "payment" ? "مرحلهٔ ۲ از ۲: شیوهٔ پرداخت" : "مرحلهٔ ۱ از ۲: اطلاعات ارسال";
    }
    updateStepper();
    updateStickyAction();
    refreshShippingSummary();
    updateStickyTotal();
    updateKeyboardViewport();

    if (options.focus) focusStepHeading(step);
    if (step === "payment" && changed && options.refresh !== false) refreshCheckoutTotals();
  }

  function advanceToPayment() {
    var invalid = findFirstInvalidRequiredField();
    if (invalid) {
      setShippingErrorSummary(true);
      focusInvalidField(invalid);
      return false;
    }
    setShippingErrorSummary(false);
    setCurrentStep("payment", { focus: true, refresh: true });
    return true;
  }

  function returnToShipping() {
    setCurrentStep("shipping", { focus: true, refresh: false });
  }

  function submitOrder() {
    if (currentStep === "shipping") {
      advanceToPayment();
      return;
    }
    if (!validatePaymentChoices()) return;
    var placeOrder = form.querySelector("#place_order");
    if (placeOrder && !placeOrder.disabled) placeOrder.click();
  }

  function handleFormSubmit(event) {
    if (currentStep === "shipping") {
      event.preventDefault();
      event.stopImmediatePropagation();
      advanceToPayment();
      return;
    }
    if (!validatePaymentChoices()) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }

  function handleCheckoutError() {
    window.setTimeout(function () {
      var invalidRows = customerDetails ? customerDetails.querySelectorAll(".woocommerce-invalid") : [];
      var firstInvalid = invalidRows.length ? invalidRows[0] : null;
      if (firstInvalid) {
        setCurrentStep("shipping", { focus: false, refresh: false });
        var field = firstInvalid.querySelector("input:not([type=hidden]), select, textarea");
        if (field) {
          setShippingErrorSummary(true);
          scrollToElement(firstInvalid);
          focusField(field);
        }
        return;
      }
      setCurrentStep("payment", { focus: false, refresh: false });
      var notice = form.querySelector(".woocommerce-NoticeGroup-checkout .woocommerce-error, .woocommerce-error[role=alert]");
      if (notice) scrollToElement(notice);
    }, 0);
  }

  function handleFieldChange(event) {
    var target = event.target;
    if (!target) return;
    var row = target.closest ? target.closest(".form-row") : null;
    if (row && customerDetails && customerDetails.contains(row)) {
      var invalid = validateFieldRow(row);
      if (!invalid && !visibleInvalidCustomerRow()) setShippingErrorSummary(false);
      refreshShippingSummary();
    }
    if (target.matches && target.matches("input.shipping_method")) showMethodError("[data-jluxe-shipping-error]", false);
    if (target.matches && target.matches('input.payment_method, input[name="payment_method"]')) showMethodError("[data-jluxe-payment-error]", false);
    if (target.name === "ship_to_different_address") refreshShippingSummary();
    scheduleStickyActionUpdate();
  }

  if (stickyAction) {
    // Move the fixed mobile action outside transformed theme wrappers; its
    // proxy still submits through WooCommerce's real #place_order control.
    if (body && stickyAction.parentNode !== body) body.appendChild(stickyAction);
    stickyAction.hidden = false;
    body.classList.add("jluxe-guided-checkout-active");
    form.classList.add("jluxe-guided-checkout--enhanced");
    if (stickyButton) stickyButton.addEventListener("click", submitOrder);
    if (stickyBack) stickyBack.addEventListener("click", returnToShipping);
  }

  if (couponButton) couponButton.addEventListener("click", applyGuidedCoupon);
  if (couponInput) {
    couponInput.addEventListener("keydown", function (event) {
      if (event.key === "Enter") applyGuidedCoupon(event);
    });
  }

  form.addEventListener("submit", handleFormSubmit, true);
  form.addEventListener("click", function (event) {
    var target = event.target && event.target.closest ? event.target.closest("button") : null;
    if (!target || !form.contains(target)) return;
    if (target.matches("[data-guided-next]")) {
      event.preventDefault();
      advanceToPayment();
      return;
    }
    if (target.matches("[data-guided-back], [data-guided-edit-shipping]")) {
      event.preventDefault();
      returnToShipping();
      return;
    }
    if (target.matches("[data-guided-step-target=shipping]")) {
      if (currentStep === "payment") {
        event.preventDefault();
        returnToShipping();
      }
      return;
    }
    if (target.matches("[data-guided-step-target=payment]")) {
      event.preventDefault();
      if (currentStep === "shipping") advanceToPayment();
    }
  });

  form.addEventListener("focusin", function (event) {
    if (!isTextEntryElement(event.target)) return;
    updateKeyboardViewport();
    window.setTimeout(updateKeyboardViewport, 80);
    scheduleFieldVisibility();
  });

  form.addEventListener("focusout", function () {
    window.setTimeout(updateKeyboardViewport, 120);
  });

  form.addEventListener("input", handleFieldChange);
  form.addEventListener("change", handleFieldChange);

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

  if ($ && body) {
    $(body).on("updated_checkout.jluxeGuided", function () {
      suppressDuplicateCheckoutNotices();
      refreshShippingSummary();
      updateStickyTotal();
      if (hasShippingMethodSelected()) showMethodError("[data-jluxe-shipping-error]", false);
      scheduleStickyActionUpdate();
    });
    $(body).on("checkout_error.jluxeGuided", handleCheckoutError);
    if (couponWidget) {
      $(body).on("errored_coupon_in_checkout.jluxeGuided", function (_event, code) {
        if (!pendingCoupon || normalizedCouponCode(code) !== pendingCoupon) return;
        couponErrorForPending = true;
        setCouponStatus("این کد تخفیف قابل اعمال نیست؛ کد را بررسی کنید.", true);
      });
      $(body).on("applied_coupon_in_checkout.jluxeGuided", function (_event, code) {
        if (!pendingCoupon || normalizedCouponCode(code) !== pendingCoupon) return;
        if (couponErrorForPending) {
          setCouponStatus("این کد تخفیف قابل اعمال نیست؛ کد را بررسی کنید.", true);
        } else {
          couponInput.value = "";
          setCouponStatus("کد تخفیف با موفقیت اعمال شد.", false);
        }
        finishCouponRequest();
      });
      $(body).on("ajaxError.jluxeGuidedCoupon", function (_event, _xhr, settings) {
        if (!pendingCoupon || !settings || String(settings.url || "").indexOf("apply_coupon") === -1) return;
        setCouponStatus("خطا در ارتباط؛ لطفاً دوباره تلاش کنید.", true);
        finishCouponRequest();
      });
    }
  }

  suppressDuplicateCheckoutNotices();
  updateStepper();
  updateStickyTotal();
  refreshShippingSummary();
  updateKeyboardViewport();
  updateStickyAction();
})();
