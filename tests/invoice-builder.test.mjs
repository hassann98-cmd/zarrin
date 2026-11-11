import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";

const root = new URL("../", import.meta.url);
const read = (path) => readFile(new URL(path, root), "utf8");

function mountLogoPicker(script) {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <main id="jluxe-invoice-builder">
        <input data-jib-logo-id type="hidden" value="">
        <input data-jib-logo-url type="url" value="">
        <button type="button" data-jib-select-logo>انتخاب</button>
        <button type="button" data-jib-remove-logo>حذف</button>
        <div data-jib-logo-preview></div>
      </main>
    </body></html>`,
    { url: "https://shop.test/wp-admin/admin.php", runScripts: "outside-only" },
  );
  dom.window.eval(script);
  dom.window.document.dispatchEvent(new dom.window.Event("DOMContentLoaded"));
  return dom;
}

test("invoice logo picker uses the WordPress media library and saves the selected attachment", async () => {
  const script = await read("assets/js/invoice-builder.js");
  const dom = mountLogoPicker(script);
  let options;
  let selected;
  let opened = false;
  dom.window.wp = {
    media(value) {
      options = value;
      return {
        on(event, callback) {
          if (event === "select") selected = callback;
        },
        state() {
          return {
            get() {
              return {
                first() {
                  return { toJSON: () => ({ id: 77, url: "https://shop.test/uploads/invoice-logo.png" }) };
                },
              };
            },
          };
        },
        open() {
          opened = true;
        },
      };
    },
  };

  dom.window.document.querySelector("[data-jib-select-logo]").click();
  assert.equal(options.title, "انتخاب لوگوی فاکتور");
  assert.equal(options.multiple, false);
  assert.equal(opened, true);
  selected();

  assert.equal(dom.window.document.querySelector("[data-jib-logo-id]").value, "77");
  assert.equal(dom.window.document.querySelector("[data-jib-logo-url]").value, "https://shop.test/uploads/invoice-logo.png");
  assert.equal(dom.window.document.querySelector("[data-jib-logo-preview] img").src, "https://shop.test/uploads/invoice-logo.png");
  dom.window.close();
});

test("invoice logo can be removed or entered as a direct URL without changing other fields", async () => {
  const script = await read("assets/js/invoice-builder.js");
  const dom = mountLogoPicker(script);
  const id = dom.window.document.querySelector("[data-jib-logo-id]");
  const url = dom.window.document.querySelector("[data-jib-logo-url]");
  id.value = "77";
  url.value = "https://shop.test/uploads/old.png";

  dom.window.document.querySelector("[data-jib-remove-logo]").click();
  assert.equal(id.value, "");
  assert.equal(url.value, "");
  assert.equal(dom.window.document.querySelector("[data-jib-logo-preview] img"), null);

  url.value = "https://shop.test/uploads/manual.png";
  url.dispatchEvent(new dom.window.Event("change", { bubbles: true }));
  assert.equal(id.value, "");
  assert.equal(dom.window.document.querySelector("[data-jib-logo-preview] img").src, "https://shop.test/uploads/manual.png");
  dom.window.close();
});

test("print button works without an inline handler or automatic dialog when disabled", async () => {
  const script = await read("assets/js/invoice-print.js");
  const dom = new JSDOM(
    '<!doctype html><body data-auto-print="0"><button data-invoice-print>چاپ فاکتور</button></body>',
    { url: "https://shop.test/invoice/", runScripts: "outside-only" },
  );
  let printCount = 0;
  dom.window.print = () => { printCount += 1; };
  dom.window.eval(script);
  dom.window.document.dispatchEvent(new dom.window.Event("DOMContentLoaded"));
  assert.equal(printCount, 0);
  dom.window.document.querySelector("[data-invoice-print]").click();
  assert.equal(printCount, 1);
  dom.window.close();
});

test("automatic invoice printing waits for the document load event", async () => {
  const script = await read("assets/js/invoice-print.js");
  const dom = new JSDOM(
    '<!doctype html><body data-auto-print="1"><button data-invoice-print>چاپ فاکتور</button></body>',
    { url: "https://shop.test/invoice/", runScripts: "outside-only" },
  );
  let printCount = 0;
  dom.window.print = () => { printCount += 1; };
  dom.window.setTimeout = (callback) => { callback(); return 1; };
  dom.window.eval(script);
  dom.window.document.dispatchEvent(new dom.window.Event("DOMContentLoaded"));
  assert.equal(printCount, 0);
  dom.window.dispatchEvent(new dom.window.Event("load"));
  await Promise.resolve();
  assert.equal(printCount, 1);
  dom.window.close();
});

test("invoice builder styles stay scoped to its page and include a responsive sample preview", async () => {
  const css = await read("assets/css/invoice-builder.css");
  assert.match(css, /^#jluxe-invoice-builder\{/);
  assert.doesNotMatch(css, /(?:^|})\s*(?:body|\.wp-admin)\s*\{/m);
  assert.match(css, /@media\(max-width:782px\)/);
  assert.match(css, /@media\(max-width:560px\)/);
  assert.match(css, /\.jib-preview-card iframe/);
});

test("customer order invoice action is conditional on the owner-bound URL helper", async () => {
  const template = await read("woocommerce/myaccount/view-order.php");
  const invoice = await read("inc/invoice.php");
  assert.match(template, /jluxe_invoice_customer_url\( \$order \)/);
  assert.match(template, /if \( '' !== \$jluxe_invoice_url \)/);
  assert.match(template, /target="_blank" rel="noopener noreferrer"/);
  assert.match(invoice, /add_action\( 'admin_post_jluxe_print_invoice'/);
  assert.match(invoice, /assets\/js\/invoice-print\.js/);
  assert.match(invoice, /data-auto-print=/);
  assert.doesNotMatch(invoice, /admin_post_nopriv_jluxe_print_invoice/);
});
