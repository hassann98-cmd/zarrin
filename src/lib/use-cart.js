import { useCallback, useEffect, useState } from "react";
import { getThemeSettings } from "./theme-settings.js";
import { getSession, siteUrl } from "./api.js";

const empty = {
  items: [],
  itemCount: 0,
  subtotalHtml: "",
  coupons: [],
  discountHtml: null,
  freeShipping: null,
  cartUrl: siteUrl("cart"),
  checkoutUrl: siteUrl("checkout"),
};
let snapshot = {
  ...empty,
  itemCount: getThemeSettings().cart?.initialCount ?? 0,
};
let authoritativeSnapshot = snapshot;
const pendingRemovals = new Set();
const pendingQuantities = new Map();
let readFlight;
let writeEpoch = 0;
let loaded = false;
let revision = 0;
let initialRequest;
let queue = Promise.resolve();
const subscribers = new Set();
// Each DOM event is shared by header, drawer and mobile subscribers.
const refreshEvents = new WeakMap();
function applyPendingRemovals(next) {
  if ((!pendingRemovals.size && !pendingQuantities.size) || !Array.isArray(next.items)) return next;
  let removedQuantity = 0;
  const items = next.items.flatMap((item) => {
    const key = String(item.key);
    const desired = pendingQuantities.get(key)?.qty;
    const qty = pendingRemovals.has(key) ? 0 : desired ?? Number(item.qty);
    removedQuantity += Number(item.qty) - qty;
    return qty > 0 ? [{ ...item, qty }] : [];
  });
  const count = Number(next.itemCount);
  return {
    ...next,
    items,
    itemCount: Number.isFinite(count)
      ? Math.max(0, count - removedQuantity)
      : next.itemCount,
  };
}

function publish(next) {
  authoritativeSnapshot = next;
  snapshot = applyPendingRemovals(next);
  loaded = true;
  revision += 1;
  subscribers.forEach((subscriber) => subscriber(snapshot));
}

function isCartSnapshot(value) {
  return !!value && Array.isArray(value.items) &&
    Number.isInteger(value.itemCount) && value.itemCount >= 0 &&
    typeof value.subtotalHtml === "string" &&
    value.items.every(item => item && typeof item === "object" &&
      typeof item.key === "string" && item.key.length > 0 &&
      Number.isFinite(item.qty) && item.qty > 0);
}

// Bound fetch AND response parsing. A local abort never proves server rollback.
function withCartDeadline(task) {
  const controller = typeof AbortController === "function" ? new AbortController() : null;
  return new Promise((resolve, reject) => {
    let settled = false;
    const timer = setTimeout(() => {
      if (settled) return;
      settled = true;
      controller?.abort();
      reject(new Error("Cart result unknown"));
    }, 20000);
    Promise.resolve().then(() => task(controller?.signal)).then(value => {
      if (settled) return;
      settled = true; clearTimeout(timer); resolve(value);
    }, error => {
      if (settled) return;
      settled = true; clearTimeout(timer); reject(error);
    });
  });
}

