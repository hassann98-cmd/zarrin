(function () {
  const C = window.DSN;
  if (!C) return;
  const hasMap = typeof L !== 'undefined';
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fa2en = s => String(s || '').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  const fullAddr = a => [a.province, a.city, a.address, a.plaque ? 'پلاک ' + a.plaque : '', a.postcode ? 'کدپستی ' + a.postcode : ''].filter(Boolean).join('، ');
  const norm = s => String(s || '').replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ').trim();
  const loaderHTML = t => '<div class="dsn-wrap"><div class="dsn-loader" role="status" aria-live="polite"><span class="dsn-spin" aria-hidden="true"></span><div class="dsn-loader-t">' + esc(t) + '</div></div></div>';
  let citiesP = null;
  const loadCities = () => citiesP || (citiesP = fetch(C.citiesUrl).then(r => r.json()).catch(() => ({})));
  const inBox = (lat, lng) => lat >= C.bbox[0] && lat <= C.bbox[2] && lng >= C.bbox[1] && lng <= C.bbox[3];
  let nonce = '';

  function ajax(action, data) {
    const fd = new FormData();
    fd.append('action', 'dsn_' + action); fd.append('nonce', nonce);
    Object.keys(data || {}).forEach(k => fd.append(k, data[k]));
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json())
      .catch(() => ({ success: false, data: { message: 'خطای ارتباط با سرور' } }));
  }

  function init(root) {
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    let cfg = {}; try { cfg = JSON.parse(root.dataset.dsn || '{}'); } catch (e) {}
    const T = cfg.t || {}, M = cfg.map || {};
    const edit = !!(window.elementorFrontend && elementorFrontend.isEditMode && elementorFrontend.isEditMode());
    const pv = edit && cfg.preview && cfg.preview !== 'none' ? cfg.preview : '';
    const view = root.querySelector('.dsn-view'), layer = root.querySelector('.dsn-layer');
    const S = { addresses: [], selected: '', draft: {}, form: {} };

    const DEMO_ADDR = [
      { id: 'd1', first_name: 'علی', last_name: 'رضایی', province: 'تهران', city: 'تهران', address: 'خیابان ولیعصر، کوچه شماره ۲', plaque: '12', postcode: '1234567890' },
      { id: 'd2', first_name: 'سارا', last_name: 'احمدی', province: 'اصفهان', city: 'اصفهان', address: 'خیابان چهارباغ، کوچه گلها', plaque: '5', postcode: '8134567890' },
    ];

    /* ---------- map ---------- */
    function makeMap(el, center, zoom, extra) {
      const o = Object.assign({ center: center, zoom: zoom }, extra || {});
      if (C.mapKey) return new L.Map(el, Object.assign({ key: C.mapKey, maptype: M.type || 'standard-day', poi: true, traffic: false }, o));
      const m = L.map(el, o); L.tileLayer(C.tiles, { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(m); return m;
    }
    const reverse = (lat, lng) => ajax('geocode', { lat: lat, lng: lng }).then(r => r.success ? r.data : { error: (r.data && r.data.message) || T.outside, outside: !!(r.data && r.data.code === 'outside') });

    /* ---------- sheets (داخل خود ویجت، تا استایل‌های المنتور اعمال شوند) ---------- */
    const sheets = [];
    function openSheet(html) {
      const ov = document.createElement('div');
      ov.className = 'dsn-sheet-ov';
      ov.innerHTML = '<div class="dsn-sheet">' + html + '</div>';
      layer.appendChild(ov); sheets.push(ov);
      requestAnimationFrame(() => requestAnimationFrame(() => ov.classList.add('on')));
      ov.addEventListener('click', e => { if (e.target === ov) closeSheet(ov); });
      const x = ov.querySelector('.dsn-x'); if (x) x.onclick = () => closeSheet(ov);
      return ov;
    }
    function closeSheet(ov) {
      if (ov._watch != null) navigator.geolocation.clearWatch(ov._watch);
      if (ov._map) { ov._map.remove(); ov._map = null; }
      ov.classList.remove('on'); setTimeout(() => ov.remove(), 300);
      const i = sheets.indexOf(ov); if (i > -1) sheets.splice(i, 1);
    }
    const closeAll = () => sheets.slice().forEach(closeSheet);

    /* ---------- list ---------- */
    function renderList() {
      const list = pv === 'list' && !S.addresses.length ? DEMO_ADDR : S.addresses;
      const sel = list === DEMO_ADDR ? 'd1' : S.selected;
      const has = list.length;
      view.innerHTML = '<div class="dsn-wrap"><h3>' + esc(T.list_title) + '</h3>' +
        (has ? list.map(a => '<label class="dsn-card' + (a.id === sel ? ' is-on' : '') + '">' +
          '<input type="checkbox" data-id="' + a.id + '"' + (a.id === sel ? ' checked' : '') + '><span class="dsn-box"></span>' +
          '<span class="dsn-txt"><b>' + esc(a.first_name + ' ' + a.last_name) + '</b><small>' + esc(fullAddr(a)) + '</small></span>' +
          '<button type="button" class="dsn-mini" data-edit="' + a.id + '">' + esc(T.edit) + '</button></label>').join('')
          : '<div class="dsn-empty">' + esc(T.empty) + '</div>') +
        '<button type="button" class="dsn-btn dsn-btn-ghost js-add">' + esc(T.add) + '</button>' +
        (has ? '<button type="button" class="dsn-btn js-next"' + (sel ? '' : ' disabled') + '>' + esc(T.next) + '</button>' : '') + '</div>';
    }
    view.addEventListener('click', e => {
      const ed = e.target.closest('[data-edit]');
      if (ed) { e.preventDefault(); e.stopPropagation(); return editAddress(ed.dataset.edit); }
      if (e.target.closest('.js-add')) return startAdd();
      if (e.target.closest('.js-next')) return showSummary();
      if (e.target.closest('.js-back')) return renderList();
      if (e.target.closest('.js-pay')) return openGateways();
    });
    view.addEventListener('change', e => {
      const cb = e.target.closest('input[data-id]'); if (!cb || pv) return;
      S.selected = cb.checked ? cb.dataset.id : '';
      ajax('select', { id: S.selected }); renderList();
    });

    /* ---------- sheet 1: map ---------- */
    function startAdd() { S.draft = {}; S.form = {}; openMapSheet(); }
    function openMapSheet() {
      const ov = openSheet('<div class="dsn-sh-h"><span>' + esc(T.map_title) + '</span><button class="dsn-x" type="button">×</button></div>' +
        '<div class="dsn-mapbox"><div class="dsn-map"></div>' + (hasMap ? '<div class="dsn-pin"></div>' + (M.locate !== false ? '<button type="button" class="dsn-loc">' + esc(T.locate) + '</button>' : '') : '') + '</div>' +
        '<div class="dsn-maddr">' + (hasMap ? esc(T.map_hint) : 'نقشه بارگذاری نشد؛ از ثبت دستی استفاده کنید.') + '</div>' +
        '<button type="button" class="dsn-btn js-setloc"' + (hasMap ? '' : ' disabled') + '>' + esc(T.set_loc) + '</button>' +
        '<button type="button" class="dsn-btn dsn-btn-ghost js-manual">' + esc(T.manual) + '</button>');
      let cur = null, circle = null;
      const box = ov.querySelector('.dsn-maddr');
      if (hasMap) {
        const c = S.draft.lat ? [+S.draft.lat, +S.draft.lng] : [M.lat || 35.6892, M.lng || 51.389];
        setTimeout(() => {
          const m = makeMap(ov.querySelector('.dsn-map'), c, S.draft.lat ? 17 : (M.zoom || 12), { minZoom: 5, maxBounds: [[C.bbox[0], C.bbox[1]], [C.bbox[2], C.bbox[3]]], maxBoundsViscosity: 1 });
          ov._map = m; setTimeout(() => m.invalidateSize(), 350);
          if (pv) return; // پیش‌نمایش ادیتور: بدون درخواست آدرس
          let t;
          const setBtn = ov.querySelector('.js-setloc');
          const fail = msg => { cur = null; box.textContent = msg; box.classList.add('is-err'); setBtn.disabled = true; };
          m.on('moveend', () => {
            clearTimeout(t);
            const p = m.getCenter();
            box.classList.remove('is-err');
            if (!inBox(p.lat, p.lng)) return fail(T.outside);
            t = setTimeout(() => {
              box.textContent = 'در حال دریافت آدرس...'; setBtn.disabled = true;
              reverse(p.lat, p.lng).then(r => {
                if (r.error) return fail(r.error);
                cur = { lat: p.lat.toFixed(6), lng: p.lng.toFixed(6), map_address: r.text, province: r.province, city: r.city, street: r.street, alley: r.alley, plaque: r.plaque, postcode: r.postcode };
                box.textContent = r.text || 'آدرس برای این نقطه یافت نشد'; setBtn.disabled = false;
              });
            }, 450);
          });
          m.fire('moveend');
        }, 60);

        const lb = ov.querySelector('.dsn-loc');
        if (lb) lb.onclick = function () {
          if (!navigator.geolocation) return alert('مرورگر شما موقعیت‌یاب را پشتیبانی نمی‌کند.');
          if (ov._watch != null) navigator.geolocation.clearWatch(ov._watch);
          const btn = this; btn.textContent = 'در حال یافتن موقعیت...';
          let best = null, done = false;
          const finish = () => {
            if (done) return; done = true;
            navigator.geolocation.clearWatch(ov._watch); ov._watch = null; btn.textContent = T.locate;
            if (!best) return alert('دسترسی به موقعیت مکانی داده نشد یا موقعیت پیدا نشد.');
            if (best.accuracy > 300) setTimeout(() => { box.textContent = 'دقت موقعیت‌یاب دستگاه پایین است (حدود ' + Math.round(best.accuracy) + ' متر). لطفاً پین را روی محل دقیق جابجا کنید.'; }, 900);
          };
          ov._watch = navigator.geolocation.watchPosition(p => {
            if (!best || p.coords.accuracy < best.accuracy) {
              best = p.coords; const ll = [best.latitude, best.longitude];
              ov._map.setView(ll, best.accuracy < 100 ? 18 : best.accuracy < 1000 ? 16 : 14);
              if (circle) ov._map.removeLayer(circle);
              circle = L.circle(ll, { radius: best.accuracy, weight: 1, color: '#5AA2E8', fillOpacity: .12 }).addTo(ov._map);
            }
            if (p.coords.accuracy <= 30) finish();
          }, () => finish(), { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 });
          setTimeout(finish, 10000);
        };
      }
      ov.querySelector('.js-setloc').onclick = () => {
        if (!cur) return;
        S.draft = cur;
        if (cur.province) S.form.province = cur.province;
        if (cur.city) S.form.city = cur.city;
        const road = [cur.street, cur.alley].filter(Boolean).join('، ');
        if (road && !S.form.address) S.form.address = road;
        if (cur.plaque && !S.form.plaque) S.form.plaque = cur.plaque;
        if (cur.postcode && !S.form.postcode) S.form.postcode = cur.postcode;
        closeAll(); openForm();
      };
      ov.querySelector('.js-manual').onclick = () => { S.draft = {}; closeAll(); openForm(); };
    }

    /* ---------- sheet 2: form ---------- */
    function editAddress(id) {
      const a = (S.addresses.concat(pv ? DEMO_ADDR : [])).find(x => x.id === id); if (!a) return;
      S.form = Object.assign({}, a);
      S.draft = a.lat ? { lat: a.lat, lng: a.lng, map_address: a.map_address } : {};
      openForm();
    }
    function readForm(ov) {
      ['first_name', 'last_name', 'phone', 'province', 'city', 'address', 'plaque', 'postcode'].forEach(k => {
        const el = ov.querySelector('[name=' + k + ']'); if (el) S.form[k] = el.value;
      });
    }
    function openForm() {
      const f = S.form, d = S.draft, hasLoc = !!d.lat && hasMap;
      const ov = openSheet('<div class="dsn-sh-h"><span>' + esc(T.form_title) + '</span><button class="dsn-x" type="button">×</button></div>' +
        '<div class="dsn-row keep"><label class="dsn-f"><span>' + esc(T.first_name) + '</span><input name="first_name" autocomplete="given-name" value="' + esc(f.first_name) + '"></label>' +
        '<label class="dsn-f"><span>' + esc(T.last_name) + '</span><input name="last_name" autocomplete="family-name" value="' + esc(f.last_name) + '"></label></div>' +
        (C.needPhone ? '<label class="dsn-f"><span>' + esc(T.phone) + '</span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="11" placeholder="09123456789" value="' + esc(f.phone) + '"></label>' : '') +
        (hasLoc ? '<div class="dsn-mapbox" style="margin-top:12px"><div class="dsn-map sm"></div><div class="dsn-pin" style="transform:rotate(-45deg) scale(.7)"></div></div>' +
          '<button type="button" class="dsn-link js-editloc">' + esc(T.edit_loc) + '</button>' +
          '<label class="dsn-f" style="margin-top:0"><span>' + esc(T.map_addr) + '</span><input readonly value="' + esc(d.map_address) + '"></label>' : '') +
        '<div class="dsn-hint">' + esc(T.form_hint) + '</div>' +
        '<div class="dsn-row keep"><label class="dsn-f"><span>' + esc(T.province) + '</span><select name="province"><option value="">' + esc(T.province_ph || 'انتخاب استان') + '</option>' +
        C.provinces.map(p => '<option' + (p === f.province ? ' selected' : '') + '>' + esc(p) + '</option>').join('') + '</select></label>' +
        '<label class="dsn-f"><span>' + esc(T.city) + '</span><select name="city" disabled><option value="">' + esc(T.city_first || 'ابتدا استان را انتخاب کنید') + '</option></select></label></div>' +
        '<label class="dsn-f"><span>' + esc(T.address) + '</span><textarea name="address" rows="2">' + esc(f.address) + '</textarea></label>' +
        '<div class="dsn-row keep"><label class="dsn-f"><span>' + esc(T.plaque) + '</span><input name="plaque" value="' + esc(f.plaque) + '"></label>' +
        '<label class="dsn-f"><span>' + esc(T.postcode) + '</span><input name="postcode" inputmode="numeric" maxlength="10" value="' + esc(f.postcode) + '"></label></div>' +
        '<div class="dsn-err"></div>' +
        '<button type="button" class="dsn-btn js-save">' + esc(T.save) + '</button>' +
        (f.id ? '<button type="button" class="dsn-link js-del">' + esc(T.delete) + '</button>' : ''));

      // شهرهای هر استان به‌صورت داینامیک
      const provSel = ov.querySelector('[name=province]'), citySel = ov.querySelector('[name=city]');
      const fillCities = (prov, selected) => loadCities().then(map => {
        const list = (map && map[prov]) || [];
        if (!prov) { citySel.disabled = true; citySel.innerHTML = '<option value="">' + esc(T.city_first || 'ابتدا استان را انتخاب کنید') + '</option>'; return; }
        let sel = selected ? (list.find(c => norm(c) === norm(selected)) || '') : '';
        let html = '<option value="">' + esc(T.city_ph || 'انتخاب شهر') + '</option>' + list.map(c => '<option' + (c === sel ? ' selected' : '') + '>' + esc(c) + '</option>').join('');
        if (selected && !sel) html += '<option selected>' + esc(selected) + '</option>'; // شهر تشخیص‌داده‌شده‌ای که در لیست نیست
        citySel.innerHTML = html; citySel.disabled = false;
      });
      fillCities(provSel.value, f.city);
      provSel.addEventListener('change', () => { S.form.city = ''; fillCities(provSel.value, ''); });

      if (hasLoc) setTimeout(() => {
        ov._map = makeMap(ov.querySelector('.dsn-map'), [+d.lat, +d.lng], 17, { zoomControl: false, dragging: false, scrollWheelZoom: false, touchZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false, attributionControl: false });
        setTimeout(() => ov._map && ov._map.invalidateSize(), 350);
      }, 60);
      const el = ov.querySelector('.js-editloc');
      if (el) el.onclick = () => { readForm(ov); closeAll(); openMapSheet(); };
      const del = ov.querySelector('.js-del');
      if (del) del.onclick = () => { if (!pv && confirm('این آدرس حذف شود؟')) ajax('delete', { id: f.id }).then(r => { if (r.success) { S.addresses = r.data.addresses; S.selected = r.data.selected; closeAll(); renderList(); } }); };
      ov.querySelector('.js-save').onclick = function () {
        if (pv) return;
        readForm(ov);
        const g = S.form, err = ov.querySelector('.dsn-err');
        g.postcode = fa2en(g.postcode).replace(/\D/g, ''); g.plaque = fa2en(g.plaque); g.phone = fa2en(g.phone).replace(/\D/g, '');
        if (!g.first_name || !g.last_name || !g.province || !g.city || !g.address || !g.plaque || !g.postcode) return err.textContent = 'لطفاً همه فیلدها را تکمیل کنید.';
        if (C.needPhone && !/^09\d{9}$/.test(g.phone || '')) return err.textContent = 'شماره موبایل معتبر نیست (مثال: 09123456789).';
        if (g.postcode.length !== 10) return err.textContent = 'کدپستی باید ۱۰ رقم باشد.';
        err.textContent = ''; this.disabled = true;
        const payload = Object.assign({}, g, { lat: S.draft.lat || '', lng: S.draft.lng || '', map_address: S.draft.map_address || '' });
        ajax('save', { data: JSON.stringify(payload) }).then(r => {
          if (!r.success) { this.disabled = false; return err.textContent = (r.data && r.data.message) || 'خطا در ثبت'; }
          S.addresses = r.data.addresses; S.selected = r.data.selected; closeAll(); renderList();
        });
      };
    }

    /* ---------- summary + gateways ---------- */
    function renderSummary(d) {
      const a = d.address;
      view.innerHTML = '<div class="dsn-wrap"><h3>' + esc(T.sum_title) + '</h3>' +
        '<div class="dsn-sum"><b>' + esc(T.deliver_to) + '</b><div class="dsn-sum-addr">' + esc(a.first_name + ' ' + a.last_name) + '<br>' + esc(fullAddr(a)) + '</div></div>' +
        '<div class="dsn-sum">' + d.items.map(i => '<div class="dsn-line"><span>' + i.name + ' × ' + i.qty + '</span><span>' + i.total + '</span></div>').join('') +
        d.rows.map((x, i) => '<div class="dsn-line' + (i === d.rows.length - 1 ? ' t' : '') + '"><span>' + esc(x[0]) + '</span><span>' + x[1] + '</span></div>').join('') + '</div>' +
        '<button type="button" class="dsn-btn js-pay">' + esc(T.pay) + '</button>' +
        '<button type="button" class="dsn-btn dsn-btn-ghost js-back">' + esc(T.back) + '</button></div>';
    }
    function showSummary() {
      if (pv) return demoSummary();
      view.innerHTML = loaderHTML(T.loading || 'لطفاً منتظر بمانید');
      ajax('summary').then(r => {
        if (!r.success) { alert((r.data && r.data.message) || 'خطا'); return renderList(); }
        renderSummary(r.data);
      });
    }
    function demoSummary() {
      renderSummary({ address: DEMO_ADDR[0], items: [{ name: 'محصول نمونه', qty: 2, total: '۱,۲۰۰,۰۰۰ تومان' }, { name: 'محصول دوم', qty: 1, total: '۴۵۰,۰۰۰ تومان' }],
        rows: [['جمع جزء', '۱,۶۵۰,۰۰۰ تومان'], ['حمل و نقل', '۶۰,۰۰۰ تومان'], ['مبلغ قابل پرداخت', '۱,۷۱۰,۰۰۰ تومان']] });
    }
    function openGateways() {
      if (pv) return gatewaySheet([{ id: 'a', title: 'درگاه پرداخت آنلاین', desc: 'پرداخت امن با کارت‌های عضو شتاب', icon: '' }, { id: 'b', title: 'پرداخت در محل', desc: '', icon: '' }]);
      const btn = view.querySelector('.js-pay'); btn.disabled = true;
      ajax('gateways').then(r => {
        btn.disabled = false;
        if (!r.success) return alert((r.data && r.data.message) || 'خطا');
        gatewaySheet(r.data);
      });
    }
    function gatewaySheet(list) {
      let sel = list[0].id;
      const ov = openSheet('<div class="dsn-sh-h"><span>' + esc(T.gw_title) + '</span><button class="dsn-x" type="button">×</button></div>' +
        list.map((g, i) => '<div class="dsn-gw' + (i ? '' : ' is-on') + '" data-g="' + esc(g.id) + '"><span class="dsn-box"></span><span style="flex:1"><b>' + esc(g.title) + '</b>' + (g.desc ? '<br><small>' + esc(g.desc) + '</small>' : '') + '</span>' + g.icon + '</div>').join('') +
        '<div class="dsn-err"></div><button type="button" class="dsn-btn js-go">' + esc(T.gw_confirm) + '</button>');
      ov.addEventListener('click', e => {
        const g = e.target.closest('.dsn-gw'); if (!g) return;
        sel = g.dataset.g; ov.querySelectorAll('.dsn-gw').forEach(x => x.classList.toggle('is-on', x === g));
      });
      ov.querySelector('.js-go').onclick = function () {
        if (pv) return;
        this.disabled = true; this.textContent = 'در حال اتصال به درگاه...';
        ajax('pay', { gateway: sel }).then(p => {
          if (p.success && p.data.redirect) return (window.location.href = p.data.redirect);
          this.disabled = false; this.textContent = T.gw_confirm;
          ov.querySelector('.dsn-err').textContent = (p.data && p.data.message) || 'خطا در پرداخت';
        });
      };
    }

    /* ---------- boot ---------- */
    loadCities(); // پیش‌بارگذاری لیست شهرها
    if (pv === 'loading') { view.innerHTML = loaderHTML(T.loading || 'لطفاً منتظر بمانید'); return; }
    if (pv) {
      renderList();
      setTimeout(() => {
        if (pv === 'map') openMapSheet();
        else if (pv === 'form') { S.form = { first_name: 'علی', last_name: 'رضایی', phone: '09123456789', province: 'تهران', city: 'تهران', address: 'خیابان ولیعصر، کوچه شماره ۲', plaque: '12', postcode: '1234567890' }; S.draft = { lat: M.lat || 35.6892, lng: M.lng || 51.389, map_address: 'تهران، خیابان ولیعصر، کوچه شماره ۲' }; openForm(); }
        else if (pv === 'summary') demoSummary();
        else if (pv === 'gateways') { demoSummary(); gatewaySheet([{ id: 'a', title: 'درگاه پرداخت آنلاین', desc: 'پرداخت امن با کارت‌های عضو شتاب', icon: '' }, { id: 'b', title: 'پرداخت در محل', desc: '', icon: '' }]); }
      }, 100);
    } else {
      ajax('list').then(r => {
        if (r.success) { nonce = r.data.nonce; S.addresses = r.data.addresses; S.selected = r.data.selected; }
        renderList();
      });
    }
  }

  window.dsnInit = init;
  const boot = () => document.querySelectorAll('.dsn-root:not([data-ready])').forEach(init);
  window.addEventListener('elementor/frontend/init', function () {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction('frontend/element_ready/dsn_address_flow.default', function ($el) {
        init($el[0].querySelector('.dsn-root'));
      });
    }
  });
  if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
})();
