import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const data = JSON.parse(fs.readFileSync(new URL("../inc/data/iran-cities.json", import.meta.url), "utf8"));
const sourceNote = fs.readFileSync(new URL("../inc/data/README.md", import.meta.url), "utf8");
const mitLicense = fs.readFileSync(new URL("../inc/data/iran-cities-MIT-LICENSE.txt", import.meta.url), "utf8");
const gplLicense = fs.readFileSync(new URL("../inc/data/iran-cities-GPL-3.0.txt", import.meta.url), "utf8");
const dsnLicense = fs.readFileSync(new URL("../inc/dsn-address-flow/CITIES-LICENSE.txt", import.meta.url), "utf8");
const dsnLoader = fs.readFileSync(new URL("../inc/dsn-address-flow/dsn-address-flow.php", import.meta.url), "utf8");
const checkoutLoader = fs.readFileSync(new URL("../inc/woocommerce/checkout-fields.php", import.meta.url), "utf8");

function cityKey(city) {
  return city
    .replace(/[ي]/g, "ی")
    .replace(/[ك]/g, "ک")
    .replace(/ـ/g, "")
    .replace(/[\s\u200c\u200d\u2060\ufeff\-‐‑‒–—―]+/gu, "")
    .trim();
}

test("WooCommerce and DSN share one province-name keyed city dataset without dropping either source", () => {
  assert.equal(Object.keys(data).length, 31);
  assert.equal(Object.keys(data).every((province) => !/^[A-Z]{3}$/.test(province)), true);
  assert.equal(Object.values(data).every(Array.isArray), true);
  assert.equal(Object.values(data).flat().length, 1487);
  for (const cities of Object.values(data)) {
    const keys = cities.map(cityKey);
    assert.equal(new Set(keys).size, keys.length, "no normalized duplicate city within a province");
  }
  assert.ok(data["کردستان"].includes("دهگلان"));
  assert.ok(data["کردستان"].includes("آرمرده"));
  assert.ok(data["کردستان"].includes("توپ آغاج"));
  assert.ok(data["هرمزگان"].includes("ابوموسی"));
  assert.ok(data["تهران"].includes("آبسرد"));
  assert.match(sourceNote, /2766b261efd9ac66b2e48c2fa58e075cb892f170/);
  assert.match(sourceNote, /GPL-3\.0/);
  assert.match(sourceNote, /MIT/);
  assert.match(mitLicense, /MIT License/);
  assert.match(mitLicense, /Copyright \(c\) 2020 jd1378/);
  assert.match(gplLicense, /GNU GENERAL PUBLIC LICENSE/);
  assert.match(gplLicense, /Version 3/);
  assert.match(dsnLicense, /\.\.\/data\/iran-cities\.json/);
  assert.match(dsnLoader, /data\/iran-cities\.json/);
  assert.doesNotMatch(dsnLoader, /assets\/iran-cities\.json/);
  assert.match(checkoutLoader, /province_by_state/);
});
