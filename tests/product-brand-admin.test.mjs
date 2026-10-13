import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";

const woocommerce = fs.readFileSync(new URL("../inc/woocommerce.php", import.meta.url), "utf8");
const brandRegistration = woocommerce.match(
  /function jluxe_register_product_brand_taxonomy\(\): void \{([\s\S]*?)\n\}/,
)?.[1] || "";

test("product brands use a distinct checkbox panel and brand-specific labels instead of the product-tags editor", () => {
  assert.ok(brandRegistration, "the real product_brand taxonomy registration is present");
  assert.match(brandRegistration, /register_taxonomy\(\s*'product_brand'\s*,\s*'product'/);
  assert.match(brandRegistration, /'hierarchical'\s*=>\s*true/, "WordPress should render brands as a separate checkbox taxonomy panel");
  assert.match(brandRegistration, /'all_items'\s*=>\s*'همهٔ برندها'/);
  assert.match(brandRegistration, /'add_new_item'\s*=>\s*'افزودن برند جدید'/);
  assert.match(brandRegistration, /'add_or_remove_items'\s*=>\s*'افزودن یا حذف برندها'/);
  assert.match(brandRegistration, /'separate_items_with_commas'\s*=>\s*'نام برندها را با ویرگول جدا کنید\.'/);
  assert.match(brandRegistration, /'choose_from_most_used'\s*=>\s*'انتخاب از برندهای پرکاربرد'/);
  assert.doesNotMatch(brandRegistration, /'hierarchical'\s*=>\s*false/);
  assert.doesNotMatch(brandRegistration, /برچسب‌ها/);
});
