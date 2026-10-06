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

function streamError(message, code = "jluxe_ai_stream_error", status = 0) {
  const error = new Error(message || "ارتباط با سرور برقرار نشد.");
  error.code = code;
  error.status = status;
  return error;
}

/** Read same-origin SSE while retaining the nonce refresh behavior of restRequest. */
export async function restStreamRequest(
  path,
  body,
  onEvent = () => {},
  { authenticated = true, retryNonce = true } = {},
) {
  const session = authenticated ? await getSession() : null;
  const headers = {
    "Content-Type": "application/json",
    Accept: "text/event-stream",
  };
  if (session?.restNonce) headers["X-WP-Nonce"] = session.restNonce;
  const response = await fetch(restUrl(path), {
    method: "POST",
    headers,
    credentials: "same-origin",
    cache: "no-store",
    body: JSON.stringify(body),
  });

  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    if (
      authenticated &&
      retryNonce &&
      data.code === "rest_cookie_invalid_nonce"
    ) {
      await getSession(true);
      return restStreamRequest(path, body, onEvent, { authenticated, retryNonce: false });
    }
    throw streamError(
      data.message || "ارتباط با سرور برقرار نشد.",
      data.code || "jluxe_ai_stream_error",
      response.status,
    );
  }

  const contentType = response.headers?.get?.("content-type")?.toLowerCase() || "";
  if (contentType.includes("application/json")) {
    const data = await response.json().catch(() => ({}));
    if (typeof data.reply === "string") {
      if (data.reply) onEvent({ type: "delta", text: data.reply });
      return {
        reply: data.reply,
        products: Array.isArray(data.products) ? data.products : [],
      };
    }
    throw streamError(
      data.message || "stream در این سرور در دسترس نیست.",
      data.code || "jluxe_ai_stream_unavailable",
      response.status,
    );
  }
  if (!contentType.includes("text/event-stream")) {
    throw streamError("پاسخِ سرور قالبِ stream را ندارد.", "jluxe_ai_stream_unavailable", response.status);
  }

  let lineBuffer = "";
  let eventName = "";
  let dataLines = [];
  let pendingCR = false;
  let accumulated = "";
  let completion = null;

  const dispatch = () => {
    if (!dataLines.length) {
      eventName = "";
      dataLines = [];
      return;
    }
    const raw = dataLines.join("\n");
    let payload;
    try {
      payload = JSON.parse(raw);
    } catch {
      payload = { text: raw };
    }
    const name = eventName || "message";
    if (name === "delta" && typeof payload.text === "string") {
      accumulated += payload.text;
      onEvent({ type: "delta", text: payload.text });
    } else if (name === "done") {
      completion = payload;
      if (typeof payload.reply === "string" && payload.reply !== accumulated) {
        accumulated = payload.reply;
        onEvent({ type: "replace", text: payload.reply });
      }
    } else if (name === "error") {
      throw streamError(
        payload.message || "دستیار نتوانست پاسخ را کامل کند.",
        payload.code || "jluxe_ai_stream_error",
        response.status,
      );
    } else if (name === "message" && typeof payload.text === "string") {
      // Some compatible gateways omit `event: delta` but keep the JSON data shape.
      accumulated += payload.text;
      onEvent({ type: "delta", text: payload.text });
    }
    eventName = "";
    dataLines = [];
  };

  const consumeLine = (line) => {
    if (line === "") {
      dispatch();
      return;
    }
    if (line.startsWith(":")) return;
    const colon = line.indexOf(":");
    const field = colon === -1 ? line : line.slice(0, colon);
    let value = colon === -1 ? "" : line.slice(colon + 1);
    if (value.startsWith(" ")) value = value.slice(1);
    if (field === "event") eventName = value;
    else if (field === "data") dataLines.push(value);
  };

  const feed = (text, final = false) => {
    if (pendingCR) {
      text = `\r${text}`;
      pendingCR = false;
    }
    let buffer = lineBuffer + text;
    if (!final && buffer.endsWith("\r")) {
      pendingCR = true;
      buffer = buffer.slice(0, -1);
    }
    buffer = buffer.replace(/\r\n|\r/g, "\n");
    let newline;
    while ((newline = buffer.indexOf("\n")) !== -1) {
      consumeLine(buffer.slice(0, newline));
      buffer = buffer.slice(newline + 1);
    }
    lineBuffer = buffer;
    if (final) {
      if (lineBuffer) consumeLine(lineBuffer);
      lineBuffer = "";
      consumeLine("");
      pendingCR = false;
    }
  };

  const reader = response.body?.getReader?.();
  if (reader) {
    const decoder = new TextDecoder();
    try {
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        feed(decoder.decode(value, { stream: true }));
      }
      feed(decoder.decode(), true);
    } catch (error) {
      await reader.cancel().catch(() => {});
      throw error;
    }
  } else if (typeof response.text === "function") {
    feed(await response.text(), true);
  } else {
    throw streamError("مرورگر از خواندنِ پاسخِ stream پشتیبانی نمی‌کند.", "jluxe_ai_stream_unavailable");
  }

  if (!completion) {
    throw streamError("جریانِ پاسخ پیش از پایان بسته شد.", "jluxe_ai_stream_incomplete");
  }
  return {
    reply: typeof completion.reply === "string" ? completion.reply : accumulated,
    products: Array.isArray(completion.products) ? completion.products : [],
  };
}

/**
 * R63 / R135: registry درخواست‌های GET بر پایهٔ کلیدِ واحد.
 * caller های هم‌زمان یک Promise مشترک می‌گیرند؛ نتیجه فقط تا TTL کوتاه
 * نگه داشته می‌شود، خطا cache نمی‌شود و تعداد نتیجه‌های نگهداری‌شده محدود است.
 */
const inflightGets = new Map();
const resolvedGets = new Map();

export function dedupeGet(key, fetcher, { ttl = 30_000, now = Date.now } = {}) {
  const ttlMs = Number.isFinite(ttl) ? Math.max(0, ttl) : 0;
  const timestamp = now();
  const cached = resolvedGets.get(key);
  if (ttlMs > 0 && cached && timestamp < cached.expiresAt) return cached.promise;
  resolvedGets.delete(key);
  const pending = inflightGets.get(key);
  if (pending) return pending;
  const promise = Promise.resolve()
    .then(fetcher)
    .then((value) => {
      if (ttlMs > 0) {
        const cachedAt = now();
        resolvedGets.set(key, { promise, expiresAt: cachedAt + ttlMs });
        for (const [cachedKey, entry] of resolvedGets) {
          if (entry.expiresAt <= cachedAt) resolvedGets.delete(cachedKey);
        }
        while (resolvedGets.size > 128) {
          resolvedGets.delete(resolvedGets.keys().next().value);
        }
      }
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
