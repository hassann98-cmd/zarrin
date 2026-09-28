import { getThemeSettings } from "./theme-settings.js";

export function siteUrl(name) {
  const urls = getThemeSettings().urls ?? {};
  return urls[name] || urls.home || "./";
}

/*
 * R87 — مسیرهایی که مدیر در «تنظیمات ← آدرس‌ها» عوض‌شان می‌کند. همان نگاشتِ
 * jluxe_resolve_site_link() سمتِ PHP (inc/urls.php): اسلاگِ خامِ «/track-order/»
 * یا «/shop/» که در تنظیماتِ فوتر یا پیش‌فرض‌ها ذخیره شده، به آدرسِ واقعیِ
 * تنظیم‌شده می‌رود، نه به مسیرِ ثابت. فقط اسلاگِ تکی و بدونِ ?/# نگاشت می‌شود.
 */
const ROUTE_SLUGS = {
  "track-order": "track_order",
  shop: "shop",
  "فروشگاه": "shop",
};

export function siteLink(value) {
  if (typeof value !== "string" || !/^\/(?!\/)/.test(value)) return value;
  const base = new URL(
    siteUrl("home"),
    typeof window === "undefined" ? undefined : window.location.href,
  );
  const url = new URL(value, base.origin);
  const prefix = base.pathname.replace(/\/+$/, "");
  const inside =
    prefix &&
    (url.pathname === prefix || url.pathname.startsWith(prefix + "/"));
  if (!url.search && !url.hash) {
    const rel = (inside ? url.pathname.slice(prefix.length) : url.pathname)
      .replace(/^\/+|\/+$/g, "");
    let slug = rel;
    try {
      slug = decodeURIComponent(rel);
    } catch {
      /* نویسهٔ نامعتبر: همان اسلاگِ خام */
    }
    const key = ROUTE_SLUGS[slug];
    const routed = key ? (getThemeSettings().urls ?? {})[key] : "";
    if (routed) return routed;
  }
  return inside
    ? url.toString()
    : new URL(value.replace(/^\/+/, ""), base).toString();
}

/** rest_url() may use either pretty permalinks or ?rest_route=/jluxe/v1/. */
export function restUrl(path, root = getThemeSettings().rest?.root) {
  if (!root)
    throw new Error(
      "پیکربندی ارتباط با سرور در دسترس نیست. صفحه را تازه کنید.",
    );
  const url = new URL(
    root,
    typeof window === "undefined" ? undefined : window.location.href,
  );
  const suffix = path.replace(/^\/+/, "");
  if (url.searchParams.has("rest_route")) {
    url.searchParams.set(
      "rest_route",
      `${url.searchParams.get("rest_route").replace(/\/+$/, "")}/${suffix}`,
    );
  } else {
    url.pathname = `${url.pathname.replace(/\/+$/, "")}/${suffix}`;
  }
  return url.toString();
}

let sessionPromise;
let sessionPending = false;
/** Fresh cookie identity/nonce from a non-cacheable, same-origin WordPress AJAX response. */
export function getSession(refresh = false) {
  // A request already in flight is fresh too; concurrent nonce failures share it.
  if (refresh && !sessionPending) sessionPromise = undefined;
  if (!sessionPromise) {
    const endpoint = getThemeSettings().rest?.sessionUrl;
    if (!endpoint)
      return Promise.reject(new Error("پیکربندی نشست در دسترس نیست."));
    sessionPending = true;
    sessionPromise = fetch(endpoint, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      body: new URLSearchParams({ action: "jluxe_session" }),
    })
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok || !result.success)
          throw new Error("نشست معتبر نیست؛ صفحه را تازه کنید.");
        return result.data;
      })
      .catch((error) => {
        sessionPromise = undefined;
        throw error;
      })
      .finally(() => {
        sessionPending = false;
      });
  }
  return sessionPromise;
}

export async function restRequest(
  path,
  body,
  { authenticated = true, retryNonce = true } = {},
) {
  const session = authenticated ? await getSession() : null;
  const headers = { "Content-Type": "application/json" };
  if (session?.restNonce) headers["X-WP-Nonce"] = session.restNonce;
  const response = await fetch(restUrl(path), {
    method: "POST",
    headers,
    credentials: "same-origin",
    cache: "no-store",
    body: JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    // Invalid REST nonces fail before the callback; retrying this particular error is safe.
    if (
      authenticated &&
      retryNonce &&
      data.code === "rest_cookie_invalid_nonce"
    ) {
      await getSession(true);
      return restRequest(path, body, { authenticated, retryNonce: false });
    }
    throw new Error(data.message || "خطایی پیش آمد. دوباره تلاش کنید.");
  }
  return data;
}

/**
 * R63 (فاز ۱): registry یکپارچهٔ درخواست‌های GET — کلیدِ واحد، یک fetch.
 * چند island که هم‌زمان همان داده را می‌خواهند (محصولات/دسته‌ها/برندها)
 * یک Promise مشترک می‌گیرند؛ نتیجه تا ttl در حافظه می‌ماند و خطا کش
 * نمی‌شود. زیرساختِ data-registry سمتِ JS — caller ها فقط fetcher می‌دهند.
 */
const inflightGets = new Map();
const resolvedGets = new Map();

export function dedupeGet(key, fetcher, { ttl = 30_000, now = Date.now } = {}) {
  const cached = resolvedGets.get(key);
  if (cached && now() - cached.at < ttl) return cached.promise;
  resolvedGets.delete(key);
  const pending = inflightGets.get(key);
  if (pending) return pending;
  const promise = Promise.resolve()
    .then(fetcher)
    .then((value) => {
      resolvedGets.set(key, { promise, at: now() });
      return value;
    })
    .finally(() => {
      inflightGets.delete(key);
    });
  inflightGets.set(key, promise);
  return promise;
}

/** فقط برای تست‌ها — وضعیتِ registry را خالی می‌کند. */
export function resetDedupeGet() {
  inflightGets.clear();
  resolvedGets.clear();
}
