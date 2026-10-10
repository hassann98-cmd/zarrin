import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/product-manager.js", import.meta.url), "utf8");

function filterMarkup(key, taxonomy, disabled = false) {
  const title = key === "category" ? "دسته‌بندی" : key === "brand" ? "برند" : "مقدار ویژگی";
  return `<div class="jluxe-pm-filter-term" id="jluxe-pm-filter-${key}" data-pm-filter-term="${key}" data-taxonomy="${taxonomy}">
    <label for="jluxe-pm-${key}-search">${title}</label>
    <div class="jluxe-pm-filter-control">
      <span class="jluxe-pm-filter-selected" data-pm-filter-selected hidden><span data-pm-filter-label></span><button type="button" data-pm-clear-filter>×</button></span>
      <input class="jluxe-pm-filter-search" id="jluxe-pm-${key}-search" type="search"${disabled ? " disabled" : ""}>
      <input type="hidden" id="jluxe-pm-${key}" value="">
    </div>
    <div class="jluxe-pm-filter-results" role="listbox" hidden></div>
  </div>`;
}

function adminMarkup() {
  return `<!doctype html><html><body>
    <div id="jluxe-product-manager">
      <div id="jluxe-pm-notice" hidden></div>
      <label><input id="jluxe-pm-search" type="search"></label>
      <select id="jluxe-pm-type"><option value=""></option><option value="simple">ساده</option><option value="variable">متغیر</option></select>
      <select id="jluxe-pm-status"><option value=""></option><option value="publish">منتشرشده</option></select>
      <select id="jluxe-pm-stock"><option value=""></option><option value="instock">موجود</option></select>
      <button id="jluxe-pm-search-button"></button><button id="jluxe-pm-reset"></button><button id="jluxe-pm-refresh"></button>
      <div class="jluxe-pm-advanced-filters">
        ${filterMarkup("category", "product_cat")}
        ${filterMarkup("brand", "product_brand")}
        <label><span>ویژگی</span><select id="jluxe-pm-attribute-taxonomy"><option value="">همه</option><option value="pa_size">اندازه</option></select></label>
        ${filterMarkup("attribute-term", "", true)}
      </div>
      <span id="jluxe-pm-summary"></span><span id="jluxe-pm-page-label"></span>
      <table><tbody id="jluxe-pm-rows"></tbody></table>
      <button id="jluxe-pm-prev"></button><button id="jluxe-pm-next"></button>
      <section id="jluxe-pm-editor" hidden>
        <div class="jluxe-pm-editor-backdrop" data-pm-close-editor></div>
        <div class="jluxe-pm-editor-card" tabindex="-1">
          <header><h2 id="jluxe-pm-editor-title"></h2><p id="jluxe-pm-editor-subtitle"></p><button type="button" data-pm-close-editor></button></header>
          <div id="jluxe-pm-editor-body"></div>
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
    type: "simple",
    typeLabel: "ساده",
    sku: "SIMPLE-101",
    status: "publish",
    regularPrice: "120",
    salePrice: "",
    priceLabel: "۱۲۰ تومان",
    manageStock: false,
    stockQuantity: "",
    stockStatus: "instock",
    stockLabel: "موجود",
    categories: [],
    brands: [],
    brandTaxonomy: "product_brand",
    variations: {},
    editUrl: "/wp-admin/post.php?post=101&action=edit",
  };
}

function variableProduct() {
  const product = simpleProduct();
  product.id = 202;
  product.name = "محصول متغیر آزمایشی";
  product.type = "variable";
  product.typeLabel = "متغیر";
  product.sku = "VAR-202";
  product.priceLabel = "از ۱۲۰ تا ۱۸۰ تومان";
  product.stockLabel = "موجودی هر تنوع";
  product.regularPrice = "";
  product.variations = {
    items: [{
      id: 203,
      sku: "VAR-203",
      regularPrice: "120",
      salePrice: "",
      manageStock: true,
      stockQuantity: 3,
      stockStatus: "instock",
      backorders: "no",
      enabled: true,
      attributes: { pa_size: "m" },
    }],
    page: 1,
    pages: 1,
    total: 1,
    attributes: [{
      name: "pa_size",
      label: "اندازه",
      options: [{ value: "m", label: "متوسط" }, { value: "l", label: "بزرگ" }],
    }],
  };
  return product;
}

function summaryOf(product) {
  return {
    id: product.id,
    name: product.name,
    sku: product.sku,
    type: product.type,
    typeLabel: product.typeLabel,
    status: product.status,
    regularPrice: product.regularPrice,
    salePrice: product.salePrice,
    priceLabel: product.priceLabel || `${product.regularPrice} تومان`,
    manageStock: product.manageStock,
    stockQuantity: product.stockQuantity,
    stockStatus: product.stockStatus,
    stockLabel: product.stockLabel || "موجود",
    categories: (product.categories || []).map((term) => term.name).join("، "),
    image: "",
    editUrl: product.editUrl,
  };
}

function createAdmin(t, { product = simpleProduct(), quickError = false, variationError = false } = {}) {
  const dom = new JSDOM(adminMarkup(), {
    url: "https://shop.test/wp-admin/admin.php?page=jluxe-product-manager",
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  window.confirm = () => true;
  window.jluxeProductManager = {
    ajaxUrl: "/wp-admin/admin-ajax.php",
    nonce: "nonce-test",
    currency: "تومان",
    brandTaxonomy: "product_brand",
    attributes: [{ taxonomy: "pa_size", label: "اندازه" }],
  };
  const sent = [];
  let currentProduct = structuredClone(product);
  const terms = {
    product_cat: [{ id: 31, name: "پوشاک", slug: "clothing" }],
    product_brand: [{ id: 41, name: "برند آزمایشی", slug: "test-brand" }],
    pa_size: [{ id: 51, name: "بزرگ", slug: "large" }],
  };

  window.fetch = async (_url, options = {}) => {
    const form = new URLSearchParams(options.body);
    const action = form.get("action");
    const data = {};
    for (const [key, value] of form.entries()) {
      if (key.endsWith("[]")) {
        const normalized = key.slice(0, -2);
        (data[normalized] ||= []).push(value);
      } else {
        data[key] = value;
      }
    }
    if (data.variation) data.variation = JSON.parse(data.variation);
    sent.push({ action, data });

    let result;
    if (action === "jluxe_product_manager_list") {
      result = { products: [summaryOf(currentProduct)], page: 1, pages: 1, total: 1, perPage: 20 };
    } else if (action === "jluxe_product_manager_get_product") {
      result = structuredClone(currentProduct);
    } else if (action === "jluxe_product_manager_terms") {
      result = { terms: terms[data.taxonomy] || [], canCreate: false };
    } else if (action === "jluxe_product_manager_quick_save") {
      if (quickError) return { ok: false, json: async () => ({ success: false, data: { message: "ذخیره رد شد." } }) };
      currentProduct.regularPrice = data.regular_price;
      currentProduct.salePrice = data.sale_price;
      currentProduct.manageStock = data.manage_stock === "1";
      currentProduct.stockQuantity = currentProduct.manageStock ? data.stock_quantity : "";
      currentProduct.stockStatus = currentProduct.manageStock && Number(data.stock_quantity) <= 0 ? "outofstock" : "instock";
      result = { message: "قیمت و موجودی ذخیره شد.", product: summaryOf(currentProduct) };
    } else if (action === "jluxe_product_manager_save_organization") {
      const selectedCategories = data.category_ids || [];
      const selectedBrands = data.brand_ids || [];
      currentProduct.categories = terms.product_cat.filter((term) => selectedCategories.includes(String(term.id)));
      currentProduct.brands = terms.product_brand.filter((term) => selectedBrands.includes(String(term.id)));
      result = { message: "دسته‌بندی و برند ذخیره شد.", product: summaryOf(currentProduct) };
    } else if (action === "jluxe_product_manager_quick_save_variation") {
      if (variationError) return { ok: false, json: async () => ({ success: false, data: { message: "ترکیب تنوع تکراری است." } }) };
      const variation = data.variation;
      const index = currentProduct.variations.items.findIndex((item) => String(item.id) === String(data.variation_id));
      currentProduct.variations.items[index] = { ...currentProduct.variations.items[index], ...variation };
      result = { message: "تنوع ذخیره شد.", variation: structuredClone(currentProduct.variations.items[index]) };
    } else if (action === "jluxe_product_manager_get_variations") {
      result = structuredClone(currentProduct.variations);
    } else {
      result = { message: "ok" };
    }
    return { ok: true, json: async () => ({ success: true, data: result }) };
  };
  window.eval(source);
  return { dom, window, sent, get product() { return currentProduct; } };
}

function wait(ms = 25) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function searchAndSelect(window, key, query) {
  const wrapper = window.document.querySelector(`#jluxe-pm-filter-${key}`);
  const input = wrapper.querySelector(".jluxe-pm-filter-search");
  input.value = query;
  input.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(250);
  const result = wrapper.querySelector("[data-pm-filter-select]");
  assert.ok(result, `the ${key} search returns an option`);
  result.click();
  await wait();
}

