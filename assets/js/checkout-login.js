/* تسویه‌حساب اصلی: انتخاب آدرس ذخیره‌شده + آکاردئون آدرس جدید با ذخیره در همان صفحه.
   استان و شهر به‌صورت لیست انتخابی با جستجو (سبک iPhone) هستند؛ شهر بعد از
   انتخاب استان لود می‌شود. مشخصات (نام/نام خانوادگی/تلفن) از حساب خوانده شده
   و در صورت نیاز قابل ویرایش است؛ اولویت با شمارهٔ واردشده در OTP است.
   بدون جاوااسکریپت، فرم تسویه‌حساب از قبل با آدرس ذخیره‌شده پر است. */
(function () {
  "use strict";

  function field(id) {
    return document.getElementById(id);
  }

  function setField(id, value) {
    var el = field(id);
    if (!el) return;
    el.value = value == null ? "" : String(value);
    el.dispatchEvent(new Event("change", { bubbles: true }));
  }

  /* اعمال استان/شهر آدرس ذخیره‌شده روی فیلدهای واقعی ووکامرس.
     تغییر برنامه‌نویسی‌شده، cascade استان→شهر (تم/پلاگین) را طوری فعال
     می‌کند که لیست شهر پاک می‌شود؛ پس مثل انتخاب واقعی کاربر رفتار می‌کنیم:
     select2:select + change روی استان، بعد شهر وقتی گزینه‌اش ساخته شد. */
  function applyStateCity(state, city) {
    var stateEl = field("billing_state");
    var cityEl = field("billing_city");
    if (!stateEl) return;
    var stateValue = state == null ? "" : String(state);
    var cityValue = city == null ? "" : String(city);
    stateEl.value = stateValue;
    if (window.jQuery) {
      window.jQuery(stateEl).trigger("select2:select");
      window.jQuery(stateEl).trigger("change");
    } else {
      stateEl.dispatchEvent(new Event("change", { bubbles: true }));
    }
    if (!cityEl) return;
    var attempts = 0;
    function setCity() {
      attempts += 1;
      var hasOption = Array.prototype.some.call(cityEl.options, function (opt) {
        return opt.value === cityValue;
      });
      if (hasOption || attempts > 40) {
        cityEl.value = cityValue;
        if (window.jQuery) {
          window.jQuery(cityEl).trigger("change");
        } else {
          cityEl.dispatchEvent(new Event("change", { bubbles: true }));
        }
        return;
      }
      window.setTimeout(setCity, 50);
    }
    window.setTimeout(setCity, 0);
  }

  function applyAddress(address, prefix) {
    if (!address) return;
    setField(prefix + "_first_name", address.first_name);
    setField(prefix + "_last_name", address.last_name);
    setField(prefix + "_company", address.company);
    setField(prefix + "_address_1", address.address_1);
    setField(prefix + "_address_2", address.address_2);
    if (prefix === "billing") {
      // استان/شهر همان فیلدهای آکاردئون‌اند و cascade مخصوص خودشان را دارند.
      applyStateCity(address.state, address.city);
    } else {
      setField(prefix + "_city", address.city);
      setField(prefix + "_state", address.state);
    }
    setField(prefix + "_postcode", address.postcode);
    setField(prefix + "_country", address.country);
    if (prefix === "billing") {
      setField("billing_phone", address.phone);
      setField("billing_landline", address.landline);
    }
  }

  function init() {
    var selector = document.querySelector("[data-jluxe-address-selector]");
    if (!selector) return;

    var list = selector.querySelector("[data-jluxe-address-list]");
    var toggle = selector.querySelector("[data-jluxe-address-toggle]");
    var form = selector.querySelector("[data-jluxe-address-form]");
    var closeBtn = selector.querySelector("[data-jluxe-address-close]");
    var saveBtn = selector.querySelector("[data-jluxe-address-save]");
    var status = selector.querySelector("[data-jluxe-address-status]");
    var config = window.JLUXE_CHECKOUT_ADDRESS || {};

    /* ---------- همگام‌سازی هویت (نام/نام خانوادگی/تلفن) با فرم تسویه‌حساب ---------- */
    selector.querySelectorAll("[data-jluxe-identity-field]").forEach(function (input) {
      var name = input.getAttribute("data-jluxe-identity-field");
      var targetId =
        name === "first_name"
          ? "billing_first_name"
          : name === "last_name"
            ? "billing_last_name"
            : "billing_phone";
      input.addEventListener("input", function () {
        setField(targetId, input.value);
      });
    });

    /* ---------- استان/شهر: همان فیلدهای واقعی ووکامرس (billing_state/billing_city) ----------
       این فیلدها در آکاردئون رندر می‌شوند (نه در فرم صورتحساب) تا مکانیزم لود
       شهر تم/پلاگین دقیقاً روی همان‌ها کار کند؛ نیازی به همگام‌سازی نیست.
       cascade استان→شهر را خودِ JS تم (woocommerce.js) انجام می‌دهد.
       استان/شهر هیچ‌وقت از کش/نشست قبلی پر نمی‌شوند؛ همیشه خالی شروع می‌شوند. */
    setField("billing_state", "");
    setField("billing_city", "");

    /* ---------- انتخاب آدرس ذخیره‌شده ---------- */
    function cards() {
      return Array.prototype.slice.call(selector.querySelectorAll("[data-jluxe-address-card]"));
    }

    function select(card) {
      cards().forEach(function (c) {
        c.classList.toggle("is-selected", c === card);
      });
      var input = card.querySelector("[data-jluxe-address-option]");
      var value = input ? input.value : "";
      var dataEl = card.querySelector("[data-jluxe-address-data]");
      if (!dataEl) return;
      var address;
      try {
        address = JSON.parse(dataEl.textContent || "{}");
      } catch (e) {
        return;
      }
      if (value === "billing") {
        applyAddress(address, "billing");
        var shipDifferent = field("ship-to-different-address-checkbox");
        if (!shipDifferent || !shipDifferent.checked) {
          applyAddress(address, "shipping");
        }
      } else if (value === "shipping") {
        applyAddress(address, "shipping");
        var shipCheckbox = field("ship-to-different-address-checkbox");
        if (shipCheckbox && !shipCheckbox.checked) {
          shipCheckbox.checked = true;
          shipCheckbox.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    }

    function bindCards() {
      cards().forEach(function (card) {
        var input = card.querySelector("[data-jluxe-address-option]");
        if (input && !input.dataset.jluxeBound) {
          input.dataset.jluxeBound = "1";
          input.addEventListener("change", function () {
            select(card);
          });
        }
        if (!card.dataset.jluxeBound) {
          card.dataset.jluxeBound = "1";
          card.addEventListener("click", function (event) {
            if (event.target.closest("input")) return;
            var opt = card.querySelector("[data-jluxe-address-option]");
            if (opt && !opt.checked) {
              opt.checked = true;
              opt.dispatchEvent(new Event("change", { bubbles: true }));
            }
          });
        }
      });
    }

    function showStatus(message, isError) {
      if (!status) return;
      status.textContent = message;
      status.hidden = !message;
      status.classList.toggle("is-error", !!isError);
    }

    function clearNewForm() {
      if (!form) return;
      // فقط فیلدهای مخصوص آکاردئون پاک می‌شوند؛ استان/شهر (billing_state/
      // billing_city) همان فیلدهای تسویه‌حساب‌اند و دست نمی‌خورند.
      form.querySelectorAll("[data-jluxe-new-field]").forEach(function (el) {
        el.value = "";
      });
    }

    function setFormOpen(open) {
      if (!form || !toggle) return;
      form.hidden = !open;
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      if (open) {
        var stateField = field("billing_state");
        if (stateField) {
          stateField.focus();
        }
      }
    }

    // آکاردئون به‌صورت پیش‌فرض بسته است؛ بدون JS فرم باز می‌ماند تا
    // فیلدهای استان/شهر در دسترس باشند (پشتیبانی پیش‌رونده).
    setFormOpen(false);

    if (toggle) {
      toggle.addEventListener("click", function () {
        setFormOpen(form.hidden);
      });
    }
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        setFormOpen(false);
      });
    }

    function newField(name) {
      return selector.querySelector('[data-jluxe-new-field="' + name + '"]');
    }

    function identityField(name) {
      return selector.querySelector('[data-jluxe-identity-field="' + name + '"]');
    }

    function collectNewAddress() {
      var data = {};
      // استان/شهر همان فیلدهای واقعی ووکامرس‌اند.
      var stateEl = field("billing_state");
      data.state = stateEl ? stateEl.value.trim() : "";
      var cityEl = field("billing_city");
      data.city = cityEl ? cityEl.value.trim() : "";
      ["address_1", "postcode", "landline"].forEach(function (name) {
        var el = newField(name);
        data[name] = el ? el.value.trim() : "";
      });
      var fn = identityField("first_name");
      data.first_name = fn ? fn.value.trim() : "";
      var ln = identityField("last_name");
      data.last_name = ln ? ln.value.trim() : "";
      var ph = identityField("phone");
      data.phone = ph ? ph.value.trim() : "";
      return data;
    }

    function ensureList() {
      if (list && document.contains(list)) return list;
      // اگر لیست در DOM نیست (HTML قدیمی/کش)، آن را قبل از دکمهٔ آدرس جدید بساز.
      list = document.createElement("div");
      list.className = "jluxe-checkout-addresses__list";
      list.setAttribute("role", "radiogroup");
      list.setAttribute("aria-label", "انتخاب آدرس تحویل");
      list.setAttribute("data-jluxe-address-list", "");
      if (toggle && toggle.parentNode) {
        toggle.parentNode.insertBefore(list, toggle);
      } else {
        selector.appendChild(list);
      }
      return list;
    }

    function appendAddress(entry, selectIt) {
      var targetList = ensureList();
      var card = document.createElement("label");
      card.className = "jluxe-checkout-addresses__card" + (selectIt ? " is-selected" : "");
      card.setAttribute("data-jluxe-address-card", "");
      var input = document.createElement("input");
      input.type = "radio";
      input.name = "jluxe_saved_address";
      input.value = entry.type;
      input.setAttribute("data-jluxe-address-option", "");
      if (selectIt) input.checked = true;
      var radio = document.createElement("span");
      radio.className = "jluxe-checkout-addresses__radio";
      radio.setAttribute("aria-hidden", "true");
      var body = document.createElement("span");
      body.className = "jluxe-checkout-addresses__card-body";
      var label = document.createElement("span");
      label.className = "jluxe-checkout-addresses__card-label";
      label.textContent = entry.label;
      var line = document.createElement("span");
      line.className = "jluxe-checkout-addresses__card-line";
      line.textContent = entry.line;
      body.appendChild(label);
      body.appendChild(line);
      if (entry.phone) {
        var phone = document.createElement("span");
        phone.className = "jluxe-checkout-addresses__card-phone";
        phone.setAttribute("dir", "ltr");
        phone.textContent = entry.phone;
        body.appendChild(phone);
      }
      var data = document.createElement("span");
      data.className = "jluxe-checkout-addresses__data";
      data.setAttribute("data-jluxe-address-data", "");
      data.hidden = true;
      data.textContent = JSON.stringify(entry.address);
      card.appendChild(input);
      card.appendChild(radio);
      card.appendChild(body);
      card.appendChild(data);
      targetList.appendChild(card);
      bindCards();
      if (selectIt) select(card);
    }

    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        var data = collectNewAddress();
        // اعتبارسنجی سمت کلاینت: نام، نام خانوادگی، استان، شهر و آدرس دقیق
        // الزامی‌اند؛ تلفن ثابت اختیاری است.
        if (!data.first_name || !data.last_name) {
          showStatus("وارد کردن نام و نام خانوادگی الزامی است.", true);
          return;
        }
        if (!data.state || !data.city) {
          showStatus("انتخاب استان و شهر الزامی است.", true);
          return;
        }
        if (!data.address_1) {
          showStatus("وارد کردن آدرس الزامی است.", true);
          var addrEl = newField("address_1");
          if (addrEl) addrEl.focus();
          return;
        }
        showStatus("", false);
        saveBtn.disabled = true;
        var body = new URLSearchParams();
        body.set("action", "jluxe_save_checkout_address");
        // nonce هم از localize و هم از data-attribute روی سکشن خوانده می‌شود.
        var nonce =
          selector.getAttribute("data-jluxe-address-nonce") || config.nonce || "";
        body.set("nonce", nonce);
        Object.keys(data).forEach(function (key) {
          body.set(key, data[key]);
        });
        // ajaxUrl را هم‌مبدأ با صفحه می‌کنیم تا mixed-content (HTTP در صفحهٔ
        // HTTPS) باعث بلاک شدن درخواست نشود.
        var ajaxUrl = config.ajaxUrl || "/wp-admin/admin-ajax.php";
        try {
          var parsed = new URL(ajaxUrl, window.location.href);
          if (parsed.origin === window.location.origin) {
            ajaxUrl = parsed.pathname + parsed.search;
          } else {
            parsed.protocol = window.location.protocol;
            ajaxUrl = parsed.toString();
          }
        } catch (e) {
          ajaxUrl = "/wp-admin/admin-ajax.php";
        }
        var payload = body.toString();

        function handleResult(result) {
          var json = result.json;
          if (json && json.success && json.data && Array.isArray(json.data.addresses)) {
            if (list) list.innerHTML = "";
            json.data.addresses.forEach(function (entry, idx) {
              appendAddress(entry, idx === json.data.addresses.length - 1);
            });
            clearNewForm();
            setFormOpen(false);
            showStatus("آدرس ذخیره شد.", false);
          } else if (json && json.data && json.data.message) {
            showStatus(json.data.message, true);
          } else if (!result.ok) {
            showStatus("خطای سرور (" + result.status + ")؛ دوباره تلاش کنید.", true);
          } else {
            showStatus("ذخیرهٔ آدرس ناموفق بود؛ دوباره تلاش کنید.", true);
          }
          saveBtn.disabled = false;
        }

        function parseResponse(ok, status, text) {
          var json = null;
          try {
            json = JSON.parse(text);
          } catch (e) {
            json = null;
          }
          return { ok: ok, status: status, json: json, text: text };
        }

        // اول fetch؛ اگر در سطح شبکه رد شد، با XMLHttpRequest دوباره تلاش می‌کنیم.
        var usedFetch = false;
        function tryXhr() {
          var xhr = new XMLHttpRequest();
          xhr.open("POST", ajaxUrl, true);
          xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
          xhr.onload = function () {
            handleResult(parseResponse(xhr.status >= 200 && xhr.status < 300, xhr.status, xhr.responseText || ""));
          };
          xhr.onerror = function () {
            showStatus("ارتباط با سرور ناموفق بود؛ دوباره تلاش کنید.", true);
            saveBtn.disabled = false;
          };
          xhr.send(payload);
        }

        if (window.fetch) {
          usedFetch = true;
          fetch(ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: payload,
          })
            .then(function (res) {
              return res.text().then(function (text) {
                handleResult(parseResponse(res.ok, res.status, text));
              });
            })
            .catch(function () {
              tryXhr();
            });
        }
        if (!usedFetch) {
          tryXhr();
        }
      });
    }

    bindCards();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
