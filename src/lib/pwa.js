/*
 * R88 — ثبتِ service worker و نوارِ «افزودن به صفحهٔ اصلی».
 *
 * - ثبت بعد از رویدادِ load (هیچ رقابتی با منابعِ مهمِ صفحه ندارد).
 * - updateViaCache:"none" ⇒ خودِ فایلِ sw هیچ‌وقت از کشِ HTTP خوانده نمی‌شود.
 * - اگر PWA در تنظیمات خاموش باشد، هر service workerِ قبلیِ همین پوسته
 *   (scriptURL شاملِ jluxe_pwa=sw) از ثبت خارج می‌شود — افزون بر نسخهٔ
 *   «خودحذف‌کن» که سرور در همان آدرس برمی‌گرداند.
 * - نوار فقط در موبایل/تبلت، وقتی مرورگر واقعاً نصب‌پذیری را اعلام کند
 *   (beforeinstallprompt)، و بیرون از سبد/تسویه/حساب/محصول نشان داده می‌شود.
 * - PROMPT_DELAY فقط تأخیرِ پیش از نمایش است؛ پس از ظاهرشدن، ۴ ثانیهٔ بی‌تعامل
 *   بعد خودکار بسته می‌شود. «بعداً» انتخابِ کاربر است و ۳۰ روز snooze می‌کند.
 */

export const DISMISS_KEY = "jluxe-pwa-dismissed";
export const AUTO_DISMISS_SESSION_KEY = "jluxe-pwa-auto-dismissed";
export const DISMISS_DAYS = 30;
export const PROMPT_DELAY = 4000;
export const AUTO_DISMISS_MS = 4000;
const SW_MARKER = "jluxe_pwa=sw";

function storage(win) {
  try {
    return win.localStorage || null;
  } catch {
    return null;
  }
}

function autoDismissedThisSession(win) {
  try {
    return win.sessionStorage?.getItem(AUTO_DISMISS_SESSION_KEY) === "1";
  } catch {
    return false;
  }
}

function rememberAutoDismiss(win) {
  try {
    win.sessionStorage?.setItem(AUTO_DISMISS_SESSION_KEY, "1");
  } catch {
    /* حالت خصوصی/فضای ذخیرهٔ مسدود — فقط همین صفحه بسته می‌شود. */
  }
}

export function recentlyDismissed(win, now = Date.now()) {
  const store = storage(win);
  if (!store) return false;
  const at = Number(store.getItem(DISMISS_KEY) || 0);
  return at > 0 && now - at < DISMISS_DAYS * 864e5;
}

export function rememberDismiss(win, now = Date.now()) {
  const store = storage(win);
  try {
    store?.setItem(DISMISS_KEY, String(now));
  } catch {
    /* حالتِ خصوصی/پرشدنِ فضا — فقط همین دفعه بسته می‌شود. */
  }
}

export function canShowInstallPrompt(win, settings) {
  if (!settings?.enabled || !settings.installPrompt || settings.suppressPrompt || autoDismissedThisSession(win)) {
    return false;
  }
  const mq = (query) => Boolean(win.matchMedia?.(query).matches);
  if (!mq("(max-width: 1023px)") || mq("(display-mode: standalone)")) {
    return false;
  }
  return !recentlyDismissed(win);
}

async function unregisterOwn(win) {
  const registrations = (await win.navigator.serviceWorker.getRegistrations?.()) || [];
  await Promise.all(
    registrations
      .filter((registration) => {
        const worker = registration.active || registration.waiting || registration.installing;
        return worker && String(worker.scriptURL).includes(SW_MARKER);
      })
      .map((registration) => registration.unregister()),
  );
  // کش‌های همین پوسته هم پاک می‌شوند تا چیزی یتیم نماند.
  const keys = (await win.caches?.keys?.()) || [];
  await Promise.all(keys.filter((key) => key.indexOf("jluxe-") === 0).map((key) => win.caches.delete(key)));
}

export function registerServiceWorker(win, settings) {
  const sw = win.navigator?.serviceWorker;
  if (!sw || !win.isSecureContext) return Promise.resolve("unsupported");
  if (!settings?.enabled || !settings.sw) {
    return unregisterOwn(win).then(() => "unregistered", () => "unregistered");
  }
  return sw
    .register(settings.sw, { scope: settings.scope || "/", updateViaCache: "none" })
    .then(
      () => "registered",
      () => "failed",
    );
}

