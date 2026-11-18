/* تسویه‌حساب اصلی: انتخاب آدرس ذخیره‌شده یا ثبت آدرس جدید.
   انتخاب آدرس قبلی فرم را پر می‌کند؛ «آدرس جدید» فرم را خالی می‌کند.
   بدون جاوااسکریپت، فرم از قبل با آدرس ذخیره‌شده پر است و مثل قبل کار می‌کند. */
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

  function clearAddress(prefix) {
    [
      "first_name",
      "last_name",
      "company",
      "address_1",
      "address_2",
      "city",
      "state",
      "postcode",
    ].forEach(function (suffix) {
      setField(prefix + "_" + suffix, "");
    });
    if (prefix === "billing") {
      setField("billing_phone", "");
      setField("billing_email", "");
    }
  }

  function init() {
    var selector = document.querySelector("[data-jluxe-address-selector]");
    if (!selector) return;

    var cards = selector.querySelectorAll("[data-jluxe-address-card]");
    var options = selector.querySelectorAll("[data-jluxe-address-option]");

    function select(card) {
      cards.forEach(function (c) {
        c.classList.toggle("is-selected", c === card);
      });
      var input = card.querySelector("[data-jluxe-address-option]");
      var value = input ? input.value : "";
      if (value === "new") {
        clearAddress("billing");
        clearAddress("shipping");
        return;
      }
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
        // آدرس ارسال هم همان آدرس صورتحساب می‌شود مگر اینکه «ارسال به آدرس دیگر» فعال باشد.
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

    options.forEach(function (input) {
      input.addEventListener("change", function () {
        var card = input.closest("[data-jluxe-address-card]");
        if (card) select(card);
      });
    });

    // کلیک روی کارت هم انتخاب را فعال می‌کند (دسترسی با کیبورد از طریق radio حفظ می‌شود).
    cards.forEach(function (card) {
      card.addEventListener("click", function (event) {
        if (event.target.closest("input")) return;
        var input = card.querySelector("[data-jluxe-address-option]");
        if (input && !input.checked) {
          input.checked = true;
          input.dispatchEvent(new Event("change", { bubbles: true }));
        }
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