test("simple-product price and inventory fields autosave together and announce success", async (t) => {
  const { window, sent, product } = createAdmin(t);
  await wait();
  assert.equal(window.document.querySelectorAll(".jluxe-pm-product-row").length, 1, "the product list loads");

  const price = window.document.querySelector("[data-pm-inline-field='regularPrice']");
  const stock = window.document.querySelector("[data-pm-inline-field='stockQuantity']");
  price.value = "145";
  price.dispatchEvent(new window.Event("input", { bubbles: true }));
  stock.value = "4";
  stock.dispatchEvent(new window.Event("input", { bubbles: true }));

  assert.match(window.document.querySelector("[data-pm-row-status='101']").textContent, /ذخیره/);
  await wait(500);
  const saves = sent.filter((item) => item.action === "jluxe_product_manager_quick_save");
  assert.equal(saves.length, 1, "rapid field changes are combined into one autosave");
  assert.equal(saves[0].data.regular_price, "145");
  assert.equal(saves[0].data.stock_quantity, "4");
  assert.equal(saves[0].data.manage_stock, "1", "entering a count enables WooCommerce stock management");
  assert.equal(product.regularPrice, "145");
  assert.equal(product.stockQuantity, "4");
  assert.match(window.document.querySelector("[data-pm-row-status='101']").textContent, /ذخیره شد/);
});

