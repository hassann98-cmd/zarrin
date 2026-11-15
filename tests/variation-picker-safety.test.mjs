import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { JSDOM } from 'jsdom';
const read = p => fs.readFileSync(new URL(p, import.meta.url), 'utf8');
const source = read('../assets/js/woocommerce.js');
const marker = source.indexOf('مودالِ انتخاب سریعِ تنوع');
const start = source.indexOf('(function () {', marker);
const end = source.indexOf('\n})();\n\n/**\n * توضیحاتِ کوتاهِ محصول', start);
assert.ok(start > marker && end > start);
const tick = () => new Promise(resolve => setTimeout(resolve, 10));
async function setup(t, overflow = '') {
  const dom = new JSDOM('<button data-jluxe-quick-variant="700">Choose</button>', { url: 'https://shop.test/product/main', runScripts: 'outside-only', pretendToBeVisual: true });
  t.after(() => dom.window.close());
  const w = dom.window;
  w.JLuxeThemeSettings = { cart: { ajaxUrl: '/ajax', nonce: 'ok' } };
  w.eval(read('../assets/js/storefront-utils.js'));
  let adds = 0;
  w.jluxeCartPost = async (_url, body) => {
    if (body.get('action') === 'jluxe_variation_picker') return { success: true, data: { name: 'Offer', url: '/offer', image: '/image.webp', html: '<form class="variations_form" data-product_id="700"><input name="variation_id" value="701"><input name="attribute_pa_size" value="medium"><input name="quantity" value="1"><button type="button" class="single_add_to_cart_button">Add</button></form>' } };
    adds++;
    return { success: true, data: { itemCount: 1 } };
  };
  w.document.body.style.overflow = overflow;
  w.eval(source.slice(start, end + '\n})();'.length));
  const open = async () => { w.document.querySelector('[data-jluxe-quick-variant]').click(); await tick(); };
  await open();
  return { w, open, adds: () => adds };
}
for (const overflow of ['', 'hidden', 'clip']) {
  test(`picker restores pre-existing overflow ${JSON.stringify(overflow)} across repeated opens`, async t => {
    const { w, open } = await setup(t, overflow);
    for (let i = 0; i < 2; i++) {
      assert.equal(w.document.body.style.overflow, 'hidden');
      w.document.querySelector('.jluxe-variant-modal-close').click();
      assert.equal(w.document.body.style.overflow, overflow);
      if (!i) await open();
    }
  });
}
test('throwing added_to_cart feedback cannot invalidate a confirmed variation add', async t => {
  const { w, adds } = await setup(t, 'hidden');
  let updates = 0;
  w.addEventListener('jluxe:cart-updated', e => { updates++; assert.equal(e.detail.itemCount, 1); });
  const jq = () => ({ trigger() { throw new Error('third-party listener failed'); } });
  jq.fn = {};
  w.jQuery = jq;
  w.document.querySelector('.jluxe-variant-modal form').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
  await tick();
  assert.equal(adds(), 1);
  assert.equal(updates, 1);
  assert.equal(w.document.querySelector('.jluxe-variant-modal-backdrop'), null);
  assert.equal(w.document.querySelector('[data-jluxe-quick-variant]').hasAttribute('data-jluxe-add-uncertain'), false);
  assert.equal(w.document.body.style.overflow, 'hidden');
});
