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
      <header class="jluxe-pm-header"></header>
      <div id="jluxe-pm-notice" hidden></div>
      <section class="jluxe-pm-filters">
        <div class="jluxe-pm-filter-main">
          <label class="jluxe-pm-search"><input id="jluxe-pm-search" type="search"></label>
          ${filterMarkup("category", "product_cat")}
          ${filterMarkup("brand", "product_brand")}
          <select id="jluxe-pm-type"><option value=""></option><option value="simple">ساده</option><option value="variable">متغیر</option></select>
          <select id="jluxe-pm-stock"><option value=""></option><option value="instock">موجود</option></select>
          <button id="jluxe-pm-search-button"></button><button id="jluxe-pm-reset"></button>
        </div>
        <details class="jluxe-pm-more-filters"><summary>فیلترهای بیشتر</summary>
          <div class="jluxe-pm-advanced-filters">
            <select id="jluxe-pm-status"><option value=""></option><option value="publish">منتشرشده</option></select>
            <label><span>ویژگی</span><select id="jluxe-pm-attribute-taxonomy"><option value="">همه</option><option value="pa_size">اندازه</option></select></label>
            ${filterMarkup("attribute-term", "", true)}
          </div>
        </details>
      </section>
      <span id="jluxe-pm-summary"></span><span id="jluxe-pm-page-label"></span>
      <button id="jluxe-pm-refresh"></button>
      <div class="jluxe-pm-table-wrap"><table><tbody id="jluxe-pm-rows"></tbody></table></div>
      <button id="jluxe-pm-prev"></button><button id="jluxe-pm-next"></button>
      <section class="jluxe-pm-editor" id="jluxe-pm-editor" hidden>
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
    featured: false,
    manageStock: false,
    stockQuantity: "",
    stockStatus: "instock",
    stockLabel: "موجود",
    categories: [],
    brands: [],
    brandTaxonomy: "product_brand",
    defaultAttributes: [],
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
  product.defaultAttributes = [{ name: "pa_size", label: "اندازه", value: "m", valueLabel: "متوسط" }];
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
      imageId: 888,
      imageUrl: "https://shop.test/uploads/variation-888.jpg",
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
    defaultAttributes: { pa_size: "m" },
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
    featured: !!product.featured,
    manageStock: product.manageStock,
    stockQuantity: product.stockQuantity,
    stockStatus: product.stockStatus,
    stockLabel: product.stockLabel || "موجود",
    defaultAttributes: product.defaultAttributes || [],
    variationCount: product.type === "variable" ? (product.variations?.total ?? product.variations?.items?.length ?? 0) : 0,
    categories: (product.categories || []).map((term) => term.name).join("، "),
    image: "",
    editUrl: product.editUrl,
  };
}

