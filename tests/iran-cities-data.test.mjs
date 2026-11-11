import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const data = JSON.parse(fs.readFileSync(new URL("../inc/data/iran-cities.json", import.meta.url), "utf8"));
const sourceNote = fs.readFileSync(new URL("../inc/data/README.md", import.meta.url), "utf8");
const mitLicense = fs.readFileSync(new URL("../inc/data/iran-cities-MIT-LICENSE.txt", import.meta.url), "utf8");

function cityKey(city) {
  return city
    .replace(/[ي]/g, "ی")
    .replace(/[ك]/g, "ک")
    .replace(/ـ/g, "")
    .replace(/[\s\u200c\u200d\u2060\ufeff\-‐‑‒–—―]+/gu, "")
    .trim();
}

test("expanded Iranian city additions remain province-scoped, deduplicated, and MIT-attributed", () => {
  assert.equal(Object.keys(data).length, 31);
  assert.equal(Object.keys(data).every((state) => /^[A-Z]{3}$/.test(state)), true);
  assert.equal(Object.values(data).every(Array.isArray), true);
  assert.equal(Object.values(data).flat().length, 1087);
  for (const cities of Object.values(data)) {
    const keys = cities.map(cityKey);
    assert.equal(new Set(keys).size, keys.length, "no normalized duplicate city within a province");
  }
  assert.ok(data.KRD.includes("دهگلان"));
  assert.ok(data.KRD.includes("آرمرده"));
  assert.ok(data.KRD.includes("توپ آغاج"));
  assert.ok(data.HRZ.includes("ابوموسی"));
  assert.match(sourceNote, /2766b261efd9ac66b2e48c2fa58e075cb892f170/);
  assert.match(sourceNote, /MIT/);
  assert.match(mitLicense, /MIT License/);
  assert.match(mitLicense, /Copyright \(c\) 2020 jd1378/);
});
