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

function bootEditor(t) {
  const dom = new JSDOM(editorMarkup(), {
    url: "https://shop.test/wp-admin/post.php",
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  window.confirm = () => true;
  window.eval(source);
  if (window.document.readyState === "loading") {
    window.document.dispatchEvent(new window.Event("DOMContentLoaded", { bubbles: true }));
  }
  return { window, root: window.document.getElementById("jluxe-product-spec-groups") };
}

function pasteClipboard(window, input, data) {
  const event = new window.Event("paste", { bubbles: true, cancelable: true });
  Object.defineProperty(event, "clipboardData", {
    value: { getData: (type) => data[type] || "" },
  });
  input.dispatchEvent(event);
  return event;
}

test("pasting a copied HTML specification table imports rows into the focused group and preserves existing values", (t) => {
  const { window, root } = bootEditor(t);
  const group = root.querySelector("[data-jluxe-spec-group]");
  const target = group.querySelector('input[name$="[label]"]');
  const event = pasteClipboard(window, target, {
    "text/html": `<table><thead><tr><th>نام ویژگی</th><th>مقدار ویژگی</th></tr></thead><tbody>
      <tr><td>برند سازنده</td><td>عروس نوین</td></tr>
      <tr><td>مدل محصول</td><td>هرمس (۱.۵ طبقه)</td></tr>
      <tr><td>قابلیت‌ها</td><td>جدا شدن سبد بالایی و تغییر جهت کشو</td></tr>
    </tbody></table>`,
  });

  assert.equal(event.defaultPrevented, true, "a recognized table is handled by the importer");
  assert.equal(group.querySelectorAll(".jluxe-spec-row").length, 4, "three features are added to the existing row");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][0][label]"]').value, "ویژگی", "existing feature label is unchanged");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][0][value]"]').value, "مقدار", "existing feature value is unchanged");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][label]"]').value, "برند سازنده");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][value]"]').value, "عروس نوین");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][3][label]"]').value, "قابلیت‌ها");
  assert.match(group.querySelector('[role="status"]').textContent, /۳ ویژگی/);
});

test("pasting a copied styled HTML table imports only safe cell text, never copied markup or scripts", (t) => {
  const { window, root } = bootEditor(t);
  const group = root.querySelector("[data-jluxe-spec-group]");
  const target = group.querySelector('input[name$="[label]"]');
  const event = pasteClipboard(window, target, {
    "text/html": `<!--StartFragment--><table style="width:100%;color:red"><thead><tr><th>نام ویژگی</th><th>مقدار ویژگی</th></tr></thead>
      <tbody><tr style="background:#f00"><td><span onclick="alert(1)">جنس بدنه</span></td>
      <td><a href="https://example.test/">استیل ضدزنگ</a><script>window.pastedCodeRan=true</script></td></tr></tbody></table><!--EndFragment-->`,
  });

  assert.equal(event.defaultPrevented, true, "copied website or office tables are recognized");
  assert.equal(window.pastedCodeRan, undefined, "clipboard scripts are never executed");
  assert.equal(group.querySelectorAll(".jluxe-spec-row").length, 2);
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][label]"]').value, "جنس بدنه");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][value]"]').value, "استیل ضدزنگ");
});

test("pasting a Markdown or tabular plain-text table strips formatting and reuses an empty row", (t) => {
  const { window, root } = bootEditor(t);
  const group = root.querySelector("[data-jluxe-spec-group]");
  group.querySelector("[data-jluxe-spec-add-row]").click();
  const emptyRow = group.querySelectorAll(".jluxe-spec-row")[1];
  const target = emptyRow.querySelector('input[name$="[value]"]');
  const event = pasteClipboard(window, target, {
    "text/plain": [
      "| **نام ویژگی** | **مقدار ویژگی** |",
      "| --- | --- |",
      "| جنس بدنه | فلز آبکاری شده با رنگ کوره‌ای |",
      "| ابعاد محصول (سانتی‌متر) | طول ۴۴ × عرض ۳۳ × ارتفاع ۴۰ سانتی‌متر |",
    ].join("\n"),
  });

  assert.equal(event.defaultPrevented, true);
  assert.equal(group.querySelectorAll(".jluxe-spec-row").length, 3, "the existing blank row is reused for the first feature");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][label]"]').value, "جنس بدنه");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][value]"]').value, "فلز آبکاری شده با رنگ کوره‌ای");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][2][label]"]').value, "ابعاد محصول (سانتی‌متر)");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][2][value]"]').value, "طول ۴۴ × عرض ۳۳ × ارتفاع ۴۰ سانتی‌متر");
});

test("plain-text spreadsheet headings such as Feature and Details are skipped during import", (t) => {
  const { window, root } = bootEditor(t);
  const group = root.querySelector("[data-jluxe-spec-group]");
  const target = group.querySelector('input[name$="[label]"]');
  const event = pasteClipboard(window, target, {
    "text/plain": [
      "Feature\tDetails",
      "Material\tPowder-coated steel",
      "Height\t40 cm",
    ].join("\n"),
  });

  assert.equal(event.defaultPrevented, true);
  assert.equal(group.querySelectorAll(".jluxe-spec-row").length, 3, "the two records are added without importing the header row");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][label]"]').value, "Material");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][1][value]"]').value, "Powder-coated steel");
  assert.equal(group.querySelector('[name="jluxe_product_specs[0][items][2][label]"]').value, "Height");
});

test("ordinary text paste is left untouched and a large table respects the 100-row server limit", (t) => {
  const { window, root } = bootEditor(t);
  const group = root.querySelector("[data-jluxe-spec-group]");
  const target = group.querySelector('input[name$="[label]"]');
  const ordinary = pasteClipboard(window, target, { "text/plain": "فقط یک متن معمولی" });
  assert.equal(ordinary.defaultPrevented, false, "ordinary text keeps native paste behavior");
  const spacedText = pasteClipboard(window, target, { "text/plain": "متن معمولی  با فاصلهٔ دوبل" });
  assert.equal(spacedText.defaultPrevented, false, "one ambiguous line separated by spaces is not treated as a table");
  assert.equal(group.querySelectorAll(".jluxe-spec-row").length, 1);

  const lines = ["نام ویژگی\tمقدار ویژگی"];
  for (let index = 1; index <= 105; index += 1) {
    lines.push("ویژگی " + index + "\tمقدار " + index);
  }
  const largeTable = pasteClipboard(window, target, { "text/plain": lines.join("\n") });
  assert.equal(largeTable.defaultPrevented, true);
  assert.equal(group.querySelectorAll(".jluxe-spec-row").length, 100, "the group never exceeds the server's 100-row limit");
  assert.match(group.querySelector('[role="status"]').textContent, /۶ ردیف/);
});