test("autosave failures stay visible beside the row and keep the edited value", async (t) => {
  const { window, sent } = createAdmin(t, { quickError: true });
  await wait();
  const price = window.document.querySelector("[data-pm-inline-field='regularPrice']");
  price.value = "155";
  price.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(470);

  assert.equal(sent.filter((item) => item.action === "jluxe_product_manager_quick_save").length, 1);
  assert.equal(price.value, "155", "an unsuccessful save does not discard the administrator's edit");
  const status = window.document.querySelector("[data-pm-row-status='101']");
  assert.match(status.textContent, /ذخیره رد شد/);
  assert.ok(status.classList.contains("is-error"), "the failure uses a clear error state");
});

test("category and brand are searchable and saved through the focused organization endpoint", async (t) => {
  const { window, sent, product } = createAdmin(t);
  await wait();
  window.document.querySelector("[data-pm-edit='101']").click();
  await wait();
  assert.equal(window.document.querySelector("#jluxe-pm-editor").hidden, false);
  assert.equal(window.document.querySelector("#jluxe-pm-native-edit").href, "https://shop.test/wp-admin/post.php?post=101&action=edit");

  const category = window.document.querySelector('.jluxe-pm-term-picker[data-taxonomy="product_cat"]');
  const categorySearch = category.querySelector(".jluxe-pm-term-search");
  categorySearch.value = "پوشاک";
  categorySearch.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(250);
  category.querySelector("[data-pm-select-term]").click();

  const brand = window.document.querySelector('.jluxe-pm-term-picker[data-taxonomy="product_brand"]');
  const brandSearch = brand.querySelector(".jluxe-pm-term-search");
  brandSearch.value = "برند";
  brandSearch.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(250);
  brand.querySelector("[data-pm-select-term]").click();

  assert.equal(window.document.querySelector("#jluxe-pm-save").disabled, false);
  window.document.querySelector("#jluxe-pm-save").click();
  await wait();

  const save = sent.find((item) => item.action === "jluxe_product_manager_save_organization");
  assert.ok(save, "category and brand use their focused save endpoint");
  assert.deepEqual(save.data.category_ids, ["31"]);
  assert.deepEqual(save.data.brand_ids, ["41"]);
  assert.equal(save.data.brand_taxonomy, "product_brand");
  assert.equal(product.categories[0].name, "پوشاک");
  assert.equal(product.brands[0].name, "برند آزمایشی");
  assert.match(window.document.querySelector("#jluxe-pm-save-state").textContent, /ذخیره شد/);
});

