import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/product-manager.js", import.meta.url), "utf8");

function adminMarkup() {
  return `<!doctype html><html><body>
    <div id="jluxe-product-manager">
      <div id="jluxe-pm-notice" hidden></div>
      <input id="jluxe-pm-search"><select id="jluxe-pm-type"><option value=""></option></select>
      <select id="jluxe-pm-status"><option value=""></option></select><select id="jluxe-pm-stock"><option value=""></option></select>
      <select id="jluxe-pm-category"><option value=""></option></select>
      <button id="jluxe-pm-search-button"></button><button id="jluxe-pm-reset"></button>
      <label><input id="jluxe-pm-select-all" type="checkbox"></label><span id="jluxe-pm-selected-count"></span>
      <button id="jluxe-pm-bulk-open"></button><button id="jluxe-pm-refresh"></button>
      <table><tbody id="jluxe-pm-rows"></tbody></table>
      <span id="jluxe-pm-summary"></span><span id="jluxe-pm-page-label"></span>
      <button id="jluxe-pm-prev"></button><button id="jluxe-pm-next"></button>
      <section id="jluxe-pm-bulk-panel" hidden>
        <div class="jluxe-pm-bulk-card"><button type="button" data-pm-close-bulk></button>
          <select id="jluxe-pm-bulk-field"><option value="regular_price">Price</option><option value="stock_quantity">Stock</option></select>
          <select id="jluxe-pm-bulk-operation"><option value="set">Set</option><option value="percent_increase">Percent</option></select>
          <input id="jluxe-pm-bulk-value"><button id="jluxe-pm-bulk-apply"></button>
        </div>
      </section>
      <section id="jluxe-pm-editor" hidden>
        <div class="jluxe-pm-editor-backdrop" data-pm-close-editor></div>
        <div class="jluxe-pm-editor-card" tabindex="-1">
          <header><h2 id="jluxe-pm-editor-title"></h2><p id="jluxe-pm-editor-subtitle"></p><button type="button" data-pm-close-editor></button></header>
          <nav id="jluxe-pm-tabs"></nav><div id="jluxe-pm-editor-body"></div>
          <footer><a id="jluxe-pm-native-edit"></a><span id="jluxe-pm-save-state"></span>
            <button type="button" data-pm-close-editor></button><button id="jluxe-pm-save"></button>
          </footer>
        </div>
      </section>
    </div>
  </body></html>`;
}

function simpleProduct() {
  return {
    id: 101,
    name: "محصول سادهٔ آزمایشی",
    slug: "simple-test",
    type: "simple",
    sku: "SIMPLE-101",
    status: "publish",
    catalogVisibility: "visible",
    description: "توضیحات کامل",
    shortDescription: "توضیح کوتاه",
    regularPrice: "120",
    salePrice: "",
    saleFrom: "",
    saleTo: "",
    manageStock: false,
    stockQuantity: "",
    stockStatus: "instock",
    stockOptions: { instock: "موجود", outofstock: "ناموجود", onbackorder: "پیش‌سفارش" },
    backorders: "no",
    lowStockAmount: "",
    soldIndividually: false,
    featured: false,
    virtual: false,
    reviewsAllowed: true,
    purchaseNote: "",
    weight: "",
    length: "",
    width: "",
    height: "",
    taxStatus: "taxable",
    taxClass: "",
    taxClasses: { "": "پیش‌فرض" },
    shippingClassId: 0,
    shippingClasses: [],
    categories: [],
    tags: [],
    brands: [],
    brandTaxonomy: "",
    attributes: [{ taxonomy: "pa_color", label: "رنگ", terms: [{ id: 1, name: "قرمز", slug: "red" }], visible: true, variation: false }],
    localAttributes: [],
    image: { id: 0, url: "" },
    gallery: [],
    faq: [{ question: "پرسش موجود", answer: "پاسخ موجود" }],
    badges: { authenticity: false, warranty: false },
    suggestedModal: "default",
    seo: {},
    variations: {},
    editUrl: "/wp-admin/post.php?post=101&action=edit",
    productUrl: "/product/simple-test/",
  };
}

