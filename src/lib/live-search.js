import { dedupeGet } from "./api.js";

const LIVE_SEARCH_CACHE_TTL = 1_000;

/** Share identical live-search requests across header islands for a short window. */
export function fetchLiveSearch(search, query) {
  const ajaxUrl = search?.ajaxUrl;
  const nonce = search?.nonce || "";
  const term = String(query ?? "").trim();
  if (!ajaxUrl) return Promise.reject(new Error("Live search is not configured."));

  const url = `${ajaxUrl}?action=jluxe_search&nonce=${encodeURIComponent(nonce)}&s=${encodeURIComponent(term)}`;
  return dedupeGet(
    `jluxe-live-search:${url}`,
    () => fetch(url).then((response) => response.json()),
    { ttl: LIVE_SEARCH_CACHE_TTL },
  );
}
