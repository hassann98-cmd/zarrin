import assert from "node:assert/strict";
import test from "node:test";
import {
  RECENT_SEARCHES_KEY,
  addRecentSearch,
  normalizeRecentSearch,
  normalizeRecentSearches,
  readRecentSearches,
  removeRecentSearch,
  writeRecentSearches,
} from "../src/lib/recent-searches.js";
import { readFile } from "node:fs/promises";

const headerSource = await readFile(new URL("../src/islands/Header.js", import.meta.url), "utf8");

test("recent searches normalize length, deduplicate, move latest to the front, and cap at six", () => {
  assert.equal(normalizeRecentSearch(" x "), "");
  assert.equal(Array.from(normalizeRecentSearch("عبارت طولانی".repeat(10))).length, 60);
  assert.deepEqual(normalizeRecentSearches(["کتاب", "کتاب", "  ", "چای", "Tea", "tea", "گل", "نور", "ساعت", "آخر"]), ["کتاب", "چای", "Tea", "گل", "نور", "ساعت"]);
  assert.deepEqual(addRecentSearch(["چای", "کتاب"], "کتاب"), ["کتاب", "چای"]);
  assert.deepEqual(removeRecentSearch(["کتاب", "چای"], "کتاب"), ["چای"]);
});

test("recent-search storage reads, writes and clears safely when localStorage throws", () => {
  const storage = {
    value: "",
    getItem(key) { assert.equal(key, RECENT_SEARCHES_KEY); return this.value; },
    setItem(key, value) { assert.equal(key, RECENT_SEARCHES_KEY); this.value = value; },
  };
  assert.deepEqual(readRecentSearches(storage), []);
  writeRecentSearches(["کتاب", "چای"], storage);
  assert.deepEqual(readRecentSearches(storage), ["کتاب", "چای"]);
  writeRecentSearches([], storage);
  assert.deepEqual(readRecentSearches(storage), [], "writing an empty list clears all saved searches");
  storage.value = "not-json";
  assert.deepEqual(readRecentSearches(storage), [], "malformed browser data is ignored safely");
  const blocked = {
    getItem() { throw new Error("private mode"); },
    setItem() { throw new Error("private mode"); },
  };
  assert.deepEqual(readRecentSearches(blocked), []);
  assert.deepEqual(writeRecentSearches(["کتاب"], blocked), ["کتاب"]);
});

test("all three header search surfaces expose zero-state discovery, submit/result tracking, and live chip selection", async () => {
  const discoverySource = await readFile(new URL("../src/islands/SearchDiscovery.js", import.meta.url), "utf8");
  const mainSource = await readFile(new URL("../src/main.js", import.meta.url), "utf8");
  assert.match(headerSource, /c\.lazy\(\(\) => window\.JLuxeSearchDiscovery\(\)\)/);
  assert.match(mainSource, /JLuxeSearchDiscovery = \(\) => import\("\.\/islands\/SearchDiscovery\.js"\)/);
  assert.match(discoverySource, /function SearchDiscovery\(/);
  assert.doesNotMatch(headerSource, /recent-searches\.js/);
  assert.equal((headerSource.match(/onSubmit: \(\) => saveRecentSearch\(/g) || []).length, 3);
  assert.match(headerSource, /onFocus: \(\) => \{ d\(!0\); loadRecentSearches\(\); \}/);
  assert.match(headerSource, /onFocus: \(\) => \{ setFocused\(!0\); loadRecentSearches\(\); \}/);
  assert.equal((headerSource.match(/onSelect: saveRecentSearch/g) || []).length, 3);
  assert.equal((headerSource.match(/renderSearchDiscovery\(\{ recent:/g) || []).length, 3);
  assert.match(headerSource, /onMouseDown: \(event\) => event\.preventDefault\(\)/);
  assert.match(headerSource, /focused && query\.trim\(\)\.length < 2 && discoveryAvailable/);
  assert.match(headerSource, /t && n\.trim\(\)\.length < 2 && discoveryAvailable/);
});
