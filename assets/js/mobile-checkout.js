/* پرداخت سریع موبایلی — مسیر مستقل کنار تسویه‌حساب اصلی. */
(function () {
  "use strict";
  var C = window.JLuxeMobileCheckout || {};
  if (!C.ajaxUrl) return;
  var hasMap = typeof L !== "undefined";
  var esc = function (s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  };
  var fa2en = function (s) {
    return String(s || "")
      .replace(/[۰-۹]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹".indexOf(d); })
      .replace(/[٠-٩]/g, function (d) { return "٠١٢٣٤٥٦٧٨٩".indexOf(d); });
  };
  var norm = function (s) {
    return String(s || "").replace(/ي/g, "ی").replace(/ك/g, "ک").replace(/\s+/g, " ").trim();
  };
  var provinceName = function (code) { return (C.provinces && C.provinces[code]) || code || ""; };
  var fullAddr = function (a) {
    return [provinceName(a.state), a.city, a.address, a.plaque ? "پلاک " + a.plaque : "", a.postcode ? "کدپستی " + a.postcode : ""].filter(Boolean).join("، ");
  };
  var inBox = function (lat, lng) {
    var b = C.bbox || [24.0, 43.5, 40.5, 64.0];
    return lat >= b[0] && lat <= b[2] && lng >= b[1] && lng <= b[3];
  };
  var nonce = "";

  function ajax(action, data) {
    var fd = new FormData();
    fd.append("action", "jluxe_mobile_" + action);
    fd.append("nonce", nonce);
    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(C.ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .catch(function () { return { success: false, data: { message: "خطای ارتباط با سرور" } }; });
  }

  function init(root) {
    if (!root || root.dataset.ready) return;
    root.dataset.ready = "1";
    var cfg = {};
    try { cfg = JSON.parse(root.dataset.config || "{}"); } catch (e) { cfg = {}; }
    var title = cfg.title || "پرداخت سریع موبایلی";
    var mapCfg = cfg.map || { lat: 35.6892, lng: 51.3890, zoom: 12, type: "standard-day" };
    var view = root.querySelector(".jluxe-mobile-checkout__view");
    var layer = root.querySelector(".jluxe-mobile-checkout__layer");
    var S = { addresses: [], selected: "", draft: {}, form: {}, shipping: [], chosenShipping: [], otpVerifiedPhone: "", otpSentPhone: "" };

    function makeMap(el, center, zoom, extra) {
      var o = Object.assign({ center: center, zoom: zoom }, extra || {});
      if (C.mapKey) return new L.Map(el, Object.assign({ key: C.mapKey, maptype: mapCfg.type || "standard-day", poi: true, traffic: false }, o));
      var m = L.map(el, o);
      L.tileLayer(C.tiles || "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, attribution: "© OpenStreetMap" }).addTo(m);
      return m;
    }
    function reverse(lat, lng) {
      return ajax("geocode", { lat: lat, lng: lng }).then(function (r) {
        if (r.success) return r.data;
        return { error: (r.data && r.data.message) || "موقعیت انتخاب‌شده خارج از ایران است.", outside: !!(r.data && r.data.code === "outside") };
      });
    }

    var sheets = [];
    function openSheet(html) {
      var ov = document.createElement("div");
      ov.className = "jluxe-mobile-checkout__sheet-ov";
      ov.innerHTML = '<div class="jluxe-mobile-checkout__sheet" role="dialog" aria-modal="true">' + html + "</div>";
      layer.appendChild(ov);
      sheets.push(ov);
      requestAnimationFrame(function () { requestAnimationFrame(function () { ov.classList.add("on"); }); });
      ov.addEventListener("click", function (e) { if (e.target === ov) closeSheet(ov); });
      var x = ov.querySelector(".jluxe-mobile-checkout__x");
      if (x) x.onclick = function () { closeSheet(ov); };
      return ov;
    }
    function closeSheet(ov) {
      if (ov._watch != null && navigator.geolocation) navigator.geolocation.clearWatch(ov._watch);
      if (ov._map) { try { ov._map.remove(); } catch (e) {} ov._map = null; }
      ov.classList.remove("on");
      setTimeout(function () { ov.remove(); }, 300);
      var i = sheets.indexOf(ov);
      if (i > -1) sheets.splice(i, 1);
    }
    function closeAll() { sheets.slice().forEach(closeSheet); }

    /* ---------- list ---------- */
    function renderList() {
      var has = S.addresses.length;
      view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>' + esc(title) + "</h3>" +
        (has ? S.addresses.map(function (a) {
          return '<label class="jluxe-mobile-checkout__addr' + (a.id === S.selected ? " is-on" : "") + '">' +
            '<input type="radio" name="jluxe-mobile-addr" data-id="' + esc(a.id) + '"' + (a.id === S.selected ? " checked" : "") + '><span class="jluxe-mobile-checkout__box"></span>' +
            '<span class="jluxe-mobile-checkout__txt"><b>' + esc(a.first_name + " " + a.last_name) + "</b><small>" + esc(fullAddr(a)) + "</small></span>" +
            '<button type="button" class="jluxe-mobile-checkout__mini" data-edit="' + esc(a.id) + '">تغییر / ویرایش</button></label>';
        }).join("") : '<div class="jluxe-mobile-checkout__empty">آدرسی وارد نشده</div>') +
        '<button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-add">افزودن آدرس</button>' +
        (has ? '<button type="button" class="jluxe-mobile-checkout__btn js-next"' + (S.selected ? "" : " disabled") + ">ادامه</button>" : "") +
        '<a class="jluxe-mobile-checkout__back" href="' + esc(window.location.origin + window.location.pathname.replace(/mobile-checkout\/?$/, "") || "/") + '">بازگشت به تسویه‌حساب اصلی</a></div>';
    }
    view.addEventListener("click", function (e) {
      var ed = e.target.closest("[data-edit]");
      if (ed) { e.preventDefault(); e.stopPropagation(); return editAddress(ed.dataset.edit); }
      if (e.target.closest(".js-add")) return startAdd();
      if (e.target.closest(".js-next")) return showShipping();
      if (e.target.closest(".js-back-list")) return renderList();
    });
    view.addEventListener("change", function (e) {
      var radio = e.target.closest('input[name="jluxe-mobile-addr"]');
      if (!radio) return;
      S.selected = radio.dataset.id;
      ajax("select", { id: S.selected }).then(function () { renderList(); });
    });

    /* ---------- map sheet ---------- */
    function startAdd() { S.draft = {}; S.form = {}; openMapSheet(); }
    function openMapSheet() {
      if (!C.mapEnabled) { openForm(); return; }
      var ov = openSheet('<div class="jluxe-mobile-checkout__sh-h"><span>تعیین موقعیت روی نقشه</span><button class="jluxe-mobile-checkout__x" type="button" aria-label="بستن">×</button></div>' +
        '<div class="jluxe-mobile-checkout__mapbox"><div class="jluxe-mobile-checkout__map"></div>' + (hasMap ? '<div class="jluxe-mobile-checkout__pin"></div><button type="button" class="jluxe-mobile-checkout__loc">📍 تشخیص موقعیت من</button>' : "") + "</div>" +
        '<div class="jluxe-mobile-checkout__maddr">' + (hasMap ? "نقشه را حرکت دهید تا پین روی محل دقیق قرار گیرد." : "نقشه بارگذاری نشد؛ از ثبت دستی استفاده کنید.") + "</div>" +
        '<button type="button" class="jluxe-mobile-checkout__btn js-setloc"' + (hasMap ? "" : " disabled") + ">ثبت موقعیت مکانی</button>" +
        '<button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-manual">ثبت دستی آدرس</button>');
      var box = ov.querySelector(".jluxe-mobile-checkout__maddr");
      if (!hasMap) {
        ov.querySelector(".js-manual").onclick = function () { S.draft = {}; closeAll(); openForm(); };
        return;
      }
      var cur = null;
      var c = S.draft.lat ? [+S.draft.lat, +S.draft.lng] : [mapCfg.lat || 35.6892, mapCfg.lng || 51.3890];
      setTimeout(function () {
        var m = makeMap(ov.querySelector(".jluxe-mobile-checkout__map"), c, S.draft.lat ? 17 : (mapCfg.zoom || 12), { minZoom: 5, maxBounds: [[C.bbox[0], C.bbox[1]], [C.bbox[2], C.bbox[3]]], maxBoundsViscosity: 1 });
        ov._map = m;
        setTimeout(function () { try { m.invalidateSize(); } catch (e) {} }, 350);
        var t;
        var setBtn = ov.querySelector(".js-setloc");
        function fail(msg) { cur = null; box.textContent = msg; box.classList.add("is-err"); setBtn.disabled = true; }
        m.on("moveend", function () {
          clearTimeout(t);
          var p = m.getCenter();
          box.classList.remove("is-err");
          if (!inBox(p.lat, p.lng)) return fail("موقعیت انتخاب‌شده خارج از ایران است. لطفاً نقطه‌ای داخل ایران انتخاب کنید.");
          t = setTimeout(function () {
            box.textContent = "در حال دریافت آدرس...";
            setBtn.disabled = true;
            reverse(p.lat, p.lng).then(function (r) {
              if (r.error) return fail(r.error);
              cur = { lat: p.lat.toFixed(6), lng: p.lng.toFixed(6), map_address: r.text || "", state: r.state || "", city: r.city || "", street: r.street || "", alley: r.alley || "", plaque: r.plaque || "", postcode: r.postcode || "" };
              box.textContent = r.text || "آدرس برای این نقطه یافت نشد؛ می‌توانید دستی کامل کنید.";
              setBtn.disabled = false;
            });
          }, 450);
        });
        m.fire("moveend");
        var lb = ov.querySelector(".jluxe-mobile-checkout__loc");
        if (lb) lb.onclick = function () {
          if (!navigator.geolocation) return alert("مرورگر شما موقعیت‌یاب را پشتیبانی نمی‌کند.");
          if (ov._watch != null) navigator.geolocation.clearWatch(ov._watch);
          var btn = this;
          btn.textContent = "در حال یافتن موقعیت...";
          var best = null, done = false;
          function finish() {
            if (done) return; done = true;
            if (navigator.geolocation && ov._watch != null) navigator.geolocation.clearWatch(ov._watch);
            ov._watch = null;
            btn.textContent = "📍 تشخیص موقعیت من";
            if (!best) return alert("دسترسی به موقعیت مکانی داده نشد یا موقعیت پیدا نشد.");
            if (best.accuracy > 300) setTimeout(function () { box.textContent = "دقت موقعیت‌یاب دستگاه پایین است (حدود " + Math.round(best.accuracy) + " متر). لطفاً پین را روی محل دقیق جابجا کنید."; }, 900);
          }
          ov._watch = navigator.geolocation.watchPosition(function (p) {
            if (!best || p.coords.accuracy < best.accuracy) {
              best = p.coords;
              var ll = [best.latitude, best.longitude];
              ov._map.setView(ll, best.accuracy < 100 ? 18 : best.accuracy < 1000 ? 16 : 14);
            }
            if (p.coords.accuracy <= 30) finish();
          }, function () { finish(); }, { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 });
          setTimeout(finish, 10000);
        };
      }, 60);
      ov.querySelector(".js-setloc").onclick = function () {
        if (!cur) return;
        S.draft = cur;
        if (cur.state) S.form.state = cur.state;
        if (cur.city) S.form.city = cur.city;
        var road = [cur.street, cur.alley].filter(Boolean).join("، ");
        if (road && !S.form.address) S.form.address = road;
        if (cur.plaque && !S.form.plaque) S.form.plaque = cur.plaque;
        if (cur.postcode && !S.form.postcode) S.form.postcode = cur.postcode;
        closeAll();
        openForm();
      };
      ov.querySelector(".js-manual").onclick = function () { S.draft = {}; closeAll(); openForm(); };
    }

    /* ---------- form sheet ---------- */
    function editAddress(id) {
      var a = S.addresses.find(function (x) { return x.id === id; });
      if (!a) return;
      S.form = Object.assign({}, a);
      S.draft = a.lat ? { lat: a.lat, lng: a.lng, map_address: a.map_address || "" } : {};
      openForm();
    }
    function provinceOptions(selected) {
      return Object.keys(C.provinces || {}).map(function (code) {
        return '<option value="' + esc(code) + '"' + (code === selected ? " selected" : "") + ">" + esc(C.provinces[code]) + "</option>";
      }).join("");
    }
    function cityOptions(state, selected) {
      var cities = (C.cities && C.cities[state]) || [];
      if (!cities.length) return '<option value="">ابتدا استان را انتخاب کنید</option>';
      return '<option value="">انتخاب شهر</option>' + cities.map(function (city) {
        return '<option value="' + esc(city) + '"' + (city === selected ? " selected" : "") + ">" + esc(city) + "</option>";
      }).join("");
    }
    function openForm() {
      var f = S.form;
      var hasLoc = !!(S.draft && S.draft.lat);
      var ov = openSheet('<div class="jluxe-mobile-checkout__sh-h"><span>ثبت آدرس</span><button class="jluxe-mobile-checkout__x" type="button" aria-label="بستن">×</button></div>' +
        '<div class="jluxe-mobile-checkout__row"><label class="jluxe-mobile-checkout__f"><span>نام</span><input name="first_name" autocomplete="given-name" value="' + esc(f.first_name || "") + '"></label>' +
        '<label class="jluxe-mobile-checkout__f"><span>نام خانوادگی</span><input name="last_name" autocomplete="family-name" value="' + esc(f.last_name || "") + '"></label></div>' +
        '<label class="jluxe-mobile-checkout__f"><span>شماره موبایل</span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="11" placeholder="09123456789" value="' + esc(f.phone || "") + '"></label>' +
        (C.otpEnabled ? '<div class="jluxe-mobile-checkout__otp"><button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-otp-send">ارسال کد تأیید</button><span class="jluxe-mobile-checkout__otp-status" role="status" aria-live="polite"></span><div class="jluxe-mobile-checkout__otp-code" hidden><label class="jluxe-mobile-checkout__f"><span>کد تأیید</span><input name="otp_code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr"></label><button type="button" class="jluxe-mobile-checkout__btn js-otp-verify">تأیید کد</button></div></div>' : "") +
        '<label class="jluxe-mobile-checkout__f"><span>آدرس ایمیل</span><input name="email" type="email" inputmode="email" autocomplete="email" value="' + esc(f.email || "") + '"></label>' +
        (hasLoc ? '<div class="jluxe-mobile-checkout__mapbox" style="margin-top:12px"><div class="jluxe-mobile-checkout__map sm"></div><div class="jluxe-mobile-checkout__pin" style="transform:rotate(-45deg) scale(.7)"></div></div><button type="button" class="jluxe-mobile-checkout__link js-editloc">ویرایش موقعیت</button><label class="jluxe-mobile-checkout__f" style="margin-top:0"><span>آدرس انتخاب‌شده روی نقشه</span><input readonly value="' + esc(S.draft.map_address || "") + '"></label>' : "") +
        '<div class="jluxe-mobile-checkout__hint">اگر آدرس نادرست است می‌توانید از ثبت دستی آدرس استفاده کنید.</div>' +
        '<div class="jluxe-mobile-checkout__row"><label class="jluxe-mobile-checkout__f"><span>استان</span><select name="state"><option value="">انتخاب استان</option>' + provinceOptions(f.state || "") + '</select></label>' +
        '<label class="jluxe-mobile-checkout__f"><span>شهر</span><select name="city"' + (f.state ? "" : " disabled") + ">" + cityOptions(f.state || "", f.city || "") + "</select></label></div>" +
        '<label class="jluxe-mobile-checkout__f"><span>آدرس دقیق</span><textarea name="address" rows="2">' + esc(f.address || "") + "</textarea></label>" +
        '<div class="jluxe-mobile-checkout__row"><label class="jluxe-mobile-checkout__f"><span>پلاک</span><input name="plaque" value="' + esc(f.plaque || "") + '"></label>' +
        '<label class="jluxe-mobile-checkout__f"><span>کدپستی</span><input name="postcode" inputmode="numeric" maxlength="10" value="' + esc(f.postcode || "") + '"></label></div>' +
        '<div class="jluxe-mobile-checkout__err" role="alert"></div>' +
        '<button type="button" class="jluxe-mobile-checkout__btn js-save">ثبت آدرس</button>' +
        (f.id ? '<button type="button" class="jluxe-mobile-checkout__link js-del">حذف این آدرس</button>' : ""));
      var stateSel = ov.querySelector('[name="state"]');
      var citySel = ov.querySelector('[name="city"]');
      var phoneInput = ov.querySelector('[name="phone"]');
      var otpStatus = ov.querySelector(".jluxe-mobile-checkout__otp-status");
      var otpCodeBox = ov.querySelector(".jluxe-mobile-checkout__otp-code");
      stateSel.addEventListener("change", function () {
        citySel.innerHTML = cityOptions(stateSel.value, "");
        citySel.disabled = !stateSel.value;
      });
      phoneInput.addEventListener("input", function () {
        S.otpVerifiedPhone = "";
        S.otpSentPhone = "";
        if (otpCodeBox) otpCodeBox.hidden = true;
        if (otpStatus) otpStatus.textContent = "";
      });
      if (C.otpEnabled) {
        ov.querySelector(".js-otp-send").onclick = function () {
          var phone = fa2en(phoneInput.value).replace(/\D/g, "");
          if (!/^09\d{9}$/.test(phone)) { otpStatus.textContent = "شماره موبایل معتبر نیست."; return; }
          otpStatus.textContent = "در حال ارسال کد...";
          ajax("otp_request", { phone: phone }).then(function (r) {
            if (r.success) { S.otpSentPhone = phone; otpCodeBox.hidden = false; otpStatus.textContent = "کد تأیید ارسال شد."; }
            else otpStatus.textContent = (r.data && r.data.message) || "خطا در ارسال کد";
          });
        };
        ov.querySelector(".js-otp-verify").onclick = function () {
          var phone = fa2en(phoneInput.value).replace(/\D/g, "");
          var code = fa2en(ov.querySelector('[name="otp_code"]').value).replace(/\D/g, "");
          otpStatus.textContent = "در حال بررسی کد...";
          ajax("otp_verify", { phone: phone, code: code }).then(function (r) {
            if (r.success) { S.otpVerifiedPhone = phone; otpStatus.textContent = "موبایل تأیید شد."; }
            else otpStatus.textContent = (r.data && r.data.message) || "کد نامعتبر است";
          });
        };
      }
      if (hasLoc && hasMap) {
        setTimeout(function () {
          var m = makeMap(ov.querySelector(".jluxe-mobile-checkout__map"), [+S.draft.lat, +S.draft.lng], 17, { zoomControl: false, dragging: false, scrollWheelZoom: false, touchZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false, attributionControl: false });
          ov._map = m;
          setTimeout(function () { try { m.invalidateSize(); } catch (e) {} }, 300);
        }, 60);
        ov.querySelector(".js-editloc").onclick = function () { closeAll(); openMapSheet(); };
      }
      function readForm() {
        ["first_name", "last_name", "phone", "email", "state", "city", "address", "plaque", "postcode"].forEach(function (k) {
          var el = ov.querySelector("[name=" + k + "]");
          if (el) S.form[k] = el.value;
        });
      }
      ov.querySelector(".js-save").onclick = function () {
        readForm();
        var err = ov.querySelector(".jluxe-mobile-checkout__err");
        var phone = fa2en(S.form.phone || "").replace(/\D/g, "");
        if (C.otpEnabled && phone !== S.otpVerifiedPhone) { err.textContent = "ابتدا موبایل را با کد تأیید کنید."; return; }
        var payload = Object.assign({}, S.form, { lat: S.draft.lat || "", lng: S.draft.lng || "", map_address: S.draft.map_address || "" });
        err.textContent = "";
        ajax("save", { data: JSON.stringify(payload) }).then(function (r) {
          if (r.success) { S.addresses = r.data.addresses; S.selected = r.data.selected; closeAll(); renderList(); }
          else err.textContent = (r.data && r.data.message) || "خطا در ثبت آدرس";
        });
      };
      var del = ov.querySelector(".js-del");
      if (del) del.onclick = function () {
        ajax("delete", { id: f.id }).then(function (r) {
          if (r.success) { S.addresses = r.data.addresses; S.selected = r.data.selected; closeAll(); renderList(); }
        });
      };
    }

    /* ---------- shipping / summary / gateways ---------- */
    function showShipping() {
      view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>روش ارسال</h3><div class="jluxe-mobile-checkout__loader" role="status" aria-live="polite"><span class="jluxe-mobile-checkout__spin" aria-hidden="true"></span><div>در حال دریافت روش‌های ارسال...</div></div></div>';
      ajax("shipping").then(function (r) {
        if (!r.success) {
          view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>روش ارسال</h3><div class="jluxe-mobile-checkout__err">' + esc((r.data && r.data.message) || "خطا") + '</div><button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-back-list">بازگشت به آدرس‌ها</button></div>';
          return;
        }
        S.shipping = r.data;
        S.chosenShipping = S.shipping.map(function (pkg) { return pkg.rates.length ? pkg.rates[0].id : ""; });
        renderShipping();
      });
    }
    function renderShipping() {
      view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>روش ارسال</h3>' +
        S.shipping.map(function (pkg, pi) {
          return (S.shipping.length > 1 ? "<h4>بستهٔ " + (pi + 1) + "</h4>" : "") + pkg.rates.map(function (rate, ri) {
            return '<label class="jluxe-mobile-checkout__gw' + (S.chosenShipping[pi] === rate.id ? " is-on" : "") + '" data-pkg="' + pi + '" data-rate="' + esc(rate.id) + '"><span class="jluxe-mobile-checkout__box"></span><span style="flex:1"><b>' + esc(rate.label) + "</b></span><span>" + rate.cost + "</span></label>";
          }).join("");
        }).join("") +
        '<div class="jluxe-mobile-checkout__err" role="alert"></div>' +
        '<button type="button" class="jluxe-mobile-checkout__btn js-to-summary">ادامه به خلاصه سفارش</button>' +
        '<button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-back-list">بازگشت به آدرس‌ها</button></div>';
      view.querySelectorAll(".jluxe-mobile-checkout__gw").forEach(function (el) {
        el.addEventListener("click", function () {
          var pi = +el.dataset.pkg;
          S.chosenShipping[pi] = el.dataset.rate;
          view.querySelectorAll('.jluxe-mobile-checkout__gw[data-pkg="' + pi + '"]').forEach(function (x) { x.classList.toggle("is-on", x === el); });
        });
      });
    }
    function showSummary() {
      view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>جزئیات سفارش</h3><div class="jluxe-mobile-checkout__loader" role="status" aria-live="polite"><span class="jluxe-mobile-checkout__spin" aria-hidden="true"></span><div>در حال محاسبه...</div></div></div>';
      ajax("summary", { shipping: JSON.stringify(S.chosenShipping) }).then(function (r) {
        if (!r.success) {
          view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>جزئیات سفارش</h3><div class="jluxe-mobile-checkout__err">' + esc((r.data && r.data.message) || "خطا") + '</div><button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-back-list">بازگشت به آدرس‌ها</button></div>';
          return;
        }
        var d = r.data;
        var a = d.address;
        view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>جزئیات سفارش</h3>' +
          '<div class="jluxe-mobile-checkout__sum"><b>آدرس تحویل</b><div class="jluxe-mobile-checkout__sum-addr">' + esc(a.first_name + " " + a.last_name) + "<br>" + esc(fullAddr(a)) + "</div></div>" +
          '<div class="jluxe-mobile-checkout__sum">' + d.items.map(function (i) { return '<div class="jluxe-mobile-checkout__line"><span>' + esc(i.name) + " × " + i.qty + "</span><span>" + i.total + "</span></div>"; }).join("") +
          d.rows.map(function (x, i) { return '<div class="jluxe-mobile-checkout__line' + (i === d.rows.length - 1 ? " t" : "") + '"><span>' + esc(x[0]) + "</span><span>" + x[1] + "</span></div>"; }).join("") + "</div>" +
          '<button type="button" class="jluxe-mobile-checkout__btn js-pay">پرداخت</button>' +
          '<button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-back-list">بازگشت به آدرس‌ها</button></div>';
      });
    }
    view.addEventListener("click", function (e) {
      if (e.target.closest(".js-to-summary")) return showSummary();
      if (e.target.closest(".js-pay")) return openGateways();
    });
    function openGateways() {
      var btn = view.querySelector(".js-pay");
      if (btn) btn.disabled = true;
      ajax("gateways").then(function (r) {
        if (!r.success) {
          view.innerHTML = '<div class="jluxe-mobile-checkout__card"><h3>انتخاب درگاه پرداخت</h3><div class="jluxe-mobile-checkout__err">' + esc((r.data && r.data.message) || "خطا") + '</div><button type="button" class="jluxe-mobile-checkout__btn jluxe-mobile-checkout__btn--ghost js-back-list">بازگشت به آدرس‌ها</button></div>';
          return;
        }
        gatewaySheet(r.data);
      });
    }
    function gatewaySheet(list) {
      var sel = list.length ? list[0].id : "";
      var ov = openSheet('<div class="jluxe-mobile-checkout__sh-h"><span>انتخاب درگاه پرداخت</span><button class="jluxe-mobile-checkout__x" type="button" aria-label="بستن">×</button></div>' +
        list.map(function (g, i) {
          return '<div class="jluxe-mobile-checkout__gw' + (i ? "" : " is-on") + '" data-g="' + esc(g.id) + '"><span class="jluxe-mobile-checkout__box"></span><span style="flex:1"><b>' + esc(g.title) + "</b>" + (g.desc ? "<br><small>" + esc(g.desc) + "</small>" : "") + "</span>" + g.icon + "</div>";
        }).join("") +
        '<div class="jluxe-mobile-checkout__err" role="alert"></div><button type="button" class="jluxe-mobile-checkout__btn js-go">تایید و پرداخت</button>');
      ov.addEventListener("click", function (e) {
        var g = e.target.closest(".jluxe-mobile-checkout__gw");
        if (!g) return;
        sel = g.dataset.g;
        ov.querySelectorAll(".jluxe-mobile-checkout__gw").forEach(function (x) { x.classList.toggle("is-on", x === g); });
      });
      ov.querySelector(".js-go").onclick = function () {
        var btn = this;
        btn.disabled = true;
        btn.textContent = "در حال اتصال به درگاه...";
        ajax("pay", { gateway: sel, shipping: JSON.stringify(S.chosenShipping) }).then(function (p) {
          if (p.success && p.data && p.data.redirect) { window.location.href = p.data.redirect; return; }
          btn.disabled = false;
          btn.textContent = "تایید و پرداخت";
          ov.querySelector(".jluxe-mobile-checkout__err").textContent = (p.data && p.data.message) || "خطا در پرداخت";
        });
      };
    }

    /* ---------- boot ---------- */
    ajax("state").then(function (r) {
      if (r.success) {
        nonce = r.data.nonce || "";
        S.addresses = r.data.addresses || [];
        S.selected = r.data.selected || "";
        if (typeof r.data.otpEnabled !== "undefined") C.otpEnabled = !!r.data.otpEnabled;
        if (typeof r.data.mapEnabled !== "undefined") C.mapEnabled = !!r.data.mapEnabled;
      }
      renderList();
    });
  }

  function boot() {
    document.querySelectorAll("[data-jluxe-mobile-checkout]:not([data-ready])").forEach(init);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