function defaultSummary(defaults, attributes) {
  return Object.entries(defaults).map(([name, value]) => {
    const definition = attributes.find((item) => item.name === name);
    const option = definition?.options.find((item) => String(item.value) === String(value));
    return { name, label: definition?.label || name, value, valueLabel: option?.label || value };
  });
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
    canUpload: true,
    attributes: [{ taxonomy: "pa_size", label: "اندازه" }],
  };
  const sent = [];
  const mediaFrames = [];
  window.wp = {
    media(options) {
      let selected = null;
      let onSelect = null;
      const frame = {
        options,
        on(event, callback) { if (event === "select") onSelect = callback; return frame; },
        open() { frame.opened = true; },
        state() {
          return { get() { return { first() { return { toJSON() { return selected; } }; } }; } };
        },
        choose(attachment) { selected = attachment; onSelect?.(); },
      };
      mediaFrames.push(frame);
      return frame;
    },
  };
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
    if (data.defaults) data.defaults = JSON.parse(data.defaults);
    sent.push({ action, data });

    let result;
    if (action === "jluxe_product_manager_list") {
      result = { products: [summaryOf(currentProduct)], page: Number(data.page) || 1, pages: 1, total: 1, perPage: 20 };
    } else if (action === "jluxe_product_manager_get_product") {
      result = structuredClone(currentProduct);
    } else if (action === "jluxe_product_manager_terms") {
      result = { terms: terms[data.taxonomy] || [], canCreate: false };
    } else if (action === "jluxe_product_manager_quick_save") {
      if (quickError) return { ok: false, json: async () => ({ success: false, data: { message: "ذخیره رد شد." } }) };
      if (Object.hasOwn(data, "regular_price")) currentProduct.regularPrice = data.regular_price;
      if (Object.hasOwn(data, "sale_price")) currentProduct.salePrice = data.sale_price;
      currentProduct.featured = data.featured === "1";
      currentProduct.manageStock = data.manage_stock === "1";
      currentProduct.stockQuantity = currentProduct.manageStock ? data.stock_quantity : "";
      currentProduct.stockStatus = currentProduct.manageStock
        ? (Number(data.stock_quantity) > 0 ? "instock" : "outofstock")
        : (data.stock_status || currentProduct.stockStatus);
      result = { message: "قیمت و موجودی ذخیره شد.", product: summaryOf(currentProduct) };
    } else if (action === "jluxe_product_manager_save_organization") {
      const selectedCategories = data.category_ids || [];
      const selectedBrands = data.brand_ids || [];
      currentProduct.categories = terms.product_cat.filter((term) => selectedCategories.includes(String(term.id)));
      currentProduct.brands = terms.product_brand.filter((term) => selectedBrands.includes(String(term.id)));
      result = { message: "دسته‌بندی و برند ذخیره شد.", product: summaryOf(currentProduct) };
    } else if (action === "jluxe_product_manager_save_defaults") {
      currentProduct.variations.defaultAttributes = data.defaults;
      currentProduct.defaultAttributes = defaultSummary(data.defaults, currentProduct.variations.attributes);
      result = {
        message: "پیش‌فرض‌های انتخاب تنوع ذخیره شد.",
        defaultMap: structuredClone(data.defaults),
        defaultAttributes: structuredClone(currentProduct.defaultAttributes),
      };
    } else if (action === "jluxe_product_manager_quick_save_variation") {
      if (variationError) return { ok: false, json: async () => ({ success: false, data: { message: "ترکیب تنوع تکراری است." } }) };
      const variation = data.variation;
      const index = currentProduct.variations.items.findIndex((item) => String(item.id) === String(data.variation_id));
      currentProduct.variations.items[index] = { ...currentProduct.variations.items[index], ...variation };
      result = { message: "تنوع ذخیره شد.", variation: structuredClone(currentProduct.variations.items[index]) };
    } else if (action === "jluxe_product_manager_get_variations") {
      result = structuredClone(currentProduct.variations);
      result.page = Number(data.page) || 1;
    } else {
      result = { message: "ok" };
    }
    return { ok: true, json: async () => ({ success: true, data: result }) };
  };
  window.eval(source);
  return { dom, window, sent, mediaFrames, get product() { return currentProduct; } };
}