export function buildInstallBanner(doc, { name, onInstall, onDismiss }) {
  const banner = doc.createElement("div");
  banner.className = "jluxe-pwa-install";
  banner.setAttribute("role", "region");
  banner.setAttribute("aria-label", "نصب اپلیکیشن");
  banner.dir = "rtl";

  const text = doc.createElement("p");
  text.className = "jluxe-pwa-install__text";
  const strong = doc.createElement("strong");
  strong.textContent = name || "فروشگاه";
  text.append(strong, doc.createTextNode(" را به صفحهٔ اصلیِ گوشی اضافه کنید؛ سریع‌تر و بدونِ نوارِ مرورگر."));

  const actions = doc.createElement("div");
  actions.className = "jluxe-pwa-install__actions";
  const install = doc.createElement("button");
  install.type = "button";
  install.className = "jluxe-pwa-install__primary";
  install.textContent = "نصب";
  install.addEventListener("click", onInstall);
  const later = doc.createElement("button");
  later.type = "button";
  later.className = "jluxe-pwa-install__later";
  later.textContent = "بعداً";
  later.addEventListener("click", onDismiss);
  actions.append(install, later);

  banner.append(text, actions);
  return banner;
}

export function setupInstallPrompt(
  win,
  settings,
  { delay = PROMPT_DELAY, autoDismissAfter = AUTO_DISMISS_MS } = {},
) {
  const doc = win.document;
  let deferred = null;
  let banner = null;
  let timer = 0;
  let autoDismissTimer = 0;

  const remove = () => {
    win.clearTimeout(timer);
    win.clearTimeout(autoDismissTimer);
    timer = 0;
    autoDismissTimer = 0;
    banner?.remove();
    banner = null;
    doc.removeEventListener("keydown", onKey);
  };
  const scheduleAutoDismiss = () => {
    win.clearTimeout(autoDismissTimer);
    if (!banner || autoDismissAfter <= 0) return;
    autoDismissTimer = win.setTimeout(() => {
      if (!banner) return;
      if (banner.contains(doc.activeElement)) {
        scheduleAutoDismiss();
        return;
      }
      rememberAutoDismiss(win);
      deferred = null;
      remove();
    }, autoDismissAfter);
  };
  const pauseAutoDismiss = () => {
    win.clearTimeout(autoDismissTimer);
    autoDismissTimer = 0;
  };
  const resumeAutoDismiss = () => {
    if (banner) scheduleAutoDismiss();
  };
  const dismiss = () => {
    rememberDismiss(win);
    remove();
  };
  function onKey(event) {
    if (event.key === "Escape") dismiss();
  }
  const install = async () => {
    const prompt = deferred;
    deferred = null;
    remove();
    if (!prompt) return;
    prompt.prompt();
    try {
      const choice = await prompt.userChoice;
      if (choice?.outcome !== "accepted") rememberDismiss(win);
    } catch {
      /* ignore */
    }
  };

  win.addEventListener("beforeinstallprompt", (event) => {
    if (!canShowInstallPrompt(win, settings)) return; // نوارِ پیش‌فرضِ مرورگر دست‌نخورده می‌ماند.
    event.preventDefault();
    deferred = event;
    win.clearTimeout(timer);
    timer = win.setTimeout(() => {
      if (banner || !deferred) return;
      banner = buildInstallBanner(doc, { name: settings.name, onInstall: install, onDismiss: dismiss });
      doc.body.append(banner);
      doc.addEventListener("keydown", onKey);
      banner.addEventListener("pointerenter", pauseAutoDismiss);
      banner.addEventListener("pointerleave", resumeAutoDismiss);
      banner.addEventListener("focusin", pauseAutoDismiss);
      banner.addEventListener("focusout", (focusEvent) => {
        if (!banner?.contains(focusEvent.relatedTarget)) resumeAutoDismiss();
      });
      scheduleAutoDismiss();
    }, delay);
  });
  win.addEventListener("appinstalled", () => {
    deferred = null;
    remove();
  });
  return { remove };
}

export function setupPwa(win, settings) {
  if (!settings || !win.navigator?.serviceWorker) return;
  const start = () => registerServiceWorker(win, settings);
  if (win.document.readyState === "complete") start();
  else win.addEventListener("load", start, { once: true });
  if (settings.enabled && settings.installPrompt) setupInstallPrompt(win, settings);
}
