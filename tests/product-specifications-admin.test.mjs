import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/product-specifications-admin.js", import.meta.url), "utf8");

function editorMarkup() {
  return `<!doctype html><html><body>
    <div id="jluxe-product-spec-groups" data-next-group-index="1">
      <section class="jluxe-spec-group" data-jluxe-spec-group data-group-index="0" data-next-row-index="1">
        <input type="text" name="jluxe_product_specs[0][title]" value="گروه اول">
        <div data-jluxe-spec-rows>
          <div class="jluxe-spec-row">
            <input type="text" name="jluxe_product_specs[0][items][0][label]" value="ویژگی">
            <input type="text" name="jluxe_product_specs[0][items][0][value]" value="مقدار">
            <button type="button" data-jluxe-spec-remove-row>حذف ویژگی</button>
          </div>
        </div>
        <button type="button" data-jluxe-spec-add-row>افزودن ویژگی</button>
        <button type="button" data-jluxe-spec-remove-group>حذف گروه</button>
      </section>
    </div>
    <template id="jluxe-product-spec-group-template">
      <section class="jluxe-spec-group" data-jluxe-spec-group data-group-index="__GROUP__" data-next-row-index="0">
        <input type="text" name="jluxe_product_specs[__GROUP__][title]" value="">
        <div data-jluxe-spec-rows></div>
        <button type="button" data-jluxe-spec-add-row>افزودن ویژگی</button>
        <button type="button" data-jluxe-spec-remove-group>حذف گروه</button>
      </section>
    </template>
    <template id="jluxe-product-spec-row-template">
      <div class="jluxe-spec-row">
        <input type="text" name="jluxe_product_specs[__GROUP__][items][__ROW__][label]" value="">
        <input type="text" name="jluxe_product_specs[__GROUP__][items][__ROW__][value]" value="">
        <button type="button" data-jluxe-spec-remove-row>حذف ویژگی</button>
      </div>
    </template>
    <button type="button" data-jluxe-spec-add-group>افزودن گروه</button>
  </body></html>`;
}

test("grouped product-specification editor adds unique rows/groups and confirms destructive removals", (t) => {
  const dom = new JSDOM(editorMarkup(), {
    url: "https://shop.test/wp-admin/post.php",
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  const confirmationAnswers = [];
  window.confirm = () => confirmationAnswers.shift() ?? true;
  window.eval(source);
  if (window.document.readyState === "loading") {
    window.document.dispatchEvent(new window.Event("DOMContentLoaded", { bubbles: true }));
  }

  const root = window.document.getElementById("jluxe-product-spec-groups");
  const addGroup = window.document.querySelector("[data-jluxe-spec-add-group]");
  addGroup.click();
  assert.equal(root.querySelectorAll("[data-jluxe-spec-group]").length, 2, "a new group is inserted into the form");
  const newGroup = root.querySelectorAll("[data-jluxe-spec-group]")[1];
  assert.equal(newGroup.getAttribute("data-group-index"), "1", "new group indexes continue after existing groups");
  assert.equal(newGroup.querySelector('[name="jluxe_product_specs[1][title]"]').value, "");
  assert.equal(newGroup.querySelector('[name="jluxe_product_specs[1][items][0][label]"]').value, "", "new group starts with a usable blank feature row");

  newGroup.querySelector("[data-jluxe-spec-add-row]").click();
  assert.equal(newGroup.querySelectorAll(".jluxe-spec-row").length, 2, "a group can add another feature row");
  assert.ok(newGroup.querySelector('[name="jluxe_product_specs[1][items][1][label]"]'), "new feature names include unique group and row indexes");

  const rowToRemove = newGroup.querySelectorAll(".jluxe-spec-row")[1];
  rowToRemove.querySelector('input[name$="[label]"]').value = "حذف‌شدنی";
  confirmationAnswers.push(false);
  rowToRemove.querySelector("[data-jluxe-spec-remove-row]").click();
  assert.equal(newGroup.querySelectorAll(".jluxe-spec-row").length, 2, "canceling the confirmation preserves a populated feature");
  confirmationAnswers.push(true);
  rowToRemove.querySelector("[data-jluxe-spec-remove-row]").click();
  assert.equal(newGroup.querySelectorAll(".jluxe-spec-row").length, 1, "confirming removes only the selected feature");

  newGroup.querySelector('input[name="jluxe_product_specs[1][title]"]').value = "حذف گروه";
  confirmationAnswers.push(false);
  newGroup.querySelector("[data-jluxe-spec-remove-group]").click();
  assert.equal(root.querySelectorAll("[data-jluxe-spec-group]").length, 2, "canceling preserves a populated group");
  confirmationAnswers.push(true);
  newGroup.querySelector("[data-jluxe-spec-remove-group]").click();
  assert.equal(root.querySelectorAll("[data-jluxe-spec-group]").length, 1, "confirming removes the selected group and its rows");
});
