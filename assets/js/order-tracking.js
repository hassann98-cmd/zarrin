(function () {
  "use strict";

  var CONTAINER_ID = "jluxe-track-order-app";
  var config = window.JLuxeOrderTracking || {};
  var API_ENDPOINT = config.endpoint;
  var STORE_URL = config.storeUrl || "./";
  var requestVersion = 0;

  var CARRIERS = {
    پست: { url: "https://tracking.post.ir/?id=%CODE%", color: "#fcba24" },
    تیپاکس: {
      url: "https://tipaxco.com/tracking?code=%CODE%",
      color: "#0ea5e9",
    },
    چاپار: { url: "https://www.chapar.io/tracking/%CODE%", color: "#16a34a" },
  };

  function init() {
    var container = document.getElementById(CONTAINER_ID);
    if (!container) {
      return;
    }
    if (container.getAttribute("data-jto-init") === "1") {
      return;
    }
    container.setAttribute("data-jto-init", "1");
    // Remove the pre-1.55.2 persistent history; do not store order/phone data.
    try {
      window.localStorage.removeItem("jluxe_track_last_order_number");
    } catch (error) {}
    renderSearchView(container);
    window.addEventListener("pagehide", function () {
      requestVersion += 1;
      renderSearchView(container);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  function toPersianDigits(input) {
    var fa = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
    return String(input).replace(/[0-9]/g, function (d) {
      return fa[d];
    });
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) {
      return "";
    }
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function qs(root, selector) {
    return root.querySelector(selector);
  }

  function qsa(root, selector) {
    return Array.prototype.slice.call(root.querySelectorAll(selector));
  }

  function renderSearchView(container) {
    container.innerHTML =
      '<div id="jto-search-wrap">' +
      '  <div class="jto-outer-card jto-animate-in w-full bg-white dark:bg-gray-900 shadow-lg dark:shadow-gray-900/50 rounded-lg p-6">' +
      '    <h2 class="font-bold text-xl mb-3">پیگیری سفارش</h2>' +
      '    <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">شماره سفارش و شماره موبایل ثبت‌شده در سفارش را وارد کنید</p>' +
      '    <form id="jto-search-form" novalidate>' +
      '      <div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl jto-field-box">' +
      '        <label for="jto-order-number" class="jto-field-label text-sm text-gray-600 dark:text-gray-300">' +
      '          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6"/></svg>' +
      "          شماره سفارش" +
      "        </label>" +
      '        <input class="jto-input jto-input-numeric w-full bg-transparent mt-1 font-bold" type="text" inputmode="numeric" dir="ltr" id="jto-order-number" placeholder="مثلاً 1024" autocomplete="off" maxlength="100" required />' +
      "      </div>" +
      '      <div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl jto-field-box">' +
      '        <label for="jto-phone" class="jto-field-label text-sm text-gray-600 dark:text-gray-300">' +
      '          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>' +
      "          شماره موبایل" +
      "        </label>" +
      '        <input class="jto-input jto-input-numeric w-full bg-transparent mt-1 font-bold" type="tel" inputmode="numeric" dir="ltr" id="jto-phone" maxlength="64" placeholder="09xxxxxxxxx" autocomplete="off" required />' +
      "      </div>" +
      '      <div id="jto-field-error" role="alert" class="jto-field-error mb-3" style="display:none;"></div>' +
      '      <button type="submit" class="w-full bg-primary text-white rounded-xl p-3 font-bold flex items-center justify-center gap-2" id="jto-submit-btn">' +
      "        <span>پیگیری سفارش</span>" +
      "      </button>" +
      "    </form>" +
      "  </div>" +
      "</div>" +
      '<div id="jto-result-area" aria-live="polite" class="mt-3"></div>';

    qs(container, "#jto-search-form").addEventListener("submit", function (e) {
      e.preventDefault();
      handleSearchSubmit(container);
    });

    var orderNumberField = qs(container, "#jto-order-number");
    if (orderNumberField) orderNumberField.focus();
  }

  function handleSearchSubmit(container) {
    var orderNumberInput = qs(container, "#jto-order-number");
    var phoneInput = qs(container, "#jto-phone");
    var submitBtn = qs(container, "#jto-submit-btn");
    if (submitBtn.disabled) return;
    var resultArea = qs(container, "#jto-result-area");
    var fieldError = qs(container, "#jto-field-error");

    var orderNumber = JLuxeStorefrontUtils.normalizeDigits(
      orderNumberInput.value || "",
    ).trim();
    var phone = JLuxeStorefrontUtils.normalizePhone(phoneInput.value || "");

    if (fieldError) {
      fieldError.style.display = "none";
      fieldError.innerHTML = "";
    }

    if (!orderNumber) {
      showFieldError(fieldError, "لطفاً شماره سفارش را وارد کنید.");
      orderNumberInput.focus();
      return;
    }
    if (!phone) {
      showFieldError(
        fieldError,
        "شماره موبایل واردشده معتبر نیست؛ لطفاً دوباره بررسی کنید.",
      );
      phoneInput.focus();
      return;
    }

    setButtonLoading(submitBtn, true);
    renderSkeletonLoader(resultArea);

    var version = ++requestVersion;
    var url = new URL(API_ENDPOINT, window.location.href);

    // Fetch a fresh nonce instead of embedding a customer's identity in cached HTML.
    fetch(config.sessionUrl, {
      method: "POST",
      cache: "no-store",
      credentials: "same-origin",
      body: new URLSearchParams({ action: "jluxe_session" }),
    })
      .then(function (response) {
        if (!response.ok) throw new Error("Session unavailable");
        return response.json();
      })
      .then(function (session) {
        if (version !== requestVersion || !session.success)
          throw new Error("Session unavailable");
        var headers = {
          Accept: "application/json",
          "Content-Type": "application/json",
        };
        if (session.data && session.data.restNonce)
          headers["X-WP-Nonce"] = session.data.restNonce;
        return fetch(url.toString(), {
          method: "POST",
          cache: "no-store",
          credentials: "same-origin",
          headers: headers,
          body: JSON.stringify({ order_number: orderNumber, phone: phone }),
        });
      })
      .then(function (response) {
        return response.json().then(function (json) {
          return { ok: response.ok, status: response.status, body: json };
        });
      })
      .then(function (result) {
        if (version !== requestVersion) return;
        setButtonLoading(submitBtn, false);

        if (!result.ok || !result.body || result.body.success !== true) {
          var message =
            (result.body && result.body.message) ||
            "سفارشی با این مشخصات یافت نشد. لطفاً اطلاعات وارد شده را بررسی کنید.";
          var type =
            result.status === 429
              ? "ratelimit"
              : result.status === 404
                ? "notfound"
                : "generic";
          renderErrorMessage(resultArea, message, type, container);
          return;
        }

        renderOrderResult(container, result.body.data);
      })
      .catch(function () {
        if (version !== requestVersion) return;
        setButtonLoading(submitBtn, false);
        renderErrorMessage(
          resultArea,
          "خطا در برقراری ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی و دوباره تلاش کنید.",
          "network",
          container,
        );
      });
  }

  function showFieldError(fieldErrorEl, message) {
    if (!fieldErrorEl) {
      return;
    }
    fieldErrorEl.innerHTML =
      '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>' +
      "<span>" +
      escapeHtml(message) +
      "</span>";
    fieldErrorEl.style.display = "flex";
  }

  function setButtonLoading(btn, isLoading) {
    if (isLoading) {
      btn.disabled = true;
      btn.innerHTML =
        '<span class="jto-spinner"></span><span>در حال جست‌وجو...</span>';
    } else {
      btn.disabled = false;
      btn.innerHTML = "<span>پیگیری سفارش</span>";
    }
  }

  function renderSkeletonLoader(resultArea) {
    resultArea.innerHTML =
      '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">' +
      '  <div class="jto-skel h-4 w-2/5 mb-2"></div>' +
      '  <div class="jto-skel h-3 w-3/5"></div>' +
      "</div>" +
      '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">' +
      '  <div class="jto-skel h-4 w-1/3"></div>' +
      "</div>";
  }

  function renderErrorMessage(resultArea, message, type, container) {
    var icons = {
      notfound: "🔍",
      ratelimit: "⏳",
      network: "📡",
      generic: "⚠️",
    };
    var icon = icons[type] || icons.generic;
    var circleClass = "jto-alert-icon-circle jto-alert-" + (type || "generic");

    resultArea.innerHTML =
      '<div class="jto-animate-in bg-[#f7f8fa] dark:bg-gray-800 p-6 rounded-xl text-center">' +
      '  <div class="' +
      circleClass +
      '">' +
      icon +
      "</div>" +
      '  <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">' +
      escapeHtml(message) +
      "</p>" +
      '  <div class="flex items-center justify-center gap-5">' +
      (container
        ? '    <button type="button" id="jto-retry-btn" class="text-sm text-primary font-bold">تلاش مجدد</button>'
        : "") +
      '    <a href="' +
      escapeHtml(STORE_URL) +
      '" class="text-sm text-gray-500 dark:text-gray-400">بازگشت به فروشگاه</a>' +
      "  </div>" +
      "</div>";

    if (container) {
      var retryBtn = qs(resultArea, "#jto-retry-btn");
      if (retryBtn) {
        retryBtn.addEventListener("click", function () {
          var phoneField = qs(container, "#jto-phone");
          resultArea.innerHTML = "";
          if (phoneField) {
            phoneField.focus();
          }
        });
      }
    }
  }

  function renderOrderResult(container, data) {
    var order = data.order || {};
    var hasDetails = data.access === "owner";
    var customer = hasDetails ? data.customer || {} : {};
    var shipping = hasDetails ? data.shipping || {} : {};
    var items = hasDetails ? data.items || [] : [];
    var timeline = data.timeline || {
      steps: [],
      is_cancelled: false,
      current_step: 1,
    };

    var jalaliDate = (order.created_date && order.created_date.jalali) || "";
    var totalFormatted = (order.total && order.total.formatted) || "";
    var percent = Math.round(((timeline.current_step || 1) / 6) * 100);
    var isCancelled = !!timeline.is_cancelled;

    var html =
      '<div class="jto-outer-card jto-animate-in w-full bg-white dark:bg-gray-900 shadow-lg dark:shadow-gray-900/50 rounded-lg p-6">';

    html +=
      '<button type="button" id="jto-back-to-search" class="mb-4 text-sm text-primary">بازگشت</button>';
    html +=
      '<h2 class="font-bold text-xl mb-3">سفارش #' +
      escapeHtml(toPersianDigits(order.order_number || "")) +
      "</h2>";

    html += '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">';
    html += '  <div class="flex flex-col gap-2 w-full">';
    html += '    <div class="jto-row-wrap">';
    html +=
      '      <span class="text-medium">' +
      escapeHtml(
        isCancelled
          ? timeline.cancel_label || order.status_label
          : order.status_label,
      ) +
      "</span>";
    html +=
      '      <span class="text-sm text-gray-600 dark:text-gray-300">' +
      escapeHtml(toPersianDigits(jalaliDate)) +
      "</span>";
    html += "    </div>";
    if (!isCancelled) {
      html +=
        '    <div class="bg-default-300/50 overflow-hidden h-3 rounded-full">';
      html +=
        '      <div class="h-full bg-primary rounded-full" style="width:' +
        percent +
        '%;transition:width .5s;"></div>';
      html += "    </div>";
    }
    html += "  </div>";
    html += "</div>";

    if (!hasDetails) {
      html +=
        '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">' +
        "<p>برای حفظ حریم خصوصی، فقط وضعیت سفارش نمایش داده می‌شود. برای جزئیات و کد رهگیری، وارد حساب ثبت‌کنندهٔ سفارش شوید. اگر مهمان خرید کرده‌اید، با پشتیبانی فروشگاه تماس بگیرید.</p>" +
        '<a class="text-primary font-bold" href="' +
        escapeHtml(config.accountUrl || STORE_URL) +
        '">ورود به حساب کاربری</a></div>';
    }
    if (hasDetails) {
      html +=
        '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">مبلغ کل: <span class="font-bold">' +
        escapeHtml(totalFormatted) +
        "</span></div>";

      if (order.payment_method) {
        html +=
          '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">روش پرداخت: ' +
          escapeHtml(toPersianDigits(order.payment_method)) +
          "</div>";
      }
    }

    if (shipping.tracking_code) {
      html += '<div class="mb-3 bg-primary-200 p-3 rounded-xl jto-row-wrap">';
      html +=
        '  <span class="jto-tracking-code-text">کد رهگیری: <span class="font-bold">' +
        escapeHtml(shipping.tracking_code) +
        "</span></span>";
      html +=
        '  <button type="button" class="text-xs font-bold" data-copy="' +
        escapeHtml(shipping.tracking_code) +
        '">کپی</button>';
      html += "</div>";

      var carrier = getCarrierInfo(
        shipping.shipping_company,
        shipping.tracking_code,
      );
      if (carrier) {
        html +=
          '<a href="' +
          escapeHtml(carrier.url) +
          '" target="_blank" rel="noopener noreferrer" ' +
          'class="mb-3 flex items-center gap-3 rounded-[12px] p-4 hover:opacity-90 transition-colors cursor-pointer" ' +
          'style="background-color:' +
          escapeHtml(carrier.color) +
          ';">' +
          '  <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-[10px]">' +
          '    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 100-4 2 2 0 000 4zM18 20a2 2 0 100-4 2 2 0 000 4z"/></svg>' +
          "  </div>" +
          '  <div class="flex flex-col flex-1">' +
          '    <span class="text-[14px] font-bold text-white">پیگیری سفارش</span>' +
          '    <span class="text-[12px] text-white">پیگیری مرسوله از طریق ' +
          escapeHtml(toPersianDigits(shipping.shipping_company || "شرکت حمل")) +
          "</span>" +
          "  </div>" +
          "</a>";
      }
    }

    if (hasDetails) {
      html +=
        '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">نام: ' +
        escapeHtml(customer.full_name || "-") +
        "</div>";
      if (customer.address) {
        html +=
          '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">آدرس: ' +
          escapeHtml(toPersianDigits(customer.address)) +
          "</div>";
      }
      if (customer.phone) {
        html +=
          '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">تلفن: <a href="tel:' +
          escapeHtml(customer.phone) +
          '" class="text-primary font-bold">' +
          escapeHtml(toPersianDigits(customer.phone)) +
          "</a></div>";
      }
    }

    if (items.length) {
      html += '<h3 class="font-bold mt-4 mb-3">محصولات سفارش</h3>';
      html += '<ul class="space-y-2">';
      items.forEach(function (item) {
        var thumbUrl = (item.image && item.image.thumbnail) || "";
        var itemSubtotal = (item.subtotal && item.subtotal.formatted) || "";
        var attrsText = "";
        if (item.attributes && item.attributes.length) {
          attrsText = item.attributes
            .map(function (a) {
              return toPersianDigits(a.name) + ": " + toPersianDigits(a.value);
            })
            .join(" | ");
        }

        html +=
          '<li class="border dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-800 p-5">';
        html += '  <div class="flex gap-3 mb-3">';
        if (thumbUrl) {
          html +=
            '    <img src="' +
            escapeHtml(thumbUrl) +
            '" alt="' +
            escapeHtml(item.product_name) +
            '" class="jto-item-image" style="width:56px;height:56px;border-radius:12px;object-fit:cover;flex-shrink:0;" />';
        }
        html += '    <div class="flex-1">';
        html +=
          '      <span class="text-sm text-gray-600 dark:text-gray-300">نام محصول: </span>';
        html +=
          '      <div class="font-bold text-base mt-1">' +
          escapeHtml(toPersianDigits(item.product_name)) +
          "</div>";
        if (attrsText) {
          html +=
            '      <div class="jto-chip mt-1">' +
            escapeHtml(attrsText) +
            "</div>";
        }
        html += "    </div>";
        html += "  </div>";
        html += '  <div class="jto-row-wrap text-sm">';
        html +=
          '    <span>تعداد: <span class="font-medium">' +
          escapeHtml(toPersianDigits(item.quantity)) +
          "</span></span>";
        html +=
          '    <span>قیمت: <span class="font-bold text-primary">' +
          escapeHtml(itemSubtotal) +
          "</span></span>";
        html += "  </div>";
        html += "</li>";
      });
      html += "</ul>";
    }

    html += "</div>";

    var resultArea = qs(container, "#jto-result-area");
    resultArea.innerHTML = html;

    var searchWrap = qs(container, "#jto-search-wrap");
    if (searchWrap) {
      searchWrap.style.display = "none";
    }

    qs(resultArea, "#jto-back-to-search").addEventListener(
      "click",
      function () {
        resultArea.innerHTML = "";
        if (searchWrap) {
          searchWrap.style.display = "";
        }
      },
    );

    bindResultEvents(resultArea);
  }

  function getCarrierInfo(companyName, trackingCode) {
    if (!companyName || !trackingCode) {
      return null;
    }
    var carrier = CARRIERS[companyName.trim()];
    if (!carrier) {
      return null;
    }
    return {
      url: carrier.url.replace("%CODE%", encodeURIComponent(trackingCode)),
      color: carrier.color || "#7c3aed",
    };
  }

  function bindResultEvents(resultArea) {
    qsa(resultArea, ".jto-item-image").forEach(function (img) {
      img.addEventListener("error", function () {
        img.style.display = "none";
      });
    });

    qsa(resultArea, "[data-copy]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var text = btn.getAttribute("data-copy");
        copyToClipboard(text)
          .then(function () {
            var original = btn.textContent;
            btn.textContent = "کپی شد ✓";
            setTimeout(function () {
              btn.textContent = original;
            }, 1800);
          })
          .catch(function () {});
      });
    });
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      try {
        var textarea = document.createElement("textarea");
        textarea.value = text;
        textarea.style.position = "fixed";
        textarea.style.opacity = "0";
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
        resolve();
      } catch (err) {
        reject(err);
      }
    });
  }
})();
