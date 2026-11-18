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

  function applyAddress(address, prefix) {
    if (!address) return;
    setField(prefix + "_first_name", address.first_name);
    setField(prefix + "_last_name", address.last_name);
    setField(prefix + "_company", address.company);
    setField(prefix + "_address_1", address.address_1);
    setField(prefix + "_address_2", address.address_2);
    setField(prefix + "_city", address.city);
    setField(prefix + "_state", address.state);
    setField(prefix + "_postcode", address.postcode);
    setField(prefix + "_country", address.country);
    if (prefix === "billing") {
      setField("billing_phone", address.phone);
      setField("billing_email", address.email);
    }
  }

  function normalizeFa(text) {
    return String(text || "")
      .replace(/[ي]/g, "ی")
      .replace(/[ك]/g, "ک")
      .replace(/\s+/g, " ")
      .trim();
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
    var provinces = config.provinces || {};
    var citiesByState = config.cities || {};

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

    /* ---------- پیکر استان/شهر با جستجو ---------- */
    function buildPicker(kind, getItems, onSelect) {
      var root = selector.querySelector('[data-jluxe-picker="' + kind + '"]');
      if (!root) return null;
      var button = root.querySelector("[data-jluxe-picker-button]");
      var valueEl = root.querySelector("[data-jluxe-picker-value]");
      var panel = root.querySelector("[data-jluxe-picker-panel]");
      var search = root.querySelector("[data-jluxe-picker-search]");
      var listEl = root.querySelector("[data-jluxe-picker-list]");
      var hidden = selector.querySelector('[data-jluxe-new-field="' + kind + '"]');
      var selected = "";

      function render(filter) {
        var items = getItems();
        var q = normalizeFa(filter);
        listEl.innerHTML = "";
        var shown = 0;
        items.forEach(function (item) {
          if (q && normalizeFa(item.label).indexOf(q) === -1) return;
          shown++;
          var li = document.createElement("li");
          li.setAttribute("role", "option");
          li.setAttribute("aria-selected", item.value === selected ? "true" : "false");
          li.textContent = item.label;
          li.classList.toggle("is-selected", item.value === selected);
          li.addEventListener("click", function () {
            selected = item.value;
            if (hidden) hidden.value = item.value;
            valueEl.textContent = item.label;
            close();
            onSelect(item);
          });
          listEl.appendChild(li);
        });
        if (!shown) {
          var empty = document.createElement("li");
          empty.className = "jluxe-checkout-picker__empty";
          empty.textContent = "موردی یافت نشد";
          listEl.appendChild(empty);
        }
      }

      function open() {
        if (button.disabled) return;
        panel.hidden = false;
        button.setAttribute("aria-expanded", "true");
        search.value = "";
        render("");
        search.focus();
      }

      function close() {
        panel.hidden = true;
        button.setAttribute("aria-expanded", "false");
      }

      button.addEventListener("click", function () {
        if (panel.hidden) open();
        else close();
      });
      search.addEventListener("input", function () {
        render(search.value);
      });
      document.addEventListener("click", function (event) {
        if (!panel.hidden && !root.contains(event.target)) close();
      });
      document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !panel.hidden) close();
      });

      return {
        setEnabled: function (enabled) {
          button.disabled = !enabled;
        },
        setPlaceholder: function (text) {
          valueEl.textContent = text;
        },
        reset: function () {
          selected = "";
          if (hidden) hidden.value = "";
          close();
        },
        refresh: function () {
          if (!panel.hidden) render(search.value);
        },
      };
    }

    var statePicker = buildPicker(
      "state",
      function () {
        return Object.keys(provinces).map(function (code) {
          return { value: provinces[code], label: provinces[code] };
        });
      },
      function () {
        cityPicker.reset();
        cityPicker.setPlaceholder("انتخاب شهر");
        cityPicker.setEnabled(true);
      }
    );

    var cityPicker = buildPicker(
      "city",
      function () {
        var stateName = "";
        var hidden = selector.querySelector('[data-jluxe-new-field="state"]');
        if (hidden) stateName = hidden.value;
        var code = "";
        Object.keys(provinces).forEach(function (c) {
          if (provinces[c] === stateName) code = c;
        });
        var cities = (code && citiesByState[code]) || [];
        return cities.map(function (city) {
          return { value: city, label: city };
        });
      },
      function () {}
    );

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
      form.querySelectorAll("[data-jluxe-new-field]").forEach(function (el) {
        el.value = "";
      });
      statePicker.reset();
      cityPicker.reset();
      cityPicker.setPlaceholder("ابتدا استان را انتخاب کنید");
      cityPicker.setEnabled(false);
    }

    function setFormOpen(open) {
      if (!form || !toggle) return;
      form.hidden = !open;
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      if (open) {
        var first = form.querySelector("[data-jluxe-picker-button]");
        if (first) first.focus();
      }
    }

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
      ["state", "city", "address_1", "postcode", "email"].forEach(function (name) {
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

    function appendAddress(entry, selectIt) {
      if (!list) return;
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
      list.appendChild(card);
      bindCards();
      if (selectIt) select(card);
    }

    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        var data = collectNewAddress();
        showStatus("", false);
        saveBtn.disabled = true;
        var body = new URLSearchParams();
        body.set("action", "jluxe_save_checkout_address");
        body.set("nonce", config.nonce || "");
        Object.keys(data).forEach(function (key) {
          body.set(key, data[key]);
        });
        fetch(config.ajaxUrl || "/wp-admin/admin-ajax.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: body.toString(),
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (json) {
            if (json && json.success && json.data && Array.isArray(json.data.addresses)) {
              if (list) list.innerHTML = "";
              json.data.addresses.forEach(function (entry, idx) {
                appendAddress(entry, idx === json.data.addresses.length - 1);
              });
              clearNewForm();
              setFormOpen(false);
              showStatus("آدرس ذخیره شد.", false);
            } else {
              showStatus((json && json.data && json.data.message) || "ذخیرهٔ آدرس ناموفق بود.", true);
            }
          })
          .catch(function () {
            showStatus("ارتباط با سرور ناموفق بود؛ دوباره تلاش کنید.", true);
          })
          .finally(function () {
            saveBtn.disabled = false;
          });
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
