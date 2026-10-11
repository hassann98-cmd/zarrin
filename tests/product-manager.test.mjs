import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/product-manager.js", import.meta.url), "utf8");
const styles = fs.readFileSync(new URL("../assets/css/product-manager.css", import.meta.url), "utf8");

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
          <div class="jluxe-pm-search"><label class="jluxe-pm-field-label" for="jluxe-pm-search">نام یا SKU</label>
            <div class="jluxe-pm-search-control"><input id="jluxe-pm-search" type="search"><button id="jluxe-pm-search-button" type="button" aria-label="جست‌وجو"></button></div>
          </div>
          ${filterMarkup("category", "product_cat")}
          ${filterMarkup("brand", "product_brand")}
          <label class="jluxe-pm-filter-select"><span>نوع</span><select id="jluxe-pm-type"><option value=""></option><option value="simple">ساده</option><option value="variable">متغیر</option></select></label>
          <label class="jluxe-pm-filter-select"><span>موجودی</span><select id="jluxe-pm-stock"><option value=""></option><option value="instock">موجود</option></select></label>
          <button id="jluxe-pm-reset" type="button"></button>
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
    onSale: false,
    featured: false,
    manageStock: false,
    stockQuantity: "",
    stockStatus: "instock",
    stockLabel: "موجود",
    categories: [],
    brands: [],
    brandTaxonomy: "product_brand",
    defaultAttributes: [],
    productUrl: "/product/101/",
    editUrl: "/wp-admin/post.php?post=101&action=edit",
  };
}

function variableProduct() {
  const product = simpleProduct();
  product.id = 202;
  product.productUrl = "/product/202/";
  product.editUrl = "/wp-admin/post.php?post=202&action=edit";
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
      onSale: false,
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
    onSale: !!product.onSale,
    featured: !!product.featured,
    manageStock: product.manageStock,
    stockQuantity: product.stockQuantity,
    stockStatus: product.stockStatus,
    stockLabel: product.stockLabel || "موجود",
    defaultAttributes: product.defaultAttributes || [],
    variationCount: product.type === "variable" ? (product.variations?.total ?? product.variations?.items?.length ?? 0) : 0,
    categories: (product.categories || []).map((term) => term.name).join("، "),
    image: "",
    productUrl: product.productUrl,
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

function createAdmin(t, { product = simpleProduct(), products = null, quickError = false, variationError = false } = {}) {
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
  const listedProducts = (products || [product]).map((item) => structuredClone(item));
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
      const term = String(data.search || "").trim().toLocaleLowerCase();
      const matchingProducts = term
        ? listedProducts.filter((item) => [item.name, item.sku, String(item.id)].some((value) => String(value || "").toLocaleLowerCase().includes(term)))
        : listedProducts;
      result = { products: matchingProducts.map(summaryOf), page: Number(data.page) || 1, pages: 1, total: matchingProducts.length, perPage: 20 };
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
  const actionControls = row.querySelector(".jluxe-pm-actions-controls");
  assert.ok(actionControls, "product actions are grouped in a layout wrapper");
  assert.equal(actionControls.querySelectorAll("button").length, 1, "simple products expose one consistently aligned action");
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
  assert.equal(row.querySelector("[data-pm-inline-field='saleEnabled']").checked, true, "entering a sale price enables its sale switch");
  assert.equal(saves[0].data.featured, "1");
  assert.equal(saves[0].data.stock_quantity, "4");
  assert.equal(saves[0].data.manage_stock, "1");
  assert.equal(product.regularPrice, "145");
  assert.equal(product.salePrice, "130");
  assert.equal(product.featured, true);
  assert.equal(product.stockQuantity, "4");
  assert.match(row.querySelector("[data-pm-row-status='101']").textContent, /ذخیره شد/);
});

