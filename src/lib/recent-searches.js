export const RECENT_SEARCHES_KEY = "jluxe_recent_searches";
export const RECENT_SEARCHES_LIMIT = 6;
export const RECENT_SEARCH_LENGTH_LIMIT = 60;

function localStorageOrNull() {
  try {
    return typeof window !== "undefined" ? window.localStorage : null;
  } catch {
    return null;
  }
}

export function normalizeRecentSearch(value) {
  const normalized = String(value ?? "").trim();
  const clipped = Array.from(normalized).slice(0, RECENT_SEARCH_LENGTH_LIMIT).join("");
  return Array.from(clipped).length >= 2 ? clipped : "";
}

export function normalizeRecentSearches(value) {
  if (!Array.isArray(value)) return [];
  const unique = new Set();
  const result = [];
  for (const item of value) {
    const search = normalizeRecentSearch(item);
    const key = search.toLocaleLowerCase();
    if (!search || unique.has(key)) continue;
    unique.add(key);
    result.push(search);
    if (result.length >= RECENT_SEARCHES_LIMIT) break;
  }
  return result;
}

export function addRecentSearch(searches, value) {
  const search = normalizeRecentSearch(value);
  if (!search) return normalizeRecentSearches(searches);
  return normalizeRecentSearches([
    search,
    ...normalizeRecentSearches(searches).filter(
      (item) => item.toLocaleLowerCase() !== search.toLocaleLowerCase(),
    ),
  ]);
}

export function removeRecentSearch(searches, value) {
  const target = normalizeRecentSearch(value).toLocaleLowerCase();
  return normalizeRecentSearches(searches).filter(
    (item) => item.toLocaleLowerCase() !== target,
  );
}

export function readRecentSearches(storage = localStorageOrNull()) {
  try {
    if (!storage) return [];
    return normalizeRecentSearches(JSON.parse(storage.getItem(RECENT_SEARCHES_KEY) || "[]"));
  } catch {
    return [];
  }
}

export function writeRecentSearches(searches, storage = localStorageOrNull()) {
  const normalized = normalizeRecentSearches(searches);
  try {
    storage?.setItem(RECENT_SEARCHES_KEY, JSON.stringify(normalized));
  } catch {
    // Private browsing and storage quotas must never block search itself.
  }
  return normalized;
}