test("variable rows autosave size, price and stock without specialist columns", async (t) => {
  const productFixture = variableProduct();
  productFixture.variations.pages = 2;
  const { window, sent, product } = createAdmin(t, { product: productFixture });
  await wait();
  window.document.querySelector("[data-pm-edit='202']").click();
  await wait();

  const variationPanel = window.document.querySelector("[data-panel='variations']");
  const nextVariationPage = variationPanel.querySelector("[data-pm-variation-page='2']");
  assert.ok(nextVariationPage, "the variation table exposes its next-page loader");
  nextVariationPage.click();
  assert.ok(variationPanel.classList.contains("is-loading"), "the loading state starts while the variation request is pending");
  await wait();
  assert.equal(variationPanel.classList.contains("is-loading"), false, "renderVariations clears the loading state as soon as rows render");

  const row = window.document.querySelector(".jluxe-pm-variation-row");
  assert.equal(window.document.querySelectorAll(".jluxe-pm-variation-row").length, 1);
  assert.equal(row.querySelectorAll("[data-v-attr]").length, 1, "configured size options can be changed directly");
  assert.equal(row.querySelectorAll("[data-v-field]").length, 3, "only prices and inventory are shown for daily edits");
  assert.equal(row.querySelector("[data-v-field='sku']"), null, "specialist SKU editing is left to WooCommerce");

  const size = row.querySelector("[data-v-attr='pa_size']");
  size.value = "l";
  size.dispatchEvent(new window.Event("change", { bubbles: true }));
  const price = row.querySelector("[data-v-field='regularPrice']");
  price.value = "165";
  price.dispatchEvent(new window.Event("input", { bubbles: true }));
  const stock = row.querySelector("[data-v-field='stockQuantity']");
  stock.value = "5";
  stock.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(500);

  const save = sent.find((item) => item.action === "jluxe_product_manager_quick_save_variation");
  assert.ok(save, "variation edits use the autosave endpoint");
  assert.equal(save.data.parent_id, "202");
  assert.equal(save.data.variation_id, "203");
  assert.equal(save.data.variation.attributes.pa_size, "l");
  assert.equal(save.data.variation.regularPrice, "165");
  assert.equal(save.data.variation.stockQuantity, "5");
  assert.equal(save.data.variation.manageStock, true);
  assert.equal(product.variations.items[0].attributes.pa_size, "l");
  assert.equal(product.variations.items[0].regularPrice, "165");
  assert.match(window.document.querySelector("[data-pm-variation-status='203']").textContent, /ذخیره شد/);
});

test("product filters search category, brand and registered attribute terms", async (t) => {
  const { window, sent } = createAdmin(t);
  await wait();
  await searchAndSelect(window, "category", "پوشاک");
  await searchAndSelect(window, "brand", "برند");

  const attribute = window.document.querySelector("#jluxe-pm-attribute-taxonomy");
  attribute.value = "pa_size";
  attribute.dispatchEvent(new window.Event("change", { bubbles: true }));
  await wait();
  await searchAndSelect(window, "attribute-term", "بزرگ");

  const lastList = sent.filter((item) => item.action === "jluxe_product_manager_list").at(-1);
  assert.equal(lastList.data.category, "31");
  assert.equal(lastList.data.brand, "41");
  assert.equal(lastList.data.attribute_taxonomy, "pa_size");
  assert.equal(lastList.data.attribute_term, "51");
});

test("the quick editor omits expert-only fields and keeps the native WooCommerce editor link", async (t) => {
  const { window } = createAdmin(t);
  await wait();
  window.document.querySelector("[data-pm-edit='101']").click();
  await wait();
  assert.equal(window.document.querySelectorAll(".jluxe-pm-tabs").length, 0);
  assert.equal(window.document.querySelectorAll("[data-field]").length, 0);
  assert.equal(window.document.querySelectorAll(".jluxe-pm-faq-row, .jluxe-pm-shipping, .jluxe-pm-seo").length, 0);
  assert.ok(window.document.querySelector("#jluxe-pm-native-edit").href.includes("post=101"));
});