function variableProduct() {
  const product = simpleProduct();
  product.id = 202;
  product.name = "محصول متغیر آزمایشی";
  product.type = "variable";
  product.sku = "VAR-202";
  product.attributes = [{
    taxonomy: "pa_color",
    label: "رنگ",
    terms: [{ id: 11, name: "قرمز", slug: "red" }, { id: 12, name: "آبی", slug: "blue" }],
    visible: true,
    variation: true,
  }];
  product.variations = {
    items: [{
      id: 203,
      sku: "VAR-203",
      regularPrice: "0",
      salePrice: "",
      manageStock: true,
      stockQuantity: 3,
      stockStatus: "instock",
      backorders: "no",
      enabled: true,
      attributes: { pa_color: "red" },
    }],
    page: 1,
    pages: 1,
    total: 1,
    perPage: 20,
    attributes: [{
      name: "pa_color",
      label: "رنگ",
      options: [{ value: "red", label: "قرمز" }, { value: "blue", label: "آبی" }],
    }],
  };
  return product;
}

function createAdmin(t, { product = simpleProduct() } = {}) {
  const dom = new JSDOM(adminMarkup(), {
    url: "https://shop.test/wp-admin/admin.php?page=jluxe-product-manager",
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  window.Element.prototype.scrollIntoView = function () {};
  window.confirm = () => true;
  window.jluxeProductManager = { ajaxUrl: "/wp-admin/admin-ajax.php", nonce: "nonce-test", currency: "تومان", canUpload: false };
  const sent = [];
  let currentProduct = product;
  window.fetch = async (_url, options = {}) => {
    const form = new URLSearchParams(options.body);
    const action = form.get("action");
    const entry = { action, payload: form.has("payload") ? JSON.parse(form.get("payload")) : null, taxonomy: form.get("taxonomy") };
    sent.push(entry);
    let data;
    if (action === "jluxe_product_manager_list") {
      const summary = {
        id: currentProduct.id,
        name: currentProduct.name,
        sku: currentProduct.sku,
        type: currentProduct.type,
        typeLabel: currentProduct.type === "variable" ? "متغیر" : "ساده",
        status: currentProduct.status,
        regularPrice: currentProduct.regularPrice,
        salePrice: currentProduct.salePrice,
        priceLabel: currentProduct.regularPrice + " تومان",
        manageStock: currentProduct.manageStock,
        stockQuantity: currentProduct.stockQuantity,
        stockStatus: currentProduct.stockStatus,
        stockLabel: currentProduct.stockStatus === "instock" ? "موجود" : "ناموجود",
        categories: "",
        image: "",
        editUrl: currentProduct.editUrl,
      };
      data = { products: [summary], page: 1, pages: 1, total: 1, perPage: 20 };
    } else if (action === "jluxe_product_manager_get_product") {
      data = structuredClone(currentProduct);
    } else if (action === "jluxe_product_manager_get_variations") {
      data = structuredClone(currentProduct.variations);
    } else if (action === "jluxe_product_manager_save_product") {
      currentProduct = { ...currentProduct, ...entry.payload, id: entry.payload.id, type: currentProduct.type, editUrl: currentProduct.editUrl, variations: currentProduct.variations };
      data = { message: "ذخیره شد.", product: structuredClone(currentProduct) };
    } else if (action === "jluxe_product_manager_terms") {
      data = { terms: [{ id: 14, name: "سبز", slug: "green" }], canCreate: false };
    } else {
      data = { message: "ok" };
    }
    return { ok: true, json: async () => ({ success: true, data }) };
  };
  window.eval(source);
  return { dom, window, sent, get product() { return currentProduct; } };
}

function settle() {
  return new Promise((resolve) => setTimeout(resolve, 15));
}

test("admin product editor adds one FAQ row and saves the FAQ payload exactly once", async (t) => {
  const { window, sent } = createAdmin(t);
  await settle();
  assert.equal(window.document.querySelectorAll(".jluxe-pm-product-row").length, 1, "the product list loads from the mocked WooCommerce endpoint");

  window.document.querySelector("[data-pm-edit='101']").click();
  await settle();
  assert.equal(window.document.querySelector("#jluxe-pm-editor").hidden, false);
  window.document.querySelector("[data-tab='zarrin']").click();
  assert.equal(window.document.querySelectorAll(".jluxe-pm-faq-row").length, 1, "the stored JLuxe FAQ is rendered");

  window.document.querySelector("[data-pm-add-faq]").click();
  const rows = window.document.querySelectorAll(".jluxe-pm-faq-row");
  assert.equal(rows.length, 2, "one click creates exactly one row despite root-level delegated events");
  const question = rows[1].querySelector("[data-faq-question]");
  const answer = rows[1].querySelector("[data-faq-answer]");
  question.value = "پرسش تازه";
  answer.value = "پاسخ تازه";
  question.dispatchEvent(new window.Event("input", { bubbles: true }));
  answer.dispatchEvent(new window.Event("input", { bubbles: true }));
  assert.equal(window.document.querySelector("#jluxe-pm-save").disabled, false, "typing marks the editor dirty");
  window.document.querySelector("#jluxe-pm-save").click();
  await settle();

  const save = sent.find((item) => item.action === "jluxe_product_manager_save_product");
  assert.ok(save, "full edits use the product-manager save endpoint");
  assert.deepEqual(save.payload.faq, [
    { question: "پرسش موجود", answer: "پاسخ موجود" },
    { question: "پرسش تازه", answer: "پاسخ تازه" },
  ]);
  assert.equal(sent.filter((item) => item.action === "jluxe_product_manager_save_product").length, 1, "the save button sends one request");
});

test("variable variation editor submits exact option values plus stock and backorder fields", async (t) => {
  const { window, sent } = createAdmin(t, { product: variableProduct() });
  await settle();
  window.document.querySelector("[data-pm-edit='202']").click();
  await settle();
  window.document.querySelector("[data-tab='variations']").click();

  const row = window.document.querySelector(".jluxe-pm-variation-row");
  const color = row.querySelector('[data-v-attr="pa_color"]');
  const stockStatus = row.querySelector('[data-v-field="stockStatus"]');
  const backorders = row.querySelector('[data-v-field="backorders"]');
  color.value = "blue";
  color.dispatchEvent(new window.Event("change", { bubbles: true }));
  stockStatus.value = "outofstock";
  stockStatus.dispatchEvent(new window.Event("change", { bubbles: true }));
  backorders.value = "notify";
  backorders.dispatchEvent(new window.Event("change", { bubbles: true }));
  window.document.querySelector("#jluxe-pm-save").click();
  await settle();

  const save = sent.find((item) => item.action === "jluxe_product_manager_save_product");
  assert.ok(save, "variable products use the same full-save endpoint");
  assert.equal(save.payload.variations.length, 1, "only the edited child variation is submitted");
	assert.equal(save.payload.variations[0].attributes.pa_color, "blue", "the selected taxonomy slug is not title-sanitized or translated");
	assert.equal(save.payload.variations[0].regularPrice, "0", "a valid zero price stays distinct from an empty price");
	assert.equal(save.payload.variations[0].stockStatus, "outofstock");
  assert.equal(save.payload.variations[0].backorders, "notify");
});

test("term selection keeps the taxonomy key and chips update once", async (t) => {
  const { window } = createAdmin(t);
  await settle();
  window.document.querySelector("[data-pm-edit='101']").click();
  await settle();
  window.document.querySelector("[data-tab='organization']").click();
  const picker = window.document.querySelector('.jluxe-pm-term-picker[data-attribute-picker="1"]');
  const input = picker.querySelector(".jluxe-pm-term-search");
	input.value = "سبز";
	input.dispatchEvent(new window.Event("input", { bubbles: true }));
	await new Promise((resolve) => setTimeout(resolve, 250));
  const option = picker.querySelector("[data-pm-select-term]");
  assert.ok(option, "mock term endpoint supplies a search result");
  option.click();
  assert.equal(picker.querySelectorAll(".jluxe-pm-term-chip").length, 2, "one selected term creates one chip");
  assert.equal(picker.querySelector(".jluxe-pm-term-chip[data-id='14']").textContent, "سبز×");
});
