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
let loaded = false;
let revision = 0;
let initialRequest;
let queue = Promise.resolve();
const subscribers = new Set();
// Each DOM event is shared by header, drawer and mobile subscribers.
const refreshEvents = new WeakMap();
function publish(next) {
  snapshot = next;
  loaded = true;
  revision += 1;
  subscribers.forEach((subscriber) => subscriber(next));
}

/** Serialize drawer requests; never turn a network/server rejection into a successful mutation. */
export function cartRequest(operation, values = {}) {
  const execute = async () => {
    const config = getThemeSettings().cart;
    if (!config?.ajaxUrl) return { snapshot, error: "سبد خرید در دسترس نیست." };
    const startedAt = revision;
    try {
      const send = async () => {
        const fields = { ...values };
        delete fields["add-to-cart"]; // Do not also trigger Woo's native form handler.
        const response = await fetch(config.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          cache: "no-store",
          body: new URLSearchParams({
            ...fields,
            action: "jluxe_cart",
            nonce: config.nonce,
            op: operation,
          }),
        });
        return { response, result: await response.json() };
      };
      let { response, result } = await send();
      // Only this nonce error is guaranteed to occur BEFORE the mutation.
      // Never replay a timeout, a malformed response, a stock failure or a 5xx.
      if (
        response.status === 403 &&
        result?.data?.code === "jluxe_cart_invalid_nonce"
      ) {
        const session = await getSession(true);
        if (!session?.cartNonce)
          return { snapshot, error: "نشست معتبر نیست؛ صفحه را تازه کنید." };
        config.nonce = session.cartNonce;
        ({ response, result } = await send());
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
      return {
        snapshot,
        error: "ارتباط با سرور برقرار نشد. دوباره تلاش کنید.",
      };
    }
  };
  queue = queue.then(execute, execute);
  return queue;
}

function useCart() {
  const [current, setCurrent] = useState(snapshot);
  const [loading, setLoading] = useState(!loaded);
  const [couponLoading, setCouponLoading] = useState(false);
  const [error, setError] = useState(null);
  const [couponError, setCouponError] = useState(null);
  useEffect(() => {
    let active = true;
    subscribers.add(setCurrent);
    if (!loaded) {
      initialRequest ||= cartRequest("get").finally(() => {
        initialRequest = null;
      });
      initialRequest.then((result) => {
        if (active) {
          setLoading(false);
          setError(result.error ?? null);
        }
      });
    }
    function refresh(event) {
      if (!refreshEvents.has(event)) {
        const next = event.detail;
        if (
          next &&
          Array.isArray(next.items) &&
          Number.isFinite(next.itemCount) &&
          next.itemCount >= 0
        ) {
          publish(next);
          refreshEvents.set(event, Promise.resolve({ snapshot }));
        } else {
          // Do not let an older read overwrite a newer external cart change.
          revision += 1;
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
  return {
    snapshot: current,
    loading,
    error,
    couponLoading,
    couponError,
    updateQty: (key, qty) => mutate("update_qty", { key, qty: String(qty) }),
    removeItem: (key) => mutate("remove", { key }),
    applyCoupon: (coupon_code) => mutate("apply_coupon", { coupon_code }, true),
    removeCoupon: (coupon_code) =>
      mutate("remove_coupon", { coupon_code }, true),
  };
}
export { useCart as u };