test("product rows keep product links, grouped prices, and sale controls without redundant status text", async (t) => {
  const saleProduct = simpleProduct();
  saleProduct.regularPrice = "2590000";
  saleProduct.salePrice = "2190000";
  saleProduct.onSale = true;
  saleProduct.featured = true;
  const { window, sent } = createAdmin(t, { product: saleProduct });
  await wait();

  const row = window.document.querySelector(".jluxe-pm-product-row");
  const links = Array.from(row.querySelectorAll(".jluxe-pm-product-links a"));
  assert.equal(links.length, 2, "each product has a view link and a WooCommerce edit link beneath its name");
  assert.equal(links[0].textContent, "مشاهده محصول");
  assert.equal(links[0].href, "https://shop.test/product/101/");
  assert.equal(links[1].textContent, "ویرایش ووکامرس");
  assert.equal(links[1].href, "https://shop.test/wp-admin/post.php?post=101&action=edit");
  assert.ok(links.every((link) => link.target === "_blank" && link.rel.includes("noopener")));

  const regular = row.querySelector("[data-pm-inline-field='regularPrice']");
  const sale = row.querySelector("[data-pm-inline-field='salePrice']");
  const saleToggle = row.querySelector("[data-pm-inline-field='saleEnabled']");
  assert.equal(regular.value, "2,590,000");
  assert.equal(sale.value, "2,190,000");
  assert.equal(saleToggle.checked, true, "the sale price is represented by a real, enabled switch");
  assert.equal(row.querySelector(".jluxe-pm-sale-state"), null, "sale status is not repeated as extra text beside the price field");
  assert.equal(row.querySelector("[data-pm-featured-hint]"), null, "the featured state is communicated by its checked switch");
  assert.equal(row.querySelector("[data-pm-inline-field='featured']").checked, true);
  assert.equal(row.querySelector(".jluxe-pm-toggle-label"), null, "the switch does not repeat the featured column label");

  regular.value = "2،590،000";
  regular.dispatchEvent(new window.Event("input", { bubbles: true }));
  assert.equal(regular.value, "2,590,000", "typing with Persian thousands punctuation is regrouped for readability");
  await wait(480);
  const save = sent.filter((item) => item.action === "jluxe_product_manager_quick_save").at(-1);
  assert.equal(save.data.regular_price, "2590000", "group separators are removed before the numeric value is saved");

  saleToggle.click();
  await wait(480);
  const disabledSaleSave = sent.filter((item) => item.action === "jluxe_product_manager_quick_save").at(-1);
  assert.equal(saleToggle.checked, false);
  assert.equal(sale.value, "", "turning off the sale switch clears the WooCommerce sale price");
  assert.equal(disabledSaleSave.data.sale_price, "", "the discount is actually disabled in the quick-save payload");
  saleToggle.click();
  assert.equal(saleToggle.checked, false, "a sale cannot be enabled without a price");
  assert.equal(window.document.activeElement, sale, "the sale-price field receives focus when an empty switch is activated");
});

