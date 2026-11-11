import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/related-products-admin.js", import.meta.url), "utf8");

async function setup() {
  const dom = new JSDOM(`<!doctype html><html><body>
    <select id="_jluxe_related_product_ids" multiple>
      <option value="101" selected>One</option>
      <option value="202" selected>Two</option>
    </select>
    <input type="hidden" name="_jluxe_related_product_order" value="101,202">
  </body></html>`, { url: "https://shop.test/wp-admin/post.php", runScripts: "outside-only" });
  const { window } = dom;
  const $ = jquery(window);
  window.jQuery = $;
  window.$ = $;
  window.eval(source);
  if (window.document.readyState === "loading") {
    await new Promise((resolve) => window.document.addEventListener("DOMContentLoaded", resolve, { once: true }));
  }
  await new Promise((resolve) => window.setTimeout(resolve, 0));
  return { window, $ };
}

function triggerSelect($, id, type = "select2:select") {
  const event = $.Event(type);
  event.params = { data: { id } };
  $("#_jluxe_related_product_ids").trigger(event);
}

test("R119 admin selector keeps saved order and appends/reorders selections in click order", async () => {
  const { window, $ } = await setup();
  const field = window.document.querySelector('[name="_jluxe_related_product_order"]');

  assert.equal(field.value, "101,202", "saved IDs remain ordered when the editor loads");
  triggerSelect($, "303");
  assert.equal(field.value, "101,202,303", "a newly selected product is appended");
  triggerSelect($, "202");
  assert.equal(field.value, "101,303,202", "reselecting a product moves it to the end");
});

test("R119 admin selector removes deselected products and clears the manual order", async () => {
  const { window, $ } = await setup();
  const field = window.document.querySelector('[name="_jluxe_related_product_order"]');

  triggerSelect($, "101", "select2:unselect");
  assert.equal(field.value, "202", "unselect removes only that product");
  triggerSelect($, "202", "select2:clear");
  assert.equal(field.value, "", "clear returns the selector to automatic mode");
});
