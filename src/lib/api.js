import { getThemeSettings } from "./theme-settings.js";

export function siteUrl(name) {
  const urls = getThemeSettings().urls ?? {};
  return urls[name] || urls.home || "./";
}

export function siteLink(value) {
  if (typeof value !== "string" || !/^\/(?!\/)/.test(value)) return value;
  const base = new URL(
    siteUrl("home"),
    typeof window === "undefined" ? undefined : window.location.href,
  );
  const url = new URL(value, base.origin);
  const prefix = base.pathname.replace(/\/+$/, "");
  return prefix &&
    (url.pathname === prefix || url.pathname.startsWith(prefix + "/"))
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
