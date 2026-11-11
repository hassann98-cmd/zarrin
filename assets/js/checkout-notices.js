/* Shared checkout notice suppression and the classic layout's payment-step coupon control. */
(function () {
  "use strict";

  var body = document.body;
  if (!body || !body.classList.contains("woocommerce-checkout")) return;

  var $ = window.jQuery || null;

  function hideRepeatedCheckoutNotice(element) {
    if (!element) return;
    element.classList.add("jluxe-checkout-suppressed-notice");
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

  if ($) $(body).on("updated_checkout.jluxeCheckoutNotices", suppressDuplicateCheckoutNotices);
  suppressDuplicateCheckoutNotices();

  // The guided layout already has its own accessible coupon control. The
  // classic checkout gets an equivalent proxy in its existing payment step.
  var checkoutForm = document.querySelector('form.checkout:not([data-jluxe-checkout-flow="guided"])');
  var couponWidget = checkoutForm && checkoutForm.querySelector("[data-classic-checkout-coupon]");
  if (!couponWidget) return;

  var couponInput = couponWidget.querySelector("[data-classic-checkout-coupon-input]");
  var couponButton = couponWidget.querySelector("[data-classic-checkout-coupon-apply]");
  var couponStatus = couponWidget.querySelector("[data-classic-checkout-coupon-status]");
  var nativeCouponForm = document.querySelector("form.checkout_coupon");
  var nativeCouponInput = nativeCouponForm && nativeCouponForm.querySelector('input[name="coupon_code"]');
  var pendingCoupon = "";
  var couponErrorForPending = false;
  var couponRequestTimer = null;

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

  function eventMatchesPendingCoupon(code) {
    return !code || normalizedCouponCode(code) === pendingCoupon;
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

  function applyCoupon(event) {
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

  if (couponButton) couponButton.addEventListener("click", applyCoupon);
  if (couponInput) {
    couponInput.addEventListener("keydown", function (event) {
      if (event.key !== "Enter") return;
      event.preventDefault();
      event.stopPropagation();
      applyCoupon(event);
    });
  }

  if ($) {
    $(body).on("errored_coupon_in_checkout.jluxeClassicCoupon", function (_event, code) {
      if (!pendingCoupon || !eventMatchesPendingCoupon(code)) return;
      couponErrorForPending = true;
      setCouponStatus("این کد تخفیف قابل اعمال نیست؛ کد را بررسی کنید.", true);
    });
    $(body).on("applied_coupon_in_checkout.jluxeClassicCoupon", function (_event, code) {
      if (!pendingCoupon || !eventMatchesPendingCoupon(code)) return;
      if (couponErrorForPending) {
        setCouponStatus("این کد تخفیف قابل اعمال نیست؛ کد را بررسی کنید.", true);
      } else {
        couponInput.value = "";
        setCouponStatus("کد تخفیف با موفقیت اعمال شد.", false);
      }
      finishCouponRequest();
    });
    $(body).on("ajaxError.jluxeClassicCoupon", function (_event, _xhr, settings) {
      if (!pendingCoupon || !settings || String(settings.url || "").indexOf("apply_coupon") === -1) return;
      setCouponStatus("خطا در ارتباط؛ لطفاً دوباره تلاش کنید.", true);
      finishCouponRequest();
    });
  }
})();