/** Serialize drawer requests; never turn a network/server rejection into a successful mutation. */
export function cartRequest(operation, values = {}) {
  const plainRead = operation === "get" && Object.keys(values).length === 0;
  if (plainRead && readFlight?.epoch === writeEpoch) return readFlight.promise;
  if (operation !== "get") writeEpoch += 1;
  let flight;
  const execute = async () => {
    if (flight) flight.started = true;
    const config = getThemeSettings().cart;
    if (!config?.ajaxUrl) return { snapshot, error: "سبد خرید در دسترس نیست." };
    const startedAt = revision;
    const send = (op = operation, input = values) => withCartDeadline(async signal => {
        const fields = { ...input };
        delete fields["add-to-cart"]; // Do not also trigger Woo's native form handler.
        const response = await fetch(config.ajaxUrl, {
          method: "POST",
          signal,
          credentials: "same-origin",
          cache: "no-store",
          body: new URLSearchParams({
            ...fields,
            action: "jluxe_cart",
            nonce: config.nonce,
            op,
          }),
        });
        return { response, result: await response.json() };
    });
    try {
      let { response, result } = await send();
      // Only this nonce error is guaranteed to occur BEFORE the mutation.
      // Never replay a timeout, a malformed response, a stock failure or a 5xx.
      if (
        response.status === 403 &&
        result?.data?.code === "jluxe_cart_invalid_nonce"
      ) {
        const session = await withCartDeadline(() => getSession(true));
        if (!session?.cartNonce)
          return { snapshot, error: "نشست معتبر نیست؛ صفحه را تازه کنید." };
        config.nonce = session.cartNonce;
        ({ response, result } = await send());
      }
      if (response.status >= 500 || !result || typeof result.success !== "boolean" ||
        (result.success && (!response.ok || !isCartSnapshot(result.data)))) {
        throw new Error("Cart result unknown");
      }
      if (!response.ok || !result.success)
        return {
          snapshot,
          error:
            result.data?.message || "درخواست انجام نشد؛ صفحه را تازه کنید.",
        };
      if (result.data && (operation !== "get" || startedAt === revision))
        publish(result.data);
      return { snapshot };
    } catch {
      // Reconcile in this queue slot (never enqueue a nested read behind ourselves).
      // A failed write is NEVER replayed; even a successful read cannot prove
      // whether the original write has finished executing on the server.
      if (operation !== "get") {
        const beforeRead = revision;
        try {
          const { response, result } = await send("get", {});
          if (response.ok && result?.success === true && isCartSnapshot(result.data)) {
            if (revision === beforeRead) publish(result.data);
            return { snapshot, uncertain: true, error: "نتیجهٔ درخواست قبلی تأیید نشد؛ آخرین وضعیت سبد دریافت شد. پیش از ادامه، سبد را بررسی کنید." };
          }
        } catch { /* Keep an explicit unknown state when reconciliation also fails. */ }
        return { snapshot, uncertain: true, error: "نتیجهٔ تغییر سبد مشخص نیست و وضعیت تازه دریافت نشد؛ پیش از تلاش دوباره، صفحهٔ سبد خرید را بررسی کنید." };
      }
      return { snapshot, error: "دریافت وضعیت سبد انجام نشد؛ صفحهٔ سبد خرید را بررسی کنید." };
    }
  };
  queue = queue.then(execute, execute);
  if (plainRead) {
    flight = { epoch: writeEpoch, promise: queue, started: false };
    readFlight = flight;
    queue.finally(() => { if (readFlight === flight) readFlight = undefined; });
  }
  return queue;
}

/** Batch a burst of +/- taps; never invent prices or replay failed mutations. */
export function updateCartQuantity(key, qty) {
  key = String(key);
  qty = Number(qty);
  const item = snapshot.items.find(item => String(item.key) === key);
  if (!item || pendingRemovals.has(key) || !Number.isInteger(qty) || qty < 0 || qty > item.maxQty) {
    return Promise.resolve({ snapshot, error: "تعداد درخواستی معتبر نیست." });
  }
  let entry = pendingQuantities.get(key);
  if (!entry) {
    entry = { qty, waiters: [], inFlight: false };
    pendingQuantities.set(key, entry);
  }
  entry.qty = qty;
  publish(authoritativeSnapshot);
  const promise = new Promise(resolve => entry.waiters.push(resolve));
  const flush = async () => {
    entry.inFlight = true;
    const sent = entry.qty;
    const result = await cartRequest("update_qty", { key, qty: String(sent) });
    entry.inFlight = false;
    if (!result.error && entry.qty !== sent) {
      entry.timer = setTimeout(flush, 140);
      return;
    }
    pendingQuantities.delete(key);
    publish(authoritativeSnapshot);
    for (const resolve of entry.waiters) resolve({ snapshot, error: result.error });
  };
  if (!entry.inFlight) {
    clearTimeout(entry.timer);
    entry.timer = setTimeout(flush, 140);
  }
  return promise;
}