function wait(ms = 30) {
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

test("product table exposes featured, regular/sale price, stock toggle, quantity, and simple stock status inline", async (t) => {
  const { window, sent, product } = createAdmin(t);
  await wait();
  const row = window.document.querySelector(".jluxe-pm-product-row");
  assert.ok(row, "the product list loads");
  assert.equal(row.children.length, 7, "product cells align with the seven table headings");
  assert.ok(row.querySelector("[data-pm-inline-field='regularPrice']"));
  assert.ok(row.querySelector("[data-pm-inline-field='salePrice']"));
  assert.ok(row.querySelector("[data-pm-inline-field='featured']"));
  const manage = row.querySelector("[data-pm-inline-field='manageStock']");
  const quantity = row.querySelector("[data-pm-inline-field='stockQuantity']");
  const stockStatus = row.querySelector("[data-pm-inline-field='stockStatus']");
  assert.equal(quantity.hidden, true, "an unmanaged simple product starts with its count hidden");
  assert.equal(stockStatus.hidden, false, "the simple product's editable status is shown while stock management is off");

  const regular = row.querySelector("[data-pm-inline-field='regularPrice']");
  const sale = row.querySelector("[data-pm-inline-field='salePrice']");
  regular.value = "145";
  regular.dispatchEvent(new window.Event("input", { bubbles: true }));
  sale.value = "130";
  sale.dispatchEvent(new window.Event("input", { bubbles: true }));
  const featured = row.querySelector("[data-pm-inline-field='featured']");
  featured.checked = true;
  featured.dispatchEvent(new window.Event("change", { bubbles: true }));

  manage.checked = true;
  manage.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(quantity.hidden, false, "the count becomes visible when management is enabled");
  assert.equal(stockStatus.hidden, true, "manual status is hidden while the count determines status");
  assert.match(row.querySelector("[data-pm-row-status='101']").textContent, /ابتدا تعداد/);
  quantity.value = "4";
  quantity.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(500);

  const saves = sent.filter((item) => item.action === "jluxe_product_manager_quick_save");
  assert.equal(saves.length, 1, "rapid inline changes are combined into one autosave");
  assert.equal(saves[0].data.regular_price, "145");
  assert.equal(saves[0].data.sale_price, "130");
  assert.equal(saves[0].data.featured, "1");
  assert.equal(saves[0].data.stock_quantity, "4");
  assert.equal(saves[0].data.manage_stock, "1");
  assert.equal(product.regularPrice, "145");
  assert.equal(product.salePrice, "130");
  assert.equal(product.featured, true);
  assert.equal(product.stockQuantity, "4");
  assert.match(row.querySelector("[data-pm-row-status='101']").textContent, /ذخیره شد/);
});

test("unmanaged simple stock status saves inline and remains editable without enabling quantity tracking", async (t) => {
  const { window, sent, product } = createAdmin(t);
  await wait();
  const row = window.document.querySelector(".jluxe-pm-product-row");
  const status = row.querySelector("[data-pm-inline-field='stockStatus']");
  status.value = "outofstock";
  status.dispatchEvent(new window.Event("change", { bubbles: true }));
  await wait(480);

  const save = sent.filter((item) => item.action === "jluxe_product_manager_quick_save").at(-1);
  assert.ok(save);
  assert.equal(save.data.manage_stock, "0");
  assert.equal(save.data.stock_status, "outofstock");
  assert.equal(product.stockStatus, "outofstock");
  assert.equal(row.querySelector("[data-pm-inline-field='stockQuantity']").hidden, true);
  assert.equal(status.hidden, false);
});

test("variable parent stock management can be switched on inline and reveals its quantity", async (t) => {
  const { window, sent, product } = createAdmin(t, { product: variableProduct() });
  await wait();
  const row = window.document.querySelector(".jluxe-pm-product-row");
  const manage = row.querySelector("[data-pm-inline-field='manageStock']");
  const quantity = row.querySelector("[data-pm-inline-field='stockQuantity']");
  const status = row.querySelector("[data-pm-stock-pill='202']");
  assert.equal(quantity.hidden, true);
  assert.match(status.textContent, /بر پایهٔ تنوع‌ها/);
  manage.checked = true;
  manage.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(quantity.hidden, false, "parent quantity appears when parent-level management is enabled");
  quantity.value = "7";
  quantity.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(500);
  const save = sent.filter((item) => item.action === "jluxe_product_manager_quick_save").at(-1);
  assert.ok(save);
  assert.equal(save.data.manage_stock, "1");
  assert.equal(save.data.stock_quantity, "7");
  assert.equal(product.manageStock, true);
  assert.equal(product.stockQuantity, "7");
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

test("category and brand stay in the visible filter bar and can be searched", async (t) => {
  const { window, sent } = createAdmin(t);
  await wait();
  assert.ok(window.document.querySelector("#jluxe-pm-filter-category").closest(".jluxe-pm-filter-main"));
  assert.ok(window.document.querySelector("#jluxe-pm-filter-brand").closest(".jluxe-pm-filter-main"));
  await searchAndSelect(window, "category", "پوشاک");
  await searchAndSelect(window, "brand", "برند");

  const lastList = sent.filter((item) => item.action === "jluxe_product_manager_list").at(-1);
  assert.equal(lastList.data.category, "31");
  assert.equal(lastList.data.brand, "41");
});

test("category and brand are saved through the focused organization endpoint and native editor remains available", async (t) => {
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

test("variation accordion edits prices, attributes, image, and independent stock settings without a separate editor", async (t) => {
  const productFixture = variableProduct();
  productFixture.variations.pages = 2;
  productFixture.variations.items[0].imageId = 888;
  const { window, sent, product, mediaFrames } = createAdmin(t, { product: productFixture });
  await wait();

  const expand = window.document.querySelector("[data-pm-variations-toggle='202']");
  assert.ok(expand, "variable row exposes its inline accordion");
  expand.click();
  await wait();
  const panel = window.document.querySelector("[data-pm-variation-panel='202']");
  assert.ok(panel, "variations open immediately below their parent row");
  assert.equal(window.document.querySelector("[data-pm-variation-panel='202']").closest("tr").previousElementSibling.dataset.productId, "202");
  const defaultSelect = panel.querySelector("[data-pm-default-attribute='pa_size']");
  assert.ok(defaultSelect, "existing default attribute choices are editable within the accordion");
  const nextPage = panel.querySelector("[data-pm-variation-page='2']");
  assert.ok(nextPage, "the variation table exposes its next-page loader");
  nextPage.click();
  assert.ok(panel.classList.contains("is-loading"), "the loading state starts while the variation request is pending");
  await wait();
  assert.equal(panel.classList.contains("is-loading"), false, "renderVariations clears the loading state once rows render");
  assert.equal(sent.filter((item) => item.action === "jluxe_product_manager_get_variations").at(-1).data.page, "2");

  const row = panel.querySelector(".jluxe-pm-variation-row");
  assert.equal(row.querySelectorAll("[data-v-attr]").length, 1, "only existing variation attribute options are selectable");
  assert.equal(row.querySelector("[data-v-field='sku']"), null, "specialist SKU editing stays in WooCommerce");
  assert.equal(row.querySelector("[data-v-field='enabled']"), null, "variation activation remains in the standard WooCommerce editor");
  const size = row.querySelector("[data-v-attr='pa_size']");
  size.value = "l";
  size.dispatchEvent(new window.Event("change", { bubbles: true }));
  const regular = row.querySelector("[data-v-field='regularPrice']");
  regular.value = "165";
  regular.dispatchEvent(new window.Event("input", { bubbles: true }));
  const sale = row.querySelector("[data-v-field='salePrice']");
  sale.value = "150";
  sale.dispatchEvent(new window.Event("input", { bubbles: true }));

  const imageButton = row.querySelector("[data-pm-variation-image]");
  imageButton.click();
  assert.equal(mediaFrames.length, 1);
  assert.equal(mediaFrames[0].options.library.type, "image", "the WordPress media picker only selects images");
  mediaFrames[0].choose({ id: 991, url: "https://shop.test/uploads/new.jpg", sizes: { thumbnail: { url: "https://shop.test/uploads/new-150.jpg" } } });
  assert.equal(row.querySelector("[data-v-field='imageId']").value, "991");

  const manage = row.querySelector("[data-v-field='manageStock']");
  manage.checked = false;
  manage.dispatchEvent(new window.Event("change", { bubbles: true }));
  const quantity = row.querySelector("[data-v-field='stockQuantity']");
  const status = row.querySelector("[data-v-field='stockStatus']");
  assert.equal(quantity.hidden, true, "the variation quantity hides when its independent management is disabled");
  assert.equal(quantity.disabled, true);
  assert.equal(status.hidden, false, "the unmanaged variation exposes its editable status");
  assert.equal(row.querySelector("[data-pm-variation-stock-pill]").hidden, true);
  status.value = "outofstock";
  status.dispatchEvent(new window.Event("change", { bubbles: true }));
  await wait(500);

  const save = sent.filter((item) => item.action === "jluxe_product_manager_quick_save_variation").at(-1);
  assert.ok(save, "variation edits use the inline autosave endpoint");
  assert.equal(save.data.parent_id, "202");
  assert.equal(save.data.variation_id, "203");
  assert.equal(save.data.variation.attributes.pa_size, "l");
  assert.equal(save.data.variation.regularPrice, "165");
  assert.equal(save.data.variation.salePrice, "150");
  assert.equal(save.data.variation.stockQuantity, "");
  assert.equal(save.data.variation.manageStock, false, "a non-empty old quantity does not silently re-enable variation stock management");
  assert.equal(save.data.variation.stockStatus, "outofstock");
  assert.equal(save.data.variation.imageId, "991");
  assert.equal(product.variations.items[0].attributes.pa_size, "l");
  assert.equal(String(product.variations.items[0].imageId), "991");
});

test("variation default values autosave from the accordion and update the product summary", async (t) => {
  const { window, sent, product } = createAdmin(t, { product: variableProduct() });
  await wait();
  window.document.querySelector("[data-pm-variations-toggle='202']").click();
  await wait();
  const select = window.document.querySelector("[data-pm-default-attribute='pa_size']");
  select.value = "l";
  select.dispatchEvent(new window.Event("change", { bubbles: true }));
  await wait(400);

  const save = sent.find((item) => item.action === "jluxe_product_manager_save_defaults");
  assert.ok(save, "default values are persisted by their dedicated validated endpoint");
  assert.deepEqual(save.data.defaults, { pa_size: "l" });
  assert.equal(product.defaultAttributes[0].value, "l");
  assert.match(window.document.querySelector("[data-pm-default-summary='202']").textContent, /بزرگ/);
  assert.match(window.document.querySelector("[data-pm-default-status='202']").textContent, /ذخیره شد/);
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

test("quick organization editor omits specialist product fields and keeps native WooCommerce as the escape hatch", async (t) => {
  const { window } = createAdmin(t);
  await wait();
  window.document.querySelector("[data-pm-edit='101']").click();
  await wait();
  assert.equal(window.document.querySelectorAll(".jluxe-pm-tabs").length, 0);
  assert.equal(window.document.querySelectorAll("[data-field]").length, 0);
  assert.equal(window.document.querySelectorAll(".jluxe-pm-faq-row, .jluxe-pm-shipping, .jluxe-pm-seo").length, 0);
  assert.ok(window.document.querySelector("#jluxe-pm-native-edit").href.includes("post=101"));
});