test("product stripes alternate by product and expanded variations inherit their parent stripe", async (t) => {
  const first = simpleProduct();
  const second = variableProduct();
  const third = simpleProduct();
  third.id = 303;
  third.productUrl = "/product/303/";
  third.editUrl = "/wp-admin/post.php?post=303&action=edit";
  const { window } = createAdmin(t, { product: second, products: [first, second, third] });
  await wait();

  const rows = Array.from(window.document.querySelectorAll(".jluxe-pm-product-row"));
  assert.equal(rows.length, 3);
  assert.equal(rows[0].classList.contains("is-alt"), false, "the first product uses the base surface");
  assert.equal(rows[1].classList.contains("is-alt"), true, "the second product uses the alternating surface");
  assert.equal(rows[2].classList.contains("is-alt"), false, "the third product returns to the base surface");
  assert.equal(
    rows[1].querySelectorAll(".jluxe-pm-actions-controls > button").length,
    2,
    "variable products group both available actions together",
  );

  rows[1].querySelector("[data-pm-variations-toggle='202']").click();
  const details = rows[1].nextElementSibling;
  assert.ok(details.classList.contains("jluxe-pm-accordion-row"));
  assert.equal(details.classList.contains("is-alt"), true, "the variation panel stays aligned with its parent's stripe");
  await wait();
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

test("product search handles partial SKU and name queries by typing, Enter, and button without duplicate requests", async (t) => {
  const coat = simpleProduct();
  coat.name = "مانتو بهاره";
  coat.sku = "COAT-101";
  const dress = variableProduct();
  const scarf = simpleProduct();
  scarf.id = 303;
  scarf.name = "شال نخی";
  scarf.sku = "SCARF-303";
  scarf.productUrl = "/product/303/";
  scarf.editUrl = "/wp-admin/post.php?post=303&action=edit";
  const { window, sent } = createAdmin(t, { product: coat, products: [coat, dress, scarf] });
  await wait();

  const search = window.document.querySelector("#jluxe-pm-search");
  search.value = "VAR-20";
  search.dispatchEvent(new window.Event("input", { bubbles: true }));
  await wait(300);
  let requests = sent.filter((item) => item.action === "jluxe_product_manager_list");
  assert.equal(requests.at(-1).data.search, "VAR-20", "debounced input sends partial SKU text to the list endpoint");
  assert.deepEqual(Array.from(window.document.querySelectorAll(".jluxe-pm-product-row"), (row) => row.dataset.productId), ["202"]);

  const beforeEnter = requests.length;
  search.value = "مانتو";
  search.dispatchEvent(new window.Event("input", { bubbles: true }));
  const enter = new window.KeyboardEvent("keydown", { key: "Enter", bubbles: true, cancelable: true });
  search.dispatchEvent(enter);
  search.dispatchEvent(new window.Event("search"));
  assert.equal(enter.defaultPrevented, true, "Enter submits the query instead of leaving it pending");
  await wait(300);
  requests = sent.filter((item) => item.action === "jluxe_product_manager_list");
  assert.equal(requests.length, beforeEnter + 1, "Enter and the native search event clear the pending debounce and do not duplicate the request");
  assert.equal(requests.at(-1).data.search, "مانتو");
  assert.deepEqual(Array.from(window.document.querySelectorAll(".jluxe-pm-product-row"), (row) => row.dataset.productId), ["101"]);

  const beforeButton = requests.length;
  search.value = "303";
  search.dispatchEvent(new window.Event("input", { bubbles: true }));
  window.document.querySelector("#jluxe-pm-search-button").click();
  await wait(300);
  requests = sent.filter((item) => item.action === "jluxe_product_manager_list");
  assert.equal(requests.length, beforeButton + 1, "the search button submits immediately and cancels the debounce");
  assert.equal(requests.at(-1).data.search, "303");
  assert.deepEqual(Array.from(window.document.querySelectorAll(".jluxe-pm-product-row"), (row) => row.dataset.productId), ["303"]);
});

test("stale product-list responses are skipped while a newer search is queued", async (t) => {
  const coat = simpleProduct();
  coat.name = "مانتو بهاره";
  const dress = variableProduct();
  const { window } = createAdmin(t, { product: coat, products: [coat, dress] });
  await wait();
  const originalFetch = window.fetch;
  window.fetch = async (url, options) => {
    const form = new URLSearchParams(options.body);
    if (form.get("action") === "jluxe_product_manager_list" && form.get("search")) await wait(80);
    return originalFetch(url, options);
  };

  const search = window.document.querySelector("#jluxe-pm-search");
  const submit = window.document.querySelector("#jluxe-pm-search-button");
  search.value = "VAR";
  submit.click();
  await wait(5);
  search.value = "مانتو";
  submit.click();
  await wait(90);
  assert.equal(window.document.querySelectorAll(".jluxe-pm-product-row").length, 0, "the old result is never rendered while the latest query is still loading");
  assert.equal(window.document.querySelector("#jluxe-pm-rows").getAttribute("aria-busy"), "true");
  await wait(100);
  assert.deepEqual(Array.from(window.document.querySelectorAll(".jluxe-pm-product-row"), (row) => row.dataset.productId), ["101"]);
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
  productFixture.variations.items[0].regularPrice = "1650000";
  productFixture.variations.items[0].salePrice = "1290000";
  productFixture.variations.items[0].onSale = true;
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
  const sale = row.querySelector("[data-v-field='salePrice']");
  const saleToggle = row.querySelector("[data-v-sale-toggle]");
  assert.equal(regular.value, "1,650,000", "variation prices also display grouped thousands");
  assert.equal(sale.value, "1,290,000");
  assert.equal(saleToggle.checked, true, "each variation sale price has its own enabled switch");
  assert.equal(row.querySelector(".jluxe-pm-sale-state"), null, "variation discounts remain editable without an extra active-status caption");
  regular.value = "1650000";
  regular.dispatchEvent(new window.Event("input", { bubbles: true }));
  sale.value = "1500000";
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
  assert.equal(save.data.variation.regularPrice, "1650000");
  assert.equal(save.data.variation.salePrice, "1500000");
  assert.equal(save.data.variation.stockQuantity, "");
  assert.equal(save.data.variation.manageStock, false, "a non-empty old quantity does not silently re-enable variation stock management");
  assert.equal(save.data.variation.stockStatus, "outofstock");
  assert.equal(save.data.variation.imageId, "991");
  assert.equal(product.variations.items[0].attributes.pa_size, "l");
  assert.equal(String(product.variations.items[0].imageId), "991");

  const livePanel = window.document.querySelector("[data-pm-variation-panel='202']");
  const currentSaleToggle = livePanel.querySelector("[data-v-sale-toggle]");
  currentSaleToggle.click();
  await wait(500);
  const saleOff = sent.filter((item) => item.action === "jluxe_product_manager_quick_save_variation").at(-1);
  const panelAfterSaleOff = window.document.querySelector("[data-pm-variation-panel='202']");
  assert.equal(panelAfterSaleOff.querySelector("[data-v-sale-toggle]").checked, false);
  assert.equal(panelAfterSaleOff.querySelector("[data-v-field='salePrice']").value, "");
  assert.equal(saleOff.data.variation.salePrice, "", "turning off a variation sale saves an empty WooCommerce sale price");
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

test("product manager follows a restrained slate-and-emerald system without the rejected plum palette", () => {
  assert.match(styles, /--pm-primary:\s*#334155;/);
  assert.match(styles, /--pm-accent:\s*#059669;/);
  assert.match(styles, /--pm-row-alt:\s*#f1f5f9;/);
  assert.match(styles, /--pm-row-hover:\s*#e2e8f0;/);
  assert.doesNotMatch(styles, /linear-gradient|#30263a|#76539a|#eee5f3/i);
  assert.match(styles, /\.jluxe-pm-toggle input:checked \+ \.jluxe-pm-toggle-track/);
  assert.match(styles, /\.jluxe-pm-sale-toggle \{/);
});

test("product and variation tables share consistent, easy-to-read slate row stripes", () => {
  assert.match(styles, /--pm-row-alt:\s*#f1f5f9;/);
  assert.match(styles, /\.jluxe-pm-table tbody > tr\.jluxe-pm-product-row\.is-alt > td \{\s*background: var\(--pm-row-alt\) !important;/);
  assert.match(styles, /tr\.jluxe-pm-product-row\.is-alt > td\.jluxe-pm-stock-cell \{\s*background: var\(--pm-row-alt\) !important;/);
  assert.match(styles, /\.jluxe-pm-accordion-row\.is-alt > td \{\s*background: var\(--pm-row-alt\) !important;/);
  assert.match(styles, /\.jluxe-pm-variation-table tbody tr:nth-child\(even\) td \{\s*background: var\(--pm-row-alt\);/);

  const mobileRules = styles.slice(styles.lastIndexOf("@media (max-width: 782px)"));
  assert.match(mobileRules, /tr\.jluxe-pm-product-row\.is-alt \{\s*border-color: var\(--pm-row-line\);\s*background: var\(--pm-row-alt\);/);
  assert.match(mobileRules, /\.jluxe-pm-variation-table tbody > tr:nth-child\(even\) \{\s*border-color: var\(--pm-row-line\);\s*background: var\(--pm-row-alt\);/);
  assert.match(mobileRules, /tr\.jluxe-pm-product-row > td \{[\s\S]*?background: transparent !important;/);
});

test("product manager uses compact quantity fields and responsive, touch-friendly cards", () => {
  assert.match(styles, /\.jluxe-pm-price-control \{[\s\S]*?display: flex;[\s\S]*?align-items: center;[\s\S]*?min-height: 40px;/);
  assert.match(styles, /\.jluxe-pm-price-control \.jluxe-pm-price-input \{[\s\S]*?flex: 1 1 auto;[\s\S]*?width: 100%;[\s\S]*?min-width: 0;/);
  assert.match(styles, /\.jluxe-pm-actions-controls \{\s*display: grid;[\s\S]*?gap: 7px;/);
  assert.match(styles, /\.jluxe-pm-stock-quantity \{\s*flex: 0 0 88px;\s*width: 88px;\s*max-width: 88px;/);

  const responsiveRules = styles.slice(styles.lastIndexOf("@media (max-width: 1100px)"));
  assert.match(responsiveRules, /tr\.jluxe-pm-product-row\s*\{[\s\S]*?display:\s*grid/);
  assert.match(responsiveRules, /jluxe-pm-variation-table\s*>\s*tbody\s*\{\s*display:\s*grid/);
  assert.match(responsiveRules, /min-height:\s*44px/);
  assert.match(responsiveRules, /\.jluxe-pm-stock-quantity \{\s*flex-basis: 88px;\s*width: 88px;\s*max-width: 88px;\s*min-height: 44px;/);
  assert.match(responsiveRules, /\.jluxe-pm-actions-controls \{\s*grid-template-columns: repeat\(auto-fit/);

  const narrowRules = styles.slice(styles.lastIndexOf("@media (max-width: 520px)"));
  assert.match(narrowRules, /jluxe-pm-product-row,\s*\.jluxe-pm-variation-table tbody > tr \{\s*grid-template-columns: minmax\(0, 1fr\);/);
  assert.match(narrowRules, /@media \(max-width: 380px\) \{[\s\S]*?\.jluxe-pm-actions-controls,[\s\S]*?grid-template-columns: minmax\(0, 1fr\);/);
});