function useCart() {
  const [current, setCurrent] = useState(snapshot);
  const [loading, setLoading] = useState(!loaded && snapshot.itemCount > 0);
  const [couponLoading, setCouponLoading] = useState(false);
  const [error, setError] = useState(null);
  const [couponError, setCouponError] = useState(null);
  const refresh = useCallback(() => {
    setLoading(true);
    return cartRequest("get").then((result) => {
      setLoading(false);
      setError(result.error ?? null);
      return result;
    });
  }, []);
  useEffect(() => {
    let active = true;
    subscribers.add(setCurrent);
    if (!loaded && snapshot.itemCount > 0) {
      initialRequest ||= cartRequest("get").finally(() => {
        initialRequest = null;
      });
      initialRequest.then((result) => {
        if (active) {
          setLoading(false);
          setError(result.error ?? null);
        }
      });
    } else if (!loaded) {
      setLoading(false);
    }
    function refresh(event) {
      if (!refreshEvents.has(event)) {
        const next = event.detail;
        // Events from other theme components/plugins cross the same trust
        // boundary as HTTP replies. Partial/invalid data is only a refresh hint.
        if (isCartSnapshot(next)) {
          publish(next);
          refreshEvents.set(event, Promise.resolve({ snapshot }));
        } else {
          // Do not let an older read overwrite a newer external cart change.
          revision += 1;
          // A new external change invalidates a read already in progress.
          if (readFlight?.started !== false) readFlight = undefined;
          refreshEvents.set(event, cartRequest("get"));
        }
      }
      setLoading(true);
      refreshEvents.get(event).then((result) => {
        if (active) {
          setLoading(false);
          setError(result.error ?? null);
        }
      });
    }
    window.addEventListener("jluxe:cart-updated", refresh);
    return () => {
      active = false;
      subscribers.delete(setCurrent);
      window.removeEventListener("jluxe:cart-updated", refresh);
    };
  }, []);
  const mutate = useCallback(async (operation, values, coupon = false) => {
    const setBusy = coupon ? setCouponLoading : setLoading;
    const setMessage = coupon ? setCouponError : setError;
    setBusy(true);
    setMessage(null);
    const result = await cartRequest(operation, values);
    setMessage(result.error ?? null);
    setBusy(false);
    return !result.error;
  }, []);
  const removeItem = useCallback(async (key) => {
    const itemKey = String(key);
    if (pendingRemovals.has(itemKey) || pendingQuantities.has(itemKey)) return false;
    // Hide the row/count immediately, including across newer snapshots from
    // earlier queued mutations. The authoritative WooCommerce reply replaces
    // the optimistic view; any failure restores the latest server snapshot.
    pendingRemovals.add(itemKey);
    publish(authoritativeSnapshot);
    setLoading(true);
    setError(null);
    const result = await cartRequest("remove", { key: itemKey });
    pendingRemovals.delete(itemKey);
    if (result.error) publish(authoritativeSnapshot);
    setError(result.error ?? null);
    setLoading(false);
    return !result.error;
  }, []);
  return {
    snapshot: current,
    refresh,
    loading: loading || pendingRemovals.size > 0 || pendingQuantities.size > 0,
    quantityPending: pendingQuantities.size > 0,
    quantityKeys: [...pendingQuantities.keys()],
    error,
    couponLoading,
    couponError,
    updateQty: async (key, qty) => {
      setError(null);
      const result = await updateCartQuantity(key, qty);
      setError(result.error ?? null);
      return !result.error;
    },
    removeItem,
    applyCoupon: (coupon_code) => mutate("apply_coupon", { coupon_code }, true),
    removeCoupon: (coupon_code) =>
      mutate("remove_coupon", { coupon_code }, true),
  };
}
export { useCart as u };
