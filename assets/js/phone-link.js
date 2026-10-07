(function () {
  'use strict';
  document.querySelectorAll('[data-jluxe-phone-link]').forEach(function (form) {
    var busy = false;
    var status = form.querySelector('[data-link-status]');
    async function submit(link) {
      if (busy) return;
      var phone = JLuxeStorefrontUtils.normalizePhone(form.elements.phone.value);
      var code = JLuxeStorefrontUtils.normalizeDigits(form.elements.code.value).trim();
      if (!phone || (link && !/^[0-9]{6}$/.test(code))) {
        status.textContent = 'شماره موبایل یا کد تأیید معتبر نیست.';
        return;
      }
      busy = true;
      form.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
      status.textContent = 'در حال بررسی…';
      try {
        var sessionResponse = await fetch(form.dataset.sessionUrl, { method: 'POST', credentials: 'same-origin', cache: 'no-store', body: new URLSearchParams({ action: 'jluxe_session' }) });
        var session = await sessionResponse.json();
        if (!session.success || !session.data.restNonce) throw new Error('نشست منقضی شده؛ دوباره وارد شوید.');
        var response = await fetch(link ? form.dataset.confirmUrl : form.dataset.requestUrl, {
          method: 'POST', credentials: 'same-origin', cache: 'no-store',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': session.data.restNonce },
          body: JSON.stringify({ phone: phone, code: code, intent: 'link' }),
        });
        var data = await response.json();
        if (!response.ok) throw new Error(data.message || 'درخواست انجام نشد.');
        status.textContent = link ? 'شماره برای ورود پیامکی تأیید شد.' : 'کد ۶ رقمی ارسال شد؛ اعتبار آن دو دقیقه است.';
        if (link) form.elements.code.value = '';
      } catch (error) {
        status.textContent = error.message || 'ارتباط با سرور برقرار نشد.';
      } finally {
        busy = false;
        form.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
      }
    }
    form.querySelector('[data-send-code]').addEventListener('click', function () { submit(false); });
    form.addEventListener('submit', function (event) { event.preventDefault(); submit(true); });
  });
}());
